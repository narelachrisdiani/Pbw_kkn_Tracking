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
// 🔥 MODE EDIT - Cek apakah ada ?edit=ID
// ========================================
$edit_mode = false;
$edit_data = null;

if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    
    // Ambil data kegiatan yang akan diedit
    $stmt = $pdo->prepare("
        SELECT k.* FROM kegiatan k
        JOIN penempatan p ON k.penempatan_id = p.id
        WHERE k.id = ? AND p.mahasiswa_id = ?
    ");
    $stmt->execute([$edit_id, $mahasiswa_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $edit_mode = true;
    } else {
        $error = "❌ Kegiatan tidak ditemukan atau tidak memiliki akses!";
    }
}

// Ambil data penempatan mahasiswa
$stmt = $pdo->prepare("
    SELECT p.id as penempatan_id, p.status,
           l.nama_desa, l.kecamatan, l.kabupaten,
           u.nama as nama_dpl
    FROM penempatan p
    JOIN lokasi l ON p.lokasi_id = l.id
    JOIN users u ON p.dpl_id = u.id
    WHERE p.mahasiswa_id = ? AND p.status = 'aktif'
");
$stmt->execute([$mahasiswa_id]);
$penempatan = $stmt->fetch(PDO::FETCH_ASSOC);

// ========================================
// PROSES SIMPAN / UPDATE KEGIATAN
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($penempatan || $edit_mode)) {
    $judul = trim($_POST['judul_kegiatan']);
    $jenis = $_POST['jenis_kegiatan'];
    $tanggal = $_POST['tanggal_kegiatan'];
    $lokasi = trim($_POST['lokasi_kegiatan']);
    $peserta = intval($_POST['peserta_count'] ?: 0);
    $deskripsi = trim($_POST['deskripsi']);
    $indikator = trim($_POST['indikator_capaian']);
    $kendala = trim($_POST['kendala']);
    $solusi = trim($_POST['solusi']);
    $status = $_POST['status'];
    
    // Validasi
    if (empty($judul) || empty($tanggal) || empty($deskripsi)) {
        $error = "❌ Judul, Tanggal, dan Deskripsi wajib diisi!";
    } else {
        try {
            if ($edit_mode && $edit_data) {
                // 🔥 MODE UPDATE - Update data yang sudah ada
                
                // Cek apakah ada upload foto baru
                $foto_name = $edit_data['dokumentasi_foto']; // Pakai foto lama dulu
                if (isset($_FILES['dokumentasi_foto']) && $_FILES['dokumentasi_foto']['error'] == 0) {
                    // Hapus foto lama jika ada
                    if ($foto_name && file_exists('../assets/uploads/' . $foto_name)) {
                        unlink('../assets/uploads/' . $foto_name);
                    }
                    
                    // Upload foto baru
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['dokumentasi_foto']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $filesize = $_FILES['dokumentasi_foto']['size'];
                    
                    if (in_array($ext, $allowed)) {
                        if ($filesize <= 2000000) {
                            $new_name = uniqid() . '_' . time() . '.' . $ext;
                            $upload_path = '../assets/uploads/' . $new_name;
                            
                            if (!file_exists('../assets/uploads')) {
                                mkdir('../assets/uploads', 0777, true);
                            }
                            
                            if (move_uploaded_file($_FILES['dokumentasi_foto']['tmp_name'], $upload_path)) {
                                $foto_name = $new_name;
                            }
                        }
                    }
                }
                
                // Update database
                $stmt = $pdo->prepare("
                    UPDATE kegiatan SET
                        judul_kegiatan = ?,
                        jenis_kegiatan = ?,
                        tanggal_kegiatan = ?,
                        lokasi_kegiatan = ?,
                        peserta_count = ?,
                        deskripsi = ?,
                        indikator_capaian = ?,
                        kendala = ?,
                        solusi = ?,
                        dokumentasi_foto = ?,
                        status = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $judul, $jenis, $tanggal, $lokasi, $peserta,
                    $deskripsi, $indikator, $kendala, $solusi,
                    $foto_name, $status, $edit_data['id']
                ]);
                
                $success = "✅ Kegiatan berhasil diupdate!";
                $edit_mode = false;
                $edit_data = null;
                
            } else {
                // MODE TAMBAH - Insert data baru
                if (!$penempatan) {
                    $error = "❌ Anda belum ditempatkan di lokasi KKN!";
                } else {
                    // Upload foto dokumentasi
                    $foto_name = '';
                    if (isset($_FILES['dokumentasi_foto']) && $_FILES['dokumentasi_foto']['error'] == 0) {
                        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                        $filename = $_FILES['dokumentasi_foto']['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $filesize = $_FILES['dokumentasi_foto']['size'];
                        
                        if (in_array($ext, $allowed)) {
                            if ($filesize <= 2000000) {
                                $new_name = uniqid() . '_' . time() . '.' . $ext;
                                $upload_path = '../assets/uploads/' . $new_name;
                                
                                if (!file_exists('../assets/uploads')) {
                                    mkdir('../assets/uploads', 0777, true);
                                }
                                
                                if (move_uploaded_file($_FILES['dokumentasi_foto']['tmp_name'], $upload_path)) {
                                    $foto_name = $new_name;
                                } else {
                                    $error = "❌ Gagal upload foto!";
                                }
                            } else {
                                $error = "❌ Ukuran foto maksimal 2MB!";
                            }
                        } else {
                            $error = "❌ Format foto harus JPG, PNG, atau GIF!";
                        }
                    }
                    
                    if (empty($error)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO kegiatan (
                                penempatan_id, judul_kegiatan, jenis_kegiatan, tanggal_kegiatan,
                                lokasi_kegiatan, peserta_count, deskripsi, indikator_capaian,
                                kendala, solusi, dokumentasi_foto, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $penempatan['penempatan_id'],
                            $judul,
                            $jenis,
                            $tanggal,
                            $lokasi,
                            $peserta,
                            $deskripsi,
                            $indikator,
                            $kendala,
                            $solusi,
                            $foto_name,
                            $status
                        ]);
                        
                        $success = "✅ Kegiatan berhasil ditambahkan!";
                        
                        // Reset form setelah sukses
                        echo "<script>
                            setTimeout(() => {
                                document.querySelector('form').reset();
                                document.getElementById('file_name').textContent = '';
                            }, 1000);
                        </script>";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "❌ Gagal menyimpan kegiatan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Edit Kegiatan' : 'Input Kegiatan' ?> - KKN Tracking</title>
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
            max-width: 1000px;
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

        .page-title {
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

        /* Info Box */
        .info-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            border-left: 4px solid var(--success);
        }

        .info-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #065f46;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .info-item {
            padding: 12px 16px;
            background: rgba(255,255,255,0.6);
            border-radius: 10px;
        }

        .info-label {
            font-size: 11px;
            color: var(--gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 14px;
            color: var(--dark);
            font-weight: 600;
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
        }

        .edit-badge {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
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

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
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

        /* File Upload */
        .file-upload {
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            padding: 32px 24px;
            text-align: center;
            background: var(--gray-50);
            transition: var(--transition);
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .file-upload label {
            cursor: pointer;
            display: block;
        }

        .file-upload .icon {
            font-size: 48px;
            margin-bottom: 12px;
            color: var(--primary);
        }

        .file-upload .text {
            color: var(--dark);
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .file-upload .hint {
            color: var(--gray);
            font-size: 12px;
        }

        .file-upload #file_name {
            margin-top: 12px;
            color: var(--success);
            font-weight: 600;
            font-size: 13px;
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

        /* Warning Box */
        .warning-box {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 28px;
            border-left: 4px solid var(--warning);
            color: #92400e;
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
            .form-row { grid-template-columns: 1fr; }
            .container { padding: 20px; }
            .page-header { padding: 24px; }
            .form-card { padding: 24px; }
            .top-bar { padding: 16px 20px; }
            .btn-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
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

        <a href="input_kegiatan.php" class="menu-item active">
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

    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-title">
            <i class="fa-solid <?= $edit_mode ? 'fa-pen-to-square' : 'fa-file-circle-plus' ?>"></i>
            <?= $edit_mode ? 'Edit Kegiatan' : 'Input Kegiatan KKN' ?>
        </h1>

        <p class="page-subtitle">
            <?= $edit_mode 
                ? 'Ubah data kegiatan yang sudah dibuat' 
                : 'Catat kegiatan yang telah atau akan Anda lakukan selama KKN' ?>
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fa-solid fa-circle-check"></i>
            <?= $success ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fa-solid fa-circle-xmark"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($penempatan || $edit_mode): ?>
        
        <?php if ($penempatan && !$edit_mode): ?>

        <!-- Informasi Penempatan -->
        <div class="info-card">

            <h2 class="info-title">
                <i class="fa-solid fa-location-dot"></i>
                Lokasi Penempatan Anda
            </h2>

            <div class="info-grid">

                <div class="info-item">
                            <div class="info-label">Desa</div>
                            <div class="info-value"><?= htmlspecialchars($penempatan['nama_desa']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Kecamatan</div>
                            <div class="info-value"><?= htmlspecialchars($penempatan['kecamatan']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Kabupaten</div>
                            <div class="info-value"><?= htmlspecialchars($penempatan['kabupaten']) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">DPL</div>
                            <div class="info-value"><?= htmlspecialchars($penempatan['nama_dpl']) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Form Input / Edit Kegiatan -->
<div class="form-card">

    <div class="form-title">

        <span>
            <i class="fa-solid <?= $edit_mode ? 'fa-pen-to-square' : 'fa-clipboard-list' ?>"></i>

            <?= $edit_mode ? 'Edit Detail Kegiatan' : 'Detail Kegiatan' ?>

            <?php if ($edit_mode): ?>
                <span class="edit-badge">
                    <i class="fa-solid fa-pen"></i>
                    Mode Edit
                </span>
            <?php endif; ?>
        </span>

        <?php if ($edit_mode): ?>
            <a href="riwayat.php" class="btn btn-secondary" style="font-size:13px;">
                <i class="fa-solid fa-xmark"></i>
                Batal
            </a>
        <?php endif; ?>

    </div>
                    
                    <?php if ($edit_mode && $edit_data): ?>
                    <div class="edit-notice">
                        <span>⚠️</span>
                        <strong>Sedang mengedit:</strong> "<?= htmlspecialchars($edit_data['judul_kegiatan']) ?>"
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Judul Kegiatan <span class="required">*</span></label>
                                <input type="text" name="judul_kegiatan" class="form-control" required 
                                       placeholder="Contoh: Sosialisasi Pentingnya Kebersihan Lingkungan"
                                       value="<?= $edit_mode && $edit_data ? htmlspecialchars($edit_data['judul_kegiatan']) : (isset($_POST['judul_kegiatan']) ? htmlspecialchars($_POST['judul_kegiatan']) : '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Jenis Kegiatan <span class="required">*</span></label>
                                <select name="jenis_kegiatan" class="form-control" required>
                                    <option value="">-- Pilih Jenis --</option>
                                    <option value="sosialisasi" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='sosialisasi') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='sosialisasi') ? 'selected' : '' ?>>Sosialisasi</option>
                                    <option value="pelatihan" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='pelatihan') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='pelatihan') ? 'selected' : '' ?>>Pelatihan</option>
                                    <option value="pembangunan" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='pembangunan') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='pembangunan') ? 'selected' : '' ?>>Pembangunan</option>
                                    <option value="pendampingan" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='pendampingan') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='pendampingan') ? 'selected' : '' ?>>Pendampingan</option>
                                    <option value="penyuluhan" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='penyuluhan') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='penyuluhan') ? 'selected' : '' ?>>Penyuluhan</option>
                                    <option value="pengabdian" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='pengabdian') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='pengabdian') ? 'selected' : '' ?>>Pengabdian Masyarakat</option>
                                    <option value="lainnya" <?= ($edit_mode && $edit_data['jenis_kegiatan']=='lainnya') || (isset($_POST['jenis_kegiatan']) && $_POST['jenis_kegiatan']=='lainnya') ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Tanggal Kegiatan <span class="required">*</span></label>
                                <input type="date" name="tanggal_kegiatan" class="form-control" required 
                                       value="<?= $edit_mode && $edit_data ? $edit_data['tanggal_kegiatan'] : (isset($_POST['tanggal_kegiatan']) ? $_POST['tanggal_kegiatan'] : date('Y-m-d')) ?>">
                            </div>
                           <div class="form-group">
    <label>
        <i class="fa-solid fa-bars-progress"></i>
        Status Kegiatan 
        <span class="required">*</span>
    </label>

    <select name="status" class="form-control" required>

        <option value="direncanakan"
            <?= ($edit_mode && $edit_data['status']=='direncanakan') || (isset($_POST['status']) && $_POST['status']=='direncanakan') ? 'selected' : '' ?>>
            📌 Direncanakan
        </option>

        <option value="berjalan"
            <?= ($edit_mode && $edit_data['status']=='berjalan') || (isset($_POST['status']) && $_POST['status']=='berjalan') ? 'selected' : '' ?>>
            ⏳ Sedang Berjalan
        </option>

        <option value="selesai"
            <?= (!$edit_mode && !$edit_data) || ($edit_mode && $edit_data['status']=='selesai') || (isset($_POST['status']) && $_POST['status']=='selesai') ? 'selected' : '' ?>>
            ✔️ Selesai
        </option>

    </select>
