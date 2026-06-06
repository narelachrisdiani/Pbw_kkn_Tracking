<?php
session_start();

// Cek apakah user sudah login
if (isset($_SESSION['user_id'])) {
    // Redirect ke dashboard admin
    // PENTING: Pastikan folder bernama "Admin" (huruf besar) dan file "dashboard.php"
    header("Location: Admin/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - KKN Tracking System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body {
            background: linear-gradient(135deg,  0%, rgb(11, 43, 40) 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h2 { color: #333; font-size: 24px; margin-bottom: 10px; }
        .login-header p { color: #666; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
        .form-group input {
            width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;
        }
        .form-group input:focus { border-color: #8e5dae; outline: none; }
        .btn-login {
            width: 100%; padding: 12px; background: #2c7334; color: white; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.3s;
        }
        .btn-login:hover { background: #a75b9a; }
        .error-msg {
            background: #ffebee; color: #c62828; padding: 10px; border-radius: 6px; margin-bottom: 20px;
            text-align: center; font-size: 14px;
        }
        .info-box {
            margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 12px;
            color: #666; text-align: center;
        }
        .info-box code { background: #e9ecef; padding: 2px 5px; border-radius: 3px; font-weight: bold; color: #333; }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-header">
            <h2>KKN Tracking System</h2>
            <p>Silakan login untuk melanjutkan</p>
        </div>

        <!-- Tampilkan Pesan Error -->
        <?php if(isset($_GET['error'])): ?>
            <div class="error-msg">
                ⚠️ <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form action="login_process.php" method="POST">
            <div class="form-group">
                <label for="npm_nip">NIP / NPM</label>
                <input type="text" id="npm_nip" name="npm_nip" placeholder="Masukkan NIP/NPM" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan Password" required>
            </div>

            <button type="submit" class="btn-login">LOGIN</button>
        </form>

        <div class="info-box">
            <p>Default Login:</p>
            <p>Role: <code>dpl</code> | Pass: <code>admin123</code></p>
        </div>
    </div>

</body>
</html>