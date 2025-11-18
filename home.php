<?php
include 'conn.php';
// Pastikan koneksi sukses dan variabel $conn tersedia
if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// === KONFIGURASI DAN INIITALISASI ===
date_default_timezone_set('Asia/Jakarta');
$today = date('Y-m-d'); 

// === 1. PENGATURAN PAGINATION ===
$limit = 5; // Batasan data per halaman
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;

// === 1. CARD SUMMARY DATA ===

// A. Total Aset
$queryTotalAset = "SELECT COUNT(*) AS total FROM aset";
$resultTotalAset = mysqli_query($conn, $queryTotalAset);
if (!$resultTotalAset) die("Query Error Total Aset: " . mysqli_error($conn));
$totalAset = mysqli_fetch_assoc($resultTotalAset)['total'] ?? 0;


// B. Maintenance Bulan Ini (asumsi: tgl_maintenance adalah riwayat)
$bulanSekarang = date('m');
$queryMaintenanceBulanIni = "
    SELECT COUNT(*) AS jumlah 
    FROM jadwal_maintenance 
    WHERE MONTH(tgl_maintenance) = '$bulanSekarang' AND YEAR(tgl_maintenance) = YEAR(CURDATE())
";
$resultMaintenanceBulanIni = mysqli_query($conn, $queryMaintenanceBulanIni);
if (!$resultMaintenanceBulanIni) die("Query Error Maintenance Bulan Ini: " . mysqli_error($conn));
$maintenanceBulanIni = mysqli_fetch_assoc($resultMaintenanceBulanIni)['jumlah'] ?? 0;


// C. Pengingat Terkirim 
$queryTotalNotif = "SELECT COUNT(*) AS total FROM notifikasi";
$resultTotalNotif = mysqli_query($conn, $queryTotalNotif);
if (!$resultTotalNotif) {
    error_log("Query Error Total Notif: " . mysqli_error($conn));
    $totalNotif = 0;
} else {
    $totalNotif = mysqli_fetch_assoc($resultTotalNotif)['total'] ?? 0;
}

$queryTerkirim = "SELECT COUNT(*) AS terkirim FROM notifikasi WHERE status='Terkirim'";
$resultTerkirim = mysqli_query($conn, $queryTerkirim);
if (!$resultTerkirim) {
    error_log("Query Error Notif Terkirim: " . mysqli_error($conn));
    $terkirim = 0;
} else {
    $terkirim = mysqli_fetch_assoc($resultTerkirim)['terkirim'] ?? 0;
}


// === 2. DATA UNTUK CHART ===
$chartQuery = mysqli_query(
    $conn,
    "SELECT MONTH(tgl_maintenance) AS bulan, COUNT(*) AS jumlah
     FROM jadwal_maintenance
     GROUP BY MONTH(tgl_maintenance)
     ORDER BY MONTH(tgl_maintenance) ASC"
);

$bulan = [];
$jumlah = [];
if ($chartQuery) {
    while ($row = mysqli_fetch_assoc($chartQuery)) {
        $bulan[] = date('F', mktime(0, 0, 0, $row['bulan'], 1));
        $jumlah[] = (int)$row['jumlah'];
    }
}


// === 3. DATA UNTUK DAFTAR JADWAL MAINTENANCE (TABLE BAWAH) ===

// A. Query untuk Menghitung Total Data yang AKAN datang (tanpa limit)
$countQuery = "
    SELECT COUNT(*) AS total 
    FROM jadwal_maintenance jm
    WHERE jm.tgl_berikutnya >= '{$today}'
";
$resultCount = mysqli_query($conn, $countQuery);
if (!$resultCount) die("Query Error Count: " . mysqli_error($conn));
$total_data = mysqli_fetch_assoc($resultCount)['total'];
$total_pages = ceil($total_data / $limit);

// B. Query untuk menampilkan Data pada Halaman Tertentu (dengan limit dan offset)
$listJadwalQuery = "
    SELECT 
        jm.id_jadwal,
        a.nama_aset, 
        jm.tgl_berikutnya AS tgl_jadwal_upcoming
    FROM jadwal_maintenance jm
    JOIN aset a ON jm.id_aset = a.id_aset
    -- HANYA tampilkan yang akan datang atau hari ini
    WHERE jm.tgl_berikutnya >= '{$today}' 
    ORDER BY jm.tgl_berikutnya ASC
    LIMIT $limit OFFSET $offset
";

$jadwal = mysqli_query($conn, $listJadwalQuery);
if (!$jadwal) die("Query Error Daftar Jadwal: " . mysqli_error($conn));


