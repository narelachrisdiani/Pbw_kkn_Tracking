<?php
session_start();
require_once '../config/database.php';

// Cek login & role mahasiswa
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../index.php");
    exit();
}

$mahasiswa_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

// Ambil data penempatan mahasiswa
$stmt = $pdo->prepare("
    SELECT p.*, l.nama_desa, l.kecamatan, l.kabupaten, 
           pk.nama_program, u.nama as nama_dpl
    FROM penempatan p
    JOIN lokasi l ON p.lokasi_id = l.id
    JOIN program_kkn pk ON p.program_id = pk.id
    JOIN users u ON p.dpl_id = u.id
    WHERE p.mahasiswa_id = ? AND p.status = 'aktif'
");
$stmt->execute([$mahasiswa_id]);
$penempatan = $stmt->fetch(PDO::FETCH_ASSOC);

// Hitung statistik
$total_kegiatan = 0;
$total_laporan = 0;
$laporan_disetujui = 0;

if ($penempatan) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM kegiatan WHERE penempatan_id = ?");
    $stmt->execute([$penempatan['id']]);
    $total_kegiatan = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM laporan l
        JOIN kegiatan k ON l.kegiatan_id = k.id
        WHERE k.penempatan_id = ?
    ");
    $stmt->execute([$penempatan['id']]);
    $total_laporan = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM laporan l
        JOIN kegiatan k ON l.kegiatan_id = k.id
        WHERE k.penempatan_id = ? AND l.status_verifikasi = 'disetujui'
    ");
    $stmt->execute([$penempatan['id']]);
    $laporan_disetujui = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - KKN Tracking</title>
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
        }

        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed);
        }

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

        .back-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateX(-4px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
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
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
            font-size: 14px;
        }

        .user-role {
            font-size: 12px;
            color: var(--gray);
        }

        /* Container */
        .container {
            padding: 32px;
            max-width: 1400px;
        }

        /* Welcome Section */
        .welcome-card {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #06b6d4 100%);
            border-radius: 24px;
            padding: 40px;
            color: white;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.3);
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }

        .welcome-subtitle {
            opacity: 0.9;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }

        /* Info Box */
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            margin-top: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .info-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .info-item {
            padding: 16px;
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
        }

        .info-label {
            font-size: 12px;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            color: var(--dark);
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin: 32px 0;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, #10b981, #34d399);
        }

        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, #06b6d4, #22d3ee);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #06b6d4, #22d3ee);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 6px;
            line-height: 1;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: white;
            border: 2px solid transparent;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .action-btn.secondary {
            background: white;
            color: var(--dark);
            border-color: rgba(0,0,0,0.1);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .action-btn.primary:hover {
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.4);
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-warning {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            border-left: 4px solid #f59e0b;
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
            }
            .sidebar.collapsed ~ .main-content {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .welcome-title { font-size: 1.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
            .container { padding: 20px; }
            .top-bar { padding: 16px 20px; }
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

        <a href="input_kegiatan.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-square-plus"></i>
            </span>
            <span class="menu-text">Input Kegiatan</span>
        </a>

        <a href="input_laporan.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-file-pen"></i>
            </span>
            <span class="menu-text">Laporan</span>
        </a>

        <a href="riwayat.php" class="menu-item">
            <span class="menu-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
            <span class="menu-text">Riwayat</span>
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
            <a href="javascript:history.back()" class="back-btn">
                <span>←</span>
                <span>Kembali</span>
            </a>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role">Mahasiswa</div>
                </div>
            </div>
        </div>

        <div class="container">
            <?php if (!$penempatan): ?>
            <div class="alert alert-warning">
                <span>⚠️</span>
                <div>
                    <strong>Perhatian:</strong> Anda belum ditempatkan di lokasi KKN. Silakan hubungi koordinator KKN.
                </div>
            </div>
            <?php endif; ?>

           <!-- Welcome Card -->
<div class="welcome-card">
    <h1 class="welcome-title">
        <i class="fa-solid fa-hand-sparkles"></i>
        Selamat Datang, <?= htmlspecialchars($_SESSION['nama']) ?>!
    </h1>

    <p class="welcome-subtitle">
        Dashboard Mahasiswa KKN - Pantau kegiatan dan laporan Anda di sini
    </p>
</div>

            <?php if ($penempatan): ?>
            <!-- Info Penempatan -->
            <div class="info-card">
                <h2 class="info-title">📍 Informasi Penempatan</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Desa/Kelurahan</div>
                        <div class="info-value"><?= htmlspecialchars($penempatan['nama_desa']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Kecamatan</div>
                        <div class="info-value"><?= htmlspecialchars($penempatan['kecamatan']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Kabupaten/Kota</div>
                        <div class="info-value"><?= htmlspecialchars($penempatan['kabupaten']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Program KKN</div>
                        <div class="info-value"><?= htmlspecialchars($penempatan['nama_program']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">DPL Pembimbing</div>
                        <div class="info-value"><?= htmlspecialchars($penempatan['nama_dpl']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tanggal Penempatan</div>
                        <div class="info-value"><?= date('d F Y', strtotime($penempatan['tanggal_penempatan'])) ?></div>
                    </div>
                </div>
            </div>

           <!-- Tambahkan Font Awesome di <head> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Stats Grid -->
<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-chart-column"></i>
        </div>
        <div class="stat-number"><?= $total_kegiatan ?></div>
        <div class="stat-label">Total Kegiatan</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-file-lines"></i>
        </div>
        <div class="stat-number"><?= $total_laporan ?></div>
        <div class="stat-label">Total Laporan</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        <div class="stat-number"><?= $laporan_disetujui ?></div>
        <div class="stat-label">Laporan Disetujui</div>
    </div>

</div>

          <!-- Tambahkan Font Awesome di <head> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- Quick Actions -->
<div class="quick-actions">

    <a href="input_kegiatan.php" class="action-btn primary">
        <span>
            <i class="fa-solid fa-square-plus"></i>
        </span>
        <span>Input Kegiatan Baru</span>
    </a>

    <a href="input_laporan.php" class="action-btn secondary">
        <span>
            <i class="fa-solid fa-file-pen"></i>
        </span>
        <span>Buat Laporan</span>
    </a>

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

            // Auto-hide alert after 5 seconds
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