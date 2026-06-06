<?php
session_start();
require_once '../config/database.php';

// Cek login & role HARUS admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

// ========================================
// STATISTIK SYSTEM-WIDE (Admin lihat SEMUA)
// ========================================

// 1. Total Users per Role
$stmt = $pdo->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
$role_stats = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $role_stats[$row['role']] = $row['total'];
}

// 2. Total Lokasi KKN
$total_lokasi = $pdo->query("SELECT COUNT(*) FROM lokasi")->fetchColumn();

// 3. Total Program Aktif
$total_program = $pdo->query("SELECT COUNT(*) FROM program_kkn WHERE status = 'aktif'")->fetchColumn();

// 4. Total Penempatan Aktif (SEMUA DPL)
$total_penempatan = $pdo->query("SELECT COUNT(*) FROM penempatan WHERE status = 'aktif'")->fetchColumn();

// 5. Total Kegiatan Berjalan
$kegiatan_berjalan = $pdo->query("SELECT COUNT(*) FROM kegiatan WHERE status = 'berjalan'")->fetchColumn();

// 6. Total Laporan Pending (SEMUA)
$laporan_pending = $pdo->query("SELECT COUNT(*) FROM laporan WHERE status_verifikasi = 'pending'")->fetchColumn();

// 7. Laporan Pending Terbaru (untuk quick action)
$stmt = $pdo->query("
    SELECT 
        l.id, l.tanggal_laporan, l.jenis_laporan, l.status_verifikasi,
        u.nama as nama_mahasiswa, u.npm_nip,
        dpl.nama as nama_dpl,
        k.judul_kegiatan,
        lok.nama_desa
    FROM laporan l
    JOIN kegiatan k ON l.kegiatan_id = k.id
    JOIN penempatan p ON k.penempatan_id = p.id
    JOIN users u ON p.mahasiswa_id = u.id
    JOIN users dpl ON p.dpl_id = dpl.id
    JOIN lokasi lok ON p.lokasi_id = lok.id
    WHERE l.status_verifikasi = 'pending'
    ORDER BY l.created_at DESC
    LIMIT 10
");
$laporan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. DPL Teraktif (yang paling banyak mahasiswa bimbingan)
$stmt = $pdo->query("
    SELECT u.nama, COUNT(p.id) as total_mahasiswa
    FROM users u
    LEFT JOIN penempatan p ON u.id = p.dpl_id AND p.status = 'aktif'
    WHERE u.role = 'dpl'
    GROUP BY u.id
    ORDER BY total_mahasiswa DESC
    LIMIT 5
");
$top_dpl = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - KKN Tracking</title>
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

        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .welcome-content h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-content p {
            opacity: 0.9;
            font-size: 14px;
        }

        .welcome-badge {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(4px);
            white-space: nowrap;
        }

        /* Statistics Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            margin: 12px 0 6px;
            color: var(--dark);
            font-weight: 800;
        }

        .stat-card p {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-card.users { border-left-color: #3b82f6; }
        .stat-card.dpl { border-left-color: #8b5cf6; }
        .stat-card.mahasiswa { border-left-color: #10b981; }
        .stat-card.lembaga { border-left-color: #f59e0b; }
        .stat-card.lokasi { border-left-color: #ef4444; }
        .stat-card.pending { border-left-color: #f97316; }

        /* Section/Card */
        .section {
            background: white;
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--gray-200);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
        }

        /* Table */
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

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
            text-align: center;
        }

        .action-btn:hover {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.15);
        }

        .action-btn .icon {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .action-btn span {
            font-size: 13px;
            font-weight: 600;
        }

        /* DPL List */
        .dpl-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .dpl-item:last-child {
            border-bottom: none;
        }

        .dpl-name {
            font-weight: 500;
            color: var(--dark);
        }

        .dpl-count {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* System Info */
        .info-box {
            background: var(--gray-50);
            padding: 16px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.8;
            color: var(--gray-700);
        }

        .info-box strong {
            color: var(--dark);
        }

        /* Empty State */
        .empty {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty .icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.4;
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
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container { padding: 20px; }
            .welcome-card { 
                flex-direction: column; 
                text-align: center; 
                padding: 32px 24px; 
                gap: 20px;
            }
            .stats { grid-template-columns: repeat(2, 1fr); }
            .top-bar { 
                padding: 16px 20px;
                flex-direction: column;
                gap: 12px;
            }
            .quick-actions { grid-template-columns: 1fr; }
            .section { padding: 20px; }
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
        <a href="dashboard.php" class="menu-item active">
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

        <a href="kelola_lokasi.php" class="menu-item">
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
            <div class="page-title">Dashboard Admin</div>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-content">
                    <h1>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?>! 👋</h1>
                    <p>Super Administrator - Akses Penuh ke Seluruh Sistem</p>
                </div>
                <div class="welcome-badge">ROLE: ADMIN</div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats">
                <div class="stat-card users">
                    <p>Total Users</p>
                    <h3><?= array_sum($role_stats) ?></h3>
                </div>
                <div class="stat-card dpl">
                    <p>DPL</p>
                    <h3><?= $role_stats['dpl'] ?? 0 ?></h3>
                </div>
                <div class="stat-card mahasiswa">
                    <p>Mahasiswa</p>
                    <h3><?= $role_stats['mahasiswa'] ?? 0 ?></h3>
                </div>
                <div class="stat-card lembaga">
                    <p>Lembaga</p>
                    <h3><?= $role_stats['lembaga'] ?? 0 ?></h3>
                </div>
                <div class="stat-card lokasi">
                    <p>Lokasi KKN</p>
                    <h3><?= $total_lokasi ?></h3>
                </div>
                <div class="stat-card pending">
                    <p>Laporan Pending</p>
                    <h3><?= $laporan_pending ?></h3>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid-2">
                <!-- Left Column: Laporan Pending -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">📋 Laporan Pending (Semua DPL)</h2>
                        <a href="verifikasi_laporan.php" class="btn">Lihat Semua</a>
                    </div>
                    
                    <?php if (count($laporan_list) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Mahasiswa</th>
                                    <th>DPL</th>
                                    <th>Kegiatan</th>
                                    <th>Lokasi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($laporan_list as $l): ?>
                                <tr>
                                    <td><?= date('d/m', strtotime($l['tanggal_laporan'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($l['nama_mahasiswa']) ?></strong><br>
                                        <small style="color:var(--gray);"><?= $l['npm_nip'] ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($l['nama_dpl']) ?></td>
                                    <td><?= htmlspecialchars($l['judul_kegiatan']) ?></td>
                                    <td><?= htmlspecialchars($l['nama_desa']) ?></td>
                                    <td>
                                        <a href="verifikasi_laporan.php?detail=<?= $l['id'] ?>" class="btn">Verifikasi</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty">
                        <div class="icon">✅</div>
                        <p><strong>Tidak ada laporan pending</strong></p>
                        <p style="font-size:13px; margin-top:5px;">Semua laporan sudah diverifikasi!</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Quick Stats & Actions -->
                <div>
                    <!-- Top DPL -->
                    <div class="section">
                        <h2 class="section-title" style="margin-bottom:20px;">🏆 DPL Teraktif</h2>
                        <?php if (count($top_dpl) > 0): ?>
                            <?php foreach ($top_dpl as $i => $dpl): ?>
                            <div class="dpl-item">
                                <span class="dpl-name"><?= htmlspecialchars($dpl['nama']) ?></span>
                                <span class="dpl-count"><?= $dpl['total_mahasiswa'] ?> mhs</span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:var(--gray); font-size:13px;">Belum ada data DPL</p>
                        <?php endif; ?>
                    </div>

                   <!-- Tambahkan Font Awesome di <head> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Quick Actions -->
<div class="section">
    <h2 class="section-title" style="margin-bottom:20px;">
        <i class="fa-solid fa-bolt"></i> Aksi Cepat
    </h2>

    <div class="quick-actions">

        <a href="kelola_user.php?role=dpl" class="action-btn">
            <div class="icon">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <span>Tambah DPL</span>
        </a>

        <a href="kelola_user.php?role=mahasiswa" class="action-btn">
            <div class="icon">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <span>Tambah Mahasiswa</span>
        </a>

        <a href="kelola_lokasi.php" class="action-btn">
            <div class="icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>
            <span>Tambah Lokasi</span>
        </a>

        <a href="kelola_program.php" class="action-btn">
            <div class="icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <span>Program Baru</span>
        </a>

    </div>
</div>
                    <!-- System Info -->
                    <div class="section">
                        <h2 class="section-title" style="margin-bottom:20px;">ℹ️ Info Sistem</h2>
                        <div class="info-box">
                            <div><strong>Program Aktif:</strong> <?= $total_program ?></div>
                            <div><strong>Penempatan Aktif:</strong> <?= $total_penempatan ?></div>
                            <div><strong>Kegiatan Berjalan:</strong> <?= $kegiatan_berjalan ?></div>
                            <div style="margin-top:12px; padding-top:12px; border-top:1px solid var(--gray-200);">
                                <strong>Database:</strong> db_kkn_tracking<br>
                                <strong>PHP Version:</strong> <?= phpversion() ?>
                            </div>
                        </div>
                    </div>
                </div>
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