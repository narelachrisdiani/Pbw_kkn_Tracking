<?php
session_start();
require_once '../config/database.php';

// Cek login: Admin ATAU DPL bisa akses
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'dpl'])) {
    header("Location: ../index.php");
    exit();
}

$pdo = (new Database())->getConnection();
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$success = '';
$error = '';
$edit_mode = false;
$edit_data = null;

// ========================================
// MODE EDIT (Klik tombol Edit)
// ========================================
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    // Jika DPL, hanya bisa edit lokasi yang "miliknya" (ada mahasiswanya)
    if ($user_role == 'dpl') {
        $stmt = $pdo->prepare("
            SELECT l.* FROM lokasi l
            JOIN penempatan p ON l.id = p.lokasi_id
            WHERE l.id = ? AND p.dpl_id = ?
            LIMIT 1
        ");
        $stmt->execute([$edit_id, $user_id]);
    } else {
        // Admin bisa edit semua
        $stmt = $pdo->prepare("SELECT * FROM lokasi WHERE id = ?");
        $stmt->execute([$edit_id]);
    }
    
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $edit_mode = true;
    } else {
        $error = "❌ Lokasi tidak ditemukan atau tidak memiliki akses!";
    }
}

// ========================================
// PROSES SIMPAN (TAMBAH / UPDATE)
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_desa = trim($_POST['nama_desa']);
    $kecamatan = trim($_POST['kecamatan']);
    $kabupaten = trim($_POST['kabupaten']);
    $provinsi = trim($_POST['provinsi']);
    $alamat = trim($_POST['alamat_detail']);
    $nama_pemdes = trim($_POST['nama_pemdes']);
    $kontak = trim($_POST['kontak_pemdes']);
    
    // Validasi wajib
    if (empty($nama_desa) || empty($kecamatan) || empty($kabupaten) || empty($provinsi)) {
        $error = "❌ Nama Desa, Kecamatan, Kabupaten, dan Provinsi wajib diisi!";
    } else {
        try {
            if ($edit_mode && $edit_data) {
                // === MODE UPDATE ===
                $stmt = $pdo->prepare("
                    UPDATE lokasi 
                    SET nama_desa=?, kecamatan=?, kabupaten=?, provinsi=?, 
                        alamat_detail=?, nama_pemdes=?, kontak_pemdes=? 
                    WHERE id=?
                ");
                $stmt->execute([
                    $nama_desa, $kecamatan, $kabupaten, $provinsi,
                    $alamat, $nama_pemdes, $kontak, $edit_data['id']
                ]);
                $success = "✅ Data desa '{$nama_desa}' berhasil diupdate!";
                $edit_mode = false; // Keluar mode edit
                $edit_data = null;
                
            } else {
                // === MODE TAMBAH ===
                $stmt = $pdo->prepare("
                    INSERT INTO lokasi (nama_desa, kecamatan, kabupaten, provinsi, alamat_detail, nama_pemdes, kontak_pemdes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nama_desa, $kecamatan, $kabupaten, $provinsi,
                    $alamat, $nama_pemdes, $kontak
                ]);
                $success = "✅ Desa '{$nama_desa}' berhasil ditambahkan!";
            }
        } catch (PDOException $e) {
            $error = "❌ Error database: " . $e->getMessage();
        }
    }
}