</div>
</div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Lokasi Kegiatan</label>
                                <input type="text" name="lokasi_kegiatan" class="form-control" 
                                       placeholder="Contoh: Balai Desa Sukamaju"
                                       value="<?= $edit_mode && $edit_data ? htmlspecialchars($edit_data['lokasi_kegiatan']) : (isset($_POST['lokasi_kegiatan']) ? htmlspecialchars($_POST['lokasi_kegiatan']) : '') ?>">
                                <div class="form-note">Lokasi spesifik pelaksanaan kegiatan</div>
                            </div>
                            <div class="form-group">
                                <label>Jumlah Peserta</label>
                                <input type="number" name="peserta_count" class="form-control" 
                                       placeholder="0" min="0"
                                       value="<?= $edit_mode && $edit_data ? $edit_data['peserta_count'] : (isset($_POST['peserta_count']) ? $_POST['peserta_count'] : '') ?>">
                                <div class="form-note">Jumlah masyarakat yang terlibat</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Deskripsi Kegiatan <span class="required">*</span></label>
                            <textarea name="deskripsi" class="form-control" required 
                                      placeholder="Jelaskan secara detail kegiatan yang dilakukan, tujuan, dan metode pelaksanaan..."><?= $edit_mode && $edit_data ? htmlspecialchars($edit_data['deskripsi']) : (isset($_POST['deskripsi']) ? htmlspecialchars($_POST['deskripsi']) : '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Indikator Capaian</label>
                            <textarea name="indikator_capaian" class="form-control" 
                                      placeholder="Apa yang berhasil dicapai dari kegiatan ini? (Target vs Realisasi)"><?= $edit_mode && $edit_data ? htmlspecialchars($edit_data['indikator_capaian']) : (isset($_POST['indikator_capaian']) ? htmlspecialchars($_POST['indikator_capaian']) : '') ?></textarea>
                            <div class="form-note">Opsional: Bisa diisi setelah kegiatan selesai</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Kendala yang Dihadapi</label>
                                <textarea name="kendala" class="form-control" 
                                          placeholder="Hambatan atau kendala yang dialami (jika ada)"><?= $edit_mode && $edit_data ? htmlspecialchars($edit_data['kendala']) : (isset($_POST['kendala']) ? htmlspecialchars($_POST['kendala']) : '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Solusi yang Diterapkan</label>
                                <textarea name="solusi" class="form-control" 
                                          placeholder="Cara mengatasi kendala tersebut (jika ada)"><?= $edit_mode && $edit_data ? htmlspecialchars($edit_data['solusi']) : (isset($_POST['solusi']) ? htmlspecialchars($_POST['solusi']) : '') ?></textarea>
                            </div>
                        </div>

                       <div class="form-group">

    <label>
        <i class="fa-solid fa-camera"></i>
        Dokumentasi Foto
    </label>

    <?php if ($edit_mode && $edit_data && $edit_data['dokumentasi_foto']): ?>

    <div style="margin-bottom:16px;">

        <img 
            src="../assets/uploads/<?= htmlspecialchars($edit_data['dokumentasi_foto']) ?>" 
            alt="Foto saat ini"
            style="
                max-width:200px;
                border-radius:14px;
                margin-bottom:10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            "
        >

        <p style="font-size:13px; color:var(--gray);">
            <i class="fa-solid fa-image"></i>
            Foto saat ini. Upload foto baru untuk mengganti.
        </p>

    </div>

    <?php endif; ?>

    <div class="file-upload" onclick="document.getElementById('foto_input').click()">

        <input 
            type="file"
            name="dokumentasi_foto"
            id="foto_input"
            accept="image/*"
            onchange="updateFileName(this)"
        >

        <label for="foto_input">

            <div class="icon">
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </div>

            <div class="text">
                <strong>Upload Dokumentasi Foto</strong>
            </div>

            <div class="hint">
                JPG, PNG, GIF • Maksimal 2MB
            </div>

            <div id="file_name"></div>

        </label>

    </div>

</div>

<div class="btn-group">

    <?php if ($edit_mode): ?>

        <button type="submit" class="btn btn-warning">
            <i class="fa-solid fa-floppy-disk"></i>
            Update Kegiatan
        </button>

        <a href="riwayat.php" class="btn btn-secondary">
            <i class="fa-solid fa-xmark"></i>
            Batal
        </a>

    <?php else: ?>

        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>
            Simpan Kegiatan
        </button>

        <button 
            type="reset"
            class="btn btn-secondary"
            onclick="return confirm('Yakin ingin reset form? Semua data yang sudah diisi akan hilang.')"
        >
            <i class="fa-solid fa-rotate-right"></i>
            Reset Form
        </button>

    <?php endif; ?>

    <a href="riwayat.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i>
        Kembali ke Riwayat
    </a>

</div>
</form>
</div>

<?php else: ?>

<!-- Warning -->
<div class="warning-box">

    <strong>
        <i class="fa-solid fa-triangle-exclamation"></i>
        Anda Belum Ditempatkan
    </strong>

    <p>
        Maaf, Anda belum ditempatkan di lokasi KKN.
        Silakan hubungi koordinator KKN untuk informasi lebih lanjut.
    </p>

</div>

<div style="text-align:center; margin-top:30px;">

    <a href="dashboard.php" class="btn btn-primary">
        <i class="fa-solid fa-arrow-left"></i>
        Kembali ke Dashboard
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

    // Update file name saat pilih file
    function updateFileName(input) {
        const fileName = input.files[0]?.name;
        const fileNameDisplay = document.getElementById('file_name');
        if (fileName) {
            fileNameDisplay.textContent = '📎 ' + fileName;
        } else {
            fileNameDisplay.textContent = '';
        }
    }
    
    // Validasi tanggal tidak boleh lebih dari hari ini untuk status "selesai"
    document.querySelector('select[name="status"]')?.addEventListener('change', function() {
        const status = this.value;
        const tanggalInput = document.querySelector('input[name="tanggal_kegiatan"]');
        const today = new Date().toISOString().split('T')[0];
        
        if (status === 'selesai' && tanggalInput.value > today) {
            alert('⚠️ Tanggal kegiatan tidak boleh di masa depan untuk status "Selesai"');
            tanggalInput.value = today;
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