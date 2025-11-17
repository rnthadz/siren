<?php
session_start();
include 'conn.php';

// Pastikan user sudah login
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}

$username = $_SESSION['username'];

// Ambil data pegawai dari tabel pegawai
$query = "SELECT * FROM pegawai WHERE username = '$username' LIMIT 1";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

// Kalau data tidak ditemukan
if (!$data) {
  echo "<script>alert('Data pegawai tidak ditemukan.'); window.location.href='home.php';</script>";
  exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profil User | SIREN</title>
  <link rel="stylesheet" href="profil.css">
</head>
<body>

  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="container-fluid">
      <div class="navbar-header">
        <img src="TVRILogo2019.png" alt="Logo" class="logo">
        <a class="navbar-brand" href="#">SIREN</a>
      </div>
      <ul class="navbar-nav">
        <li><a href="home.php">Home</a></li>
        <li><a href="jadwal.php">Input Jadwal</a></li>
        <li><a href="histori.php">Histori</a></li>
        <li class="active"><a href="profil.php">Profil</a></li>
      </ul>
    </div>
  </nav>

  <!-- PROFIL CONTAINER -->
  <div class="profile-container">
    <div class="profile-card">
      <div class="profile-header">
        <img src="profile.png" alt="Foto Profil" class="profile-pic">
        <h2><?= htmlspecialchars($data['nama_pegawai']) ?></h2>
        <p>NIP : <?= htmlspecialchars($data['nip']) ?></p>
      </div>

      <div class="profile-info">
        <h3>Informasi Akun</h3>
        <div class="info-item">
          <span class="label">Username:</span>
          <span class="value"><?= htmlspecialchars($data['username']) ?></span>
        </div>
        <div class="info-item">
          <span class="label">Jabatan:</span>
          <span class="value"><?= htmlspecialchars($data['jabatan']) ?></span>
        </div>
        <div class="info-item">
          <span class="label">No. Telepon:</span>
          <span class="value"><?= htmlspecialchars($data['kontak_telegram']) ?></span>
        </div>
        <div class="info-item">
          <span class="label">Lokasi:</span>
          <span class="value"><?= htmlspecialchars($data['lokasi']) ?></span>
        </div>
      </div>

      <div class="profile-actions">
        <a href="edit_profil.php" class="btn-action btn-edit">Edit Profil</a>
        <a href="login.php" class="btn-action btn-logout">Logout</a>
      </div>
    </div>
  </div>

</body>
</html>
