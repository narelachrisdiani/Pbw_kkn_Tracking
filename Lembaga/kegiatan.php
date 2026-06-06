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

// Filter
$filter_jenis = $_GET['jenis'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Query dasar
$query = "SELECT k.*, u.nama as nama_mahasiswa 
          FROM kegiatan k 
          JOIN penempatan p ON k.penempatan_id = p.id 
          JOIN users u ON p.mahasiswa_id = u.id
          WHERE p.lokasi_id = ?";
$params = [$lokasi_id];

// Tambahkan filter
if ($filter_jenis) {
    $query .= " AND k.jenis_kegiatan = ?";
    $params[] = $filter_jenis;
}
if ($filter_bulan) {
    $query .= " AND MONTH(k.tanggal_kegiatan) = ?";
    $params[] = $filter_bulan;
}
if ($filter_status) {
    $query .= " AND k.status = ?";
    $params[] = $filter_status;
}

$query .= " ORDER BY k.tanggal_kegiatan DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$kegiatan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil total tanpa filter untuk badge
$stmt = $pdo->prepare("SELECT COUNT(*) FROM kegiatan k JOIN penempatan p ON k.penempatan_id = p.id WHERE p.lokasi_id = ?");
$stmt->execute([$lokasi_id]);
$total_all = $stmt->fetchColumn();

function isActive($page) { 
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : ''; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kegiatan - KKN Tracking</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
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

        .page-badge {
            background: linear-gradient(135deg, var(--accent), #22d3ee);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Filter Card */
        .filter-card {
            background: white;
            padding: 28px;
            border-radius: 20px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .filter-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        .filter-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
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

        .badge-direncanakan {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
            .page-header { 
                flex-direction: column; 
                align-items: flex-start; 
                padding: 24px; 
            }
            .filter-card { padding: 20px; }
            .filter-row { grid-template-columns: 1fr; }
            .filter-actions { width: 100%; }
            .filter-actions .btn { width: 100%; justify-content: center; }
            .top-bar { padding: 16px 20px; flex-direction: column; gap: 12px; }
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

        <a href="kegiatan.php" class="menu-item active">
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
            <a href="javascript:history.back()" class="back-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--dark); text-decoration: none; transition: var(--transition);">
                <span>←</span>
                <span>Kembali</span>
            </a>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role">Lembaga Mitra</div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title-main"> Daftar Kegiatan</h1>
                    <p class="page-subtitle">Semua kegiatan mahasiswa di wilayah Anda</p>
                </div>
                <span class="page-badge"><?= $total_all ?> Kegiatan</span>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <h3 class="filter-title">🔍 Filter Kegiatan</h3>
                <form method="GET" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Jenis Kegiatan</label>
                            <select name="jenis" class="filter-control" onchange="this.form.submit()">
                                <option value="">Semua Jenis</option>
                                <option value="sosialisasi" <?= $filter_jenis=='sosialisasi'?'selected':'' ?>>Sosialisasi</option>
                                <option value="pelatihan" <?= $filter_jenis=='pelatihan'?'selected':'' ?>>Pelatihan</option>
                                <option value="pembangunan" <?= $filter_jenis=='pembangunan'?'selected':'' ?>>Pembangunan</option>
                                <option value="pendampingan" <?= $filter_jenis=='pendampingan'?'selected':'' ?>>Pendampingan</option>
                                <option value="penyuluhan" <?= $filter_jenis=='penyuluhan'?'selected':'' ?>>Penyuluhan</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Bulan</label>
                            <select name="bulan" class="filter-control" onchange="this.form.submit()">
                                <option value="">Semua Bulan</option>
                                <?php
                                $bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                for ($i=1; $i<=12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $filter_bulan==$i?'selected':'' ?>><?= $bulan_nama[$i] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="filter-control" onchange="this.form.submit()">
                                <option value="">Semua Status</option>
                                <option value="direncanakan" <?= $filter_status=='direncanakan'?'selected':'' ?>>Direncanakan</option>
                                <option value="berjalan" <?= $filter_status=='berjalan'?'selected':'' ?>>Berjalan</option>
                                <option value="selesai" <?= $filter_status=='selesai'?'selected':'' ?>>Selesai</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="kegiatan.php" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </form>
            </div>

            <!-- Table Section -->
            <div class="table-section">
                <div class="table-title">
                    <span> Daftar Kegiatan Mahasiswa</span>
                </div>
                
                <?php if (count($kegiatan_list) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="12%">Tanggal</th>
                                <th width="25%">Judul Kegiatan</th>
                                <th width="15%">Pelaksana</th>
                                <th width="12%">Jenis</th>
                                <th width="10%">Peserta</th>
                                <th width="10%">Status</th>
                                <th width="11%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($kegiatan_list as $k): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($k['tanggal_kegiatan'])) ?></td>
                                <td><strong><?= htmlspecialchars($k['judul_kegiatan']) ?></strong></td>
                                <td><?= htmlspecialchars($k['nama_mahasiswa']) ?></td>
                                <td><?= ucfirst($k['jenis_kegiatan'] ?? '-') ?></td>
                                <td><?= isset($k['peserta_count']) ? $k['peserta_count'] : '-' ?> orang</td>
                                <td><span class="badge badge-<?= $k['status'] ?>"><?= ucfirst($k['status']) ?></span></td>
                                <td>
                                    <a href="#" class="btn btn-primary btn-sm" onclick="showDetail(<?= htmlspecialchars(json_encode($k)) ?>); return false;"> Detail</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <div class="icon">📋</div>
                    <p><strong>Tidak ada kegiatan ditemukan</strong></p>
                    <p style="font-size:14px;">Coba ubah filter atau belum ada kegiatan yang tercatat</p>
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
    });

    // Show Detail (bisa dikembangkan jadi modal)
    function showDetail(data) {
        alert('Judul: ' + data.judul_kegiatan + '\nTanggal: ' + data.tanggal_kegiatan + '\nDeskripsi: ' + (data.deskripsi || '-'));
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