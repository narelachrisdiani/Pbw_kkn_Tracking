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
$edit_mode = false;
$edit_data = null;

// ========================================
// MODE EDIT (Klik tombol Edit)
// ========================================
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM program_kkn WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $edit_mode = true;
    } else {
        $error = "❌ Program tidak ditemukan!";
    }
}

// ========================================
// PROSES SIMPAN (TAMBAH / UPDATE)
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode = trim($_POST['kode_program']);
    $nama = trim($_POST['nama_program']);
    $periode = trim($_POST['periode']);
    $tahun = $_POST['tahun'];
    $tgl_mulai = $_POST['tanggal_mulai'] ?: null;
    $tgl_selesai = $_POST['tanggal_selesai'] ?: null;
    $status = $_POST['status'] ?? 'aktif';
    
    // Validasi wajib
    if (empty($kode) || empty($nama) || empty($periode) || empty($tahun)) {
        $error = "❌ Kode, Nama Program, Periode, dan Tahun wajib diisi!";
    } else {
        try {
            if ($edit_mode && $edit_data) {
                // === MODE UPDATE ===
                $stmt = $pdo->prepare("
                    UPDATE program_kkn 
                    SET kode_program=?, nama_program=?, periode=?, tahun=?, 
                        tanggal_mulai=?, tanggal_selesai=?, status=? 
                    WHERE id=?
                ");
                $stmt->execute([
                    $kode, $nama, $periode, $tahun,
                    $tgl_mulai, $tgl_selesai, $status, $edit_data['id']
                ]);
                $success = "✅ Program '{$nama}' berhasil diupdate!";
                $edit_mode = false;
                $edit_data = null;
                
            } else {
                // === MODE TAMBAH ===
                // Cek duplikat kode program
                $check = $pdo->prepare("SELECT id FROM program_kkn WHERE kode_program = ?");
                $check->execute([$kode]);
                
                if ($check->rowCount() > 0) {
                    $error = "❌ Kode program '{$kode}' sudah terdaftar!";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO program_kkn (kode_program, nama_program, periode, tahun, tanggal_mulai, tanggal_selesai, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $kode, $nama, $periode, $tahun,
                        $tgl_mulai, $tgl_selesai, $status
                    ]);
                    $success = "✅ Program '{$nama}' berhasil ditambahkan!";
                }
            }
        } catch (PDOException $e) {
            $error = "❌ Error database: " . $e->getMessage();
        }
    }
}

// ========================================
// PROSES HAPUS
// ========================================
if (isset($_GET['hapus'])) {
    $hapus_id = $_GET['hapus'];
    
    // CEK KEAMANAN: Jangan hapus jika ada penempatan aktif
    $cek = $pdo->prepare("SELECT COUNT(*) FROM penempatan WHERE program_id = ? AND status = 'aktif'");
    $cek->execute([$hapus_id]);
    
    if ($cek->fetchColumn() > 0) {
        $error = "❌ Tidak bisa hapus! Masih ada mahasiswa yang ditempatkan di program ini.";
    } else {
        // Ambil nama program untuk konfirmasi
        $nama_hapus = $pdo->prepare("SELECT nama_program FROM program_kkn WHERE id = ?");
        $nama_hapus->execute([$hapus_id]);
        $nama = $nama_hapus->fetchColumn();
        
        try {
            $pdo->prepare("DELETE FROM program_kkn WHERE id = ?")->execute([$hapus_id]);
            $success = "✅ Program '{$nama}' berhasil dihapus!";
        } catch (PDOException $e) {
            $error = "❌ Gagal menghapus: " . $e->getMessage();
        }
    }
}

