<?php
session_start();
// Autentikasi: Pastikan pengguna sudah login.
// Jika belum ada implementasi login, gunakan ID default sementara.
if (!isset($_SESSION['id_pegawai'])) {
    // Implementasi otentikasi harus ada di sini (e.g., redirect ke login.php)
    // Untuk testing, gunakan ID default:
    $_SESSION['id_pegawai'] = 1; 
}
$id_pegawai = $_SESSION['id_pegawai'];

// Pastikan file koneksi database Anda sudah benar
include 'conn.php';

// --- BAGIAN 1: LOGIKA PENYIMPANAN DATA (PHP) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ambil data dari form.
    $nama_aset_input = trim((string)($_POST['nama_aset'] ?? '')); 
    $tgl_maintenance = (string)($_POST['tgl_maintenance'] ?? ''); 
    $jenis_maintenance = trim((string)($_POST['jenis_maintenance'] ?? '')); 
    
    // VARIABEL DUREBAS: Menggunakan 'periode_angka' untuk konsistensi dengan 'Periode' Anda
    $periode = (int)($_POST['periode'] ?? 0); 
    $periode_unit = trim((string)($_POST['satuan_periode'] ?? '')); 
    
    // --- LANGKAH 1: Validasi dan Mencari ID Aset yang sesuai dengan Nama Aset ---
    $id_aset = null;
    
    if (!empty($nama_aset_input)) {
        // Mencari ID Aset berdasarkan Nama Aset (Case-Insensitive)
        $stmt_aset = $conn->prepare("SELECT id_aset FROM aset WHERE LOWER(nama_aset) = LOWER(?) LIMIT 1");
        if ($stmt_aset === false) {
             echo "<script>alert('Error menyiapkan query aset: " . $conn->error . "');</script>";
             goto end_post;
        }
        $stmt_aset->bind_param("s", $nama_aset_input);
        $stmt_aset->execute();
        $result_aset = $stmt_aset->get_result();
        
        if ($result_aset && $result_aset->num_rows > 0) {
            $row_aset = $result_aset->fetch_assoc();
            $id_aset = $row_aset['id_aset']; // ID Aset valid ditemukan!
        }
        $stmt_aset->close();
    }
    
    // --- LANGKAH 2: Perhitungan Tanggal Berikutnya ---
    $tgl_berikutnya = null;
    $tgl_maintenance_db = null;

    if (!empty($tgl_maintenance)) {
        // Format tanggal ke Y-m-d untuk database
        $tgl_maintenance_db = date('Y-m-d', strtotime($tgl_maintenance));
    }
    
    // Menggunakan $periode_angka
    if ($tgl_maintenance_db && $periode > 0 && $periode_unit) {
        // Menerjemahkan satuan bahasa Indonesia ke format yang bisa dibaca strtotime
        $unit_map = ['Hari' => 'days', 'Bulan' => 'months', 'Tahun' => 'years'];
        $unit_strtotime = $unit_map[$periode_unit] ?? '';
        
        if ($unit_strtotime) {
            // Lakukan perhitungan tanggal berikutnya
            $tgl_berikutnya = date('Y-m-d', strtotime("$tgl_maintenance_db +$periode $unit_strtotime"));
        }
    }
    
    // --- LANGKAH 3: Validasi Akhir dan Eksekusi INSERT ---
    
    $error_fields = [];
    if (empty($nama_aset_input)) $error_fields[] = "Nama Aset";
    if (empty($tgl_maintenance)) $error_fields[] = "Tanggal Maintenance Sebelumnya"; 
    if (empty($jenis_maintenance)) $error_fields[] = "Jenis Maintenance";
    // Menggunakan $periode_angka
    if ($periode <= 0) $error_fields[] = "Periode Maintenance"; 
    if (empty($periode_unit)) $error_fields[] = "Satuan (Hari/Bulan/Tahun)";


    if (!empty($error_fields)) {
        // Tampilkan daftar field yang bermasalah
        $field_list = implode(", ", $error_fields);
        echo "<script>alert('Validasi Gagal! Mohon isi field berikut dengan benar: \\n\\n{$field_list}');</script>";
         
    } elseif (!$id_aset) {
         // Pesan error jika Nama Aset TIDAK DITEMUKAN
         echo "<script>alert('Error: Nama Aset \"$nama_aset_input\" tidak ditemukan dalam daftar Aset. Pastikan penulisan benar, atau aset tersebut sudah terdaftar di database.');</script>";
         
    } else {
        // Data Aset sudah valid, lakukan INSERT ke jadwal_maintenance.
        
        $insert_query = "
            INSERT INTO jadwal_maintenance (
                id_aset, id_pegawai, 
                nama_aset, 
                tgl_maintenance, 
                jenis_maintenance, 
                periode,             /* Kolom di DB */
                satuan_periode,      /* Kolom di DB */
                tgl_berikutnya
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt_insert = $conn->prepare($insert_query);
        
        if ($stmt_insert === false) {
             echo "<script>alert('Error menyiapkan query INSERT: " . $conn->error . "');</script>";
             goto end_post;
        }

        // Tipe Parameter: iisssiss (INT, INT, STRING, STRING, STRING, INT, STRING, STRING)
        $stmt_insert->bind_param("iisssiss", 
            $id_aset,                // 1. id_aset (INT)
            $id_pegawai,             // 2. id_pegawai (INT)
            $nama_aset_input,        // 3. nama_aset (STRING)
            $tgl_maintenance_db,     // 4. tgl_maintenance (STRING - DATE)
            $jenis_maintenance,      // 5. jenis_maintenance (STRING)
            $periode,          // 6. periode (INT) <-- Menggunakan variabel baru
            $periode_unit,           // 7. satuan_periode (STRING)
            $tgl_berikutnya          // 8. tgl_berikutnya (STRING - DATE)
        );
        
        if ($stmt_insert->execute()) {
            echo "<script>alert('Data berhasil disimpan! Maintenance berikutnya: $tgl_berikutnya'); window.location.href='home.php';</script>";
        } else {
            $error_message = $stmt_insert->error;
            echo "<script>alert('Terjadi kesalahan saat menyimpan data: $error_message');</script>";
        }
        $stmt_insert->close();
    }
}
end_post:
// --- BAGIAN 2: TAMPILAN HTML ---
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Jadwal Maintenance | SIREN</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="jadwal.css"> 
    
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-inverse"> 
  <div class="container-fluid">
    <div class="navbar-left-group">
    <div class="navbar-header">
      <img src="TVRILogo2019.png" alt="Logo Aplikasi" class="logo"> 
      <a class="navbar-brand" href="#">SIREN</a>
    </div>

    <ul class="nav navbar-nav">
      <li><a href="home.php">Home</a></li>
      <li class="active"><a href="jadwal.php">Input Jadwal</a></li>
      <li><a href="histori.php">Histori</a></li>
    </ul>
  </div>

    <div class="navbar-profile">
      <a href="profil.php" title="Buka Halaman Profil">
      <img src="profile.png" alt="Profil" class="profile-icon"> 
      </a>
    </div>
  </div>
