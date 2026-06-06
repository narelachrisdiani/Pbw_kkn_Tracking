<?php
session_start();
require_once '../config/database.php';

// Cek apakah user sudah login dan role-nya dpl
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dpl') {
    header("Location: ../index.php");
    exit();
}

$database = new Database();
$pdo = $database->getConnection();

$success = '';
$error = '';

// ========================================
// PROSES TAMBAH LOKASI
// ========================================
if (isset($_POST['tambah'])) {
    $nama_desa = trim($_POST['nama_desa']);
    $kecamatan = trim($_POST['kecamatan']);
    $kabupaten = trim($_POST['kabupaten']);
    $provinsi = trim($_POST['provinsi']);
    $alamat_detail = trim($_POST['alamat_detail']);
    $nama_pemdes = trim($_POST['nama_pemdes']);
    $kontak_pemdes = trim($_POST['kontak_pemdes']);

    if (empty($nama_desa) || empty($kecamatan) || empty($kabupaten) || empty($provinsi)) {
        $error = "❌ Nama Desa, Kecamatan, Kabupaten, dan Provinsi wajib diisi!";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO lokasi (nama_desa, kecamatan, kabupaten, provinsi, alamat_detail, nama_pemdes, kontak_pemdes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $nama_desa, $kecamatan, $kabupaten, $provinsi, 
                $alamat_detail, $nama_pemdes, $kontak_pemdes
            ]);
            $success = "✅ Lokasi berhasil ditambahkan!";
        } catch (PDOException $e) {
            $error = "❌ Gagal menambah lokasi: " . $e->getMessage();
        }
    }
}

// ========================================
// PROSES HAPUS LOKASI
// ========================================
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    
    // Cek apakah lokasi sedang digunakan
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM penempatan WHERE lokasi_id = ?");
    $stmt->execute([$id]);
    $total = $stmt->fetch()['total'];
    
    if ($total > 0) {
        $error = "❌ Lokasi tidak bisa dihapus karena sudah ada mahasiswa yang ditempatkan!";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM lokasi WHERE id = ?");
            $stmt->execute([$id]);
            $success = "✅ Lokasi berhasil dihapus!";
        } catch (PDOException $e) {
            $error = "❌ Gagal menghapus lokasi: " . $e->getMessage();
        }
    }
}

