<?php
session_start();
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $npm_nip = trim($_POST['npm_nip']);
    $password = $_POST['password'];

    $database = new Database();
    $conn = $database->getConnection();

    // Cari user berdasarkan NIP/NPM
    $query = "SELECT * FROM users WHERE npm_nip = :npm_nip";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':npm_nip', $npm_nip);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Validasi password: terima 'admin123' (default) ATAU password hash dari DB
        if ($password === 'admin123' || password_verify($password, $user['password'])) {
            
            // Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['npm_nip'] = $user['npm_nip'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Redirect berdasarkan role
            switch($user['role']) {
                case 'admin':
                    header("Location: Admin/dashboard.php");
                    break;
                case 'dpl':
                    header("Location: Dpl/dashboard.php");
                    break;
                case 'mahasiswa':
                    header("Location: mahasiswa/dashboard.php");
                    break;
                case 'lembaga':
                    header("Location: lembaga/dashboard.php");
                    break;
                default:
                    header("Location: index.php");
                    break;
            }
            exit();
            
        } else {
            header("Location: index.php?error=Password salah!");
        }
    } else {
        header("Location: index.php?error=User tidak ditemukan!");
    }
} else {
    header("Location: index.php");
}
?>