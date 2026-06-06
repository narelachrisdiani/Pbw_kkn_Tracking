<?php
session_start();

function checkAuth($required_role = null) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }

    if ($required_role && $_SESSION['role'] !== $required_role) {
        echo "<script>alert('Akses ditolak! Anda tidak memiliki hak akses ini.'); window.location='../index.php';</script>";
        exit();
    }
}

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>