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
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_bulan = $_GET['bulan'] ?? '';

// Query laporan yang sudah disetujui
$query = "
    SELECT l.*, k.judul_kegiatan, k.tanggal_kegiatan, u.nama as nama_mahasiswa
    FROM laporan l
    JOIN kegiatan k ON l.kegiatan_id = k.id
    JOIN penempatan p ON k.penempatan_id = p.id
    JOIN users u ON p.mahasiswa_id = u.id
    WHERE p.lokasi_id = ? AND l.status_verifikasi = 'disetujui'
";
$params = [$lokasi_id];

if ($filter_tahun) {
    $query .= " AND YEAR(l.tanggal_laporan) = ?";
    $params[] = $filter_tahun;
}
if ($filter_bulan) {
    $query .= " AND MONTH(l.tanggal_laporan) = ?";
    $params[] = $filter_bulan;
}

$query .= " ORDER BY l.tanggal_laporan DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$laporan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM laporan l
    JOIN kegiatan k ON l.kegiatan_id = k.id
    JOIN penempatan p ON k.penempatan_id = p.id
    WHERE p.lokasi_id = ? AND l.status_verifikasi = 'disetujui'
");
$stmt->execute([$lokasi_id]);
$total_laporan = $stmt->fetchColumn();

function isActive($page) { 
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : ''; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - KKN Tracking</title>
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

        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar-header { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between; transition: var(--transition); }
        .sidebar-brand { font-size: 1.4rem; font-weight: 700; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; white-space: nowrap; overflow: hidden; transition: var(--transition); }
        .sidebar.collapsed .sidebar-brand { opacity: 0; width: 0; }
        .toggle-btn { background: rgba(255,255,255,0.1); border: none; color: white; width: 36px; height: 36px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition); font-size: 18px; }
        .toggle-btn:hover { background: rgba(255,255,255,0.2); transform: rotate(180deg); }
        .sidebar-menu { padding: 24px 16px; display: flex; flex-direction: column; gap: 8px; }
        .menu-item { display: flex; align-items: center; padding: 14px 18px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px; transition: var(--transition); position: relative; overflow: hidden; white-space: nowrap; }
        .menu-item::before { content: ''; position: absolute; left: 0; top: 0; width: 0; height: 100%; background: linear-gradient(90deg, var(--primary), var(--secondary)); transition: var(--transition); }
        .menu-item:hover::before, .menu-item.active::before { width: 100%; }
        .menu-item:hover, .menu-item.active { color: white; transform: translateX(4px); }
        .menu-icon { font-size: 22px; margin-right: 14px; min-width: 24px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; }
        .menu-text { font-weight: 500; font-size: 14px; position: relative; z-index: 1; transition: var(--transition); }
        .sidebar.collapsed .menu-text { opacity: 0; width: 0; }
        .sidebar-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .logout-btn { display: flex; align-items: center; padding: 12px 18px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; transition: var(--transition); width: 100%; text-decoration: none; white-space: nowrap; overflow: hidden; }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4); }
        .logout-icon { margin-right: 12px; font-size: 18px; min-width: 24px; }
        .sidebar.collapsed .logout-text { display: none; }

        /* Main Content */
        .main-content { margin-left: var(--sidebar-width); transition: var(--transition); min-height: 100vh; width: calc(100% - var(--sidebar-width)); }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); width: calc(100% - var(--sidebar-collapsed)); }

        /* Top Bar */
        .top-bar { background: rgba(255,255,255,0.9); backdrop-filter: blur(12px); padding: 20px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; }
        .page-title { font-weight: 600; color: var(--dark); font-size: 18px; }
        .user-info { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .user-avatar { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; flex-shrink: 0; }
        .user-details { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; color: var(--dark); font-size: 14px; white-space: nowrap; }
        .user-role { font-size: 12px; color: var(--gray); }

        /* Container */
        .container { padding: 32px; max-width: 1400px; margin: 0 auto; }

        /* Page Header */
        .page-header { background: white; padding: 32px; border-radius: 20px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .page-title-main { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .page-subtitle { color: var(--gray); font-size: 14px; }
        .page-badge { background: linear-gradient(135deg, var(--success), #34d399); color: white; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; }

        /* Filter Card */
        .filter-card { background: white; padding: 28px; border-radius: 20px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .filter-title { font-size: 1.1rem; font-weight: 700; color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); font-size: 14px; }
        .filter-control { width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: 10px; font-size: 14px; transition: var(--transition); background: white; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
        .filter-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .filter-actions { display: flex; gap: 12px; flex-wrap: wrap; }

        /* Report Cards */
        .report-list { display: flex; flex-direction: column; gap: 24px; }
        .report-card { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); transition: var(--transition); }
        .report-card:hover { transform: translateY(-4px); box-shadow: 0 12px 40px rgba(99, 102, 241, 0.15); }
        .report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid var(--gray-200); flex-wrap: wrap; gap: 12px; }
        .report-title { font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
        .report-meta { display: flex; gap: 16px; color: var(--gray); font-size: 13px; flex-wrap: wrap; }
        .report-meta span { display: flex; align-items: center; gap: 4px; }
        .badge-status { background: linear-gradient(135deg, var(--success), #34d399); color: white; padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .report-section { margin-bottom: 20px; }
        .report-section:last-child { margin-bottom: 0; }
        .report-label { font-weight: 600; color: var(--dark); font-size: 13px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .report-text { color: var(--gray-700); font-size: 14px; line-height: 1.7; background: var(--gray-50); padding: 16px; border-radius: 12px; white-space: pre-wrap; }
        .highlight-impact { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border-left: 4px solid var(--success); }
        .highlight-impact .report-label { color: #065f46; }
        .highlight-impact .report-text { background: transparent; color: #047857; }

        /* PDF Download Section */
        .pdf-section {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border-left: 4px solid var(--danger);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .pdf-section .report-label {
            color: #dc2626;
            margin-bottom: 12px;
        }

        .pdf-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-pdf {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-pdf-primary {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
        }

        .btn-pdf-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-pdf-secondary {
            background: var(--gray-200);
            color: var(--dark);
        }

        .btn-pdf-secondary:hover {
            background: var(--gray-300);
        }

        .pdf-info {
            margin-top: 10px;
            font-size: 12px;
            color: var(--gray);
        }

        /* Empty State */
        .empty { background: white; border-radius: 20px; padding: 60px 40px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .empty .icon { font-size: 64px; margin-bottom: 16px; opacity: 0.4; }
        .empty strong { color: var(--dark); display: block; margin-bottom: 8px; font-size: 16px; }
        .empty p { color: var(--gray); font-size: 14px; }

        /* Buttons */
        .btn { padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .btn-secondary { background: var(--gray-200); color: var(--dark); }
        .btn-secondary:hover { background: var(--gray-300); }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .sidebar.collapsed ~ .main-content { margin-left: 0; width: 100%; }
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .page-header { flex-direction: column; align-items: flex-start; padding: 24px; }
            .filter-card { padding: 20px; }
            .filter-row { grid-template-columns: 1fr; }
            .filter-actions { width: 100%; }
            .filter-actions .btn { width: 100%; justify-content: center; }
            .top-bar { padding: 16px 20px; flex-direction: column; gap: 12px; }
            .report-card { padding: 24px; }
            .report-header { flex-direction: column; }
            .report-meta { flex-direction: column; gap: 8px; }
            .empty { padding: 40px 20px; }
            .pdf-buttons { flex-direction: column; }
            .btn-pdf { width: 100%; justify-content: center; }
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
            <a href="dashboard.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-chart-line"></i></span><span class="menu-text">Dashboard</span></a>
            <a href="kegiatan.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-list-check"></i></span><span class="menu-text">Kegiatan</span></a>
            <a href="laporan.php" class="menu-item active"><span class="menu-icon"><i class="fa-solid fa-file-lines"></i></span><span class="menu-text">Laporan</span></a>
            <a href="dokumentasi.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-image"></i></span><span class="menu-text">Dokumentasi</span></a>
            <a href="profil.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-user"></i></span><span class="menu-text">Profil</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn"><span class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="logout-text">Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <a href="javascript:history.back()" class="back-btn" style="display: flex; align-items: center; gap: 8px; padding: 10px 18px; background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--dark); text-decoration: none; transition: var(--transition);">
                <span>←</span><span>Kembali</span>
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
                    <h1 class="page-title-main"><i class="fa-solid fa-file-lines"></i> Laporan Kegiatan</h1>
                    <p class="page-subtitle">Laporan yang telah diverifikasi DPL</p>
                </div>
                <span class="page-badge"><?= $total_laporan ?> Laporan</span>
            </div>

            <!-- Filter Card -->
            <div class="filter-card">
                <h3 class="filter-title"><i class="fa-solid fa-magnifying-glass"></i> Filter Laporan</h3>
                <form method="GET" id="filterForm">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Tahun</label>
                            <select name="tahun" class="filter-control" onchange="this.form.submit()">
                                <?php 
                                $tahun_sekarang = date('Y');
                                for ($t = $tahun_sekarang; $t >= $tahun_sekarang - 2; $t--): ?>
                                    <option value="<?= $t ?>" <?= $filter_tahun == $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Bulan</label>
                            <select name="bulan" class="filter-control" onchange="this.form.submit()">
                                <option value="">Semua Bulan</option>
                                <?php
                                $bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                for ($i=1; $i<=12; $i++): ?>
                                    <option value="<?= $i ?>" <?= $filter_bulan == $i ? 'selected' : '' ?>><?= $bulan_nama[$i] ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                        <a href="laporan.php" class="btn btn-secondary"><i class="fa-solid fa-rotate"></i> Reset</a>
                    </div>
                </form>
            </div>

            <!-- Report List -->
            <div class="report-list">
                <?php if (count($laporan_list) > 0): ?>
                    <?php foreach ($laporan_list as $l): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div>
                                <div class="report-title"><?= htmlspecialchars($l['judul_kegiatan']) ?></div>
                                <div class="report-meta">
                                    <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($l['tanggal_laporan'])) ?></span>
                                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($l['nama_mahasiswa']) ?></span>
                                    <span><i class="fa-solid fa-file-lines"></i> <?= ucfirst($l['jenis_laporan']) ?></span>
                                </div>
                            </div>
                            <span class="badge-status"><i class="fa-solid fa-check-circle"></i> Disetujui</span>
                        </div>
                        
                        <!-- PDF Download Section -->
                        <?php if (!empty($l['file_pdf'])): ?>
                        <div class="pdf-section">
                            <div class="report-label">
                                <i class="fa-solid fa-file-pdf"></i> File Laporan PDF
                            </div>
                            <div class="pdf-buttons">
                                <a href="../assets/uploads/laporan/<?= htmlspecialchars($l['file_pdf']) ?>" 
                                   class="btn-pdf btn-pdf-primary" 
                                   download>
                                    <i class="fa-solid fa-download"></i> Download PDF
                                </a>
                                <a href="../assets/uploads/laporan/<?= htmlspecialchars($l['file_pdf']) ?>" 
                                   class="btn-pdf btn-pdf-secondary" 
                                   target="_blank">
                                    <i class="fa-solid fa-eye"></i> Lihat PDF
                                </a>
                            </div>
                            <div class="pdf-info">
                                <i class="fa-solid fa-paperclip"></i> File: <?= htmlspecialchars($l['file_pdf']) ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="pdf-section" style="background: var(--gray-50); border-left-color: var(--gray);">
                            <div class="report-label" style="color: var(--gray);">
                                <i class="fa-solid fa-file-pdf"></i> File Laporan PDF
                            </div>
                            <p style="color: var(--gray); margin: 0; font-size: 13px;">
                                <i class="fa-solid fa-circle-info"></i> Mahasiswa tidak mengupload file PDF untuk laporan ini
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="report-section">
                            <span class="report-label"><i class="fa-solid fa-pen-to-square"></i> Uraian Kegiatan</span>
                            <div class="report-text"><?= nl2br(htmlspecialchars($l['uraian_kegiatan'])) ?></div>
                        </div>
                        
                        <?php if ($l['capaian']): ?>
                        <div class="report-section">
                            <span class="report-label"><i class="fa-solid fa-target"></i> Capaian</span>
                            <div class="report-text"><?= nl2br(htmlspecialchars($l['capaian'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($l['kendala_lapangan']): ?>
                        <div class="report-section">
                            <span class="report-label"><i class="fa-solid fa-triangle-exclamation"></i> Kendala Lapangan</span>
                            <div class="report-text"><?= nl2br(htmlspecialchars($l['kendala_lapangan'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($l['dampak_sosial']): ?>
                        <div class="report-section highlight-impact">
                            <span class="report-label"><i class="fa-solid fa-star"></i> Dampak Sosial</span>
                            <div class="report-text"><?= nl2br(htmlspecialchars($l['dampak_sosial'])) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($l['rekomendasi']): ?>
                        <div class="report-section">
                            <span class="report-label"><i class="fa-solid fa-lightbulb"></i> Rekomendasi</span>
                            <div class="report-text"><?= nl2br(htmlspecialchars($l['rekomendasi'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="empty">
                    <div class="icon"><i class="fa-solid fa-file-circle-xmark"></i></div>
                    <strong>Tidak ada laporan ditemukan</strong>
                    <p>Belum ada laporan yang terverifikasi untuk periode ini</p>
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