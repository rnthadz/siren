<?php
include 'conn.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=riwayat_maintenance.xls");

echo "<table border='1'>
<tr>
  <th>No</th>
  <th>Nama Alat</th>
  <th>Tanggal Maintenance</th>
  <th>Jenis Maintenance</th>
  <th>Durasi</th>
  <th>Keterangan</th>
</tr>";

$query = "SELECT * FROM jadwal_maintenance ORDER BY tgl_maintenance DESC";
$result = mysqli_query($conn, $query);
$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
  echo "<tr>
    <td>{$no}</td>
    <td>{$row['nama_aset']}</td>
    <td>{$row['tgl_maintenance']}</td>
    <td>{$row['jenis_maintenance']}</td>
    <td>{$row['periode']} {$row['satuan_periode']}</td>
    <td>" . ($row['keterangan'] ?? '-') . "</td>
  </tr>";
  $no++;
}
echo "</table>";
?>
