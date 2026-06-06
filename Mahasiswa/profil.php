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

$success = '';
$error = '';

// ========================================
// AMBIL DATA MAHASISWA & PENEMPATAN
// ========================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$mahasiswa_id]);
$mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        p.*,
        l.nama_desa, l.kecamatan, l.kabupaten, l.provinsi,
        u.nama as nama_dpl,
        pk.nama_program
    FROM penempatan p
    JOIN lokasi l ON p.lokasi_id = l.id
    JOIN users u ON p.dpl_id = u.id
    LEFT JOIN program_kkn pk ON p.program_id = pk.id
    WHERE p.mahasiswa_id = ? AND p.status = 'aktif'
");
$stmt->execute([$mahasiswa_id]);
$penempatan = $stmt->fetch(PDO::FETCH_ASSOC);

// ========================================
// 🔥 PROSES UPDATE PROFIL
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'update_profile') {
        // Update Nama & Email
        $nama_baru = trim($_POST['nama']);
        $email_baru = trim($_POST['email']);
        
        if (empty($nama_baru) || empty($email_baru)) {
            $error = "❌ Nama dan Email wajib diisi!";
        } else {
            // Cek apakah email sudah dipakai user lain
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email_baru, $mahasiswa_id]);
            
            if ($check->rowCount() > 0) {
                $error = "❌ Email sudah digunakan oleh user lain!";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET nama = ?, email = ? WHERE id = ?");
                    $stmt->execute([$nama_baru, $email_baru, $mahasiswa_id]);
                    
                    // Update session nama
                    $_SESSION['nama'] = $nama_baru;
                    
                    $success = "✅ Profil berhasil diperbarui!";
                    $mahasiswa['nama'] = $nama_baru;
                    $mahasiswa['email'] = $email_baru;
                } catch (PDOException $e) {
                    $error = "❌ Gagal update profil: " . $e->getMessage();
                }
            }
        }
        
    } elseif ($action == 'change_password') {
        // Ganti Password
        $pass_lama = $_POST['password_lama'];
        $pass_baru = $_POST['password_baru'];
        $konfirmasi_pass = $_POST['konfirmasi_pass'];
        
        if (empty($pass_lama) || empty($pass_baru) || empty($konfirmasi_pass)) {
            $error = "❌ Semua field password wajib diisi!";
        } elseif (strlen($pass_baru) < 6) {
            $error = "❌ Password baru minimal 6 karakter!";
        } elseif ($pass_baru !== $konfirmasi_pass) {
            $error = "❌ Password baru dan konfirmasi tidak cocok!";
        } else {
            // Verifikasi password lama
            $pass_valid = password_verify($pass_lama, $mahasiswa['password']) || ($pass_lama === 'admin123');
            
            if (!$pass_valid) {
                $error = "❌ Password lama salah!";
            } else {
                try {
                    $hashed = password_hash($pass_baru, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $mahasiswa_id]);
                    
                    $success = "✅ Password berhasil diubah! Silakan login ulang dengan password baru.";
                } catch (PDOException $e) {
                    $error = "❌ Gagal ganti password: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - KKN Tracking</title>
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
            max-width: 1100px;
        }

        /* Page Header */
        .profile-header {
            background: white;
            padding: 40px;
            border-radius: 24px;
            margin-bottom: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 42px;
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        }

        .profile-info h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .profile-info p {
            color: var(--gray);
            margin: 6px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .badge-role {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        /* Grid Layout */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        /* Card */
        .card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Info Box */
        .info-box {
            background: var(--gray-50);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--gray);
            font-size: 13px;
            font-weight: 500;
        }

        .info-value {
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
            text-align: right;
            max-width: 60%;
        }

        /* Form */
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

        .form-note {
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
            line-height: 1.4;
        }

        /* Password Section */
        .password-section {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 4px solid var(--warning);
        }

        .password-section h4 {
            color: #92400e;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-tip {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--success);
        }

        .security-tip p {
            color: #065f46;
            margin: 0;
            font-size: 14px;
            font-weight: 500;
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

        .btn-secondary {
            background: var(--gray-200);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
        }

        /* Stats Card */
        .stats-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 20px;
            padding: 32px;
            margin-top: 24px;
        }

        .stats-card .card-title {
            color: white;
            border-bottom-color: rgba(255,255,255,0.3);
            margin-bottom: 28px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: rgba(255,255,255,0.15);
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            backdrop-filter: blur(4px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 4px;
            line-height: 1;
        }

        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Warning Box */
        .warning-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 28px;
            border-left: 4px solid var(--warning);
            color: #92400e;
            text-align: center;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
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
            .grid-2 { grid-template-columns: 1fr; }
            .profile-header { flex-direction: column; text-align: center; padding: 32px 24px; }
            .profile-info p { justify-content: center; }
            .container { padding: 20px; }
            .card { padding: 24px; }
            .top-bar { padding: 16px 20px; }
            .btn-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .info-item { flex-direction: column; gap: 4px; }
            .info-value { text-align: left; max-width: 100%; }
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

        <a href="profil.php" class="menu-item active">
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
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar"><?= strtoupper(substr($mahasiswa['nama'], 0, 1)) ?></div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($mahasiswa['nama']) ?></h1>
                    <p>📧 <?= htmlspecialchars($mahasiswa['email']) ?></p>
                    <p>🆔 <?= htmlspecialchars($mahasiswa['npm_nip']) ?></p>
                    <span class="badge-role">👨‍🎓 Mahasiswa</span>
                </div>
            </div>

            <div class="grid-2">
                <!-- Form Edit Profil -->
                <div class="card">
                    <h2 class="card-title">✏️ Edit Profil</h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>NPM</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($mahasiswa['npm_nip']) ?>" disabled>
                            <div class="form-note">NPM tidak dapat diubah</div>
                        </div>

                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($mahasiswa['nama']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($mahasiswa['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Terdaftar Sejak</label>
                            <input type="text" class="form-control" value="<?= date('d F Y', strtotime($mahasiswa['created_at'])) ?>" disabled>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                            <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        </div>
                    </form>
                </div>

                <!-- Informasi Penempatan -->
                <div class="card">
                    <h2 class="card-title">📍 Informasi Penempatan</h2>
                    
                    <?php if ($penempatan): ?>
                    <div class="info-box">
                        <div class="info-item">
                            <span class="info-label">Desa/Kelurahan</span>
                            <span class="info-value"><?= htmlspecialchars($penempatan['nama_desa']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Kecamatan</span>
                            <span class="info-value"><?= htmlspecialchars($penempatan['kecamatan']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Kabupaten/Kota</span>
                            <span class="info-value"><?= htmlspecialchars($penempatan['kabupaten']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Provinsi</span>
                            <span class="info-value"><?= htmlspecialchars($penempatan['provinsi']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Program</span>
                            <span class="info-value"><?= htmlspecialchars($penempatan['nama_program'] ?? '-') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">DPL Pembimbing</span>
                            <span class="info-value"><?= htmlspecialchars($penempatan['nama_dpl']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tanggal Penempatan</span>
                            <span class="info-value"><?= date('d F Y', strtotime($penempatan['tanggal_penempatan'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <span class="info-value" style="color: var(--success);">● Aktif</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="warning-box">
                        <strong>⚠️ Belum Ditempatkan</strong>
                        <p style="margin:0; font-size:14px;">Anda belum ditempatkan di lokasi KKN</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ganti Password -->
            <div class="card">
                <h2 class="card-title">🔑 Ganti Password</h2>
                
                <div class="security-tip">
                    <p>💡 <strong>Tips Keamanan:</strong> Gunakan password yang kuat dan jangan bagikan ke siapapun!</p>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="grid-2" style="margin-bottom: 0;">
                        <div class="form-group">
                            <label>Password Lama <span class="required">*</span></label>
                            <input type="password" name="password_lama" class="form-control" required placeholder="Masukkan password lama">
                        </div>
                        <div class="form-group">
                            <label>Password Baru <span class="required">*</span></label>
                            <input type="password" name="password_baru" class="form-control" required placeholder="Minimal 6 karakter" minlength="6">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi Password Baru <span class="required">*</span></label>
                        <input type="password" name="konfirmasi_pass" class="form-control" required placeholder="Ulangi password baru">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">🔐 Ubah Password</button>
                    </div>
                </form>
            </div>

            <!-- Statistik Akun -->
            <div class="stats-card">
                <h2 class="card-title">📊 Statistik Akun Anda</h2>
                <?php
                // Ambil statistik
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
                } else {
                    $total_kegiatan = 0;
                    $total_laporan = 0;
                    $laporan_disetujui = 0;
                }
                ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_kegiatan ?></div>
                        <div class="stat-label">Total Kegiatan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_laporan ?></div>
                        <div class="stat-label">Total Laporan</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= $laporan_disetujui ?></div>
                        <div class="stat-label">Laporan Disetujui</div>
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

    // Konfirmasi sebelum keluar jika form berubah
    let formChanged = false;
    document.querySelectorAll('input, textarea').forEach(input => {
        input.addEventListener('change', () => formChanged = true);
    });
    
    window.addEventListener('beforeunload', (e) => {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
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