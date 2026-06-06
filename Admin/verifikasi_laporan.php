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
$detail_laporan = null;

// ========================================
// FILTER PENCARIAN
// ========================================
$filter_status = $_GET['status'] ?? 'all';
$filter_dpl = $_GET['dpl'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// ========================================
// PROSES VERIFIKASI (APPROVE/REJECT)
// ========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verifikasi'])) {
    $laporan_id = $_POST['laporan_id'];
    $status_baru = $_POST['status_verifikasi']; // 'disetujui' atau 'revisi'
    $catatan = trim($_POST['catatan_admin']);

    try {
        $stmt = $pdo->prepare("
            UPDATE laporan 
            SET status_verifikasi = ?, catatan_dpl = ?, verified_at = NOW(), verified_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$status_baru, $catatan, $admin_id, $laporan_id]);
        
        $action = $status_baru == 'disetujui' ? 'DISETUJUI' : 'DIKEMBALIKAN untuk revisi';
        $success = "✅ Laporan berhasil {$action}!";
    } catch (PDOException $e) {
        $error = "❌ Gagal memverifikasi: " . $e->getMessage();
    }
}

// ========================================
// AMBIL DETAIL LAPORAN (UNTUK MODAL)
// ========================================
if (isset($_GET['detail'])) {
    $laporan_id = $_GET['detail'];
    $stmt = $pdo->prepare("
        SELECT 
            lap.*,
            mhs.nama as nama_mahasiswa, mhs.npm_nip, mhs.email as email_mhs,
            dpl.nama as nama_dpl, dpl.email as email_dpl,
            k.judul_kegiatan, k.jenis_kegiatan, k.tanggal_kegiatan, k.deskripsi,
            lok.nama_desa, lok.kecamatan, lok.kabupaten, lok.provinsi,
            pk.nama_program, pk.periode
        FROM laporan lap
        JOIN kegiatan k ON lap.kegiatan_id = k.id
        JOIN penempatan p ON k.penempatan_id = p.id
        JOIN users mhs ON p.mahasiswa_id = mhs.id
        JOIN users dpl ON p.dpl_id = dpl.id
        JOIN lokasi lok ON p.lokasi_id = lok.id
        JOIN program_kkn pk ON p.program_id = pk.id
        WHERE lap.id = ?
    ");
    $stmt->execute([$laporan_id]);
    $detail_laporan = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ========================================
// AMBIL DAFTAR DPL (untuk filter)
// ========================================
$dpl_list = $pdo->query("SELECT id, nama FROM users WHERE role = 'dpl' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// QUERY LAPORAN DENGAN FILTER
// ========================================
$query = "
    SELECT 
        lap.id, lap.tanggal_laporan, lap.jenis_laporan, lap.status_verifikasi, lap.created_at, lap.file_pdf,
        mhs.nama as nama_mahasiswa, mhs.npm_nip,
        dpl.nama as nama_dpl,
        k.judul_kegiatan,
        lok.nama_desa,
        pk.nama_program
    FROM laporan lap
    JOIN kegiatan k ON lap.kegiatan_id = k.id
    JOIN penempatan p ON k.penempatan_id = p.id
    JOIN users mhs ON p.mahasiswa_id = mhs.id
    JOIN users dpl ON p.dpl_id = dpl.id
    JOIN lokasi lok ON p.lokasi_id = lok.id
    JOIN program_kkn pk ON p.program_id = pk.id
    WHERE 1=1
";
$params = [];

// Filter status
if ($filter_status != 'all') {
    $query .= " AND lap.status_verifikasi = ?";
    $params[] = $filter_status;
}

// Filter DPL
if ($filter_dpl != 'all') {
    $query .= " AND p.dpl_id = ?";
    $params[] = $filter_dpl;
}

// Search nama mahasiswa atau judul kegiatan
if (!empty($search)) {
    $query .= " AND (mhs.nama LIKE ? OR k.judul_kegiatan LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " ORDER BY 
    CASE lap.status_verifikasi 
        WHEN 'pending' THEN 1 
        WHEN 'disetujui' THEN 2 
        WHEN 'revisi' THEN 3 
    END,
    lap.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$laporan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ========================================
// STATISTIK VERIFIKASI
// ========================================
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status_verifikasi = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status_verifikasi = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
        SUM(CASE WHEN status_verifikasi = 'revisi' THEN 1 ELSE 0 END) as revisi
    FROM laporan
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Laporan - Admin KKN Tracking</title>
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
        .page-header { background: white; padding: 32px; border-radius: 20px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .page-title-main { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .page-subtitle { color: var(--gray); font-size: 14px; }

        /* Alert */
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; border-left: 4px solid; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); color: #065f46; border-left-color: var(--success); }
        .alert-error { background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%); color: #991b1b; border-left-color: var(--danger); }

        /* Stats Cards */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; padding: 28px; border-radius: 16px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-left: 4px solid var(--primary); transition: var(--transition); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 2.5rem; margin: 12px 0 6px; color: var(--dark); font-weight: 800; line-height: 1; }
        .stat-card p { font-size: 12px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .stat-card.total { border-left-color: var(--primary); }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.disetujui { border-left-color: var(--success); }
        .stat-card.revisi { border-left-color: var(--danger); }

        /* Filter Bar */
        .filter-card { background: white; padding: 24px; border-radius: 20px; margin-bottom: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .filter-row { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); font-size: 14px; }
        .filter-control { width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: 10px; font-size: 14px; transition: var(--transition); background: white; }
        .filter-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .filter-actions { display: flex; gap: 12px; flex-shrink: 0; }
        .btn-filter { padding: 12px 24px; background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: var(--transition); display: inline-flex; align-items: center; gap: 6px; }
        .btn-filter:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); }
        .btn-reset { padding: 12px 24px; background: var(--gray-200); color: var(--dark); border: none; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; transition: var(--transition); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-reset:hover { background: var(--gray-300); transform: translateY(-2px); }

        /* Table Section */
        .table-section { background: white; border-radius: 20px; padding: 28px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); }
        .table-title { font-size: 1.25rem; font-weight: 700; color: var(--dark); margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; }
        .table-badge { background: var(--gray-100); color: var(--gray-700); padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
        th { background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover { background: var(--gray-50); }
        tr:last-child td { border-bottom: none; }

        /* Badge */
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-disetujui { background: #dcfce7; color: #166534; }
        .badge-revisi { background: #fecaca; color: #991b1b; }

        /* Buttons */
        .btn { padding: 8px 16px; border: none; border-radius: 10px; cursor: pointer; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); }
        .btn-success { background: linear-gradient(135deg, var(--success), #34d399); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
        .btn-secondary { background: var(--gray-200); color: var(--dark); }
        .btn-secondary:hover { background: var(--gray-300); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* Empty State */
        .empty { text-align: center; padding: 50px 20px; color: var(--gray); }
        .empty .icon { font-size: 48px; margin-bottom: 15px; opacity: 0.4; }
        .empty p { margin: 5px 0; }
        .empty strong { color: var(--dark); display: block; margin-bottom: 4px; }

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; display: flex; justify-content: center; align-items: center; padding: 20px; backdrop-filter: blur(4px); animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background: white; border-radius: 20px; max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: 1px solid rgba(0,0,0,0.1); }
        .modal-header { padding: 24px 28px; border-bottom: 2px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: white; z-index: 10; border-radius: 20px 20px 0 0; }
        .modal-header h2 { margin: 0; color: var(--dark); font-size: 1.25rem; font-weight: 700; }
        .modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: var(--gray); text-decoration: none; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 10px; transition: var(--transition); }
        .modal-close:hover { background: var(--gray-100); color: var(--dark); }
        .modal-body { padding: 28px; }

        /* Detail Sections */
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; background: var(--gray-50); padding: 20px; border-radius: 12px; }
        .detail-item label { font-size: 11px; color: var(--gray); display: block; margin-bottom: 4px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .detail-item strong { color: var(--dark); font-size: 14px; font-weight: 600; }
        .detail-item small { color: var(--gray); font-size: 12px; }
        .detail-section { margin-bottom: 24px; padding: 20px; background: var(--gray-50); border-radius: 12px; }
        .detail-section h4 { color: var(--dark); margin-bottom: 12px; font-size: 1rem; font-weight: 700; padding-bottom: 12px; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; gap: 8px; }
        .detail-section p { color: var(--gray-700); line-height: 1.7; white-space: pre-wrap; font-size: 14px; }
        .highlight-box { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid var(--success); }
        .highlight-box h4 { color: #065f46; margin-bottom: 12px; font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .highlight-box p { color: #047857; line-height: 1.7; white-space: pre-wrap; font-size: 14px; }

        /* Form Verifikasi */
        .verify-form { margin-top: 28px; padding-top: 24px; border-top: 2px solid var(--gray-200); }
        .verify-form h4 { margin-bottom: 20px; color: var(--dark); font-size: 1.1rem; font-weight: 700; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); font-size: 14px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid var(--gray-200); border-radius: 10px; font-size: 14px; transition: var(--transition); background: white; }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        textarea.form-control { min-height: 100px; resize: vertical; line-height: 1.6; }
        .form-note { font-size: 12px; color: var(--gray); margin-top: 6px; line-height: 1.4; }
        .verify-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        .verified-info { margin-top: 24px; padding: 20px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 12px; text-align: center; border-left: 4px solid var(--accent); }
        .verified-info p { margin: 4px 0; color: var(--dark); font-size: 14px; }
        .verified-info strong { color: var(--primary); font-weight: 700; }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .sidebar.collapsed ~ .main-content { margin-left: 0; width: 100%; }
        }
        @media (max-width: 768px) {
            .container { padding: 20px; }
            .page-header { padding: 24px; }
            .filter-card { padding: 20px; }
            .filter-row { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .filter-actions { width: 100%; }
            .btn-filter, .btn-reset { width: 100%; justify-content: center; }
            .top-bar { padding: 16px 20px; flex-direction: column; gap: 12px; }
            .table-section { padding: 20px; }
            table { font-size: 13px; }
            th, td { padding: 12px 10px; }
            .modal-content { margin: 10px; max-height: calc(100vh - 20px); }
            .modal-header { padding: 20px 24px; }
            .modal-body { padding: 24px; }
            .detail-grid { grid-template-columns: 1fr; }
            .verify-actions { flex-direction: column; }
            .verify-actions .btn { width: 100%; justify-content: center; }
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
            <a href="dashboard.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-house"></i></span><span class="menu-text">Dashboard</span></a>
            <a href="kelola_user.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-users"></i></span><span class="menu-text">Kelola User</span></a>
            <a href="kelola_lokasi.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-location-dot"></i></span><span class="menu-text">Kelola Lokasi</span></a>
            <a href="kelola_program.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-clipboard-list"></i></span><span class="menu-text">Program</span></a>
            <a href="verifikasi_laporan.php" class="menu-item active"><span class="menu-icon"><i class="fa-solid fa-circle-check"></i></span><span class="menu-text">Verifikasi</span></a>
            <a href="profil.php" class="menu-item"><span class="menu-icon"><i class="fa-solid fa-circle-user"></i></span><span class="menu-text">Profil</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php" class="logout-btn"><span class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="logout-text">Logout</span></a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">Verifikasi Laporan</div>
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
                <h1 class="page-title-main"><i class="fa-solid fa-clipboard-check"></i> Verifikasi Laporan KKN</h1>
                <p class="page-subtitle">Review, setujui, atau minta revisi laporan dari mahasiswa KKN</p>
            </div>

            <!-- Alert Messages -->
            <?php if ($success): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-circle-xmark"></i> <?= $error ?></div><?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats">
                <div class="stat-card total"><h3><?= $stats['total'] ?? 0 ?></h3><p>Total Laporan</p></div>
                <div class="stat-card pending"><h3><?= $stats['pending'] ?? 0 ?></h3><p>⏳ Pending</p></div>
                <div class="stat-card disetujui"><h3><?= $stats['disetujui'] ?? 0 ?></h3><p>✅ Disetujui</p></div>
                <div class="stat-card revisi"><h3><?= $stats['revisi'] ?? 0 ?></h3><p>📝 Revisi</p></div>
            </div>

            <!-- Filter Bar -->
            <div class="filter-card">
                <form method="GET" class="filter-row">
                    <div class="filter-group">
                        <label>Status Laporan</label>
                        <select name="status" class="filter-control" onchange="this.form.submit()">
                            <option value="all" <?= $filter_status=='all'?'selected':'' ?>>Semua Status</option>
                            <option value="pending" <?= $filter_status=='pending'?'selected':'' ?>>⏳ Pending</option>
                            <option value="disetujui" <?= $filter_status=='disetujui'?'selected':'' ?>>✅ Disetujui</option>
                            <option value="revisi" <?= $filter_status=='revisi'?'selected':'' ?>>📝 Perlu Revisi</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>DPL Pembimbing</label>
                        <select name="dpl" class="filter-control" onchange="this.form.submit()">
                            <option value="all" <?= $filter_dpl=='all'?'selected':'' ?>>Semua DPL</option>
                            <?php foreach ($dpl_list as $dpl): ?>
                                <option value="<?= $dpl['id'] ?>" <?= $filter_dpl==$dpl['id']?'selected':'' ?>><?= htmlspecialchars($dpl['nama']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Cari Mahasiswa / Kegiatan</label>
                        <input type="text" name="search" class="filter-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama atau judul...">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
                        <a href="verifikasi_laporan.php" class="btn-reset"><i class="fa-solid fa-rotate"></i> Reset</a>
                    </div>
                </form>
            </div>

            <!-- Tabel Laporan -->
            <div class="table-section">
                <div class="table-title">
                    <span>📋 Daftar Laporan</span>
                    <span class="table-badge"><?= count($laporan_list) ?> hasil</span>
                </div>

                <?php if (count($laporan_list) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th width="8%">Tanggal</th>
                                <th width="15%">Mahasiswa</th>
                                <th width="10%">NPM</th>
                                <th width="12%">DPL</th>
                                <th width="15%">Kegiatan</th>
                                <th width="10%">Lokasi</th>
                                <th width="8%">Jenis</th>
                                <th width="8%">Status</th>
                                <th width="7%">File PDF</th>
                                <th width="7%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($laporan_list as $l): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($l['tanggal_laporan'])) ?></td>
                                <td><strong><?= htmlspecialchars($l['nama_mahasiswa']) ?></strong></td>
                                <td><?= htmlspecialchars($l['npm_nip']) ?></td>
                                <td><?= htmlspecialchars($l['nama_dpl']) ?></td>
                                <td><?= htmlspecialchars($l['judul_kegiatan']) ?></td>
                                <td><?= htmlspecialchars($l['nama_desa']) ?></td>
                                <td><?= ucfirst($l['jenis_laporan']) ?></td>
                                <td><span class="badge badge-<?= $l['status_verifikasi'] ?>"><?= ucfirst($l['status_verifikasi']) ?></span></td>
                                
                                <!-- KOLOM FILE PDF DI TABEL -->
                                <td>
                                    <?php if (!empty($l['file_pdf'])): ?>
                                        <a href="../assets/uploads/laporan/<?= htmlspecialchars($l['file_pdf']) ?>" 
                                           class="btn btn-sm" 
                                           target="_blank"
                                           style="background: var(--danger); color: white; text-decoration: none;">
                                            <i class="fa-solid fa-file-pdf"></i> Lihat
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--gray); font-size:12px;">-</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a href="?detail=<?= $l['id'] ?><?= $filter_status!='all'?'&status='.$filter_status:'' ?><?= $filter_dpl!='all'?'&dpl='.$filter_dpl:'' ?><?= !empty($search)?'&search='.urlencode($search):'' ?>" 
                                       class="btn btn-primary btn-sm">
                                       <i class="fa-solid fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty">
                    <div class="icon">📭</div>
                    <p><strong>Tidak ada laporan ditemukan</strong></p>
                    <p style="font-size:13px;">Coba ubah filter pencarian atau tunggu mahasiswa input laporan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- MODAL DETAIL & VERIFIKASI -->
    <?php if ($detail_laporan): ?>
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fa-solid fa-file-lines"></i> Detail Laporan #<?= $detail_laporan['id'] ?></h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <!-- Info Utama -->
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Mahasiswa</label>
                        <strong><?= htmlspecialchars($detail_laporan['nama_mahasiswa']) ?></strong><br>
                        <small><?= $detail_laporan['npm_nip'] ?></small>
                    </div>
                    <div class="detail-item">
                        <label>Email Mahasiswa</label>
                        <strong><?= htmlspecialchars($detail_laporan['email_mhs']) ?></strong>
                    </div>
                    <div class="detail-item">
                        <label>DPL Pembimbing</label>
                        <strong><?= htmlspecialchars($detail_laporan['nama_dpl']) ?></strong><br>
                        <small><?= htmlspecialchars($detail_laporan['email_dpl']) ?></small>
                    </div>
                    <div class="detail-item">
                        <label>Lokasi KKN</label>
                        <strong><?= htmlspecialchars($detail_laporan['nama_desa']) ?></strong><br>
                        <small><?= htmlspecialchars($detail_laporan['kecamatan']) ?>, <?= htmlspecialchars($detail_laporan['kabupaten']) ?></small>
                    </div>
                    <div class="detail-item">
                        <label>Program</label>
                        <strong><?= htmlspecialchars($detail_laporan['nama_program']) ?></strong><br>
                        <small><?= htmlspecialchars($detail_laporan['periode']) ?></small>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <span class="badge badge-<?= $detail_laporan['status_verifikasi'] ?>"><?= ucfirst($detail_laporan['status_verifikasi']) ?></span>
                    </div>
                </div>

                <!-- SECTION FILE PDF DI MODAL -->
                <?php if (!empty($detail_laporan['file_pdf'])): ?>
                <div class="detail-section" style="background: #fef2f2; border-left: 4px solid var(--danger);">
                    <h4><i class="fa-solid fa-file-pdf" style="color: var(--danger);"></i> File Laporan PDF</h4>
                    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <a href="../assets/uploads/laporan/<?= htmlspecialchars($detail_laporan['file_pdf']) ?>" 
                           class="btn" 
                           target="_blank"
                           style="background: var(--danger); color: white; text-decoration: none;">
                            <i class="fa-solid fa-download"></i> Download PDF
                        </a>
                        <a href="../assets/uploads/laporan/<?= htmlspecialchars($detail_laporan['file_pdf']) ?>" 
                           class="btn btn-secondary" 
                           target="_blank"
                           style="text-decoration: none;">
                            <i class="fa-solid fa-eye"></i> Lihat di Tab Baru
                        </a>
                    </div>
                    <small style="color: var(--gray); margin-top: 8px; display: block;">
                        📎 Nama File: <?= htmlspecialchars($detail_laporan['file_pdf']) ?>
                    </small>
                </div>
                <?php else: ?>
                <div class="detail-section" style="background: var(--gray-50); border-left: 4px solid var(--gray);">
                    <h4><i class="fa-solid fa-file-pdf" style="color: var(--gray);"></i> File Laporan PDF</h4>
                    <p style="color: var(--gray); margin: 0;"><em>⚠️ Mahasiswa tidak mengupload file PDF untuk laporan ini.</em></p>
                </div>
                <?php endif; ?>
                <!-- AKHIR SECTION FILE PDF -->

                <!-- Detail Kegiatan -->
                <div class="detail-section">
                    <h4>🎯 Kegiatan: <?= htmlspecialchars($detail_laporan['judul_kegiatan']) ?></h4>
                    <p><strong>Jenis:</strong> <?= ucfirst($detail_laporan['jenis_kegiatan']) ?><br>
                    <strong>Tanggal:</strong> <?= date('d/m/Y', strtotime($detail_laporan['tanggal_kegiatan'])) ?><br>
                    <strong>Deskripsi:</strong><br><?= nl2br(htmlspecialchars($detail_laporan['deskripsi'])) ?></p>
                </div>

                <!-- Isi Laporan -->
                <div class="detail-section">
                    <h4>📝 Uraian Kegiatan</h4>
                    <p><?= nl2br(htmlspecialchars($detail_laporan['uraian_kegiatan'])) ?></p>
                </div>

                <div class="detail-section">
                    <h4>🎯 Capaian</h4>
                    <p><?= nl2br(htmlspecialchars($detail_laporan['capaian'])) ?></p>
                </div>

                <div class="detail-section">
                    <h4>⚠️ Kendala Lapangan</h4>
                    <p><?= nl2br(htmlspecialchars($detail_laporan['kendala_lapangan'])) ?: '<em style="color:var(--gray);">Tidak ada kendala dilaporkan</em>' ?></p>
                </div>

                <!-- DAMPAK SOSIAL (Highlight) -->
                <div class="highlight-box">
                    <h4>🌟 DAMPAK SOSIAL</h4>
                    <p><?= nl2br(htmlspecialchars($detail_laporan['dampak_sosial'])) ?></p>
                </div>

                <?php if ($detail_laporan['rekomendasi']): ?>
                <div class="detail-section">
                    <h4>💡 Rekomendasi</h4>
                    <p><?= nl2br(htmlspecialchars($detail_laporan['rekomendasi'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($detail_laporan['catatan_dpl']): ?>
                <div class="detail-section" style="background: #fffbeb; padding: 16px; border-radius: 12px; border-left: 4px solid var(--warning);">
                    <h4>📌 Catatan Verifikasi Sebelumnya</h4>
                    <p><?= nl2br(htmlspecialchars($detail_laporan['catatan_dpl'])) ?></p>
                    <?php if ($detail_laporan['verified_at']): ?>
                    <small style="color:#92400e;">Diverifikasi: <?= date('d/m/Y H:i', strtotime($detail_laporan['verified_at'])) ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- FORM VERIFIKASI (Hanya jika pending) -->
                <?php if ($detail_laporan['status_verifikasi'] == 'pending'): ?>
                <form method="POST" class="verify-form">
                    <h4>✍️ Keputusan Verifikasi</h4>
                    
                    <div class="form-group">
                        <label>Status Verifikasi *</label>
                        <select name="status_verifikasi" class="form-control" required onchange="toggleCatatan(this.value)">
                            <option value="">-- Pilih Keputusan --</option>
                            <option value="disetujui">✅ Disetujui</option>
                            <option value="revisi">📝 Perlu Revisi</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Catatan untuk Mahasiswa</label>
                        <textarea name="catatan_admin" class="form-control" placeholder="Berikan feedback, pujian, atau poin revisi..."></textarea>
                        <div class="form-note">Catatan ini akan dikirim ke mahasiswa sebagai notifikasi</div>
                    </div>

                    <input type="hidden" name="laporan_id" value="<?= $detail_laporan['id'] ?>">
                    <div class="verify-actions">
                        <button type="submit" name="verifikasi" class="btn btn-success"><i class="fa-solid fa-save"></i> Simpan Verifikasi</button>
                        <button type="button" onclick="closeModal()" class="btn btn-secondary">Tutup</button>
                    </div>
                </form>
                <?php else: ?>
                <div class="verified-info">
                    <p><strong>Laporan ini sudah diverifikasi</strong></p>
                    <p>Status: <strong><?= ucfirst($detail_laporan['status_verifikasi']) ?></strong></p>
                    <p>Tanggal: <?= date('d/m/Y H:i', strtotime($detail_laporan['verified_at'])) ?></p>
                </div>
                <?php endif; ?>
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

    // Modal Functions
    function closeModal() {
        const params = new URLSearchParams(window.location.search);
        params.delete('detail');
        window.location.href = 'verifikasi_laporan.php' + (params.toString() ? '?' + params.toString() : '');
    }
    
    function toggleCatatan(value) {
        const textarea = document.querySelector('textarea[name="catatan_admin"]');
        if (value === 'revisi') {
            textarea.placeholder = 'Wajib diisi: Jelaskan bagian mana yang perlu diperbaiki...';
            textarea.required = true;
            textarea.style.borderColor = 'var(--danger)';
        } else {
            textarea.placeholder = 'Berikan pujian atau saran konstruktif untuk mahasiswa...';
            textarea.required = false;
            textarea.style.borderColor = 'var(--gray-200)';
        }
    }
    
    // Auto-focus textarea saat pilih revisi
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.querySelector('select[name="status_verifikasi"]');
        if (select && select.value === 'revisi') {
            document.querySelector('textarea[name="catatan_admin"]')?.focus();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    
    // Close modal when clicking outside
    document.querySelector('.modal-overlay')?.addEventListener('click', function(e) {
        if (e.target === this) closeModal();
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