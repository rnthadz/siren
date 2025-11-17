<?php
session_start();
include 'conn.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $query = "SELECT * FROM pegawai WHERE username='$username'";
    $result = mysqli_query($conn, $query);

    if(!$result){
        die("Query error: " . mysqli_error($conn));
    }

    $data = mysqli_fetch_assoc($result);

    // Cek password (ganti dengan password_verify() jika pakai hash)
    if($data && $data['password'] === $password){
        $_SESSION['id_pegawai'] = $data['id_pegawai'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['nama_pegawai'] = $data['nama_pegawai'];
        $_SESSION['nip'] = $data['nip'];
        $_SESSION['jabatan'] = $data['jabatan'];
        $_SESSION['kontak_telegram'] = $data['kontak_telegram'];
        $_SESSION['lokasi'] = $data['lokasi'];

        header("Location: home.php");
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SIREN - Login</title>
<link rel="stylesheet" href="login.css">
</head>

<script>
function togglePassword(){
    const pwd = document.getElementById('password');
    const icon = document.querySelector('.toggle-icon');
    if(pwd.type === 'password'){
        pwd.type = 'text';
        icon.classList.add('show-password');
    } else {
        pwd.type = 'password';
        icon.classList.remove('show-password');
    }
}
</script>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="material-logo">
                <img src="TVRILogo2019.png" alt="Logo" class="logo">
            </div>
            <h2>Masuk</h2>
            <p>untuk lanjut ke akun Anda</p>
        </div>

        <?php if($error): ?>
            <div class="error-message show"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" required placeholder=" ">
                    <label for="username">Username</label>
                    <div class="input-line"></div>
                </div>
            </div>

            <div class="form-group">
                <div class="input-wrapper password-wrapper">
                    <input type="password" id="password" name="password" required placeholder=" ">
                    <label for="password">Password</label>
                    <div class="input-line"></div>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <span class="toggle-icon"></span>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="remember" name="remember" required>
                    <label for="remember" class="checkbox-label">
                        <span class="checkbox-material"></span>
                        Ingat saya
                    </label>
                </div>
                <a href="#" class="forgot-password">Lupa password?</a>
            </div>

            <button type="submit" class="login-btn material-btn">
                <span class="btn-text">MASUK</span>
            </button>
        </form>
    </div>
</div>