// ========================================
// PROSES HAPUS
// ========================================
if (isset($_GET['hapus'])) {
    $hapus_id = $_GET['hapus'];
    
    // CEK KEAMANAN: Jangan hapus jika ada mahasiswa di lokasi ini
    $cek = $pdo->prepare("SELECT COUNT(*) FROM penempatan WHERE lokasi_id = ? AND status = 'aktif'");
    $cek->execute([$hapus_id]);
    
    if ($cek->fetchColumn() > 0) {
        $error = "❌ Tidak bisa hapus! Masih ada mahasiswa KKN aktif di desa ini.";
    } else {
        // Ambil nama desa untuk konfirmasi
        $nama_hapus = $pdo->prepare("SELECT nama_desa FROM lokasi WHERE id = ?");
        $nama_hapus->execute([$hapus_id]);
        $nama = $nama_hapus->fetchColumn();
        
        try {
            $pdo->prepare("DELETE FROM lokasi WHERE id = ?")->execute([$hapus_id]);
            $success = "✅ Desa '{$nama}' berhasil dihapus!";
        } catch (PDOException $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    }
}

// ========================================
// AMBIL DATA LOKASI UNTUK TABEL
// ========================================
if ($user_role == 'dpl') {
    // DPL hanya lihat lokasi yang pernah/sedang dipakai mahasiswanya
    $stmt = $pdo->prepare("
        SELECT DISTINCT l.* FROM lokasi l
        JOIN penempatan p ON l.id = p.lokasi_id
        WHERE p.dpl_id = ?
        ORDER BY l.nama_desa
    ");
    $stmt->execute([$user_id]);
} else {
    // Admin lihat SEMUA lokasi
    $stmt = $pdo->query("SELECT * FROM lokasi ORDER BY provinsi, kabupaten, nama_desa");
}
$lokasi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total untuk badge
$total_lokasi = count($lokasi_list);
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

        .edit-badge {
            background: linear-gradient(135deg, var(--warning), #f97316);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .form-header {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid var(--warning);
            color: #92400e;
        }

        .form-header h3 {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 600;
        }

        .form-header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
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

        .form-control:disabled {
            background: var(--gray-100);
            cursor: not-allowed;
            color: var(--gray-400);
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

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #f97316);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
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

        /* 🔥 ACTION BUTTONS - LEBIH RAPI */
        .action-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 8px;
            white-space: nowrap;
        }

        .action-buttons .btn-sm {
            padding: 6px 10px;
            font-size: 11px;
        }

        .action-buttons .btn-text {
            display: inline;
        }

        /* Responsive: Icon only di mobile */
        @media (max-width: 768px) {
            .action-buttons .btn-text {
                display: none;
            }
            .action-buttons .btn {
                width: 36px;
                height: 36px;
                padding: 0;
                border-radius: 8px;
                justify-content: center;
            }
            .action-buttons {
                gap: 4px;
            }
        }

        /* Table */
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
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

        /* Location Info */
        .loc-info {
            font-size: 13px;
            color: var(--gray-700);
            line-height: 1.5;
        }

        .loc-info strong {
            color: var(--dark);
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .loc-info small {
            color: var(--primary);
            font-weight: 500;
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
            .loc-info { font-size: 12px; }
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

        <?php if ($user_role == 'admin'): ?>

        <a href="dashboard.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-house"></i>
            </span>
            <span class="menu-text">Dashboard</span>
        </a>

        <a href="kelola_user.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-users"></i>
            </span>
            <span class="menu-text">Kelola User</span>
        </a>

        <a href="kelola_lokasi.php" class="menu-item active">
            <span class="menu-icon">
                <i class="fa-solid fa-location-dot"></i>
            </span>
            <span class="menu-text">Kelola Lokasi</span>
        </a>

        <a href="kelola_program.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </span>
            <span class="menu-text">Program</span>
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

        <?php else: ?>

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

        <?php endif; ?>

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
            <div class="page-title">Kelola Lokasi</div>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role"><?= ucfirst($user_role) ?></div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title-main">📍 Kelola Lokasi KKN</h1>
                <p class="page-subtitle">Tambah, edit, atau hapus data desa/kelurahan tempat KKN dilaksanakan</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- FORM SECTION: Tambah / Edit Lokasi -->
            <div class="form-card">
                <div class="form-title">
                    <span>
                        <?= $edit_mode ? '✏️ Edit Data Desa' : '➕ Tambah Lokasi KKN Baru' ?>
                        <?php if ($edit_mode): ?>
                            <span class="edit-badge">Mode Edit</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($edit_mode): ?>
                        <a href="kelola_lokasi.php" class="btn btn-secondary btn-sm">❌ Batal</a>
                    <?php endif; ?>
                </div>

                <?php if ($edit_mode && $edit_data): ?>
                <div class="form-header">
                    <h3>Sedang mengedit: <strong><?= htmlspecialchars($edit_data['nama_desa']) ?></strong></h3>
                    <p>Desa ini berada di <?= htmlspecialchars($edit_data['kecamatan']) ?>, <?= htmlspecialchars($edit_data['kabupaten']) ?></p>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Desa/Kelurahan <span class="required">*</span></label>
                            <input type="text" name="nama_desa" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['nama_desa'] ?? '') ?>" 
                                   required placeholder="Contoh: Sukamaju">
                        </div>
                        <div class="form-group">
                            <label>Kecamatan <span class="required">*</span></label>
                            <input type="text" name="kecamatan" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['kecamatan'] ?? '') ?>" 
                                   required placeholder="Contoh: Cikarang Utara">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Kabupaten/Kota <span class="required">*</span></label>
                            <input type="text" name="kabupaten" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['kabupaten'] ?? '') ?>" 
                                   required placeholder="Contoh: Bekasi">
                        </div>
                        <div class="form-group">
                            <label>Provinsi <span class="required">*</span></label>
                            <input type="text" name="provinsi" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['provinsi'] ?? '') ?>" 
                                   required placeholder="Contoh: Jawa Barat">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Alamat Detail</label>
                        <textarea name="alamat_detail" class="form-control" 
                                  placeholder="Alamat lengkap, titik koordinat, atau catatan tambahan..."><?= htmlspecialchars($edit_data['alamat_detail'] ?? '') ?></textarea>
                        <div class="form-note">Opsional: Informasi tambahan seperti koordinat GPS atau patokan lokasi</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Kepala Desa/Lurah</label>
                            <input type="text" name="nama_pemdes" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['nama_pemdes'] ?? '') ?>" 
                                   placeholder="Contoh: H. Suryadi">
                        </div>
                        <div class="form-group">
                            <label>Kontak (WhatsApp/Telp)</label>
                            <input type="text" name="kontak_pemdes" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['kontak_pemdes'] ?? '') ?>" 
                                   placeholder="Contoh: 0812-3456-7890">
                        </div>
                    </div>

                    <div class="btn-group">
                        <?php if ($edit_mode): ?>
                            <button type="submit" class="btn btn-warning">💾 Update Data Desa</button>
                            <a href="kelola_lokasi.php" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">💾 Simpan Lokasi</button>
                            <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- TABLE SECTION: Daftar Lokasi -->
            <div class="table-section">
                <div class="table-title">
                    <span>📋 Daftar Lokasi KKN</span>
                    <span class="table-badge"><?= $total_lokasi ?> Desa</span>
                </div>

                <?php if ($total_lokasi > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="25%">Desa / Kelurahan</th>
                                <th width="30%">Wilayah</th>
                                <th width="20%">Kontak Pemdes</th>
                                <th width="20%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($lokasi_list as $lok): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($lok['nama_desa']) ?></strong>
                                </td>
                                <td class="loc-info">
                                    <strong><?= htmlspecialchars($lok['kecamatan']) ?></strong>
                                    <?= htmlspecialchars($lok['kabupaten']) ?>, <?= htmlspecialchars($lok['provinsi']) ?>
                                </td>
                                <td class="loc-info">
                                    <?= htmlspecialchars($lok['nama_pemdes'] ?: '-') ?><br>
                                    <small><?= htmlspecialchars($lok['kontak_pemdes'] ?: 'No contact') ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?= $lok['id'] ?>" 
                                           class="btn btn-primary btn-sm" 
                                           title="Edit Lokasi">
                                            <span>✏️</span>
                                            <span class="btn-text">Edit</span>
                                        </a>
                                        <a href="?hapus=<?= $lok['id'] ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Hapus desa <?= addslashes($lok['nama_desa']) ?>?\n\n⚠️ Pastikan tidak ada mahasiswa aktif di lokasi ini!')"
                                           title="Hapus Lokasi">
                                            <span>🗑️</span>
                                            <span class="btn-text">Hapus</span>
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
                    <p style="font-size:13px;">Silakan tambahkan desa/kelurahan menggunakan form di atas</p>
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

        // Auto-focus ke field pertama saat load
        const firstInput = document.querySelector('input[name="nama_desa"]');
        if (firstInput && !firstInput.disabled) firstInput.focus();

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