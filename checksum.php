<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require './vendor/phpmailer/phpmailer/src/PHPMailer.php';
require './vendor/phpmailer/phpmailer/src/Exception.php';
require './vendor/phpmailer/phpmailer/src/SMTP.php';

// Load Composer's autoloader
require 'vendor/autoload.php';

// Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

// Server settings
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'elfondadaffa12345@gmail.com';             // SMTP username
$mail->Password   = 'peee qskb vktf vije';                  // SMTP password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            // SMTP SSL Connection (SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS jika ingin menggunakan koneksi TLS)
$mail->Port       = 465;                                    // TCP 465 untuk SSL, 587 untuk TLS

$mail->setFrom('from@example.com', 'Mailer');               // Email pengirim
$mail->addAddress('elfondadaffa12345@gmail.com', 'Elfonda Daffa'); // Email penerima
$mail->isHTML(true);                                        // Set email format to HTML

// Inisialisasi database SQLite
$db = new SQLite3('monitoring.db');

// Buat tabel jika belum ada
$db->exec('CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME,
    folder_path TEXT,
    file_name TEXT,
    initial_checksum TEXT,
    last_checksum TEXT
)');

function calculate_checksum($file_path) {
    // Calculate the checksum of a file
    $hasher = hash_init("sha256");
    $handle = fopen($file_path, "rb");
    while (!feof($handle)) {
        $data = fread($handle, 65536);  // Read in 64k chunks
        hash_update($hasher, $data);
    }
    fclose($handle);
    return hash_final($hasher);
}

function send_email($subject, $body) {
    global $mail;
    try{
        $mail->Subject = $subject;
        $mail->Body    = $body; // AltBody untuk plaintext, body untuk html based
        $mail->send();
        echo 'Message has been sent';
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function send_email_with_log($subject, $folder_path, $file_name, $initial_checksum, $last_checksum) {
    // Kirim email dengan log perubahan folder
    global $db;
    date_default_timezone_set('Asia/Jakarta');
    $timestamp = date("Y-m-d H:i:s");
    $body = "Timestamp: $timestamp\n <br>" .
            "Folder Path: $folder_path\n <br>" .
            "File Changed: $file_name\n <br>".
            "Initial Checksum: $initial_checksum\ <br>" .
            "Last Checksum: $last_checksum\n <br>";
    send_email($subject, $body);
    // Simpan log ke database SQLite
    $stmt = $db->prepare('INSERT INTO logs (timestamp, folder_path, file_name, initial_checksum, last_checksum) VALUES (:timestamp, :folder_path, :file_name, :initial_checksum, :last_checksum)');
    $stmt->bindValue(':timestamp', $timestamp, SQLITE3_TEXT);
    $stmt->bindValue(':folder_path', $folder_path, SQLITE3_TEXT);
    $stmt->bindValue(':file_name', $file_name, SQLITE3_TEXT);
    $stmt->bindValue(':initial_checksum', $initial_checksum, SQLITE3_TEXT);
    $stmt->bindValue(':last_checksum', $last_checksum, SQLITE3_TEXT);
    $stmt->execute();
}

function monitor_folder($folder_path) {
    // Monitor perubahan folder
    $folder_status = [];

    while (true) {
        $current_status = [];
        $files = scandir($folder_path);
        foreach ($files as $file) {
            if ($file === "." || $file === "..") continue;
            $current_status[$file] = calculate_checksum($folder_path . DIRECTORY_SEPARATOR . $file);
        }
        foreach ($current_status as $file_name => $last_checksum) {
            $initial_checksum = isset($folder_status[$file_name]) ? $folder_status[$file_name] : null;
            if ($initial_checksum !== $last_checksum) {
                send_email_with_log("Folder $folder_path status update", $folder_path, $file_name, $initial_checksum, $last_checksum);
                $folder_status[$file_name] = $last_checksum;
                print_r($folder_status);
            }
        }
        sleep(5);  // Check setiap 60 detik
    }
}

// Inisialisasi logging
error_log("Monitoring started\n", 3, "error.log");

$folder_path = "C:\\Users\\SWIRTY-COM\\Documents\\data dummy";  // Path folder yang ingin dimonitor

// Mulai monitoring folder dalam thread terpisah agar halaman web dapat ditampilkan
if (php_sapi_name() !== 'cli') {
    // Hanya jalankan monitor_folder jika script dijalankan melalui web (bukan CLI)
    // agar skrip dapat berjalan secara paralel dengan halaman web
    ignore_user_abort(true);
    set_time_limit(0);
    ob_start();
    header('Connection: close');
    header('Content-Length: ' . ob_get_length());
    ob_end_flush();
    ob_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    monitor_folder($folder_path);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Anomali</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f2f2f2;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Monitoring Anomali</h1>
        <table>
            <tr>
                <th>Timestamp</th>
                <th>Folder Path</th>
                <th>File Name</th>
                <th>Initial Checksum</th>
                <th>Last Checksum</th>
            </tr>
            <?php
            // Ambil data log dari database
            $result = $db->query('SELECT * FROM logs');

            // Tampilkan tabel HTML untuk menampilkan data log
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                // Tampilkan hanya entri yang menunjukkan adanya perubahan anomali
                if ($row['initial_checksum'] !== $row['last_checksum']) {
                    echo "<tr>";
                    echo "<td>" . $row['timestamp'] . "</td>";
                    echo "<td>" . $row['folder_path'] . "</td>";
                    echo "<td>" . $row['file_name'] . "</td>";
                    echo "<td>" . $row['initial_checksum'] . "</td>";
                    echo "<td>" . $row['last_checksum'] . "</td>";
                    echo "</tr>";
                }
            }

            // Tutup koneksi database
            $db->close();
            ?>
        </table>
    </div>
    <div class="footer">
        <p>&copy; 2024 Monitoring Anomali. All rights reserved.</p>
    </div>
</body>
</html>
