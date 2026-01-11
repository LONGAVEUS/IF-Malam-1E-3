<?php
session_start();
require_once 'koneksi.php';

// Nama File: jadwal_rapat.php
// Deskripsi: Menampilkan jadwal rapat dalam bentuk kalender untik notulis yang login
// Dibuat oleh: Arnol Hutagalung - 3312511130
// Tanggal: 2 Desember 2025


// Proteksi login dan role notulis
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'notulis') {
    header("Location: login.php");
    exit();
}

// Ambil data user login
$user_id = $_SESSION['user_id'];

$stmtUser = $conn->prepare("SELECT full_name, role, photo FROM user WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userLogin = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

// Ambil foto profil
$foto_sekarang = $userLogin['photo'];
$path_valid = (!empty($userLogin['photo'])) ? $userLogin['photo'] : 'uploads/profile_photos/default_profile.png';
$current_photo_url = $path_valid . "?t=" . time();

// ================== LOGOUT ==================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

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

// Ambil notulen yang terkait dengan notulis yang login
$tanggal_awal = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$tanggal_akhir = date('Y-m-t', strtotime("$tahun-$bulan-01"));

// Query untuk mengambil notulen yang terkait dengan notulis yang login
$sql_notulen = "SELECT DISTINCT n.id, n.judul, n.tanggal, n.jam_mulai, n.tempat, 
                n.penanggung_jawab, n.status 
                FROM notulen n
                LEFT JOIN peserta_notulen pn ON n.id = pn.notulen_id
                WHERE (n.created_by_user_id = ? OR pn.user_id = ?)
                AND DATE(n.tanggal) BETWEEN ? AND ?
                AND n.status IN ('draft', 'sent', 'final')
                ORDER BY n.tanggal ASC";
$stmt = $conn->prepare($sql_notulen);
$stmt->bind_param("iiss", $user_id, $user_id, $tanggal_awal, $tanggal_akhir);
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

// Dashboard URL untuk notulis
$dashboard_url = "notulis.php";
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <title>Jadwal Rapat - Notulis</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="jadwal-style.css">
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-area">
        <a href="notulis.php" class="header-logo">
          <img src="if.png" alt="Politeknik Negeri Batam" />
        </a>
      </div>

      <button class="toggler" type="button">
        <div class="hamburger-icon">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </button>
    </div>

    <nav class="sidebar-nav">
      <!-- Primary top nav -->
      <ul class="nav-list primary-nav">
        <li class="nav-item">
          <a href="notulis.php" class="nav-link">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-label">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="notulen_rapat.php" class="nav-link active">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="jadwal_rapat.php" class="nav-link">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Jadwal Rapat</span>
          </a>
        </li>
      </ul>

      <!-- Secondary bottom nav -->
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="profile.php" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profil Saya</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="tamu.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Keluar</span>
          </a>
        </li>

        <!-- PROFIL LOGIN -->
        <li class="nav-item profile-user">
          <img src="<?= $current_photo_url ?>" class="profile-avatar" alt="Foto Profil" />
          <div class="profile-info">
            <span class="profile-name">
              <?= htmlspecialchars($userLogin['full_name']) ?>
            </span>
            <span class="profile-role">
              <?= ucfirst($userLogin['role']) ?>
            </span>
          </div>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="dashboard-header">
      <div>
        <h1>Jadwal Rapat - Notulis</h1>
        <p>Berikut adalah jadwal rapat yang sudah direncanakan. Klik pada teks notulen untuk melihat detail.</p>
      </div>
      <div class="time-info">
        <div class="live-clock">
          <i class="fas fa-clock"></i>
          <span id="liveTime">Loading...</span>
        </div>
        <div class="current-date" id="currentDate"></div>
      </div>
    </div>

    <div class="calendar-container">
      <div class="calendar-header">
    <div class="calendar-navigation">
        <a href="jadwal_rapat.php?bulan=<?= $bulan_sebelumnya ?>&tahun=<?= $tahun_sebelumnya ?>" 
           class="nav-btn" data-short="Prev">
            <i class="fas fa-chevron-left"></i>
            <span>Bulan Sebelumnya</span>
        </a>
        <h2><?= getNamaBulan($bulan) . ' ' . $tahun ?></h2>
        <a href="jadwal_rapat.php?bulan=<?= $bulan_selanjutnya ?>&tahun=<?= $tahun_selanjutnya ?>" 
           class="nav-btn" data-short="Next">
            <span>Bulan Selanjutnya</span>
            <i class="fas fa-chevron-right"></i>
        </a>
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
          <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="calendar-day empty"></div>
          <?php endfor; ?>

          <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
            <?php
              $tanggal_lengkap = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
              $is_today = ($tanggal_lengkap == date('Y-m-d')) ? 'today' : '';
              $has_notulen = isset($notulen_per_hari[$hari]) ? 'has-events' : '';
              
              // Tentukan class status untuk mobile
              $status_class = '';
              if (isset($notulen_per_hari[$hari])) {
                  $jumlah_notulen = count($notulen_per_hari[$hari]);
                  if ($jumlah_notulen == 1) {
                      $status_class = $notulen_per_hari[$hari][0]['status'];
                  } else {
                      $status_class = 'multi';
                  }
              }
            ?>

            <div class="calendar-day <?= $is_today ?> <?= $has_notulen ?> <?= $status_class ?>" data-date="<?= $tanggal_lengkap ?>">
              <div class="day-number"><?= $hari ?></div>

              <?php if (isset($notulen_per_hari[$hari])): ?>
                <div class="day-events">
                  <?php 
                    $jumlah_notulen = count($notulen_per_hari[$hari]);
                    if ($jumlah_notulen == 1): 
                      $notulen = $notulen_per_hari[$hari][0];
                      $status_class_link = $notulen['status'];
                      $judul_pendek = strlen($notulen['judul']) > 20 ? substr($notulen['judul'], 0, 20) . '...' : $notulen['judul'];
                  ?>
                    <a href="<?= $dashboard_url ?>?notulen_id=<?= $notulen['id'] ?>"
                      class="notulen-text-link <?= $status_class_link ?>" title="<?= htmlspecialchars($notulen['judul']) ?>">
                      <?= htmlspecialchars($judul_pendek) ?>
                    </a>
                  <?php 
                    else: 
                      $tanggal_param = date('Y-m-d', strtotime($tanggal_lengkap));
                  ?>
                    <a href="<?= $dashboard_url ?>?tanggal=<?= $tanggal_param ?>" class="notulen-count-text"
                      title="Klik untuk melihat <?= $jumlah_notulen ?> notulen pada tanggal ini">
                      <?= $jumlah_notulen ?> Notulen
                    </a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endfor; ?>

          <?php
            $total_cells = $offset + $jumlah_hari;
            $remaining_cells = 42 - $total_cells;
            if ($remaining_cells > 0):
              for ($i = 0; $i < $remaining_cells; $i++):
          ?>
            <div class="calendar-day empty"></div>
          <?php endfor; endif; ?>
        </div>
      </div>

      <div class="calendar-legend">
        <div class="legend-item">
          <span class="legend-color" style="background-color: #fff3cd;"></span>
          <span class="legend-text">Hari Ini</span>
        </div>
        <div class="legend-item">
          <span class="legend-color" style="background-color: #f39c12;"></span>
          <span class="legend-text">Draft</span>
        </div>
        <div class="legend-item">
          <span class="legend-color" style="background-color: #27ae60;"></span>
          <span class="legend-text">Terkirim</span>
        </div>
        <div class="legend-item">
          <span class="legend-color" style="background-color: #2ecc71;"></span>
          <span class="legend-text">Final</span>
        </div>
      </div>
    </div>
  </div>
  
  <script src="jadwal-rapat.js"></script>
</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
