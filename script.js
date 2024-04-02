document.addEventListener('DOMContentLoaded', function() {
    var backgroundColor = ['rgba(255, 99, 132, 0.8)','rgba(54, 162, 235, 0.8)','rgba(255, 206, 86, 0.8)','rgba(75, 192, 192, 0.8)'];
    var borderColor = ['rgba(255, 99, 132, 1)','rgba(54, 162, 235, 1)','rgba(255, 206, 86, 1)','rgba(75, 192, 192, 1)'];

    var pieChart = document.getElementById('myPieChart').getContext('2d');
    var myPieChart = new Chart(pieChart, {
        type: 'pie',
        data: {
            labels: ['Changed Files', 'Deleted Files', 'Added Files'],
            datasets: [{
                label: 'File Change Summary',
                data: [0, 0, 0], // Inisialisasi dengan nilai 0
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
                        weight: 'bolder'
                    },
                    font: {
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

    // Inisialisasi nilai awal untuk variabel changedFilesCount, deletedFilesCount, dan addedFilesCount
    var changedFilesCount = 0;
    var deletedFilesCount = 0;
    var addedFilesCount = 0;

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
                    updateTable(response.data, label); // Mengirim label sebagai parameter tambahan
                    // Perbarui nilai changedFilesCount, deletedFilesCount, dan addedFilesCount
                    changedFilesCount = response.changedFilesCount;
                    deletedFilesCount = response.deletedFilesCount;
                    addedFilesCount = response.addedFilesCount;
                    // Perbarui pie chart setelah memperbarui nilai-nilai di atas
                    updatePieChart();
                } else {
                    // Tangani kesalahan
                    console.error('Failed to fetch data from the server.');
                }
            }
        };
        xhr.open('GET', 'fetch_data.php?label=' + label, true);
        xhr.send();
    }

    // Fungsi untuk memperbarui pie chart dengan data yang diterima dari server
    function updatePieChart() {
        myPieChart.data.datasets[0].data = [changedFilesCount, deletedFilesCount, addedFilesCount];
        myPieChart.update();
    }

    // Fungsi untuk memperbarui tabel dengan data yang diterima
    function updateTable(data, label) {
        var tableBody = document.getElementById('fileTableBody');
        tableBody.innerHTML = ''; // Kosongkan isi tabel sebelum memperbarui

        // Loop melalui data dan tambahkan baris ke tabel
        data.forEach(function(row) {
            var tr = document.createElement('tr');
            var timestampTd = document.createElement('td');
            var folderPathTd = document.createElement('td');
            var fileNameTd = document.createElement('td');

            // Tentukan waktu yang sesuai berdasarkan jenis label
            var time;
            switch (label) {
                case 'Changed Files':
                    time = row.timestamp;
                    break;
                case 'Deleted Files':
                    time = row.deletion_time;
                    break;
                case 'Added Files':
                    time = row.addition_time;
                    break;
                default:
                    time = '';
                    break;
            }

            timestampTd.textContent = time;
            folderPathTd.textContent = row.folder_path;
            fileNameTd.textContent = row.file_name;

            tr.appendChild(timestampTd);
            tr.appendChild(folderPathTd);
            tr.appendChild(fileNameTd);

            tableBody.appendChild(tr);
        });
    }
});

