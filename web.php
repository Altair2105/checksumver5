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
        .background {
            background-image: url('WhatsApp Image 2024-03-18 at 14.06.28.jpeg');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            position: relative;
            z-index: 0;
            background-attachment: fixed;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        .dashboard {
            background-color: #007bff;
            color: #fff;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;     
        }
        .dashboard p {
            margin: 0;
        }

        .pie-chart-container {
            text-align: center;
            margin-top: 20px;
        }
        .pie-chart {
            width: 300px;
            height: 300px;
            margin: 0 auto;
        }
    </style>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js" integrity="sha512-JPcRR8yFa8mmCsfrw4TNte1ZvF1e3+1SdGMslZvmrzDYxS69J7J49vkFL8u6u8PlPJK+H3voElBtUCzaXj+6ig==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>
    <div class="background">
        <div class="container">
            <h1>Monitoring Anomali</h1>
            <div class="pie-chart-container">
                <canvas id="myPieChart" class="pie-chart"></canvas>
            </div>

            <!-- Dashboard section -->
            <div class="dashboard">
                <h2>Statistik Perubahan File</h2>

                <?php
                // Inisialisasi database SQLite
                $db = new SQLite3('monitoring.db');

                // Tampilkan ringkasan status folder
                $folder_summary = $db->querySingle('SELECT COUNT(*) AS total_files, SUM(CASE WHEN initial_checksum <> last_checksum THEN 1 ELSE 0 END) AS changed_files FROM logs', true);
                echo "<p>Total Anomali: " . $folder_summary['changed_files'] . "</p>";
                echo "<p>Total File: " . $folder_summary['total_files'] . "</p>";

                // Mengambil jumlah file yang dihapus
                $deletedFilesCount = $db->querySingle('SELECT COUNT(*) FROM deleted_files');

                // Ambil jumlah file yang ditambahkan
                $addedFilesCount = $db->querySingle('SELECT COUNT(*) FROM added_files');

                // Function to get the latest change summary
                function get_latest_change_summary($db, $limit = 5) {
                    $query = "SELECT * FROM logs ORDER BY timestamp DESC LIMIT $limit";
                    $result = $db->query($query);
                    $changes = [];
                    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                        $changes[] = $row;
                    }
                    return $changes;
                }

                // Function to get the most frequently changed files
                function get_most_changed_files($db, $limit = 5) {
                $query = "SELECT file_name, COUNT(*) as change_count FROM logs WHERE initial_checksum <> last_checksum GROUP BY file_name ORDER BY change_count DESC LIMIT $limit";
                $result = $db->query($query);
                $files = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $files[] = $row;
                }
                return $files;
                }

               // Tampilkan daftar file yang sering berubah
                $most_changed_files = get_most_changed_files($db);
                echo "<h2>Daftar File yang Sering Berubah</h2>";
                echo "<ul>";
                foreach ($most_changed_files as $file) {
                echo "<li>{$file['file_name']} ({$file['change_count']} kali)</li>";
                }
                echo "</ul>";

                // Tampilkan ringkasan status perubahan terkini
                $latest_changes = get_latest_change_summary($db); // Panggil fungsi di sini
                echo "<h2>Ringkasan Status Perubahan Terkini</h2>";
                echo "<ul>";
                foreach ($latest_changes as $change) {
                    echo "<li>{$change['timestamp']}: {$change['file_name']} diubah pada {$change['folder_path']}</li>";
                }
                echo "</ul>";

                ?>
            </div>
            <!-- Table section -->
            <table>
                <thead>
                    <tr>
                        <th>Times Stamp</th>
                        <th>Folder Path</th>
                        <th>File Name</th>
                    </tr>
                </thead>
                <tbody id="fileTableBody"> <!-- Tambahkan id untuk memperbarui tabel -->
                    <!-- Isi tabel akan diperbarui melalui JavaScript -->
                </tbody>
            </table>
            
            <!-- Menampilkan File yang Telah Diedit -->
            <div class="dashboard">
                <h2>File yang Telah Diedit</h2>
                <ul>
                    <?php
                    // Ambil data log dari database dengan filter untuk menampilkan hanya entri yang menujukkan file yang telah diedit
                    $edited_files = $db->query('SELECT DISTINCT file_name FROM logs WHERE initial_checksum <> last_checksum');
                    while ($row = $edited_files->fetchArray(SQLITE3_ASSOC)) {
                        echo  "<li>{$row['file_name']}</li>";
                    }
                    ?>
                </ul>
            </div>
            <!-- Menampilkan File yang Telah Dihapus -->
            <div class="dashboard">
                <h2>File yang Telah Dihapus</h2>
                <ul>
                <?php
        // Ambil data file yang telah dihapus dari tabel 'deleted_files' beserta timestamp
        $deleted_files = $db->query('SELECT file_name, deletion_time FROM deleted_files');
        while ($row = $deleted_files->fetchArray(SQLITE3_ASSOC)) {
            echo "<li>{$row['file_name']} (Deleted at: {$row['deletion_time']})</li>";
        }
        ?>
                 </ul>
            </div>
            
            <!-- Menampilkan File yang Telah Ditambah -->
            <div class="dashboard">
                <h2>File yang Telah Ditambah</h2>
                <ul>
                <?php
        // Ambil data file yang telah ditambah dari tabel 'added_files' beserta timestamp
        $added_files = $db->query('SELECT file_name, addition_time FROM added_files');
        while ($row = $added_files->fetchArray(SQLITE3_ASSOC)) {
            echo "<li>{$row['file_name']} (Added at: {$row['addition_time']})</li>";
        }
        ?>
                </ul>
            </div>
        </div>
        <div class="footer">
            <p>&copy; 2024 Monitoring Anomali. All rights reserved.</p>
        </div>
    </div>
