<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lembaga') { 
    header("Location: ../index.php"); 
    exit(); 
}

$pdo = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT lokasi_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$lokasi_id = $stmt->fetchColumn();

$docs = [];
if ($lokasi_id) {
    $stmt = $pdo->prepare("
        SELECT k.judul_kegiatan, k.tanggal_kegiatan, k.dokumentasi_foto
        FROM kegiatan k
        JOIN penempatan p ON k.penempatan_id = p.id
        WHERE p.lokasi_id = ? AND k.dokumentasi_foto != '' AND k.dokumentasi_foto IS NOT NULL
        ORDER BY k.tanggal_kegiatan DESC
    ");
    $stmt->execute([$lokasi_id]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Dokumentasi - KKN Tracking</title>
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

        /* Gallery Grid */
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .gallery-item {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .gallery-item:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.2);
        }

        .gallery-image {
            position: relative;
            height: 200px;
            overflow: hidden;
        }

        .gallery-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .gallery-item:hover .gallery-image img {
            transform: scale(1.05);
        }

        .gallery-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            color: white;
        }

        .gallery-overlay h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gallery-overlay p {
            font-size: 12px;
            opacity: 0.9;
        }

        .gallery-info {
            padding: 16px 20px;
            border-top: 1px solid var(--gray-100);
        }

        .gallery-info h4 {
            color: var(--dark);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .gallery-info p {
            color: var(--gray);
            font-size: 12px;
        }

        /* Empty State */
        .empty {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .empty .icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.4;
        }

        .empty strong {
            color: var(--dark);
            display: block;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .empty p {
            color: var(--gray);
            font-size: 14px;
        }

        /* Lightbox Modal */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        .lightbox.active {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .lightbox-content {
            position: relative;
            max-width: 90%;
            max-height: 90vh;
        }

        .lightbox img {
            max-width: 100%;
            max-height: 90vh;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }

        .lightbox-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 32px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: var(--transition);
        }

        .lightbox-close:hover {
            background: rgba(255,255,255,0.1);
        }

        .lightbox-caption {
            text-align: center;
            color: white;
            margin-top: 16px;
            font-size: 14px;
        }

        .lightbox-caption strong {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .lightbox-caption span {
            opacity: 0.8;
            font-size: 13px;
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
                text-align: center; 
                padding: 24px; 
                gap: 12px;
            }
            .gallery { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; }
            .gallery-image { height: 140px; }
            .gallery-info { padding: 12px 16px; }
            .gallery-info h4 { font-size: 13px; }
            .gallery-info p { font-size: 11px; }
            .top-bar { 
                padding: 16px 20px;
                flex-direction: column;
                gap: 12px;
            }
            .empty { padding: 40px 20px; }
            .lightbox-close { top: -30px; font-size: 28px; }
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

        <a href="dokumentasi.php" class="menu-item active">
            <span class="menu-icon">
                <i class="fa-solid fa-camera"></i>
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
                <h1 class="page-title-main">📸 Galeri Dokumentasi</h1>
                <p class="page-subtitle">Foto kegiatan mahasiswa di wilayah Anda</p>
            </div>

            <?php if (count($docs) > 0): ?>
            <div class="gallery">
                <?php foreach ($docs as $d): ?>
                <div class="gallery-item" onclick="openLight('<?= htmlspecialchars($d['dokumentasi_foto']) ?>', '<?= htmlspecialchars($d['judul_kegiatan']) ?>', '<?= date('d/m/Y', strtotime($d['tanggal_kegiatan'])) ?>')">
                    <div class="gallery-image">
                        <img src="../assets/uploads/<?= htmlspecialchars($d['dokumentasi_foto']) ?>" 
                             alt="<?= htmlspecialchars($d['judul_kegiatan']) ?>" 
                             onerror="this.src='https://via.placeholder.com/400x300?text=Foto+Tidak+Tersedia'">
                        <div class="gallery-overlay">
                            <h4><?= htmlspecialchars($d['judul_kegiatan']) ?></h4>
                            <p><?= date('d/m/Y', strtotime($d['tanggal_kegiatan'])) ?></p>
                        </div>
                    </div>
                    <div class="gallery-info">
                        <h4><?= htmlspecialchars($d['judul_kegiatan']) ?></h4>
                        <p>📅 <?= date('d/m/Y', strtotime($d['tanggal_kegiatan'])) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty">
                <div class="icon">📷</div>
                <strong>Belum ada dokumentasi foto</strong>
                <p>Foto akan muncul setelah mahasiswa mengupload dokumentasi kegiatan</p>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Lightbox Modal -->
    <div class="lightbox" id="lightbox">
        <div class="lightbox-content">
            <span class="lightbox-close" onclick="closeLight()">&times;</span>
            <img id="light-img" src="" alt="Preview">
            <div class="lightbox-caption">
                <strong id="light-title"></strong>
                <span id="light-date"></span>
            </div>
        </div>
    </div>

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

    // Lightbox Functions
    function openLight(src, title, date) {
        document.getElementById('light-img').src = '../assets/uploads/' + src;
        document.getElementById('light-title').textContent = title;
        document.getElementById('light-date').textContent = '📅 ' + date;
        document.getElementById('lightbox').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLight() {
        document.getElementById('lightbox').classList.remove('active');
        document.body.style.overflow = '';
    }

    // Close lightbox with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLight();
    });

    // Close lightbox when clicking outside
    document.getElementById('lightbox').addEventListener('click', function(e) {
        if (e.target === this) closeLight();
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