// ========================================
// AMBIL DATA PROGRAM UNTUK TABEL
// ========================================
$stmt = $pdo->query("SELECT * FROM program_kkn ORDER BY tahun DESC, created_at DESC");
$program_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik
$total_program = count($program_list);
$aktif_count = $pdo->query("SELECT COUNT(*) FROM program_kkn WHERE status='aktif'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Program - Admin KKN Tracking</title>
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

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
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

        .stat-card h4 {
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

        .stat-card.total { border-left-color: var(--primary); }
        .stat-card.active { border-left-color: var(--success); }
        .stat-card.other { border-left-color: var(--warning); }

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

        .form-header {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 4px solid var(--warning);
            color: #92400e;
        }

        .form-header.edit {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-left-color: var(--warning);
        }

        .form-header h3 {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 600;
        }

        .form-header p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
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

        .btn-success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
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

        .table-badge {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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

        /* Program Info */
        .prog-code {
            font-family: 'SF Mono', 'Fira Code', monospace;
            background: var(--gray-100);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            color: var(--dark);
            font-weight: 600;
        }

        .prog-info {
            font-size: 13px;
            color: var(--gray-700);
            line-height: 1.5;
        }

        .prog-info strong {
            color: var(--dark);
            font-weight: 600;
        }

        .prog-info small {
            color: var(--gray);
            font-size: 12px;
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

        .badge-aktif {
            background: #dcfce7;
            color: #166534;
        }

        .badge-selesai {
            background: #f3f4f6;
            color: #374151;
        }

        .badge-ditunda {
            background: #fef3c7;
            color: #92400e;
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
            .stats-row { grid-template-columns: 1fr; }
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

        <a href="kelola_program.php" class="menu-item active">
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
            <div class="page-title">Kelola Program</div>
            
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
                <h1 class="page-title-main">📋 Kelola Program KKN</h1>
                <p class="page-subtitle">Tambah, edit, atau hapus program KKN yang tersedia untuk mahasiswa</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
                <div class="alert alert-success">✅ <?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card total">
                    <h4><?= $total_program ?></h4>
                    <p>Total Program</p>
                </div>
                <div class="stat-card active">
                    <h4><?= $aktif_count ?></h4>
                    <p>Aktif</p>
                </div>
                <div class="stat-card other">
                    <h4><?= $total_program - $aktif_count ?></h4>
                    <p>Selesai/Ditunda</p>
                </div>
            </div>

            <!-- FORM SECTION: Tambah / Edit Program -->
            <div class="form-card">
                <div class="form-title">
                    <span>
                        <?= $edit_mode ? '✏️ Edit Program KKN' : '➕ Tambah Program Baru' ?>
                        <?php if ($edit_mode): ?>
                            <span class="edit-badge">Mode Edit</span>
                        <?php endif; ?>
                    </span>
                    <?php if ($edit_mode): ?>
                        <a href="kelola_program.php" class="btn btn-secondary btn-sm">❌ Batal</a>
                    <?php endif; ?>
                </div>

                <?php if ($edit_mode && $edit_data): ?>
                <div class="form-header edit">
                    <h3>Sedang mengedit: <strong><?= htmlspecialchars($edit_data['nama_program']) ?></strong></h3>
                    <p>Kode: <span class="prog-code"><?= htmlspecialchars($edit_data['kode_program']) ?></span> | Periode: <?= htmlspecialchars($edit_data['periode']) ?></p>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kode Program <span class="required">*</span></label>
                            <input type="text" name="kode_program" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['kode_program'] ?? '') ?>" 
                                   required placeholder="Contoh: KKN2024-01" maxlength="20">
                            <div class="form-note">Kode unik, tidak boleh duplikat</div>
                        </div>
                        <div class="form-group">
                            <label>Nama Program <span class="required">*</span></label>
                            <input type="text" name="nama_program" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['nama_program'] ?? '') ?>" 
                                   required placeholder="Contoh: KKN Reguler Semester Genap">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Periode <span class="required">*</span></label>
                            <input type="text" name="periode" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['periode'] ?? '') ?>" 
                                   required placeholder="Contoh: Jan-Mar 2024">
                        </div>
                        <div class="form-group">
                            <label>Tahun <span class="required">*</span></label>
                            <input type="number" name="tahun" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['tahun'] ?? date('Y')) ?>" 
                                   required min="2020" max="2030">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Mulai</label>
                            <input type="date" name="tanggal_mulai" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['tanggal_mulai'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Selesai</label>
                            <input type="date" name="tanggal_selesai" class="form-control" 
                                   value="<?= htmlspecialchars($edit_data['tanggal_selesai'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status Program</label>
                        <select name="status" class="form-control">
                            <option value="aktif" <?= ($edit_data['status'] ?? '') == 'aktif' ? 'selected' : '' ?>>🟢 Aktif</option>
                            <option value="selesai" <?= ($edit_data['status'] ?? '') == 'selesai' ? 'selected' : '' ?>>⚪ Selesai</option>
                            <option value="ditunda" <?= ($edit_data['status'] ?? '') == 'ditunda' ? 'selected' : '' ?>>🟡 Ditunda</option>
                        </select>
                    </div>

                    <div class="btn-group">
                        <?php if ($edit_mode): ?>
                            <button type="submit" class="btn btn-warning">💾 Update Program</button>
                            <a href="kelola_program.php" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-success">💾 Simpan Program</button>
                            <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- TABLE SECTION: Daftar Program -->
            <div class="table-section">
                <div class="table-title">
                    <span>📋 Daftar Program KKN</span>
                    <span class="table-badge"><?= $total_program ?> Program</span>
                </div>

                <?php if ($total_program > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kode</th>
                                <th width="25%">Nama Program</th>
                                <th width="15%">Periode</th>
                                <th width="10%">Tahun</th>
                                <th width="12%">Status</th>
                                <th width="18%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($program_list as $prog): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><span class="prog-code"><?= htmlspecialchars($prog['kode_program']) ?></span></td>
                                <td>
                                    <strong><?= htmlspecialchars($prog['nama_program']) ?></strong>
                                    <?php if ($prog['tanggal_mulai'] && $prog['tanggal_selesai']): ?>
                                    <br><small style="color:var(--gray);">
                                        <?= date('d/m/Y', strtotime($prog['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($prog['tanggal_selesai'])) ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($prog['periode']) ?></td>
                                <td><?= $prog['tahun'] ?></td>
                                <td>
                                    <span class="badge badge-<?= $prog['status'] ?>">
                                        <?= ucfirst($prog['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="?edit=<?= $prog['id'] ?>" 
                                           class="btn btn-primary btn-sm" 
                                           title="Edit Program">
                                            <span>✏️</span>
                                            <span class="btn-text">Edit</span>
                                        </a>
                                        <a href="?hapus=<?= $prog['id'] ?>" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Hapus program <?= addslashes($prog['nama_program']) ?>?\n\n⚠️ Pastikan tidak ada mahasiswa aktif di program ini!')"
                                           title="Hapus Program">
                                            <span>🗑️</span>
                                            <span class="btn-text">Hapus</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <div class="icon">📋</div>
                    <p><strong>Belum ada program KKN</strong></p>
                    <p style="font-size:13px;">Silakan tambahkan program menggunakan form di atas</p>
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

        // Auto-focus ke field pertama saat load
        const firstInput = document.querySelector('input[name="kode_program"]');
        if (firstInput && !firstInput.disabled) firstInput.focus();

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

    // Validasi tanggal: selesai harus >= mulai
    document.querySelector('form')?.addEventListener('change', function(e) {
        const mulai = document.querySelector('input[name="tanggal_mulai"]')?.value;
        const selesai = document.querySelector('input[name="tanggal_selesai"]')?.value;
        
        if (mulai && selesai && new Date(selesai) < new Date(mulai)) {
            alert('⚠️ Tanggal selesai tidak boleh lebih awal dari tanggal mulai!');
            document.querySelector('input[name="tanggal_selesai"]').value = '';
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