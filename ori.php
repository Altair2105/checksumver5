<?php
// Konfigurasi Email
$smtp_port = 465;
$smtp_server = "smtp.gmail.com";
$email_from = "elfondadaffa12345@gmail.com";
$email_to = "elfondadaffa12345@gmail.com";
$pswd = "peee qskb vktf vije";

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
    // Kirim email
    global $smtp_server, $smtp_port, $email_from, $email_to, $pswd;
    $message = "Subject: $subject\n\n$body";
    $context = stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);
    $smtp = stream_socket_client("ssl://$smtp_server:$smtp_port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
    if ($smtp === false) {
        error_log("Failed to connect to SMTP server: $errstr ($errno)\n", 3, "error.log");
        return;
    }
    fwrite($smtp, "EHLO localhost\r\n");
    fwrite($smtp, "AUTH LOGIN\r\n");
    fwrite($smtp, base64_encode($email_from) . "\r\n");
    fwrite($smtp, base64_encode($pswd) . "\r\n");
    fwrite($smtp, "MAIL FROM: <$email_from>\r\n");
    fwrite($smtp, "RCPT TO: <$email_to>\r\n");
    fwrite($smtp, "DATA\r\n");
    fwrite($smtp, $message . "\r\n.\r\n");
    fwrite($smtp, "QUIT\r\n");
    fclose($smtp);
}

function send_email_with_log($subject, $folder_path, $file_name, $initial_checksum, $last_checksum) {
    // Kirim email dengan log perubahan folder
    global $db;
    $timestamp = date("Y-m-d H:i:s");
    $body = "Timestamp: $timestamp\n" .
            "Folder Path: $folder_path\n" .
            "File Changed: $file_name\n" .
            "Initial Checksum: $initial_checksum\n" .
            "Last Checksum: $last_checksum\n";
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
            }
        }
        sleep(60);  // Check setiap 60 detik
    }
}

// Inisialisasi logging
error_log("Monitoring started\n", 3, "error.log");

$folder_path = "C:\\Users\\SWIRTY-COM\\Documents\\data dummy";  // Path folder yang ingin dimonitor

try {
    monitor_folder($folder_path);
} catch (Exception $e) {
    // Logging kesalahan
    error_log("An error occurred: " . $e->getMessage() . "\n", 3, "error.log");
}
?>
