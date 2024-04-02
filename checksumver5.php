<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require './vendor/phpmailer/phpmailer/src/PHPMailer.php';
require './vendor/phpmailer/phpmailer/src/Exception.php';
require './vendor/phpmailer/phpmailer/src/SMTP.php';

//Load Composer's autoloader
require 'vendor/autoload.php';

//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

//Server settings
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'elfondadaffa12345@gmail.com';             //SMTP username
$mail->Password   = 'peee qskb vktf vije';                  //SMTP password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //SMTP SSL Connection (SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS jika ingin menggunakan koneksi TLS)
$mail->Port       = 465;                                    //TCP 465 untuk SSL, 587 untuk TLS

$mail->setFrom('from@example.com', 'Mailer');               //Email pengirim
$mail->addAddress('elfondadaffa12345@gmail.com', 'Elfonda Daffa'); // Email penerima
$mail->isHTML(true);                                        //Set email format to HTML

// Initialize database connection
try {
    $db = new SQLite3('monitoring.db');
} catch (Exception $e) {
    // Log the error and handle it appropriately
    echo "Failed to connect to the database: " . $e->getMessage();
    exit; // Terminate script execution or handle the error gracefully
}

// Check if database connection is successful
if (!$db) {
    echo "Failed to connect to the database.";
    exit; // Terminate script execution or handle the error gracefully
}

// Buat tabel jika belum ada
$db->exec('CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp DATETIME,
    folder_path TEXT,
    file_name TEXT,
    initial_checksum TEXT,
    last_checksum TEXT
)');

$db->exec('CREATE TABLE IF NOT EXISTS datamaster (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT,
    initial_checksum TEXT
)');

$db->exec('CREATE TABLE IF NOT EXISTS deleted_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT NOT NULL,
    folder_path TEXT NOT NULL,
    deletion_time DATETIME NOT NULL
)');


