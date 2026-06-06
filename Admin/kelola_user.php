<?php
session_start();
require_once '../config/database.php';

// HANYA ADMIN YANG BISA AKSES
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$database = new Database();
$pdo = $database->getConnection();

$success = '';
$error = '';
$roles = ['admin', 'dpl', 'mahasiswa', 'lembaga'];
$current_role = $_GET['role'] ?? 'all';

// ========================================
// 🔥 FITUR BARU: MODE EDIT
// ========================================
$edit_mode = false;
$edit_data = null;

if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $edit_mode = true;
    } else {
        $error = "❌ User tidak ditemukan!";
    }
}

// ========================================
// 🔥 FITUR BARU: DETAIL MAHASISWA (DPL + LEMBAGA)
// ========================================
$detail_mahasiswa = null;

if (isset($_GET['detail']) && $_GET['role'] == 'mahasiswa') {
    $mhs_id = $_GET['detail'];
    
    $stmt = $pdo->prepare("
        SELECT 
            m.id, m.nama, m.npm_nip, m.email,
            dpl.nama as nama_dpl, dpl.email as email_dpl,
            l.nama_desa, l.kecamatan, l.kabupaten, l.provinsi,
            l.nama_pemdes, l.kontak_pemdes,
            pk.nama_program, pk.periode,
            p.tanggal_penempatan, p.status as status_penempatan
        FROM users m
        LEFT JOIN penempatan p ON m.id = p.mahasiswa_id AND p.status = 'aktif'
        LEFT JOIN users dpl ON p.dpl_id = dpl.id
        LEFT JOIN lokasi l ON p.lokasi_id = l.id
        LEFT JOIN program_kkn pk ON p.program_id = pk.id
        WHERE m.id = ? AND m.role = 'mahasiswa'
    ");
    $stmt->execute([$mhs_id]);
    $detail_mahasiswa = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ========================================
// AMBIL DATA UNTUK DROPDOWN
// ========================================
$dpl_list = $pdo->query("SELECT id, nama, npm_nip FROM users WHERE role = 'dpl' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
$lokasi_list = $pdo->query("SELECT id, nama_desa FROM lokasi ORDER BY nama_desa")->fetchAll(PDO::FETCH_ASSOC);
$program_list = $pdo->query("SELECT id, nama_program FROM program_kkn WHERE status = 'aktif' ORDER BY nama_program")->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// 🔥 FITUR BARU: PROSES UPDATE USER
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // === MODE UPDATE (EDIT) ===
    if (isset($_POST['update_user'])) {
        $user_id = $_POST['user_id'];
        $npm_nip = trim($_POST['npm_nip']);
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password_baru = $_POST['password_baru'] ?? '';
        
        if (empty($npm_nip) || empty($nama) || empty($email)) {
            $error = "❌ Semua field wajib diisi!";
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->execute([$email, $user_id]);
                
                if ($check->rowCount() > 0) {
                    $error = "❌ Email sudah digunakan user lain!";
                } else {
                    if (!empty($password_baru)) {
                        if (strlen($password_baru) < 6) {
                            $error = "❌ Password baru minimal 6 karakter!";
                        } else {
                            $hashed = password_hash($password_baru, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET npm_nip=?, nama=?, email=?, password=? WHERE id=?");
                            $stmt->execute([$npm_nip, $nama, $email, $hashed, $user_id]);
                            $success = "✅ User '{$nama}' berhasil diupdate dengan password baru!";
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET npm_nip=?, nama=?, email=? WHERE id=?");
                        $stmt->execute([$npm_nip, $nama, $email, $user_id]);
                        $success = "✅ User '{$nama}' berhasil diupdate!";
                    }
                    $edit_mode = false;
                    $edit_data = null;
                }
            } catch (PDOException $e) {
                $error = "❌ Gagal update: " . $e->getMessage();
            }
        }
        
    } elseif (isset($_POST['tambah_user'])) {
        $npm_nip = trim($_POST['npm_nip']);
        $nama = trim($_POST['nama']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $password = 'admin123';
        
        if (empty($npm_nip) || empty($nama) || empty($email)) {
            $error = "❌ Semua field wajib diisi!";
        } else {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE npm_nip = ? OR email = ?");
                $check->execute([$npm_nip, $email]);
                
                if ($check->rowCount() > 0) {
                    $error = "❌ NIP/NPM atau Email sudah terdaftar!";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (npm_nip, nama, email, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$npm_nip, $nama, $email, $hashed, $role]);
                    $user_id = $pdo->lastInsertId();
                    
                    if ($role == 'mahasiswa') {
                        $dpl_id = $_POST['dpl_id'] ?? null;
                        $lokasi_id = $_POST['lokasi_id'] ?? null;
                        $program_id = $_POST['program_id'] ?? null;
                        
                        if ($dpl_id) {
                            $stmt_penempatan = $pdo->prepare("
                                INSERT INTO penempatan (mahasiswa_id, dpl_id, lokasi_id, program_id, tanggal_penempatan, status)
                                VALUES (?, ?, ?, ?, NOW(), 'aktif')
                            ");
                            $stmt_penempatan->execute([$user_id, $dpl_id, $lokasi_id ?: null, $program_id ?: null]);
                            $success = "✅ User '{$nama}' berhasil ditambahkan! Password: admin123<br>";
                            $success .= "📍 Mahasiswa ditempatkan dengan DPL: " . 
                                       $pdo->query("SELECT nama FROM users WHERE id = $dpl_id")->fetchColumn();
                        } else {
                            $success = "✅ User '{$nama}' berhasil ditambahkan! Password: admin123<br>";
                            $success .= "⚠️ Mahasiswa belum ditempatkan";
                        }
                    } else {
                        $success = "✅ User '{$nama}' berhasil ditambahkan! Password: admin123";
                    }
                }
            } catch (PDOException $e) {
                $error = "❌ Gagal: " . $e->getMessage();
            }
        }
    }
}

// ========================================
// HAPUS USER
// ========================================
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    
    if ($id == $admin_id) {
        $error = "❌ Tidak bisa menghapus akun sendiri!";
    } else {
        try {
            $cek = $pdo->prepare("SELECT COUNT(*) FROM penempatan WHERE mahasiswa_id = ? AND status = 'aktif'");
            $cek->execute([$id]);
            
            if ($cek->fetchColumn() > 0) {
                $pdo->prepare("UPDATE penempatan SET status = 'selesai' WHERE mahasiswa_id = ?")->execute([$id]);
            }
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $success = "✅ User berhasil dihapus!";
        } catch (PDOException $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    }
}

// ========================================
// AMBIL DATA USER
// ========================================
if ($current_role == 'all') {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY role, nama");
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? ORDER BY nama");
    $stmt->execute([$current_role]);
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$admin_nama = $_SESSION['nama'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Admin KKN Tracking</title>
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

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
            padding: 6px;
            background: var(--gray-100);
            border-radius: 12px;
            width: fit-content;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: var(--gray);
            border-radius: 10px;
            transition: var(--transition);
            text-decoration: none;
        }

        .tab:hover {
            color: var(--dark);
        }

        .tab.active {
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .tab-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 6px;
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

        /* Edit Notice */
        .edit-notice {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid var(--warning);
            color: #92400e;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
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

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
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

        /* Conditional Fields */
        .conditional-field {
            display: none;
            animation: slideDown 0.3s ease;
        }

        .conditional-field.show {
            display: block;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Hover effects yang lebih smooth */
        .action-buttons .btn {
            transition: all 0.2s ease;
            transform: none;
        }

        .action-buttons .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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

        /* Badge */
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

        .badge-admin { background: var(--dark); color: white; }
        .badge-dpl { background: var(--secondary); color: white; }
        .badge-mahasiswa { background: var(--success); color: white; }
        .badge-lembaga { background: var(--warning); color: var(--dark); }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            max-width: 750px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 1px solid rgba(0,0,0,0.1);
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            border-radius: 20px 20px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: 1.25rem;
            font-weight: 700;
        }

        .modal-header .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--gray);
            text-decoration: none;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: var(--transition);
        }

        .modal-header .close-btn:hover {
            background: var(--gray-100);
            color: var(--dark);
        }

        .modal-body {
            padding: 28px;
        }

        .info-section {
            background: var(--gray-50);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 16px;
            border-left: 4px solid var(--primary);
        }

        .info-section h4 {
            margin: 0 0 16px;
            color: var(--dark);
            font-size: 1rem;
            font-weight: 700;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-section p {
            margin: 8px 0;
            color: var(--gray-700);
            font-size: 14px;
            line-height: 1.6;
            display: flex;
            justify-content: space-between;
        }

        .info-section p strong {
            color: var(--dark);
            font-weight: 600;
            text-align: right;
            max-width: 60%;
        }

        .info-dpl { border-left-color: var(--secondary); background: #f5f3ff; }
        .info-lokasi { border-left-color: var(--success); background: #ecfdf5; }
        .info-program { border-left-color: var(--accent); background: #f0f9ff; }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--gray-200);
            text-align: right;
            background: var(--gray-50);
            border-radius: 0 0 20px 20px;
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
            .info-section p { flex-direction: column; gap: 4px; }
            .info-section p strong { text-align: left; max-width: 100%; }
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

        <a href="kelola_user.php" class="menu-item active">
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
            <div class="page-title">Kelola Pengguna</div>
            
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div>
                    <div class="user-role">Administrator</div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title-main">👥 Kelola Pengguna Sistem</h1>
                <p class="page-subtitle">Tambah, edit, atau hapus user dengan role Admin, DPL, Mahasiswa, atau Lembaga</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <a href="?role=all" class="tab <?= $current_role=='all'?'active':'' ?>">
                    Semua <span class="tab-count"><?= count($users) ?></span>
                </a>
                <?php foreach(['dpl','mahasiswa','lembaga'] as $r): ?>
                    <a href="?role=<?= $r ?>" class="tab <?= $current_role==$r?'active':'' ?>">
                        <?= ucfirst($r) ?> 
                        <span class="tab-count"><?= $pdo->query("SELECT COUNT(*) FROM users WHERE role='$r'")->fetchColumn() ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Form Tambah / Edit User -->
            <div class="form-card">
                <div class="form-title">
                    <span>
                        <?= $edit_mode ? '✏️ Edit User' : '➕ Tambah User Baru' ?>
                        <?php if ($edit_mode): ?>
                            <span class="edit-badge">Mode Edit</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($edit_mode): ?>
                        <a href="kelola_user.php?role=<?= $current_role ?>" class="btn btn-secondary btn-sm">❌ Batal</a>
                    <?php endif; ?>
                </div>
                
                <?php if ($edit_mode && $edit_data): ?>
                <div class="edit-notice">
                    <span>⚠️</span>
                    <strong>Sedang mengedit:</strong> <?= htmlspecialchars($edit_data['nama']) ?> (<?= htmlspecialchars($edit_data['npm_nip']) ?>)
                </div>
                <?php endif; ?>

                <form method="POST" id="formUser">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="user_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>NIP/NPM <span class="required">*</span></label>
                            <input type="text" name="npm_nip" class="form-control" required 
                                   value="<?= htmlspecialchars($edit_data['npm_nip'] ?? '') ?>" 
                                   placeholder="Contoh: DPL004 atau M006">
                        </div>
                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama" class="form-control" required 
                                   value="<?= htmlspecialchars($edit_data['nama'] ?? '') ?>" 
                                   placeholder="Nama lengkap user">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?= htmlspecialchars($edit_data['email'] ?? '') ?>" 
                                   placeholder="email@contoh.com">
                        </div>
                        <div class="form-group">
                            <label>Role <span class="required">*</span></label>
                            <select name="role" id="selectRole" class="form-control" required onchange="toggleMahasiswaFields()" <?= $edit_mode ? 'disabled' : '' ?>>
                                <?php foreach($roles as $r): if($r=='admin') continue; ?>
                                    <option value="<?= $r ?>" <?= ($edit_data['role'] ?? $current_role)==$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($edit_mode): ?>
                                <div class="form-note">⚠️ Role tidak bisa diubah saat edit</div>
                                <input type="hidden" name="role" value="<?= $edit_data['role'] ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$edit_mode): ?>
                    <div id="mahasiswaFields" class="conditional-field">
                        <div style="background: #f0f4ff; padding: 16px; border-radius: 12px; margin-bottom: 16px; border-left: 4px solid var(--primary);">
                            <strong style="color: var(--primary-dark); display: block; margin-bottom: 4px;">📍 Penempatan Mahasiswa</strong>
                            <small style="color: var(--gray);">Pilih DPL pembimbing (langsung tersimpan ke database)</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>DPL Pembimbing <span class="required">*</span></label>
                                <select name="dpl_id" class="form-control">
                                    <option value="">-- Pilih DPL --</option>
                                    <?php foreach ($dpl_list as $dpl): ?>
                                        <option value="<?= $dpl['id'] ?>"><?= htmlspecialchars($dpl['nama']) ?> (<?= $dpl['npm_nip'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Lokasi KKN (Opsional)</label>
                                <select name="lokasi_id" class="form-control">
                                    <option value="">-- Belum ditentukan --</option>
                                    <?php foreach ($lokasi_list as $lok): ?>
                                        <option value="<?= $lok['id'] ?>"><?= htmlspecialchars($lok['nama_desa']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Program KKN (Opsional)</label>
                                <select name="program_id" class="form-control">
                                    <option value="">-- Belum ditentukan --</option>
                                    <?php foreach ($program_list as $prog): ?>
                                        <option value="<?= $prog['id'] ?>"><?= htmlspecialchars($prog['nama_program']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($edit_mode): ?>
                    <div class="password-section">
                        <h4>🔑 Ganti Password (Opsional)</h4>
                        <div class="form-note" style="margin-bottom:10px;">Kosongkan jika tidak ingin mengubah password</div>
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" placeholder="Minimal 6 karakter" minlength="6">
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); padding: 14px 18px; border-radius: 12px; margin: 16px 0; border-left: 4px solid var(--warning);">
                        <small style="color:#924004;"><strong>🔑 Password default: admin123</strong> (user bisa ganti sendiri)</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="btn-group">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_user" class="btn btn-warning">💾 Update User</button>
                            <a href="kelola_user.php?role=<?= $current_role ?>" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" name="tambah_user" class="btn btn-primary">💾 Tambah User</button>
                            <button type="reset" class="btn btn-secondary">🔄 Reset</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabel User -->
            <div class="table-section">
                <div class="table-title">
                    <span>📋 Daftar Pengguna</span>
                    <span style="font-size:13px; color:var(--gray); font-weight:500;"><?= count($users) ?> user</span>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>NIP/NPM</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Terdaftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['npm_nip']) ?></strong></td>
                                <td><?= htmlspecialchars($u['nama']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                                <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($u['role'] == 'mahasiswa'): ?>
                                            <a href="?detail=<?= $u['id'] ?>&role=<?= $current_role ?>" 
                                               class="btn btn-primary btn-sm" 
                                               title="Lihat Detail">
                                                <span></span>
                                                <span class="btn-text">Detail</span>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="?edit=<?= $u['id'] ?>&role=<?= $current_role ?>" 
                                           class="btn btn-warning btn-sm" 
                                           title="Edit User">
                                            <span>✏️</span>
                                            <span class="btn-text">Edit</span>
                                        </a>
                                        
                                        <?php if ($u['id'] != $admin_id): ?>
                                            <a href="?hapus=<?= $u['id'] ?>&role=<?= $current_role ?>" 
                                               class="btn btn-danger btn-sm" 
                                               onclick="return confirm('Hapus user <?= addslashes($u['nama']) ?>?')"
                                               title="Hapus User">
                                                <span>🗑️</span>
                                                <span class="btn-text">Hapus</span>
                                            </a>
                                        <?php else: ?>
                                            <span style="color:var(--gray);font-size:11px;">(Akun Anda)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Detail Mahasiswa -->
    <?php if ($detail_mahasiswa): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👤 Detail Mahasiswa</h3>
                <a href="?role=<?= $current_role ?>" class="close-btn">&times;</a>
            </div>
            
            <div class="modal-body">
                <!-- Info Mahasiswa -->
                <div class="info-section">
                    <h4>📋 Data Mahasiswa</h4>
                    <p><span>Nama</span><strong><?= htmlspecialchars($detail_mahasiswa['nama']) ?></strong></p>
                    <p><span>NPM</span><strong><?= htmlspecialchars($detail_mahasiswa['npm_nip']) ?></strong></p>
                    <p><span>Email</span><strong><?= htmlspecialchars($detail_mahasiswa['email']) ?></strong></p>
                    <p><span>Status Penempatan</span>
                        <strong>
                            <span style="padding:4px 10px; border-radius:12px; font-size:11px; font-weight:600; background:<?= $detail_mahasiswa['status_penempatan']=='aktif'?'#dcfce7':'#f3f4f6' ?>; color:<?= $detail_mahasiswa['status_penempatan']=='aktif'?'#166534':'#374151' ?>;">
                                <?= ucfirst($detail_mahasiswa['status_penempatan'] ?? 'Belum Ditempatkan') ?>
                            </span>
                        </strong>
                    </p>
                </div>
                
                <!-- Info DPL -->
                <?php if ($detail_mahasiswa['nama_dpl']): ?>
                <div class="info-section info-dpl">
                    <h4>👨‍🏫 DPL Pembimbing</h4>
                    <p><span>Nama</span><strong><?= htmlspecialchars($detail_mahasiswa['nama_dpl']) ?></strong></p>
                    <p><span>Email</span><strong><?= htmlspecialchars($detail_mahasiswa['email_dpl']) ?></strong></p>
                    <p><span>Tanggal Penempatan</span><strong><?= $detail_mahasiswa['tanggal_penempatan'] ? date('d/m/Y', strtotime($detail_mahasiswa['tanggal_penempatan'])) : '-' ?></strong></p>
                </div>
                <?php else: ?>
                <div style="background:#fffbeb; padding:16px; border-radius:12px; margin-bottom:16px; color:#92400e; border-left:4px solid var(--warning);">
                    ⚠️ Mahasiswa ini belum memiliki DPL pembimbing.
                </div>
                <?php endif; ?>
                
                <!-- Info Lembaga/Desa -->
                <?php if ($detail_mahasiswa['nama_desa']): ?>
                <div class="info-section info-lokasi">
                    <h4>🏢 Lembaga / Desa</h4>
                    <p><span>Desa</span><strong><?= htmlspecialchars($detail_mahasiswa['nama_desa']) ?></strong></p>
                    <p><span>Kecamatan</span><strong><?= htmlspecialchars($detail_mahasiswa['kecamatan']) ?></strong></p>
                    <p><span>Kabupaten</span><strong><?= htmlspecialchars($detail_mahasiswa['kabupaten']) ?></strong></p>
                    <p><span>Provinsi</span><strong><?= htmlspecialchars($detail_mahasiswa['provinsi']) ?></strong></p>
                    <?php if ($detail_mahasiswa['nama_pemdes']): ?>
                    <p><span>Kepala Desa</span><strong><?= htmlspecialchars($detail_mahasiswa['nama_pemdes']) ?> (<?= htmlspecialchars($detail_mahasiswa['kontak_pemdes']) ?>)</strong></p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div style="background:#fffbeb; padding:16px; border-radius:12px; margin-bottom:16px; color:#92400e; border-left:4px solid var(--warning);">
                    ⚠️ Mahasiswa ini belum ditempatkan di desa/lokasi tertentu.
                </div>
                <?php endif; ?>
                
                <!-- Info Program -->
                <?php if ($detail_mahasiswa['nama_program']): ?>
                <div class="info-section info-program">
                    <h4>📋 Program KKN</h4>
                    <p><span>Nama Program</span><strong><?= htmlspecialchars($detail_mahasiswa['nama_program']) ?></strong></p>
                    <p><span>Periode</span><strong><?= htmlspecialchars($detail_mahasiswa['periode']) ?></strong></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer">
                <a href="?role=<?= $current_role ?>" class="btn btn-secondary">Tutup</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    // Toggle Mahasiswa Fields
    function toggleMahasiswaFields() {
        const role = document.getElementById('selectRole').value;
        const mahasiswaFields = document.getElementById('mahasiswaFields');
        const dplSelect = document.querySelector('select[name="dpl_id"]');
        if (role === 'mahasiswa') {
            mahasiswaFields.classList.add('show');
            dplSelect.required = true;
        } else {
            mahasiswaFields.classList.remove('show');
            dplSelect.required = false;
            dplSelect.value = '';
        }
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.querySelector('.modal-overlay');
            if (modal) {
                window.location.href = '?role=' + (document.querySelector('[name="role"]')?.value || 'all');
            }
        }
    });

    // Close modal when clicking outside
    document.querySelector('.modal-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) {
            window.location.href = '?role=' + (document.querySelector('[name="role"]')?.value || 'all');
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