</nav>

    <!-- FORM INPUT JADWAL MAINTENANCE -->
    <main class="form-container">
        <h2>Form Input Jadwal Maintenance</h2>
        <form action="jadwal.php" method="post" class="maintenance-form">

            <!-- Nama Aset (Input Teks) -->
            <div class="form-group">
                <label for="nama-aset">Nama Aset</label>
                <input type="text" id="nama-aset" name="nama_aset" placeholder="Contoh: Pendingin Ruangan" required>
            </div>

            <!-- Tanggal Maintenance Sebelumnya -->
            <div class="form-group">
                <label for="tanggal">Tanggal Maintenance Sebelumnya</label>
                <input type="date" id="tgl-maintenance" name="tgl_maintenance" required>
            </div>

            <!-- Jenis Maintenance -->
            <div class="form-group">
                <label for="jenis-maintenance">Jenis Maintenance</label>
                <input type="text" id="jenis-maintenance" name="jenis_maintenance" placeholder="Contoh: Pemeliharaan Rutin" required>
            </div>

            <!-- Periode Maintenance -->
            <div class="form-group">
                <label for="periode-angka">Periode Maintenance</label>
                <div class="durasi-wrapper">
                    <!-- name="periode_angka" -->
                    <input type="number" id="periode" name="periode" placeholder="Contoh: 3" min="1" required>
                    <select id="satuan-periode" name="satuan_periode" required>
                        <option value="Hari">Hari</option>
                        <option value="Bulan">Bulan</option>
                        <option value="Tahun">Tahun</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit" name="simpan">Simpan</button>
                <button type="reset" class="btn-reset">Bersihkan</button>
            </div>
        </form>
    </main>
</body>
</html>