// ========================================
// AMBIL SEMUA LOKASI
// ========================================
$stmt = $pdo->query("SELECT * FROM lokasi ORDER BY created_at DESC");
$lokasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Lokasi - KKN Tracking</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --accent: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #64748b;
            --sidebar-width: 280px;
            --sidebar-collapsed: 80px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        
        html, body { 
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Sidebar Elegant */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: white;
            z-index: 1000;
            transition: var(--transition);
            overflow-x: hidden;
            overflow-y: auto;
            box-shadow: 4px 0 24px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed);
        }

        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: var(--transition);
        }

        .sidebar-brand {
            font-size: 1.4rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            white-space: nowrap;
            overflow: hidden;
            transition: var(--transition);
        }

        .sidebar.collapsed .sidebar-brand {
            opacity: 0;
            width: 0;
        }

        .toggle-btn {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-size: 18px;
            flex-shrink: 0;
        }

        .toggle-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: rotate(180deg);
        }

        .sidebar-menu {
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: var(--transition);
        }

        .menu-item:hover::before,
        .menu-item.active::before {
            width: 100%;
        }

        .menu-item:hover,
        .menu-item.active {
            color: white;
            transform: translateX(4px);
        }

        .menu-icon {
            font-size: 22px;
            margin-right: 14px;
            min-width: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .menu-text {
            font-weight: 500;
            font-size: 14px;
            position: relative;
            z-index: 1;
            transition: var(--transition);
        }

        .sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
            width: 100%;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }

        .logout-icon {
            margin-right: 12px;
            font-size: 18px;
            min-width: 24px;
        }

        .sidebar.collapsed .logout-text {
            display: none;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: var(--transition);
            min-height: 100vh;
            width: calc(100% - var(--sidebar-width));
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed);
            width: calc(100% - var(--sidebar-collapsed));
        }

        /* Top Bar */
        .top-bar {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .page-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 18px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
            white-space: nowrap;
        }

        .user-role {
            font-size: 12px;
            color: var(--gray);
        }

        /* Container */
        .container {
            padding: 32px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .page-title-main {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 14px;
        }

        /* Alert */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-left-color: var(--success);
        }

        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
            color: #991b1b;
            border-left-color: var(--danger);
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 28px;
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* Form */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .form-group label .required {
            color: var(--danger);
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
            line-height: 1.6;
        }

        .form-note {
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
            line-height: 1.4;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            letter-spacing: -0.01em;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-badge {
            background: linear-gradient(135deg, var(--accent), #22d3ee);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        th {
            background: var(--gray-50);
            color: var(--gray-600);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: var(--gray-50);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Empty State */
        .empty {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray);
        }

        .empty .icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.4;
        }

        .empty p {
            margin: 5px 0;
        }

        .empty strong {
            color: var(--dark);
            display: block;
            margin-bottom: 4px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .container { padding: 20px; }
            .page-header { padding: 24px; }
            .form-card { padding: 24px; }
            .top-bar { padding: 16px 20px; flex-direction: column; gap: 12px; }
            .btn-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .form-row { grid-template-columns: 1fr; }
            .form-title { flex-direction: column; align-items: flex-start; }
            .table-section { padding: 20px; }
            table { font-size: 13px; }
            th, td { padding: 12px 10px; }
        }
    </style>
</head>
<body>
   <!-- Tambahkan ini di bagian <head> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Sidebar Elegant -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">KKN Tracking</div>

        <button class="toggle-btn" onclick="toggleSidebar()" title="Toggle Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-house"></i>
            </span>
            <span class="menu-text">Dashboard</span>
        </a>

        <a href="kelola_lokasi.php" class="menu-item active">
            <span class="menu-icon">
                <i class="fa-solid fa-location-dot"></i>
            </span>
            <span class="menu-text">Kelola Lokasi</span>
        </a>

        <a href="kelola_mahasiswa.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-user-graduate"></i>
            </span>
            <span class="menu-text">Mahasiswa</span>
        </a>

        <a href="verifikasi_laporan.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-circle-check"></i>
            </span>
            <span class="menu-text">Verifikasi</span>
        </a>

        <a href="profil.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-circle-user"></i>
            </span>
            <span class="menu-text">Profil</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <span class="logout-icon">
                <i class="fa-solid fa-right-from-bracket"></i>
            </span>
            <span class="logout-text">Logout</span>
        </a>
    </div>
</aside>
    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <a href="javascript:history.back()" class="back-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--dark); text-decoration: none; transition: var(--transition);">
                <span>←</span>
                <span>Kembali</span>
            </a>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role">Dosen Pembimbing</div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title-main">📍 Kelola Lokasi KKN</h1>
                <p class="page-subtitle">Tambah atau hapus data desa/kelurahan tempat KKN dilaksanakan</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- Form Tambah Lokasi -->
            <div class="form-card">
                <div class="form-title">
                    <span>➕ Tambah Lokasi KKN Baru</span>
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Desa/Kelurahan <span class="required">*</span></label>
                            <input type="text" name="nama_desa" class="form-control" required placeholder="Contoh: Sukamaju">
                        </div>
                        <div class="form-group">
                            <label>Kecamatan <span class="required">*</span></label>
                            <input type="text" name="kecamatan" class="form-control" required placeholder="Contoh: Cikarang Utara">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Kabupaten/Kota <span class="required">*</span></label>
                            <input type="text" name="kabupaten" class="form-control" required placeholder="Contoh: Bekasi">
                        </div>
                        <div class="form-group">
                            <label>Provinsi <span class="required">*</span></label>
                            <input type="text" name="provinsi" class="form-control" required placeholder="Contoh: Jawa Barat">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alamat Detail</label>
                        <textarea name="alamat_detail" class="form-control" placeholder="Alamat lengkap desa, titik koordinat, atau catatan tambahan..."></textarea>
                        <div class="form-note">Opsional: Informasi tambahan untuk memudahkan akses ke lokasi</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Kepala Desa</label>
                            <input type="text" name="nama_pemdes" class="form-control" placeholder="Contoh: H. Suryadi">
                        </div>
                        <div class="form-group">
                            <label>Kontak Kepala Desa</label>
                            <input type="text" name="kontak_pemdes" class="form-control" placeholder="Contoh: 0812-3456-7890">
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" name="tambah" class="btn btn-success">💾 Simpan Lokasi</button>
                        <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
                    </div>
                </form>
            </div>

          <!-- Tabel Daftar Lokasi -->
<div class="table-section">
    <div class="table-title">
        <span>📍 Daftar Lokasi KKN</span>
        <span class="table-badge"><?= count($lokasi_list) ?> Lokasi</span>
    </div>
    
    <?php if (count($lokasi_list) > 0): ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">Nama Desa</th>
                    <th width="15%">Kecamatan</th>
                    <th width="15%">Kabupaten</th>
                    <th width="15%">Provinsi</th>
                    <th width="15%">Kepala Desa</th>
                    <th width="10%">Kontak</th>
                    <th width="15%">Aksi</th>
                </tr>
            </thead>

            <tbody>
                <?php 
                $no = 1;
                foreach ($lokasi_list as $lokasi): 
                ?>
                <tr>
                    <td><?= $no++ ?></td>

                    <td>
                        <strong><?= htmlspecialchars($lokasi['nama_desa']) ?></strong>
                    </td>

                    <td><?= htmlspecialchars($lokasi['kecamatan']) ?></td>

                    <td><?= htmlspecialchars($lokasi['kabupaten']) ?></td>

                    <td><?= htmlspecialchars($lokasi['provinsi']) ?></td>

                    <td><?= htmlspecialchars($lokasi['nama_pemdes'] ?? '-') ?></td>

                    <td><?= htmlspecialchars($lokasi['kontak_pemdes'] ?? '-') ?></td>

                    <td>
                        <div style="display:flex; gap:8px; justify-content:center;">

                            <!-- Tombol Edit -->
                            <a href="?edit=<?= $lokasi['id'] ?>" 
                               class="btn btn-warning btn-sm"
                               style="
                               border-radius:10px;
                               padding:7px 12px;
                               display:flex;
                               align-items:center;
                               gap:5px;
                               ">
                                ✏
                                <span>Edit</span>
                            </a>

                            <!-- Tombol Hapus -->
                            <a href="?hapus=<?= $lokasi['id'] ?>" 
                               class="btn btn-danger btn-sm"
                               style="
                               border-radius:10px;
                               padding:7px 12px;
                               display:flex;
                               align-items:center;
                               gap:5px;
                               "
                               onclick="return confirm('Yakin ingin menghapus lokasi ini?')">
                                🗑
                                <span>Hapus</span>
                            </a>

                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
                <?php else: ?>
                <div class="empty">
                    <div class="icon">🗺️</div>
                    <p><strong>Belum ada data lokasi KKN</strong></p>
                    <p style="font-size:13px;">Silakan tambah lokasi menggunakan form di atas</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    // Restore sidebar state
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }

        // Auto-hide alert setelah 5 detik
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s, transform 0.5s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    });

    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.toggle-btn');
        
        if (window.innerWidth <= 1024 && 
            !sidebar.contains(e.target) && 
            !toggleBtn.contains(e.target) && 
            sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
    </script>
</body>
</html>