$db->exec('CREATE TABLE IF NOT EXISTS added_files (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_name TEXT NOT NULL,
    folder_path TEXT NOT NULL,
    addition_time DATETIME NOT NULL
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
        $mail->Body    = $body; //AltBody untuk plaintext, body untuk html based
        $mail->send();
        echo 'Message has been sent';
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

// Fungsi untuk merekam file yang ditambahkan
function record_added_file($file_name, $folder_path) {
    global $db;
    date_default_timezone_set('Asia/Jakarta');
    $addition_time = date("Y-m-d H:i:s");
    $stmt = $db->prepare('INSERT INTO added_files (file_name, folder_path, addition_time) VALUES (:file_name, :folder_path, :addition_time)');
    $stmt->bindValue(':file_name', $file_name, SQLITE3_TEXT);
    $stmt->bindValue(':folder_path', $folder_path, SQLITE3_TEXT);
    $stmt->bindValue(':addition_time', $addition_time, SQLITE3_TEXT);
    $stmt->execute();
}

// Fungsi untuk merekam file yang dihapus
function record_deleted_file($file_name, $folder_path) {
    global $db;
    date_default_timezone_set('Asia/Jakarta');
    $deletion_time = date("Y-m-d H:i:s");
    $stmt = $db->prepare('INSERT INTO deleted_files (file_name, folder_path, deletion_time) VALUES (:file_name, :folder_path, :deletion_time)');
    $stmt->bindValue(':file_name', $file_name, SQLITE3_TEXT);
    $stmt->bindValue(':folder_path', $folder_path, SQLITE3_TEXT);
    $stmt->bindValue(':deletion_time', $deletion_time, SQLITE3_TEXT);
    $stmt->execute();
}

// Fungsi untuk mengirim email dengan log perubahan folder
function send_email_with_log($subject, $folder_path, $file_name, $initial_checksum, $last_checksum) {
    global $mail, $db;
    date_default_timezone_set('Asia/Jakarta');
    $timestamp = date("Y-m-d H:i:s");
    $body = "Timestamp: $timestamp\n <br>" .
            "Folder Path: $folder_path\n <br>" .
            "File Changed: $file_name\n <br>" .
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

// Monitor perubahan folder
function monitor_folder($folder_path, $db) {
    // Monitor perubahan folder
    $folder_status = [];

    while (true) {
        $current_status = [];
        $files = scandir($folder_path);
        foreach ($files as $file) {
            if ($file === "." || $file === ".."  || strpos($file, "~$") === 0) continue;
            $current_status[$file] = calculate_checksum($folder_path . DIRECTORY_SEPARATOR . $file);
        }

        // Hitung ringkasan status folder
        $total_files = count($current_status);
        $changed_files = 0;
        foreach ($current_status as $file_name => $last_checksum) {
            $initial_checksum = isset($folder_status[$file_name]) ? $folder_status[$file_name] : null;
            if ($initial_checksum !== $last_checksum) {
                $changed_files++;
            }
        }
        $unchanged_files = $total_files - $changed_files;

        // Tampilkan ringkasan status folder
        echo "<div class='dashboard'>";
        echo "<p>Total Anomali: $changed_files</p>";
        echo "<p>Total File: $total_files</p>";
        echo "</div>";

        // Simpan log perubahan jika ada
        foreach ($current_status as $file_name => $last_checksum) {
            $initial_checksum = isset($folder_status[$file_name]) ? $folder_status[$file_name] : null;
            if ($initial_checksum !== $last_checksum) {
                // Cek apakah file_name sudah ada di tabel datamaster
                $result = $db->querySingle("SELECT COUNT(*) FROM datamaster WHERE file_name='$file_name'");
                if (!$result) {
                    // Jika belum ada, tambahkan ke tabel datamaster
                    $initial_checksum = calculate_checksum($folder_path . DIRECTORY_SEPARATOR . $file_name);
                    $stmt = $db->prepare('INSERT INTO datamaster (file_name, initial_checksum) VALUES (:file_name, :initial_checksum)');
                    $stmt->bindValue(':file_name', $file_name, SQLITE3_TEXT);
                    $stmt->bindValue(':initial_checksum', $initial_checksum, SQLITE3_TEXT);
                    $stmt->execute();

                    // Rekam file yang baru ditambahkan
                    record_added_file($file_name, $folder_path);
                }
                send_email_with_log("Folder $folder_path status update", $folder_path, $file_name, $initial_checksum, $last_checksum);
                $folder_status[$file_name] = $last_checksum;
                print_r($folder_status);
            }
        }

     // Hapus file yang tidak ada di folder lagi dari datamaster
$file_names = array_keys($current_status);
if (!empty($file_names)) {
    $db->exec("DELETE FROM datamaster WHERE file_name NOT IN ('" . implode("','", $file_names) . "')");
}


       // Periksa file yang telah ditambahkan
       foreach ($current_status as $file_name => $last_checksum) {
           if (!isset($folder_status[$file_name])) {
               // File telah ditambahkan
               record_added_file($file_name, $folder_path);
               $folder_status[$file_name] = $last_checksum;
           }
       }
   // Periksa file yang telah dihapus
        foreach ($folder_status as $file_name => $initial_checksum) {
            if (!isset($current_status[$file_name])) {
                // File telah dihapus
                record_deleted_file($file_name, $folder_path);
                unset($folder_status[$file_name]); // Hapus file dari folder_status
            }
        }
    sleep(5);  // Check setiap 60 detik
}
}

// Inisialisasi logging
error_log("Monitoring started\n", 3, "error.log");

$folder_path = "C:\Users\SWIRTY-COM\Documents\data dummy"; // Path folder yang ingin dimonitor

try {
monitor_folder($folder_path, $db);
} catch (Exception $e) {
// Logging kesalahan
error_log("An error occurred: " . $e->getMessage() . "\n", 3, "error.log");
}
?>
