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
$edit_mode = false;
$edit_data = null;

// ========================================
// 🔥 MODE EDIT (Klik tombol Edit)
// ========================================
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT l.*, k.judul_kegiatan 
        FROM laporan l
        JOIN kegiatan k ON l.kegiatan_id = k.id
        JOIN penempatan p ON k.penempatan_id = p.id
        WHERE l.id = ? AND p.mahasiswa_id = ?
    ");
    $stmt->execute([$edit_id, $mahasiswa_id]);
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($edit_data) {
        $edit_mode = true;
    } else {
        $error = "❌ Laporan tidak ditemukan!";
    }
}

// Ambil data penempatan mahasiswa
$stmt = $pdo->prepare("
    SELECT p.id as penempatan_id, p.status
    FROM penempatan p
    WHERE p.mahasiswa_id = ? AND p.status = 'aktif'
");
$stmt->execute([$mahasiswa_id]);
$penempatan = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil kegiatan yang sudah dibuat mahasiswa
$kegiatan_list = [];
if ($penempatan) {
    $stmt = $pdo->prepare("
        SELECT id, judul_kegiatan, tanggal_kegiatan, jenis_kegiatan 
        FROM kegiatan 
        WHERE penempatan_id = ? 
        ORDER BY tanggal_kegiatan DESC
    ");
    $stmt->execute([$penempatan['penempatan_id']]);
    $kegiatan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ========================================
// 🔥 PROSES SIMPAN / UPDATE LAPORAN
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kegiatan_id = $_POST['kegiatan_id'];
    $jenis = $_POST['jenis_laporan'];
    $tanggal = $_POST['tanggal_laporan'];
    $uraian = trim($_POST['uraian_kegiatan']);
    $capaian = trim($_POST['capaian']);
    $kendala = trim($_POST['kendala_lapangan']);
    $dampak = trim($_POST['dampak_sosial']);
    $rekomendasi = trim($_POST['rekomendasi']);
    
    // ==========================================
    // LOGIC UPLOAD FILE PDF
    // ==========================================
    $file_pdf_name = '';
    if (isset($_FILES['file_pdf']) && $_FILES['file_pdf']['error'] == 0) {
        $allowed_ext = ['pdf'];
        $filename = $_FILES['file_pdf']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $filesize = $_FILES['file_pdf']['size'];
        
        if (in_array($ext, $allowed_ext)) {
            if ($filesize <= 5000000) { // Max 5MB
                $new_name = 'laporan_' . uniqid() . '_' . time() . '.pdf';
                $upload_path = '../assets/uploads/laporan/' . $new_name;
                
                // Buat folder jika belum ada
                if (!file_exists('../assets/uploads/laporan')) {
                    mkdir('../assets/uploads/laporan', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['file_pdf']['tmp_name'], $upload_path)) {
                    $file_pdf_name = $new_name;
                } else {
                    $error = "❌ Gagal mengupload file PDF!";
                }
            } else {
                $error = "❌ Ukuran file PDF terlalu besar! Maksimal 5MB.";
            }
        } else {
            $error = "❌ Hanya file dengan format .PDF yang diperbolehkan!";
        }
    } elseif ($edit_mode && $edit_data) {
        // Jika mode edit dan tidak upload file baru, pakai file lama
        $file_pdf_name = $edit_data['file_pdf'];
    }

    // Validasi
    if (empty($kegiatan_id) || empty($tanggal) || empty($uraian)) {
        $error = "❌ Kegiatan, Tanggal, dan Uraian wajib diisi!";
    } elseif (empty($dampak)) {
        $error = "❌ Dampak sosial wajib diisi!";
    } else {
        try {
            if ($edit_mode && $edit_data) {
                // === MODE UPDATE ===
                $stmt = $pdo->prepare("
                    UPDATE laporan 
                    SET jenis_laporan=?, tanggal_laporan=?, uraian_kegiatan=?, 
                        capaian=?, kendala_lapangan=?, dampak_sosial=?, rekomendasi=?, file_pdf=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $jenis, $tanggal, $uraian,
                    $capaian, $kendala, $dampak, $rekomendasi, $file_pdf_name,
                    $edit_data['id']
                ]);
                $success = "✅ Laporan berhasil diupdate!";
                $edit_mode = false;
                $edit_data = null;
                
            } else {
                // === MODE TAMBAH ===
                // Cek apakah sudah ada laporan untuk kegiatan ini
                $check = $pdo->prepare("SELECT id FROM laporan WHERE kegiatan_id = ? AND jenis_laporan = ?");
                $check->execute([$kegiatan_id, $jenis]);
                
                if ($check->rowCount() > 0) {
                    $error = "❌ Laporan {$jenis} untuk kegiatan ini sudah ada!";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO laporan (
                            kegiatan_id, jenis_laporan, tanggal_laporan, uraian_kegiatan,
                            capaian, kendala_lapangan, dampak_sosial, rekomendasi, file_pdf, status_verifikasi
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $kegiatan_id, $jenis, $tanggal,
                        $uraian, $capaian, $kendala, $dampak, $rekomendasi, $file_pdf_name
                    ]);
                    $success = "✅ Laporan berhasil dikirim! Menunggu verifikasi DPL.";
                }
            }
        } catch (PDOException $e) {
            $error = "❌ Gagal menyimpan laporan: " . $e->getMessage();
        }
    }
}

