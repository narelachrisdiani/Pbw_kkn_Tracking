<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo " Koneksi database BERHASIL!<br>";
    echo "Database: db_kkn_tracking<br>";
    
    // Cek jumlah tabel
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->rowCount();
    echo "Jumlah tabel: $tables tabel<br>";
    
    // Cek data users
    $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
    $total = $stmt->fetch()['total'];
    echo "Total users: $total users";
} else {
    echo " Koneksi database GAGAL!";
}
?>