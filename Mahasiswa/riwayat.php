<?php
session_start();
require_once '../config/database.php';

// Cek login & role mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

$mahasiswa_id = $_SESSION['user_id'];
$pdo = (new Database())->getConnection();

$success = '';
$error = '';

// ========================================
// 🔥 HANDLE HAPUS KEGIATAN
// ========================================
if (isset($_GET['hapus_kegiatan'])) {
    $id = $_GET['hapus_kegiatan'];
    $check = $pdo->prepare("
        SELECT k.id FROM kegiatan k 
        JOIN penempatan p ON k.penempatan_id = p.id 
        WHERE k.id = ? AND p.mahasiswa_id = ?
    ");
    $check->execute([$id, $mahasiswa_id]);
    
    if ($check->rowCount() > 0) {
        // Cek apakah kegiatan ini punya laporan
        $cek_laporan = $pdo->prepare("SELECT COUNT(*) FROM laporan WHERE kegiatan_id = ?");
        $cek_laporan->execute([$id]);
        if ($cek_laporan->fetchColumn() > 0) {
            $error = "❌ Kegiatan tidak bisa dihapus karena sudah ada laporan terkait!";
        } else {
            $pdo->prepare("DELETE FROM kegiatan WHERE id = ?")->execute([$id]);
            $success = "✅ Kegiatan berhasil dihapus!";
        }
    } else {
        $error = "❌ Data tidak ditemukan atau akses ditolak!";
    }
}

// ========================================
// 🔥 HANDLE HAPUS LAPORAN
// ========================================
if (isset($_GET['hapus_laporan'])) {
    $id = $_GET['hapus_laporan'];
    $check = $pdo->prepare("
        SELECT l.id, l.file_pdf FROM laporan l 
        JOIN kegiatan k ON l.kegiatan_id = k.id 
        JOIN penempatan p ON k.penempatan_id = p.id 
        WHERE l.id = ? AND p.mahasiswa_id = ?
    ");
    $check->execute([$id, $mahasiswa_id]);
    $data_hapus = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($data_hapus) {
        // Hapus file PDF dari server jika ada
        if (!empty($data_hapus['file_pdf']) && file_exists('../assets/uploads/laporan/' . $data_hapus['file_pdf'])) {
            unlink('../assets/uploads/laporan/' . $data_hapus['file_pdf']);
        }
        
        $pdo->prepare("DELETE FROM laporan WHERE id = ?")->execute([$id]);
        $success = "✅ Laporan berhasil dihapus!";
    } else {
        $error = "❌ Data tidak ditemukan atau akses ditolak!";
    }
}

// ========================================
// 🔥 AMBIL DATA PENEMPATAN & RIWAYAT
// ========================================
$stmt = $pdo->prepare("SELECT id FROM penempatan WHERE mahasiswa_id = ? AND status = 'aktif'");
$stmt->execute([$mahasiswa_id]);
$penempatan = $stmt->fetch(PDO::FETCH_ASSOC);

$kegiatan_list = [];
$laporan_list = [];

if ($penempatan) {
    // Ambil semua kegiatan
    $stmt = $pdo->prepare("
        SELECT id, judul_kegiatan, jenis_kegiatan, tanggal_kegiatan, status 
        FROM kegiatan 
        WHERE penempatan_id = ? 
        ORDER BY tanggal_kegiatan DESC
    ");
    $stmt->execute([$penempatan['id']]);
    $kegiatan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil semua laporan + status verifikasi + file_pdf
    $stmt = $pdo->prepare("
        SELECT l.id, l.jenis_laporan, l.tanggal_laporan, l.status_verifikasi, l.file_pdf, k.judul_kegiatan
        FROM laporan l
        JOIN kegiatan k ON l.kegiatan_id = k.id
        JOIN penempatan p ON k.penempatan_id = p.id
        WHERE p.mahasiswa_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$mahasiswa_id]);
    $laporan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat - KKN Tracking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        
        body { 
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            min-height: 100vh;
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
            box-shadow: 4px 0 24px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed { width: var(--sidebar-collapsed); }

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

        .sidebar.collapsed .sidebar-brand { opacity: 0; width: 0; }

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
        }

        .toggle-btn:hover { background: rgba(255,255,255,0.2); transform: rotate(180deg); }

        .sidebar-menu { padding: 24px 16px; display: flex; flex-direction: column; gap: 8px; }

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

        .menu-item:hover::before, .menu-item.active::before { width: 100%; }
        .menu-item:hover, .menu-item.active { color: white; transform: translateX(4px); }

        .menu-icon { font-size: 22px; margin-right: 14px; min-width: 24px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; }
        .menu-text { font-weight: 500; font-size: 14px; position: relative; z-index: 1; transition: var(--transition); }
        .sidebar.collapsed .menu-text { opacity: 0; width: 0; }

        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }

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

        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4); }
        .logout-icon { margin-right: 12px; font-size: 18px; min-width: 24px; }
        .sidebar.collapsed .logout-text { display: none; }

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); transition: var(--transition); min-height: 100vh; width: calc(100% - var(--sidebar-width)); }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); }

        /* Top Bar */
        .top-bar {
            background: rgba(255,255,255,0.8);
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

        .back-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 18px;
            background: white;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            transition: var(--transition);
            text-decoration: none;
        }

        .back-btn:hover { background: var(--primary); color: white; transform: translateX(-4px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }

        .user-info { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .user-avatar { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .user-name { font-weight: 600; color: var(--dark); font-size: 14px; }
        .user-role { font-size: 12px; color: var(--gray); }

        /* Container */
        .container { padding: 32px; max-width: 1200px; }

        /* Page Header */
        .page-header { background: white; padding: 32px; border-radius: 20px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .page-title { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .page-subtitle { color: var(--gray); font-size: 14px; }

        /* Alert */
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; border-left: 4px solid; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #065f46; border-left-color: var(--success); }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #991b1b; border-left-color: var(--danger); }

        /* Tabs */
        .tabs { display: flex; gap: 8px; margin-bottom: 28px; padding: 6px; background: var(--gray-100); border-radius: 12px; width: fit-content; }
        .tab { padding: 12px 24px; background: none; border: none; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--gray); border-radius: 10px; transition: var(--transition); position: relative; }
        .tab:hover { color: var(--dark); }
        .tab.active { color: white; background: linear-gradient(135deg, var(--primary), var(--secondary)); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .tab-count { background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 6px; }
        .tab-content { display: none; animation: fadeIn 0.3s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Card */
        .card { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--gray-200); }
        .card-title { font-size: 1.25rem; font-weight: 700; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .badge-count { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        /* Status Badge */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .badge-direncanakan { background: #dbeafe; color: #1e40af; }
        .badge-berjalan { background: #fef3c7; color: #92400e; }
        .badge-selesai { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-disetujui { background: #dcfce7; color: #166534; }
        .badge-revisi { background: #fecaca; color: #991b1b; }

        /* Buttons */
        .btn { padding: 8px 14px; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: var(--transition); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #f97316); color: white; }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }
        .btn-secondary { background: var(--gray-200); color: var(--dark); }
        .btn-secondary:hover { background: var(--gray-300); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* Empty State */
        .empty { text-align: center; padding: 50px 20px; color: var(--gray); }
        .empty .icon { font-size: 48px; margin-bottom: 15px; opacity: 0.4; }
        .empty p { margin: 5px 0; }
        .empty strong { color: var(--dark); display: block; margin-bottom: 4px; }

        /* Warning Box */
        .warning-box { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); padding: 24px; border-radius: 16px; margin-bottom: 28px; border-left: 4px solid var(--warning); color: #92400e; }
        .warning-box strong { display: block; margin-bottom: 8px; font-size: 15px; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .sidebar.collapsed ~ .main-content { margin-left: 0; }
        }

        @media (max-width: 768px) {
            .container { padding: 20px; }
            .page-header { padding: 24px; }
            .card { padding: 20px; }
            .top-bar { padding: 16px 20px; }
            .tabs { width: 100%; overflow-x: auto; padding-bottom: 0; }
            .tab { flex-shrink: 0; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 12px; }
            table { font-size: 13px; }
            th, td { padding: 12px 10px; }
        }
    </style>
</head>
<body>
    <!-- Sidebar Elegant -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">KKN Tracking</div>
            <button class="toggle-btn" onclick="toggleSidebar()" title="Toggle Menu"><i class="fa-solid fa-bars"></i></button>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-house"></i></span><span class="menu-text">Dashboard</span></a>
            <a href="input_kegiatan.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-square-plus"></i></span><span class="menu-text">Input Kegiatan</span></a>
            <a href="input_laporan.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-file-pen"></i></span><span class="menu-text">Laporan</span></a>
            <a href="riwayat.php" class="menu-item active"><span class="menu-icon"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="menu-text">Riwayat</span></a>
            <a href="profil.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-circle-user"></i></span><span class="menu-text">Profil</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn"><span class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="logout-text">Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <a href="javascript:history.back()" class="back-btn"><span>←</span><span>Kembali</span></a>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div><div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div><div class="user-role">Mahasiswa</div></div>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">📋 Riwayat Kegiatan & Laporan</h1>
                <p class="page-subtitle">Pantau semua aktivitas dan status verifikasi laporan Anda di sini</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error">❌ <?= $error ?></div><?php endif; ?>

            <?php if ($penempatan): ?>
                <!-- Tabs Navigation -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('kegiatan')">📌 Kegiatan <span class="tab-count"><?= count($kegiatan_list) ?></span></button>
                    <button class="tab" onclick="switchTab('laporan')">📄 Laporan <span class="tab-count"><?= count($laporan_list) ?></span></button>
                </div>

                <!-- TAB 1: RIWAYAT KEGIATAN -->
                <div id="tab-kegiatan" class="tab-content active">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">📚 Riwayat Kegiatan KKN</h2>
                            <span class="badge-count"><?= count($kegiatan_list) ?> Kegiatan</span>
                        </div>
                        
                        <?php if (count($kegiatan_list) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th width="5%">No</th>
                                        <th width="15%">Tanggal</th>
                                        <th width="30%">Judul Kegiatan</th>
                                        <th width="15%">Jenis</th>
                                        <th width="15%">Status</th>
                                        <th width="20%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($kegiatan_list as $k): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><div style="display:flex; align-items:center; gap:6px;"><span>📅</span><span><?= date('d/m/Y', strtotime($k['tanggal_kegiatan'])) ?></span></div></td>
                                        <td><div style="display:flex; align-items:center; gap:8px;"><span>📌</span><strong><?= htmlspecialchars($k['judul_kegiatan']) ?></strong></div></td>
                                        <td><span style="background:#fff7ed; color:#ea580c; padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600;">🏷️ <?= ucfirst($k['jenis_kegiatan']) ?></span></td>
                                        <td><span class="badge badge-<?= $k['status'] ?>"><?= $k['status'] == 'selesai' ? '✨' : ($k['status'] == 'berjalan' ? '🚀' : '🗓️') ?> <?= ucfirst($k['status']) ?></span></td>
                                        <td>
                                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                <a href="input_kegiatan.php?edit=<?= $k['id'] ?>" class="btn btn-warning btn-sm" style="display:flex; align-items:center; gap:6px; border-radius:10px; padding:8px 14px;">✏️ <span>Edit</span></a>
                                                <a href="?hapus_kegiatan=<?= $k['id'] ?>" class="btn btn-danger btn-sm" style="display:flex; align-items:center; gap:6px; border-radius:10px; padding:8px 14px;" onclick="return confirm('Hapus kegiatan ini? \n⚠️ Pastikan tidak ada laporan terkait!')">🗑️ <span>Hapus</span></a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty">
                            <div class="icon" style="font-size:60px;">🗂️</div>
                            <p><strong>Belum ada kegiatan</strong></p>
                            <p>Yuk mulai input kegiatan KKN pertamamu ✨</p>
                            <a href="input_kegiatan.php" class="btn btn-primary" style="margin-top:18px;">➕ Tambah Kegiatan</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB 2: RIWAYAT LAPORAN -->
                <div id="tab-laporan" class="tab-content">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title"><i class="fa-solid fa-folder-open"></i> Daftar Laporan</h2>
                            <span class="badge-count"><i class="fa-solid fa-file-lines"></i> <?= count($laporan_list) ?> Laporan</span>
                        </div>
                                    
                        <?php if (count($laporan_list) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th width="5%"><i class="fa-solid fa-hashtag"></i> No</th>
                                        <th width="12%"><i class="fa-solid fa-calendar-days"></i> Tanggal</th>
                                        <th width="25%"><i class="fa-solid fa-clipboard-list"></i> Kegiatan Terkait</th>
                                        <th width="12%"><i class="fa-solid fa-layer-group"></i> Jenis</th>
                                        <th width="13%"><i class="fa-solid fa-circle-check"></i> Status</th>
                                        <th width="10%"><i class="fa-solid fa-file-pdf"></i> File</th> <!-- KOLOM FILE DITAMBAHKAN -->
                                        <th width="23%"><i class="fa-solid fa-gear"></i> Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($laporan_list as $l): ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td><?= date('d/m/Y', strtotime($l['tanggal_laporan'])) ?></td>
                                        <td><?= htmlspecialchars($l['judul_kegiatan']) ?></td>
                                        <td><?= ucfirst($l['jenis_laporan']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $l['status_verifikasi'] ?>">
                                                <?= ucfirst($l['status_verifikasi']) ?>
                                            </span>
                                        </td>
                                        
                                        <!-- TAMPILKAN LINK PDF JIKA ADA -->
                                        <td>
                                            <?php if (!empty($l['file_pdf'])): ?>
                                                <a href="../assets/uploads/laporan/<?= htmlspecialchars($l['file_pdf']) ?>" 
                                                   class="btn btn-sm" 
                                                   target="_blank"
                                                   style="background: #ef4444; color: white; text-decoration: none;">
                                                    <i class="fa-solid fa-eye"></i> Lihat
                                                </a>
                                            <?php else: ?>
                                                <span style="color:var(--gray); font-size:12px;">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($l['status_verifikasi'] == 'pending'): ?>
                                                <a href="input_laporan.php?edit=<?= $l['id'] ?>" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                                <a href="?hapus_laporan=<?= $l['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus laporan ini?')"><i class="fa-solid fa-trash"></i></a>
                                            <?php else: ?>
                                                <span style="color:var(--gray); font-size:12px;"><i class="fa-solid fa-circle-check"></i> Sudah diverifikasi</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty">
                            <div class="icon"><i class="fa-solid fa-file-circle-xmark"></i></div>
                            <p><strong>Belum ada laporan</strong></p>
                            <p>Buat laporan dari kegiatan yang sudah Anda lakukan</p>
                            <a href="input_laporan.php" class="btn btn-primary" style="margin-top:15px;"><i class="fa-solid fa-file-circle-plus"></i> Buat Laporan</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Warning jika belum ditempatkan -->
                <div class="warning-box">
                    <strong>⚠️ Anda Belum Ditempatkan</strong>
                    <p>Maaf, Anda belum ditempatkan di lokasi KKN. Silakan hubungi koordinator KKN.</p>
                </div>
                <div style="text-align:center; margin-top:30px;">
                    <a href="dashboard.php" class="btn btn-primary">⬅️ Kembali ke Dashboard</a>
                </div>
            <?php endif; ?>
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

    // Fungsi Ganti Tab
    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.closest('.tab').classList.add('active');
    }

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