// ========================================
// 🔥 PROSES HAPUS LAPORAN
// ========================================
if (isset($_GET['hapus'])) {
    $hapus_id = $_GET['hapus'];
    
    try {
        // Cek apakah laporan milik mahasiswa ini
        $check = $pdo->prepare("
            SELECT l.id, l.file_pdf FROM laporan l
            JOIN kegiatan k ON l.kegiatan_id = k.id
            JOIN penempatan p ON k.penempatan_id = p.id
            WHERE l.id = ? AND p.mahasiswa_id = ?
        ");
        $check->execute([$hapus_id, $mahasiswa_id]);
        $data_hapus = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($data_hapus) {
            // Hapus file PDF dari server jika ada
            if (!empty($data_hapus['file_pdf']) && file_exists('../assets/uploads/laporan/' . $data_hapus['file_pdf'])) {
                unlink('../assets/uploads/laporan/' . $data_hapus['file_pdf']);
            }
            
            $pdo->prepare("DELETE FROM laporan WHERE id = ?")->execute([$hapus_id]);
            $success = "✅ Laporan berhasil dihapus!";
        } else {
            $error = "❌ Laporan tidak ditemukan atau tidak memiliki akses!";
        }
    } catch (PDOException $e) {
        $error = "❌ Gagal menghapus: " . $e->getMessage();
    }
}

// ========================================
// 🔥 AMBIL DAFTAR LAPORAN (READ)
// ========================================
$laporan_list = [];
if ($penempatan) {
    $stmt = $pdo->prepare("
        SELECT 
            l.id, l.jenis_laporan, l.tanggal_laporan, l.status_verifikasi, l.created_at, l.file_pdf,
            k.judul_kegiatan, k.tanggal_kegiatan,
            COUNT(*) OVER() as total
        FROM laporan l
        JOIN kegiatan k ON l.kegiatan_id = k.id
        JOIN penempatan p ON k.penempatan_id = p.id
        WHERE p.mahasiswa_id = ?
        ORDER BY l.created_at DESC
    ");
    $stmt->execute([$mahasiswa_id]);
    $laporan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Edit Laporan' : 'Input Laporan' ?> - KKN Tracking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5; --secondary: #8b5cf6;
            --accent: #06b6d4; --success: #10b981; --warning: #f59e0b;
            --danger: #ef4444; --dark: #1e293b; --light: #f8fafc; --gray: #64748b;
            --sidebar-width: 280px; --sidebar-collapsed: 80px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        body { background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%); min-height: 100vh; }

        /* Sidebar & Layout Styles (Sama seperti sebelumnya) */
        .sidebar { position: fixed; left: 0; top: 0; width: var(--sidebar-width); height: 100vh; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); color: white; z-index: 1000; transition: var(--transition); overflow-x: hidden; overflow-y: auto; box-shadow: 4px 0 24px rgba(0,0,0,0.1); }
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
        .main-content { margin-left: var(--sidebar-width); transition: var(--transition); min-height: 100vh; width: calc(100% - var(--sidebar-width)); }
        .sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed); width: calc(100% - var(--sidebar-collapsed)); }
        .top-bar { background: rgba(255,255,255,0.8); backdrop-filter: blur(12px); padding: 20px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; }
        .back-btn { display: flex; align-items: center; gap: 10px; padding: 10px 18px; background: white; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 500; color: var(--dark); transition: var(--transition); text-decoration: none; }
        .back-btn:hover { background: var(--primary); color: white; transform: translateX(-4px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .user-info { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .user-avatar { width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; }
        .user-name { font-weight: 600; color: var(--dark); font-size: 14px; }
        .user-role { font-size: 12px; color: var(--gray); }
        .container { padding: 32px; max-width: 1100px; }
        .page-header { background: white; padding: 32px; border-radius: 20px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .page-title { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .page-subtitle { color: var(--gray); font-size: 14px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; border-left: 4px solid; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #065f46; border-left-color: var(--success); }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #991b1b; border-left-color: var(--danger); }
        .form-card { background: white; border-radius: 20px; padding: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); margin-bottom: 28px; }
        .form-title { font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .edit-badge { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); color: white; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .edit-notice { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; border-left: 4px solid var(--warning); color: #92400e; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); font-size: 14px; }
        .form-group label .required { color: var(--danger); margin-left: 2px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: 10px; font-size: 14px; transition: var(--transition); background: white; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .form-control:disabled { background: var(--gray-100); cursor: not-allowed; color: var(--gray-400); }
        textarea.form-control { min-height: 120px; resize: vertical; line-height: 1.6; }
        select.form-control { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
        .form-note { font-size: 12px; color: var(--gray); margin-top: 6px; line-height: 1.4; }
        .highlight-box { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); padding: 24px; border-radius: 16px; margin: 24px 0; border-left: 4px solid var(--success); }
        .highlight-title { color: #065f46; font-weight: 700; font-size: 15px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .highlight-desc { color: #047857; font-size: 13px; margin-bottom: 12px; font-weight: 500; }
        .btn { padding: 12px 24px; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); letter-spacing: -0.01em; }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4); }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #f97316); color: white; }
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #dc2626); color: white; }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4); }
        .btn-secondary { background: var(--gray-200); color: var(--dark); }
        .btn-secondary:hover { background: var(--gray-300); transform: translateY(-2px); }
        .btn-sm { padding: 8px 16px; font-size: 13px; }
        .btn-group { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 28px; padding-top: 24px; border-top: 1px solid var(--gray-200); }
        .warning-box { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); padding: 24px; border-radius: 16px; margin-bottom: 28px; border-left: 4px solid var(--warning); color: #92400e; }
        .warning-box strong { display: block; margin-bottom: 8px; font-size: 15px; }
        .table-section { background: white; border-radius: 20px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .table-title { font-size: 1.1rem; font-weight: 700; color: var(--dark); margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid var(--gray-200); display: flex; align-items: center; gap: 8px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--gray-200); }
        th { background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background: var(--gray-50); }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-disetujui { background: #d4edda; color: #155724; }
        .badge-revisi { background: #f8d7da; color: #721c24; }
        @media (max-width: 1024px) { .sidebar { transform: translateX(-100%); } .sidebar.mobile-open { transform: translateX(0); } .main-content { margin-left: 0; } .sidebar.collapsed ~ .main-content { margin-left: 0; } }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .container { padding: 20px; } .page-header { padding: 24px; } .form-card { padding: 24px; } .top-bar { padding: 16px 20px; } .btn-group { flex-direction: column; } .btn { width: 100%; justify-content: center; } .form-title { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">KKN Tracking</div>
            <button class="toggle-btn" onclick="toggleSidebar()" title="Toggle Menu"><i class="fa-solid fa-bars"></i></button>
        </div>
        <nav class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-house"></i></span><span class="menu-text">Dashboard</span></a>
            <a href="input_kegiatan.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-square-plus"></i></span><span class="menu-text">Input Kegiatan</span></a>
            <a href="input_laporan.php" class="menu-item active"><span class="menu-icon"><i class="fa-solid fa-file-pen"></i></span><span class="menu-text">Laporan</span></a>
            <a href="riwayat.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="menu-text">Riwayat</span></a>
            <a href="profil.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-circle-user"></i></span><span class="menu-text">Profil</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn"><span class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="logout-text">Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <a href="javascript:history.back()" class="back-btn"><span>←</span><span>Kembali</span></a>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['nama'], 0, 1)) ?></div>
                <div><div class="user-name"><?= htmlspecialchars($_SESSION['nama']) ?></div><div class="user-role">Mahasiswa</div></div>
            </div>
        </div>

        <div class="container">
            <div class="page-header">
                <h1 class="page-title"><i class="fa-solid <?= $edit_mode ? 'fa-pen-to-square' : 'fa-file-lines' ?>"></i> <?= $edit_mode ? 'Edit Laporan' : 'Input Laporan KKN' ?></h1>
                <p class="page-subtitle"><?= $edit_mode ? 'Ubah laporan yang sudah dibuat' : 'Buat laporan dari kegiatan yang telah Anda lakukan' ?></p>
            </div>

            <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i> <?= $error ?></div><?php endif; ?>

            <?php if ($penempatan): ?>
            <div class="form-card">
                <div class="form-title">
                    <span><i class="fa-solid <?= $edit_mode ? 'fa-pen' : 'fa-square-plus' ?>"></i> <?= $edit_mode ? 'Edit Laporan' : 'Buat Laporan Baru' ?>
                        <?php if ($edit_mode): ?><span class="edit-badge"><i class="fa-solid fa-pen-to-square"></i> Mode Edit</span><?php endif; ?>
                    </span>
                    <?php if ($edit_mode): ?><a href="input_laporan.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Batal</a><?php endif; ?>
                </div>
                        
                <?php if ($edit_mode && $edit_data): ?>
                <div class="edit-notice"><span><i class="fa-solid fa-triangle-exclamation"></i></span><strong>Sedang mengedit:</strong> Laporan <?= ucfirst($edit_data['jenis_laporan']) ?> - <?= htmlspecialchars($edit_data['judul_kegiatan']) ?></div>
                <?php endif; ?>

                <?php if (count($kegiatan_list) > 0): ?>
                <!-- PENTING: enctype="multipart/form-data" WAJIB ADA UNTUK UPLOAD FILE -->
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?><input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>"><?php endif; ?>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Pilih Kegiatan <span class="required">*</span></label>
                            <select name="kegiatan_id" class="form-control" required <?= $edit_mode ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Kegiatan --</option>
                                <?php foreach ($kegiatan_list as $k): ?>
                                    <option value="<?= $k['id'] ?>" <?= ($edit_mode && $edit_data['kegiatan_id'] == $k['id']) || (isset($_POST['kegiatan_id']) && $_POST['kegiatan_id'] == $k['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($k['judul_kegiatan']) ?> (<?= date('d/m/Y', strtotime($k['tanggal_kegiatan'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="kegiatan_id" value="<?= $edit_data['kegiatan_id'] ?>">
                                <div class="form-note">⚠️ Kegiatan tidak bisa diubah saat edit</div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Jenis Laporan <span class="required">*</span></label>
                            <select name="jenis_laporan" class="form-control" required <?= $edit_mode ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Jenis --</option>
                                <option value="harian" <?= ($edit_mode && $edit_data['jenis_laporan']=='harian') || (isset($_POST['jenis_laporan']) && $_POST['jenis_laporan']=='harian') ? 'selected' : '' ?>>📅 Harian</option>
                                <option value="mingguan" <?= ($edit_mode && $edit_data['jenis_laporan']=='mingguan') || (isset($_POST['jenis_laporan']) && $_POST['jenis_laporan']=='mingguan') ? 'selected' : '' ?>>📆 Mingguan</option>
                                <option value="bulanan" <?= ($edit_mode && $edit_data['jenis_laporan']=='bulanan') || (isset($_POST['jenis_laporan']) && $_POST['jenis_laporan']=='bulanan') ? 'selected' : '' ?>>📅 Bulanan</option>
                            </select>
                            <?php if ($edit_mode): ?><input type="hidden" name="jenis_laporan" value="<?= $edit_data['jenis_laporan'] ?>"><?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Laporan <span class="required">*</span></label>
                        <input type="date" name="tanggal_laporan" class="form-control" required value="<?= $edit_mode ? $edit_data['tanggal_laporan'] : (isset($_POST['tanggal_laporan']) ? $_POST['tanggal_laporan'] : date('Y-m-d')) ?>">
                    </div>

                    <div class="form-group">
                        <label>Uraian Kegiatan <span class="required">*</span></label>
                        <textarea name="uraian_kegiatan" class="form-control" required placeholder="Jelaskan secara detail kegiatan yang dilakukan..."><?= $edit_mode ? htmlspecialchars($edit_data['uraian_kegiatan']) : (isset($_POST['uraian_kegiatan']) ? htmlspecialchars($_POST['uraian_kegiatan']) : '') ?></textarea>
                        <div class="form-note">Deskripsikan aktivitas yang dilakukan secara kronologis</div>
                    </div>

                    <div class="form-group">
                        <label>Capaian Kegiatan</label>
                        <textarea name="capaian" class="form-control" placeholder="Apa yang berhasil dicapai? (Target vs Realisasi)"><?= $edit_mode ? htmlspecialchars($edit_data['capaian']) : (isset($_POST['capaian']) ? htmlspecialchars($_POST['capaian']) : '') ?></textarea>
                        <div class="form-note">Sebutkan target dan realisasi pencapaian</div>
                    </div>

                    <div class="form-group">
                        <label>Kendala Lapangan</label>
                        <textarea name="kendala_lapangan" class="form-control" placeholder="Hambatan yang dihadapi di lapangan..."><?= $edit_mode ? htmlspecialchars($edit_data['kendala_lapangan']) : (isset($_POST['kendala_lapangan']) ? htmlspecialchars($_POST['kendala_lapangan']) : '') ?></textarea>
                        <div class="form-note">Jelaskan kendala yang dialami (jika ada)</div>
                    </div>

                    <div class="highlight-box">
                        <div class="highlight-title">🌟 Dampak Sosial <span style="color:var(--danger);">*</span></div>
                        <p class="highlight-desc"><strong>Ini adalah bagian terpenting dari laporan KKN!</strong></p>
                        <textarea name="dampak_sosial" class="form-control" required placeholder="Apa manfaat/impact kegiatan ini untuk masyarakat? (Wajib diisi!)" style="border-color: var(--success);"><?= $edit_mode ? htmlspecialchars($edit_data['dampak_sosial']) : (isset($_POST['dampak_sosial']) ? htmlspecialchars($_POST['dampak_sosial']) : '') ?></textarea>
                        <div class="form-note">Jelaskan dampak nyata untuk masyarakat desa</div>
                    </div>

                    <div class="form-group">
                        <label>Rekomendasi</label>
                        <textarea name="rekomendasi" class="form-control" placeholder="Saran untuk kegiatan lanjutan..."><?= $edit_mode ? htmlspecialchars($edit_data['rekomendasi']) : (isset($_POST['rekomendasi']) ? htmlspecialchars($_POST['rekomendasi']) : '') ?></textarea>
                        <div class="form-note">Rekomendasi untuk program selanjutnya</div>
                    </div>

                    <!-- INPUT FILE PDF BARU -->
                    <div class="form-group">
                        <label>Upload File Laporan (PDF) <?= $edit_mode ? '' : '<span class="required">*</span>' ?></label>
                        <input type="file" name="file_pdf" class="form-control" accept=".pdf" <?= $edit_mode ? '' : 'required' ?>>
                        <?php if ($edit_mode && !empty($edit_data['file_pdf'])): ?>
                            <div class="form-note">
                                File saat ini: <a href="../assets/uploads/laporan/<?= htmlspecialchars($edit_data['file_pdf']) ?>" target="_blank" style="color:var(--primary);"><?= htmlspecialchars($edit_data['file_pdf']) ?></a>
                                <br>Kosongkan jika tidak ingin mengubah file.
                            </div>
                        <?php else: ?>
                            <div class="form-note">📎 Format: .PDF | Maksimal: 5MB</div>
                        <?php endif; ?>
                    </div>

                    <div class="btn-group">
                        <?php if ($edit_mode): ?>
                            <button type="submit" class="btn btn-warning">💾 Update Laporan</button>
                            <a href="input_laporan.php" class="btn btn-secondary">Batal</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary">📤 Kirim Laporan</button>
                            <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php else: ?>
                <div class="warning-box"><strong>⚠️ Belum Ada Kegiatan</strong><p>Anda harus input kegiatan terlebih dahulu sebelum membuat laporan.</p></div>
                <a href="input_kegiatan.php" class="btn btn-primary">➕ Input Kegiatan Sekarang</a>
                <?php endif; ?>
            </div>

            <!-- Daftar Laporan -->
            <?php if (count($laporan_list) > 0): ?>
            <div class="table-section">
                <h2 class="table-title"><i class="fa-solid fa-folder-open"></i> Daftar Laporan Anda</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fa-solid fa-calendar-days"></i> Tanggal</th>
                                <th><i class="fa-solid fa-file-lines"></i> Kegiatan</th>
                                <th><i class="fa-solid fa-layer-group"></i> Jenis</th>
                                <th><i class="fa-solid fa-circle-check"></i> Status</th>
                                <th><i class="fa-solid fa-file-pdf"></i> File</th> <!-- KOLOM FILE DITAMBAHKAN -->
                                <th><i class="fa-solid fa-gear"></i> Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($laporan_list as $l): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($l['tanggal_laporan'])) ?></td>
                                <td><?= htmlspecialchars($l['judul_kegiatan']) ?></td>
                                <td><?= ucfirst($l['jenis_laporan']) ?></td>
                                <td><span class="badge badge-<?= $l['status_verifikasi'] ?>"><?= ucfirst($l['status_verifikasi']) ?></span></td>
                                
                                <!-- TAMPILKAN LINK PDF JIKA ADA -->
                                <td>
                                    <?php if (!empty($l['file_pdf'])): ?>
                                        <a href="../assets/uploads/laporan/<?= htmlspecialchars($l['file_pdf']) ?>" class="btn btn-sm" style="background:#ef4444; color:white; text-decoration:none;" target="_blank">
                                            <i class="fa-solid fa-eye"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#95a5a6;">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a href="?edit=<?= $l['id'] ?>" class="btn btn-warning btn-sm" style="margin-right:5px;"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                    <a href="?hapus=<?= $l['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus laporan ini?')"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="warning-box"><strong>⚠️ Anda Belum Ditempatkan</strong><p>Maaf, Anda belum ditempatkan di lokasi KKN. Silakan hubungi koordinator KKN.</p></div>
            <div style="text-align:center; margin-top:30px;"><a href="dashboard.php" class="btn btn-primary">⬅️ Kembali ke Dashboard</a></div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) sidebar.classList.add('collapsed');
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s, transform 0.5s';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    });
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.toggle-btn');
        if (window.innerWidth <= 1024 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target) && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
        }
    });
    </script>
</body>
</html>