<?php
include 'conn.php';
set_time_limit(0);

// === PENTING: SET ZONA WAKTU KE WIB (Asia/Jakarta) ===
date_default_timezone_set('Asia/Jakarta');

// === TOKEN BOT TELEGRAM ===
$botToken = "8594491754:AAHrbz0EgbS6SUHvA5vp92Ts6vuquWXG0VA";

// === TANGGAL HARI INI (WIB) ===
$today = date('Y-m-d');
$logFile = __DIR__ . '/log_reminder.txt'; // file log di folder yang sama

// === Fungsi untuk nulis log ===
function tulisLog($pesan) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s]');
    // Tambahkan \n di CMD atau <br> di browser
    $output = (php_sapi_name() == 'cli') ? "\n" : "<br>"; 
    file_put_contents($logFile, "$timestamp $pesan" . PHP_EOL, FILE_APPEND);
}

// === AMBIL DATA MAINTENANCE YANG JATUH TEMPO HARI INI ===
// Pastikan tgl_berikutnya di database adalah tipe DATE saja, bukan DATETIME.
$query = "
    SELECT jm.*, p.nama_pegawai AS nama_pegawai, p.chat_id 
    FROM jadwal_maintenance jm
    JOIN pegawai p ON jm.id_pegawai = p.id_pegawai
    WHERE jm.tgl_berikutnya = '$today'
";

tulisLog("Mengecek jadwal untuk tanggal: $today");

$result = mysqli_query($conn, $query);

// === CEK ERROR QUERY DULU (DEBUG FRIENDLY) ===
if (!$result) {
    tulisLog("Fatal Error: Query error: " . mysqli_error($conn));
    die("Query error: " . mysqli_error($conn));
}

// === JIKA ADA JADWAL HARI INI ===
if (mysqli_num_rows($result) > 0) {
    tulisLog("🔔 Ada " . mysqli_num_rows($result) . " jadwal maintenance hari ini.");
    
    // Siapkan array untuk menyimpan semua update (untuk eksekusi di akhir)
    $updates = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $chat_id = $row['chat_id'];
        $nama = $row['nama_pegawai'];
        $aset = $row['nama_aset'];
        $jenis = $row['jenis_maintenance'];
        $periode = $row['periode'];
        $satuan_periode = strtolower(trim($row['satuan_periode']));

        // *** PENTING: Lakukan sanitasi Chat ID sebelum kirim ***
        // Pastikan Chat ID hanya angka dan tidak kosong
        if (empty($chat_id) || !is_numeric($chat_id)) {
            $msg = "⚠️ Gagal kirim ke $nama. Chat ID kosong/tidak valid: '$chat_id'.";
            echo "❌ $msg<br>";
            tulisLog($msg);
            continue; // Lanjut ke jadwal berikutnya
        }

        // === BUAT PESAN TELEGRAM ===
        $message = "🔔 *Pengingat Maintenance*\n\n"
                  . "👤 Pegawai: *$nama*\n"
                  . "📅 Jadwal hari ini: *$jenis*\n"
                  . "🧰 Aset: *$aset*\n\n"
                  . "Mohon segera lakukan pemeriksaan atau tindak lanjut.";

        // === KIRIM PESAN TELEGRAM MENGGUNAKAN cURL (Lebih Stabil dan Ada Feedback Respon) ===
        $url = "https://api.telegram.org/bot$botToken/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];
        
        // Inisialisasi cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $resultSend = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $isSuccess = ($resultSend !== false && $http_code == 200);

        // === HITUNG TANGGAL BERIKUTNYA ===
        $newDate = $row['tgl_berikutnya']; // Ambil tanggal lama sebagai default
        
        if ($isSuccess && !empty($periode) && is_numeric($periode)) {
            $date_str = "+$periode $satuan_periode"; // Contoh: "+1 months"
            $newDate = date('Y-m-d', strtotime($date_str, strtotime($today)));

            // Simpan data update di array
            $updates[] = [
                'id_jadwal' => $row['id_jadwal'],
                'new_date' => $newDate,
            ];
        }

        // === FEEDBACK HASIL ===
        if ($isSuccess) {
            echo "✅ Reminder terkirim ke $nama ($chat_id) — aset: $aset — next: $newDate<br>";
            tulisLog("✅ Reminder terkirim ke $nama ($chat_id) — aset: $aset — next: $newDate");
        } else {
            // Tulis response dari Telegram jika gagal untuk debugging
            $errorMsg = ($resultSend === false) ? "Error cURL." : "Response: " . $resultSend;
            echo "❌ Gagal kirim ke $nama ($chat_id). HTTP Code: $http_code. $errorMsg<br>";
            tulisLog("❌ Gagal kirim ke $nama ($chat_id). HTTP Code: $http_code. $errorMsg");
        }
    }
    
    // === EKSEKUSI SEMUA UPDATE DATABASE SETELAH PENGIRIMAN PESAN BERHASIL ===
    if (!empty($updates)) {
        tulisLog("Memulai update " . count($updates) . " jadwal...");
        foreach ($updates as $update) {
            $updateQuery = "
                UPDATE jadwal_maintenance 
                SET tgl_berikutnya = '{$update['new_date']}' 
                WHERE id_jadwal = {$update['id_jadwal']}
            ";
            if (!mysqli_query($conn, $updateQuery)) {
                tulisLog("❌ Gagal Update ID {$update['id_jadwal']}: " . mysqli_error($conn));
            } else {
                tulisLog("✅ Berhasil Update ID {$update['id_jadwal']} ke {$update['new_date']}");
            }
        }
    }


} else {
    echo "ℹ️ Tidak ada jadwal maintenance hari ini: $today";
    tulisLog("ℹ️ Tidak ada jadwal maintenance hari ini.");
}

// Tutup koneksi database
mysqli_close($conn);

?>