</div>
<script>
    // Fetch data from PHP and initialize the pie chart
    var changedFilesCount = <?php echo $folder_summary['changed_files']; ?>;
    var deletedFilesCount = <?php echo $deletedFilesCount; ?>;
    var addedFilesCount = <?php echo $addedFilesCount; ?>;
    
    var backgroundColor = ['rgba(255, 99, 132, 0.8)','rgba(54, 162, 235, 0.8)','rgba(255, 206, 86, 0.8)','rgba(75, 192, 192, 0.8)'];
    var borderColor = ['rgba(255, 99, 132, 1)','rgba(54, 162, 235, 1)','rgba(255, 206, 86, 1)','rgba(75, 192, 192, 1)'];
    var ctx = document.getElementById('myPieChart').getContext('2d');
    var myPieChart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Changed Files', 'Deleted Files', 'Added Files'],
            datasets: [{
                label: 'File Change Summary',
                data: [changedFilesCount, deletedFilesCount, addedFilesCount],
                backgroundColor: backgroundColor,
                borderColor: borderColor,
                borderWidth: 1
            }]
        },
        plugins: [ChartDataLabels],
        options: {
            plugins: {
                legend: {
                    display: true
                },
                datalabels: {
                    color: 'white',
                    backgroundColor: borderColor,
                    borderColor: 'bolder',
                    borderWidth: 1,
                    borderRadius: 7,
                    font: {
                        weight: 'bolder',
                        size: 18
                    },
                    padding: 10
                }
            },
            onClick: function(event, chartElement) {
                if (chartElement.length > 0) {
                    var index = chartElement[0].index;
                    var label = this.data.labels[index];
                    // Kirim permintaan Ajax ke server dengan label yang diklik
                    // Anda akan mengambil data dari server dan memperbarui tabel
                    fetchDataAndUpdateTable(label);
                }
            }
        }
        
    });

    // Fungsi untuk mengambil data dari server dan memperbarui tabel
    function fetchDataAndUpdateTable(label) {
        // Kirim permintaan Ajax ke server
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (xhr.readyState === XMLHttpRequest.DONE) {
                if (xhr.status === 200) {
                    // Tangani respons dari server
                    var response = JSON.parse(xhr.responseText);
                    // Perbarui tabel dengan data yang diterima
                    updateTable(response.data);
                } else {
                    // Tangani kesalahan
                    console.error('Failed to fetch data from the server.');
                }
            }
        };
        xhr.open('GET', 'fetch_data.php?label=' + label, true);
        xhr.send();
    }

    // Fungsi untuk memperbarui tabel dengan data yang diterima
    function updateTable(data) {
        var tableBody = document.getElementById('fileTableBody');
        tableBody.innerHTML = ''; // Kosongkan isi tabel sebelum memperbarui

        // Loop melalui data dan tambahkan baris ke tabel
        data.forEach(function(row) {
            var tr = document.createElement('tr');
            var timestampTd = document.createElement('td');
            timestampTd.textContent = row.timestamp;
            var folderPathTd = document.createElement('td');
            folderPathTd.textContent = row.folder_path;
            var fileNameTd = document.createElement('td');
            fileNameTd.textContent = row.file_name;

            tr.appendChild(timestampTd);
            tr.appendChild(folderPathTd);
            tr.appendChild(fileNameTd);

            tableBody.appendChild(tr);
        });
    }
</script>

</body>
</html>
