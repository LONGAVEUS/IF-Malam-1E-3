<?php
require_once 'koneksi.php';

// Ambil parameter bulan dan tahun dari URL, default ke bulan dan tahun saat ini
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Validasi bulan
if ($bulan < 1 || $bulan > 12) {
    $bulan = date('n');
    $tahun = date('Y');
}

// Hitung bulan sebelumnya dan selanjutnya
$bulan_sebelumnya = $bulan - 1;
$tahun_sebelumnya = $tahun;
if ($bulan_sebelumnya < 1) {
    $bulan_sebelumnya = 12;
    $tahun_sebelumnya = $tahun - 1;
}

$bulan_selanjutnya = $bulan + 1;
$tahun_selanjutnya = $tahun;
if ($bulan_selanjutnya > 12) {
    $bulan_selanjutnya = 1;
    $tahun_selanjutnya = $tahun + 1;
}

// Ambil semua notulen untuk bulan dan tahun yang dipilih (hanya yang status sent)
$tanggal_awal = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$tanggal_akhir = date('Y-m-t', strtotime("$tahun-$bulan-01"));

$sql_notulen = "SELECT id, judul, tanggal, penanggung_jawab, status FROM notulen 
                WHERE DATE(tanggal) BETWEEN ? AND ? AND status = 'sent'
                ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql_notulen);
$stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
$stmt->execute();
$result_notulen = $stmt->get_result();

$notulen_per_hari = [];
while ($notulen = $result_notulen->fetch_assoc()) {
    $hari = date('j', strtotime($notulen['tanggal']));
    if (!isset($notulen_per_hari[$hari])) {
        $notulen_per_hari[$hari] = [];
    }
    $notulen_per_hari[$hari][] = $notulen;
}
$stmt->close();

// Fungsi untuk mendapatkan nama bulan dalam Bahasa Indonesia
function getNamaBulan($bulan) {
    $nama_bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $nama_bulan[$bulan];
}

// Hitung hari dalam bulan
$jumlah_hari = date('t', strtotime("$tahun-$bulan-01"));
$hari_pertama = date('N', strtotime("$tahun-$bulan-01")); // 1=Senin, 7=Minggu

// Sesuaikan untuk kalender (Minggu = 0, Senin = 1, ..., Sabtu = 6)
$offset = $hari_pertama - 1;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <title>Jadwal Rapat - Portal Tamu</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="jadwal-style.css">
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo-area">
        <a href="tamu.php" class="header-logo">
          <img src="poltek1.png" alt="Politeknik Negeri Batam" />
        </a>
      </div>
      <button class="toggler" id="sidebarToggler">
        <span class="fas fa-chevron-left"></span>
      </button>
    </div>

    <nav class="sidebar-nav">
      <ul class="nav-list primary-nav">
        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-label">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="jadwal_rapat_tamu.php" class="nav-link active">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Jadwal Rapat</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-info-circle nav-icon"></i>
            <span class="nav-label">Informasi</span>
          </a>
        </li>
      </ul>
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profil</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="login.php" class="nav-link">
            <i class="fas fa-sign-in-alt nav-icon"></i>
            <span class="nav-label">Login</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="dashboard-header">
      <h1>Jadwal Rapat - Portal Tamu</h1>
      <p>Berikut adalah jadwal rapat yang sudah direncanakan. Anda dapat melihat detail notulen setelah rapat selesai.</p>
    </div>

    <div class="calendar-container">
      <div class="calendar-header">
        <div class="calendar-navigation">
          <a href="jadwal_rapat_tamu.php?bulan=<?php echo $bulan_sebelumnya; ?>&tahun=<?php echo $tahun_sebelumnya; ?>" class="nav-btn">
            <i class="fas fa-chevron-left"></i> Bulan Sebelumnya
          </a>
          <h2><?php echo getNamaBulan($bulan) . ' ' . $tahun; ?></h2>
          <a href="jadwal_rapat_tamu.php?bulan=<?php echo $bulan_selanjutnya; ?>&tahun=<?php echo $tahun_selanjutnya; ?>" class="nav-btn">
            Bulan Selanjutnya <i class="fas fa-chevron-right"></i>
          </a>
        </div>
        <div class="calendar-actions">
          <a href="jadwal_rapat_tamu.php" class="btn btn-today">Hari Ini</a>
        </div>
      </div>

      <div class="calendar">
        <div class="calendar-weekdays">
          <div class="weekday">Senin</div>
          <div class="weekday">Selasa</div>
          <div class="weekday">Rabu</div>
          <div class="weekday">Kamis</div>
          <div class="weekday">Jumat</div>
          <div class="weekday">Sabtu</div>
          <div class="weekday">Minggu</div>
        </div>

        <div class="calendar-days">
          <?php
          // Tambah sel kosong untuk hari sebelum bulan dimulai
          for ($i = 0; $i < $offset; $i++) {
              echo '<div class="calendar-day empty"></div>';
          }

          // Tampilkan hari dalam bulan
          for ($hari = 1; $hari <= $jumlah_hari; $hari++) {
              $tanggal_lengkap = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
              $is_today = ($tanggal_lengkap == date('Y-m-d')) ? 'today' : '';
              $has_notulen = isset($notulen_per_hari[$hari]) ? 'has-events' : '';
              
              echo '<div class="calendar-day ' . $is_today . ' ' . $has_notulen . '" data-date="' . $tanggal_lengkap . '">';
              echo '<div class="day-number">' . $hari . '</div>';
              
              if (isset($notulen_per_hari[$hari])) {
                  echo '<div class="day-events">';
                  foreach ($notulen_per_hari[$hari] as $notulen) {
                      $status_class = 'sent'; // Untuk tamu, hanya menampilkan yang sent
                      echo '<div class="event-item ' . $status_class . '" data-id="' . $notulen['id'] . '">';
                      echo '<div class="event-title">' . htmlspecialchars($notulen['judul']) . '</div>';
                      echo '<div class="event-time">' . date('H:i', strtotime($notulen['tanggal'])) . '</div>';
                      echo '</div>';
                  }
                  echo '</div>';
              }
              
              echo '</div>';
          }

          // Tambah sel kosong untuk hari setelah bulan berakhir
          $total_cells = $offset + $jumlah_hari;
          $remaining_cells = 42 - $total_cells; // 6 baris x 7 hari = 42 sel
          if ($remaining_cells > 0) {
              for ($i = 0; $i < $remaining_cells; $i++) {
                  echo '<div class="calendar-day empty"></div>';
              }
          }
          ?>
        </div>
      </div>
      
      <div class="calendar-legend">
        <div class="legend-item">
          <span class="legend-color" style="background-color: #fff3cd;"></span>
          <span class="legend-text">Hari Ini</span>
        </div>
        <div class="legend-item">
          <span class="legend-color" style="background-color: #27ae60;"></span>
          <span class="legend-text">Rapat Terjadwal</span>
        </div>
      </div>
    </div>

    <!-- Modal Detail Notulen -->
    <div class="modal-overlay" id="detailModal">
      <div class="modal-container">
        <div class="modal-header">
          <h2 id="modalTitle">Detail Notulen</h2>
          <button class="modal-close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div id="modalContent">
            <!-- Konten akan diisi oleh JavaScript -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="jadwal-script.js"></script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}