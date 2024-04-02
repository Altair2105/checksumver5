<?php
// Inisialisasi database SQLite
$db = new SQLite3('monitoring.db');

// Periksa apakah parameter label telah diterima dari permintaan GET
if (isset($_GET['label'])) {
    $label = $_GET['label'];
    
    // Tentukan query SQL berdasarkan label yang diterima
    switch ($label) {
        case 'Changed Files':
            $query = 'SELECT timestamp, folder_path, file_name FROM logs WHERE initial_checksum <> last_checksum';
            break;
        case 'Deleted Files':
            $query = 'SELECT deletion_time AS timestamp, folder_path, file_name FROM deleted_files';
            break;
        case 'Added Files':
            $query = 'SELECT addition_time AS timestamp, folder_path, file_name FROM added_files';
            break;
        default:
            // Label tidak valid, kirim respons dengan status kode 400 (Bad Request)
            http_response_code(400);
            echo json_encode(array("message" => "Invalid label."));
            exit();
    }
    
    // Eksekusi query SQL
    $result = $db->query($query);
    
    // Siapkan array untuk menyimpan data
    $data = array();
    
    // Loop melalui hasil query dan tambahkan ke array data
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[] = $row;
    }
    
    // Kirim respons JSON dengan data
    echo json_encode(array("data" => $data));
} else {
    // Parameter label tidak diterima, kirim respons dengan status kode 400 (Bad Request)
    http_response_code(400);
    echo json_encode(array("message" => "Missing label parameter."));
}
?>
