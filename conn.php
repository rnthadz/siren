<?php
$host = "localhost";
$user = "root"; // default XAMPP
$pass = "";
$db   = "siren"; // sesuaikan dengan nama database kamu

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
