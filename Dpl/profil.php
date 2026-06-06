<?php
session_start();
require_once '../config/database.php';

// Cek login & role DPL
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'dpl') {
    header("Location: ../index.php");
    exit();
}

$dpl_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

$success = '';
$error = '';

// ========================================
// AMBIL DATA USER SAAT INI (READ)
// ========================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$dpl_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ========================================
// PROSES UPDATE PROFIL (UPDATE)
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_baru = trim($_POST['nama']);
    $email_baru = trim($_POST['email']);
    $pass_lama = $_POST['password_lama'] ?? '';
    $pass_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_pass = $_POST['konfirmasi_pass'] ?? '';

    // Validasi dasar
    if (empty($nama_baru) || empty($email_baru)) {
        $error = "❌ Nama dan Email wajib diisi!";
    } else {
        // Cek apakah user ingin ganti password
        $ganti_password = false;
        $hash_baru = null; // ✅ Inisialisasi variable di awal
        
        if (!empty($pass_lama) || !empty($pass_baru) || !empty($konfirmasi_pass)) {
            
            // Jika user isi salah satu field password, harus isi semua
            if (empty($pass_lama) || empty($pass_baru) || empty($konfirmasi_pass)) {
                $error = "❌ Jika ingin ganti password, semua kolom password harus diisi!";
            } else {
                // Verifikasi password lama
                // Cek hash di database ATAU password default 'admin123'
                $pass_valid = password_verify($pass_lama, $user['password']) || ($pass_lama === 'admin123');

                if (!$pass_valid) {
                    $error = "❌ Password lama salah!";
                } elseif ($pass_baru !== $konfirmasi_pass) {
                    $error = "❌ Password baru dan konfirmasi tidak cocok!";
                } elseif (strlen($pass_baru) < 6) {
                    $error = "❌ Password baru minimal 6 karakter!";
                } else {
                    $ganti_password = true;
                    $hash_baru = password_hash($pass_baru, PASSWORD_DEFAULT); // ✅ Definisi variable
                }
            }
        }

        // Jika tidak ada error, jalankan update
        if (empty($error)) {
            try {
                if ($ganti_password && $hash_baru !== null) {
                    // Update Nama, Email, DAN Password
                    $stmt = $pdo->prepare("UPDATE users SET nama=?, email=?, password=? WHERE id=?");
                    $stmt->execute([$nama_baru, $email_baru, $hash_baru, $dpl_id]);
                } else {
                    // Hanya Update Nama & Email
                    $stmt = $pdo->prepare("UPDATE users SET nama=?, email=? WHERE id=?");
                    $stmt->execute([$nama_baru, $email_baru, $dpl_id]);
                }

                $success = "✅ Profil berhasil diperbarui!";
                
                // Update Session agar nama di Navbar langsung berubah
                $_SESSION['nama'] = $nama_baru;
                
                // Refresh data user
                $user['nama'] = $nama_baru;
                $user['email'] = $email_baru;

            } catch (PDOException $e) {
                // Cek error duplikat email
                if ($e->getCode() == 23000) {
                    $error = "❌ Email sudah digunakan oleh user lain!";
                } else {
                    $error = "❌ Gagal update profil: " . $e->getMessage();
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
    <title>Edit Profil - KKN Tracking</title>
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
            max-width: 900px;
            margin: 0 auto;
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

        /* Form Card */
        .card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 24px;
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

        .form-note {
            font-size: 12px;
            color: var(--gray);
            margin-top: 6px;
            line-height: 1.4;
        }

        /* Divider */
        .divider {
            margin: 32px 0;
            border-top: 2px dashed var(--gray-200);
            position: relative;
        }

        .divider span {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 16px;
            color: var(--gray);
            font-size: 13px;
            font-weight: 500;
        }

        /* Password Section */
        .password-tip {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid var(--success);
        }

        .password-tip p {
            color: #065f46;
            margin: 4px 0;
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
            .profile-header { 
                flex-direction: column; 
                text-align: center; 
                padding: 32px 24px; 
            }
            .profile-info p { justify-content: center; }
            .card { padding: 24px; }
            .top-bar { padding: 16px 20px; flex-direction: column; gap: 12px; }
            .btn-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .form-row { grid-template-columns: 1fr; }
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

        <a href="kelola_lokasi.php" class="menu-item">
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
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['nama']) ?></h1>
                    <p>📧 <?= htmlspecialchars($user['email']) ?></p>
                    <p>🆔 <?= htmlspecialchars($user['npm_nip']) ?></p>
                    <span class="badge-role">👨‍🏫 Dosen Pembimbing</span>
                </div>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- Form Edit Profil -->
            <div class="card">
                <h2 class="card-title">✏️ Edit Profil Saya</h2>
                
                <form method="POST">
                    <!-- Data Pribadi -->
                    <div class="form-group">
                        <label>NIP (ID Pengguna)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['npm_nip']) ?>" disabled>
                        <div class="form-note">*ID tidak dapat diubah</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="divider">
                        <span>🔒 Ganti Password (Opsional)</span>
                    </div>

                    <!-- Password Section -->
                    <div class="password-tip">
                        <p><strong>💡 Tips:</strong></p>
                        <p>• Password minimal 6 karakter</p>
                        <p>• Gunakan kombinasi huruf, angka, dan simbol</p>
                        <p>• Jangan gunakan password yang sama dengan akun lain</p>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Password Lama</label>
                            <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password lama untuk verifikasi">
                        </div>
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="Minimal 6 karakter" minlength="6">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="konfirmasi_pass" class="form-control" placeholder="Ulangi password baru">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                        <a href="dashboard.php" class="btn btn-secondary">❌ Batal</a>
                    </div>
                </form>
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

    // Konfirmasi sebelum keluar jika ada perubahan
    let formChanged = false;
    document.querySelectorAll('input, select, textarea').forEach(input => {
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
