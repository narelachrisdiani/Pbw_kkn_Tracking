<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lembaga') { 
    header("Location: ../index.php"); 
    exit(); 
}

$pdo = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Ambil lokasi_id milik lembaga
$stmt = $pdo->prepare("SELECT lokasi_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$lokasi_id = $stmt->fetchColumn();

$lokasi = null;
if ($lokasi_id) {
    $stmt = $pdo->prepare("SELECT * FROM lokasi WHERE id = ?");
    $stmt->execute([$lokasi_id]);
    $lokasi = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Statistik
$total_kegiatan = 0; 
$total_mahasiswa = 0; 
$laporan_disetujui = 0;

if ($lokasi_id) {
    $total_kegiatan = $pdo->query("SELECT COUNT(*) FROM kegiatan k JOIN penempatan p ON k.penempatan_id = p.id WHERE p.lokasi_id = $lokasi_id")->fetchColumn();
    $total_mahasiswa = $pdo->query("SELECT COUNT(DISTINCT p.mahasiswa_id) FROM penempatan p WHERE p.lokasi_id = $lokasi_id AND p.status = 'aktif'")->fetchColumn();
    $laporan_disetujui = $pdo->query("SELECT COUNT(*) FROM laporan l JOIN kegiatan k ON l.kegiatan_id = k.id JOIN penempatan p ON k.penempatan_id = p.id WHERE p.lokasi_id = $lokasi_id AND l.status_verifikasi = 'disetujui'")->fetchColumn();
}

// Kegiatan terbaru - pastikan SELECT kolom yang dibutuhkan
$recent_kegiatan = [];
if ($lokasi_id) {
    $stmt = $pdo->prepare("
        SELECT k.id, k.judul_kegiatan, k.jenis_kegiatan, k.tanggal_kegiatan, k.status 
        FROM kegiatan k 
        JOIN penempatan p ON k.penempatan_id = p.id 
        WHERE p.lokasi_id = ? 
        ORDER BY k.tanggal_kegiatan DESC 
        LIMIT 5
    ");
    $stmt->execute([$lokasi_id]);
    $recent_kegiatan = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function isActive($page) { 
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : ''; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Lembaga - KKN Tracking</title>
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
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            color: white;
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 30px rgba(6, 182, 212, 0.3);
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
        }

        /* Location Info Box */
        .location-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            border-left: 4px solid var(--accent);
        }

        .location-box h3 {
            color: var(--dark);
            margin-bottom: 12px;
            font-size: 1rem;
            font-weight: 700;
        }

        .location-box p {
            margin: 6px 0;
            color: var(--gray-700);
            font-size: 14px;
        }

        .location-box strong {
            color: var(--dark);
            font-weight: 600;
        }

        /* Stats Cards */
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
            line-height: 1;
        }

        .stat-card p {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-card.kegiatan { border-left-color: var(--primary); }
        .stat-card.mahasiswa { border-left-color: var(--success); }
        .stat-card.laporan { border-left-color: var(--accent); }

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

        /* Status Badge */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-selesai {
            background: #dcfce7;
            color: #166534;
        }

        .badge-berjalan {
            background: #fef3c7;
            color: #92400e;
        }

        /* Buttons */
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
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
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

        /* Warning Box */
        .warning-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 16px 20px;
            border-radius: 12px;
            margin-top: 20px;
            border-left: 4px solid var(--warning);
            color: #92400e;
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
        <a href="dashboard.php" class="menu-item active">
            <span class="menu-icon">
                <i class="fa-solid fa-house"></i>
            </span>
            <span class="menu-text">Dashboard</span>
        </a>

        <a href="kegiatan.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-list-check"></i>
            </span>
            <span class="menu-text">Kegiatan</span>
        </a>

        <a href="laporan.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-file-lines"></i>
            </span>
            <span class="menu-text">Laporan</span>
        </a>

        <a href="dokumentasi.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-image"></i>
            </span>
            <span class="menu-text">Dokumentasi</span>
        </a>

        <a href="profil.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-user"></i>
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
            <div class="page-title">Dashboard Lembaga</div>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role">Lembaga Mitra</div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-content">
                    <h1>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?>! 👋</h1>
                    <p>Dashboard Lembaga Mitra KKN - Pantau kegiatan mahasiswa di wilayah Anda</p>
                </div>
                <div class="welcome-badge">ROLE: LEMBAGA</div>
            </div>

            <!-- Location Info -->
            <?php if ($lokasi): ?>
            <div class="location-box">
                <h3>📍 Wilayah Binaan</h3>
                <p><strong>Desa:</strong> <?= htmlspecialchars($lokasi['nama_desa']) ?></p>
                <p><strong>Kecamatan:</strong> <?= htmlspecialchars($lokasi['kecamatan']) ?></p>
                <p><strong>Kabupaten:</strong> <?= htmlspecialchars($lokasi['kabupaten']) ?></p>
                <p><strong>Kepala Desa:</strong> <?= htmlspecialchars($lokasi['nama_pemdes'] ?? '-') ?></p>
            </div>
            <?php else: ?>
            <div class="warning-box">
                <strong>⚠️ Akun Belum Terhubung</strong>
                <p style="margin:8px 0 0;">Akun Anda belum terhubung dengan lokasi desa. Silakan hubungi administrator untuk penyesuaian.</p>
            </div>
            <?php endif; ?>

            <?php if ($lokasi_id): ?>
            <!-- Statistics Cards -->
            <div class="stats">
                <div class="stat-card kegiatan">
                    <h3><?= $total_kegiatan ?></h3>
                    <p>Total Kegiatan</p>
                </div>
                <div class="stat-card mahasiswa">
                    <h3><?= $total_mahasiswa ?></h3>
                    <p>Mahasiswa Aktif</p>
                </div>
                <div class="stat-card laporan">
                    <h3><?= $laporan_disetujui ?></h3>
                    <p>Laporan Disetujui</p>
                </div>
            </div>

            <!-- Table Section -->
            <div class="table-section">
                <div class="table-title">
                    <span>📋 Kegiatan Terbaru</span>
                    <a href="kegiatan.php" class="btn">Lihat Semua</a>
                </div>
                
                <?php if (count($recent_kegiatan) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="15%">Tanggal</th>
                                <th width="40%">Judul</th>
                                <th width="20%">Jenis</th>
                                <th width="15%">Status</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_kegiatan as $k): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($k['tanggal_kegiatan'])) ?></td>
                                <td><strong><?= htmlspecialchars($k['judul_kegiatan']) ?></strong></td>
                                <td>
                                    <?php 
                                    if (isset($k['jenis_kegiatan']) && !empty(trim($k['jenis_kegiatan']))) {
                                        echo ucfirst($k['jenis_kegiatan']);
                                    } else {
                                        echo '<em style="color:var(--gray);">-</em>';
                                    }
                                    ?>
                                </td>
                                <td><span class="badge badge-<?= $k['status'] ?>"><?= ucfirst($k['status']) ?></span></td>
                                <td>
                                    <a href="kegiatan.php?detail=<?= $k['id'] ?>" class="btn" style="padding:6px 12px; font-size:12px;">detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <div class="icon">📭</div>
                    <p><strong>Belum ada kegiatan tercatat</strong></p>
                    <p style="font-size:13px;">Tunggu mahasiswa input kegiatan di wilayah Anda</p>
                </div>
                <?php endif; ?>
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