// Tutup koneksi setelah semua data diambil
mysqli_close($conn); 
?>

<!DOCTYPE html>

<html lang="id">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SIREN | Dashboard</title>
  <link rel="stylesheet" href="home.css"> 
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body>

<nav class="navbar navbar-inverse"> 
  <div class="container-fluid">
    <div class="navbar-left-group">
    <div class="navbar-header">
      <img src="TVRILogo2019.png" alt="Logo Aplikasi" class="logo"> 
      <a class="navbar-brand" href="#">SIREN</a>
    </div>

    <ul class="nav navbar-nav">
      <li class="active"><a href="home.php">Home</a></li>
      <li><a href="jadwal.php">Input Jadwal</a></li>
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

  <div class="content">
  <div class="summary">
    <div class="card">
      <h3>Total Aset</h3>
      <p><?php echo $totalAset; ?></p>
    </div>

    <div class="card">
      <h3>Maintenance Bulan Ini</h3>
      <p><?php echo $maintenanceBulanIni; ?></p>
    </div>

    

  <div class="chart">
    <h2>Jumlah Maintenance per Bulan</h2>
    <canvas id="chartMaintenance"></canvas> 
  </div>

    <!-- TABLE -->
    <div class="upcoming">
      <h2>Daftar Jadwal Maintenance Mendatang</h2>
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Aset</th>
            <th>Tanggal Berikutnya</th>
            <th>Status Countdown</th>
          </tr>
        </thead>
        <tbody>

          <?php
          $no = $offset + 1; // Nomor urut disesuaikan dengan halaman
          if ($jadwal && mysqli_num_rows($jadwal) > 0) {
            while ($row = mysqli_fetch_assoc($jadwal)) {
              $tgl_berikutnya = $row['tgl_jadwal_upcoming']; 
              $status_text = 'T/A';
              $status_class = 'status-td';

              if ($tgl_berikutnya) {
                  // Hitung selisih hari
                  $date_diff = (strtotime($tgl_berikutnya) - strtotime($today)) / (60 * 60 * 24);
                  $days_left = floor($date_diff); 

                  if ($days_left == 0) {
                      // Hari ini
                      $status_text = 'JATUH TEMPO HARI INI';
                      $status_class = 'status-segera'; // Ganti ke segera agar lebih menonjol
                  } elseif ($days_left <= 7) {
                      // Kurang dari atau sama dengan 7 hari
                      $status_text = "SEGERA! Sisa {$days_left} Hari";
                      $status_class = 'status-segera';
                  } else {
                      // Lebih dari 7 hari
                      $status_text = "Sisa {$days_left} Hari";
                      $status_class = 'status-aman';
                  }
              }
              
              // Cek apakah jatuh tempo hari ini untuk memberikan class critical pada baris (TR)
              $tr_class = ($status_text === 'JATUH TEMPO HARI INI') ? 'class="status-critical"' : '';


              echo "<tr {$tr_class}>
                        <td>{$no}</td>
                        <td>" . htmlspecialchars($row['nama_aset']) . "</td>
                        <td>" . date('d-m-Y', strtotime($tgl_berikutnya)) . "</td>
                        <td><span class='status {$status_class}'>{$status_text}</span></td>
                    </tr>";
              $no++;
            }
          } else {
            echo "<tr><td colspan='4' style='text-align:center;'>Tidak ada jadwal maintenance mendatang yang tercatat.</td></tr>";
          }
          ?>
        </tbody>
      </table>

      <!-- PAGINATION BUTTONS -->
      <div class="pagination">
          <?php if ($page > 1): ?>
              <a href="?halaman=<?php echo $page - 1; ?>">Previous</a>
          <?php else: ?>
              <a href="#" class="disabled">Previous</a>
          <?php endif; ?>

          <?php if ($page < $total_pages): ?>
              <a href="?halaman=<?php echo $page + 1; ?>">Next</a>
          <?php else: ?>
              <a href="#" class="disabled">Next</a>
          <?php endif; ?>
      </div>
    </div>
  </div>
    <script>
      const ctx = document.getElementById('chartMaintenance').getContext('2d');

      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: <?= json_encode($bulan) ?>,
          datasets: [{
            label: 'Jumlah Maintenance',
            data: <?= json_encode($jumlah) ?>,
            backgroundColor: '#4e73df'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { y: { ticks: { precision:0 } } },
          plugins: { legend: { display: false } }
        }
      });
    </script>
</body>
</html>
