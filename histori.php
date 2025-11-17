<?php
session_start();
// Jika belum ada implementasi login, gunakan ID default sementara.
if (!isset($_SESSION['id_pegawai'])) {
    $_SESSION['id_pegawai'] = 1; 
}

include 'conn.php';

// --- HELPER UNTUK KONVERSI BULAN KE INDONESIA ---
function getIndonesianMonth($month_number) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $months[(int)$month_number] ?? '';
}

// --- LOGIKA FILTER PHP ---
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_year = isset($_GET['tahun']) ? $conn->real_escape_string($_GET['tahun']) : ''; // Format: YYYY
$filter_month = isset($_GET['bulan']) ? $conn->real_escape_string($_GET['bulan']) : ''; // Format: MM

$where_clauses = [];

// 1. Filter berdasarkan nama alat (Search)
if (!empty($search_query)) {
    // Cari nama aset yang mengandung string pencarian
    $where_clauses[] = "nama_aset LIKE '%$search_query%'";
}

// 2. Filter berdasarkan Tahun
if (!empty($filter_year)) {
    // Menggunakan fungsi MySQL YEAR()
    $where_clauses[] = "YEAR(tgl_maintenance) = " . (int)$filter_year;
}

// 3. Filter berdasarkan Bulan (hanya diterapkan jika filter tahun juga ada atau bulan dipilih)
if (!empty($filter_month)) {
    // Menggunakan fungsi MySQL MONTH()
    $where_clauses[] = "MONTH(tgl_maintenance) = " . (int)$filter_month;
}


$sql_where = '';
if (!empty($where_clauses)) {
    $sql_where = ' WHERE ' . implode(' AND ', $where_clauses);
}

// Logika pengambilan data Histori Maintenance
$query = "SELECT * FROM jadwal_maintenance" . $sql_where . " ORDER BY tgl_maintenance DESC";
$result = $conn->query($query);
$history_data = [];

if ($result === false) {
    // Handle error jika query gagal
    echo "Error: " . $conn->error;
} else if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $history_data[] = $row;
    }
}

// Data unik TAHUN yang ada di database untuk opsi filter tahun
$year_options = [];
$year_result = $conn->query("SELECT DISTINCT YEAR(tgl_maintenance) AS year_num FROM jadwal_maintenance ORDER BY year_num DESC");
if ($year_result && $year_result->num_rows > 0) {
    while ($row = $year_result->fetch_assoc()) {
        $year_options[] = $row['year_num'];
    }
}

