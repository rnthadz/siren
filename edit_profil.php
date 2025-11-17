<?php
session_start();
include 'conn.php';

// Pastikan user sudah login
if (!isset($_SESSION['username'])) {
  header("Location: login.php");
  exit();
}

$username = $_SESSION['username'];

// Ambil data pegawai dari database
$query = "SELECT * FROM pegawai WHERE username = '$username' LIMIT 1";
$result = mysqli_query($conn, $query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
  echo "<script>alert('Data pegawai tidak ditemukan.'); window.location.href='profil.php';</script>";
  exit();
}

// Update data jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama = mysqli_real_escape_string($conn, $_POST['nama_pegawai']);
  $nip = mysqli_real_escape_string($conn, $_POST['nip']);
  $jabatan = mysqli_real_escape_string($conn, $_POST['jabatan']);
  $no_telepon = mysqli_real_escape_string($conn, $_POST['kontak_telegram']);
  $lokasi = mysqli_real_escape_string($conn, $_POST['lokasi']);

  $update = "UPDATE pegawai 
             SET nama_pegawai='$nama', nip='$nip', jabatan='$jabatan', kontak_telegram='$no_telepon', lokasi='$lokasi'
             WHERE username='$username'";

  if (mysqli_query($conn, $update)) {
    echo "<script>alert('Profil berhasil diperbarui!'); window.location.href='profil.php';</script>";
  } else {
    echo "<script>alert('Terjadi kesalahan saat memperbarui profil.');</script>";
  }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profil | SIREN</title>
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

  <!-- FORM EDIT PROFIL -->
  <div class="profile-container">
    <div class="profile-card">

      <form method="post" class="edit-form">
        <h2>Edit Profil</h2>
        <div class="form-group">
          <label for="nama_pegawai">Nama Lengkap</label>
          <input type="text" id="nama_pegawai" name="nama_pegawai" value="<?= htmlspecialchars($data['nama_pegawai']) ?>" required>
        </div>

        <div class="form-group">
          <label for="nip">NIP</label>
          <input type="text" id="nip" name="nip" value="<?= htmlspecialchars($data['nip']) ?>" required>
        </div>

        <div class="form-group">
          <label for="jabatan">Jabatan</label>
          <input type="text" id="jabatan" name="jabatan" value="<?= htmlspecialchars($data['jabatan']) ?>" required>
        </div>

        <div class="form-group">
          <label for="no_telepon">Nomor Telepon</label>
          <input type="text" id="no_telepon" name="kontak_telegram" value="<?= htmlspecialchars($data['kontak_telegram']) ?>" required>
        </div>

        <div class="form-group">
          <label for="lokasi_kerja">Lokasi Kerja</label>
          <input type="text" id="lokasi_kerja" name="lokasi" value="<?= htmlspecialchars($data['lokasi']) ?>" required>
        </div>

        <div class="edit-actions">
          <button type="submit" class="btn-submit">Simpan Perubahan</button>
          <a href="edit_profil.php" class="btn-reset">Batal</a>
        </div>
      </form>
    </div>
  </div>

</body>
</html>
