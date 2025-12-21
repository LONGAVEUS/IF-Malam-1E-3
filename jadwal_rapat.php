<?php
session_start();
require_once 'koneksi.php';

/* ==================================================
   1. CEK LOGIN
================================================== */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

/* ==================================================
   2. HANDLE LOGOUT
================================================== */
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
    header("Location: login.php");
    exit();
}

/* ==================================================
   3. AMBIL DATA USER LOGIN
================================================== */
$user_id = $_SESSION['user_id'];

$stmtUser = $conn->prepare("
    SELECT full_name, role, photo
    FROM user
    WHERE user_id = ?
");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userLogin = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

/* ==================================================
   4. VALIDASI USER
================================================== */
if (!$userLogin) {
    // Jika user tidak ditemukan
    session_destroy();
    header("Location: login.php");
    exit();
}

$role = $userLogin['role'];

/* ==================================================
   5. FOTO PROFIL (FALLBACK)
================================================== */
$fotoProfil = (!empty($userLogin['photo']) && file_exists($userLogin['photo']))
    ? $userLogin['photo']
    : 'uploads/profile_photos/default_profile.png';

/* ==================================================
   6. DASHBOARD BERDASARKAN ROLE
================================================== */
$dashboard = match ($role) {
    'admin'   => 'admin.php',
    'notulis' => 'notulis.php',
    default   => 'tamu.php',
};


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

// Ambil semua notulen untuk bulan dan tahun yang dipilih
$tanggal_awal = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$tanggal_akhir = date('Y-m-t', strtotime("$tahun-$bulan-01"));

$sql_notulen = "SELECT id, judul, tanggal, penanggung_jawab, status FROM isinotulen 
                WHERE DATE(tanggal) BETWEEN ? AND ? 
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
  <title>Jadwal Rapat</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="jadwal-style.css">
</head>

<body>
  <!-- ================= SIDEBAR ================= -->
  <div class="sidebar">

    <div class="sidebar-header">
            <div class="logo-area">
                <a href="#" class="header-logo">
                    <!-- Ganti dengan path logo yang benar -->
                    <img src="poltek1.png" alt="Politeknik Negeri Batam" />
                </a>
            </div>
            <button class="toggler">
                <span class="fas fa-chevron-left"></span>
            </button>
        </div>

    <nav class="sidebar-nav">

      <!-- ===== PRIMARY NAV ===== -->
      <ul class="nav-list primary-nav">

        <li class="nav-item">
          <a href="<?= $dashboard ?>" class="nav-link">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-label">Dashboard</span>
          </a>
        </li>

        <?php if ($role === 'admin'): ?>

        <li class="nav-item">
          <a href="jadwal_rapat.php" class="nav-link">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Jadwal Rapat</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="notulen_list_admin.php" class="nav-link">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="user_management.php" class="nav-link">
            <i class="fas fa-users nav-icon"></i>
            <span class="nav-label">Daftar Pengguna</span>
          </a>
        </li>

        <?php elseif ($role === 'notulis'): ?>

        <li class="nav-item">
          <a href="notulis.php" class="nav-link">
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

        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="fas fa-bell nav-icon"></i>
            <span class="nav-label">Notifikasi</span>
          </a>
        </li>

        <?php elseif ($role === 'tamu'): ?>

        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="jadwal_rapat_tamu.php" class="nav-link">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Jadwal Rapat</span>
          </a>
        </li>

        <?php endif; ?>

      </ul>

      <!-- ===== SECONDARY NAV ===== -->
      <ul class="nav-list secondary-nav">

        <li class="nav-item">
          <a href="profile.php" class="nav-link active">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profil Saya</span>
          </a>
        </li>

        <li class="nav-item">
          <a href="login.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Keluar</span>
          </a>
        </li>

        <!-- PROFIL LOGIN -->
        <li class="nav-item profile-user">
          <img src="<?= $fotoProfil ?>" class="profile-avatar" alt="Foto Profil">

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
  <div class="main-content" id="mainContent">
    <div class="dashboard-header">
      <h1>Jadwal Rapat</h1>
      <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
    </div>

    <div class="calendar-container">
      <div class="calendar-header">
        <div class="calendar-navigation">
          <a href="jadwal_rapat.php?bulan=<?php echo $bulan_sebelumnya; ?>&tahun=<?php echo $tahun_sebelumnya; ?>"
            class="nav-btn">
            <i class="fas fa-chevron-left"></i> Bulan Sebelumnya
          </a>
          <h2><?php echo getNamaBulan($bulan) . ' ' . $tahun; ?></h2>
          <a href="jadwal_rapat.php?bulan=<?php echo $bulan_selanjutnya; ?>&tahun=<?php echo $tahun_selanjutnya; ?>"
            class="nav-btn">
            Bulan Selanjutnya <i class="fas fa-chevron-right"></i>
          </a>
        </div>
        <div class="calendar-actions">
          <a href="jadwal_rapat.php" class="btn btn-today">Hari Ini</a>
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
                      $status_class = $notulen['status'] === 'draft' ? 'draft' : 'sent';
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
?>