// Data BULAN standar (1-12) untuk opsi filter bulan
$month_options = [];
for ($i = 1; $i <= 12; $i++) {
    // Value = MM (dua digit, untuk kejelasan)
    $month_options[sprintf('%02d', $i)] = getIndonesianMonth($i);
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histori Maintenance | SIREN</title>
    <!-- Memuat file CSS utama (asumsi ini berisi style navbar) -->
    <link rel="stylesheet" href="home.css">
    <!-- Memuat file CSS khusus Histori -->
    <link rel="stylesheet" href="histori.css">
    <!-- Memuat library untuk export ke excel -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.17.4/dist/xlsx.full.min.js"></script>
</head>
<body>

    <!-- NAVBAR - ASUMSI GAYA DI AMBIL DARI home.css -->
    <nav class="navbar navbar-inverse"> 
  <div class="container-fluid">
    <div class="navbar-left-group">
    <div class="navbar-header">
      <img src="TVRILogo2019.png" alt="Logo Aplikasi" class="logo"> 
      <a class="navbar-brand" href="#">SIREN</a>
    </div>

    <ul class="nav navbar-nav">
      <li><a href="home.php">Home</a></li>
      <li><a href="jadwal.php">Input Jadwal</a></li>
      <li class="active"><a href="histori.php">Histori</a></li>
    </ul>
  </div>

    <div class="navbar-profile">
      <a href="profil.php" title="Buka Halaman Profil">
      <img src="profile.png" alt="Profil" class="profile-icon"> 
      </a>
    </div>
  </div>
</nav>

    <main class="content-container">
        <!-- Riwayat Maintenance -->
        <div class="card">
            <h2>Riwayat Maintenance</h2>
            <p class="description-text">Lihat histori perawatan alat</p>

            <!-- FORM FILTER: Action ke halaman ini sendiri, method GET -->
            <form action="histori.php" method="GET" class="filter-controls" id="filter-form">
                
                <!-- Input Search -->
                <input type="text" name="search" id="search-input" 
                       placeholder="Cari berdasarkan nama alat" class="search-input" 
                       value="<?php echo htmlspecialchars($search_query); ?>">
                
                <!-- Select Filter Bulan -->
                <select name="bulan" class="month-select" id="month-select">
                    <option value="">Semua Bulan</option>
                    <?php 
                    // Looping opsi bulan
                    foreach ($month_options as $value => $label) {
                        $selected = ($filter_month == $value) ? 'selected' : '';
                        // VALUE KRUSIAL: format MM (dua digit)
                        // LABEL: format Nama Bulan Indonesia
                        echo "<option value='{$value}' {$selected}>{$label}</option>";
                    }
                    ?>
                </select>

                <!-- Select Filter Tahun -->
                <select name="tahun" class="year-select" id="year-select">
                    <option value="">Semua Tahun</option>
                    <?php 
                    // Looping opsi tahun
                    foreach ($year_options as $year) {
                        $selected = ($filter_year == $year) ? 'selected' : '';
                        echo "<option value='{$year}' {$selected}>{$year}</option>";
                    }
                    ?>
                </select>
                
                <button type="submit" class="btn-terapkan">Terapkan</button>
                <!-- Tombol Export Excel (Type Button agar tidak ikut submit form) -->
                <button type="button" id="export-excel-btn" class="btn-export">Export ke Excel</button>
            </form>

            <!-- Tabel Histori Maintenance -->
            <div class="table-responsive">
                <table id="maintenance-table">
                    <thead>
                        <tr>
                            <th class="col-no text-center">No</th> 
                            <th class="col-nama-alat">Nama Alat</th> 
                            <th class="col-tanggal text-center">Tanggal Maintenance</th>
                            <th class="col-jenis">Jenis Maintenance</th>
                            <th class="col-periode text-center">Periode</th>
                            <th class="col-tgl-next text-center">Tanggal Berikutnya</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; ?>
                        <?php foreach ($history_data as $row): ?>
                        <tr>
                            <td class="col-no text-center"><?php echo $no++; ?></td>
                            <td class="col-nama-alat" data-search="<?php echo htmlspecialchars($row['nama_aset'] ?? ''); ?>"><?php echo htmlspecialchars($row['nama_aset'] ?? ''); ?></td>
                            <td class="col-tanggal text-center"><?php echo htmlspecialchars($row['tgl_maintenance'] ?? ''); ?></td>
                            <td class="col-jenis"><?php echo htmlspecialchars($row['jenis_maintenance'] ?? ''); ?></td>
                            <td class="col-periode text-center"><?php echo htmlspecialchars(($row['periode'] ?? '') . ' ' . ($row['satuan_periode'] ?? '')); ?></td>
                            <td class="col-tgl-next text-center"><?php echo htmlspecialchars($row['tgl_berikutnya'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history_data)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Tidak ada riwayat maintenance ditemukan.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
    
    <script>
        // --- FUNGSI EXPORT KE EXCEL ---
        document.getElementById('export-excel-btn').addEventListener('click', function() {
            // Ambil elemen tabel
            const table = document.getElementById('maintenance-table');
            
            // Konversi tabel ke format array
            // Di sini kita menggunakan library XLSX yang sudah di-load di head.
            const data = XLSX.utils.table_to_sheet(table);
            
            // Buat workbook
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, data, "Riwayat Maintenance");
            
            // Tentukan nama file
            const filename = "Riwayat_Maintenance_<?php echo date('Ymd'); ?>.xlsx";
            
            // Tulis dan download file
            XLSX.writeFile(workbook, filename);

            // Tampilkan pesan sukses kustom (pengganti alert)
            displayMessage("Data berhasil diexport ke Excel!", "success");
        });
        
        // --- Custom Message Box (Pengganti Alert) ---
        function displayMessage(message, type = "info") {
            // Fungsi ini membuat pesan notifikasi di sudut kanan atas (seperti pop-up)
            const messageBox = document.createElement('div');
            messageBox.style.position = 'fixed';
            messageBox.style.top = '20px';
            messageBox.style.right = '20px';
            messageBox.style.padding = '15px';
            messageBox.style.borderRadius = '8px';
            messageBox.style.zIndex = '1000';
            messageBox.style.color = '#fff';
            messageBox.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.2)';
            messageBox.style.transition = 'opacity 0.5s ease-in-out';
            messageBox.style.opacity = 1;
            
            if (type === "success") {
                messageBox.style.backgroundColor = '#28a745'; 
            } else if (type === "error") {
                messageBox.style.backgroundColor = '#dc3545'; 
            } else {
                messageBox.style.backgroundColor = '#007bff'; 
            }

            messageBox.textContent = message;
            document.body.appendChild(messageBox);

            // Hilangkan setelah 3 detik
            setTimeout(() => {
                messageBox.style.opacity = 0;
                setTimeout(() => messageBox.remove(), 500); 
            }, 3000);
        }
    </script>

</body>
</html>