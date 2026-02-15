<?php
session_start();
require_once 'koneksi.php';

// Nama File: jadwal_rapat.php
// Deskripsi: Menampilkan jadwal rapat dalam bentuk kalender untuk notulis dan admin
// Dibuat oleh: Arnol Hutagalung - 3312511130
// Tanggal: 2 Desember 2025
// Modifikasi: Dapat diakses oleh admin dengan view semua notulen

/******************************************************************************
 * PROTEKSI AKSES - MENJAMIN HANYA NOTULIS DAN ADMIN YANG BISA AKSES
 ******************************************************************************/
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] !== 'notulis' && $_SESSION['role'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

// Ambil data user yang sedang login dari session
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

/******************************************************************************
 * AMBIL DATA USER DARI DATABASE UNTUK DITAMPILKAN DI SIDEBAR
 ******************************************************************************/
$stmtUser = $conn->prepare("SELECT full_name, role, photo FROM user WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userLogin = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

// Siapkan URL foto profil dengan timestamp untuk menghindari cache
$foto_sekarang = $userLogin['photo'];
$path_valid = (!empty($userLogin['photo'])) ? $userLogin['photo'] : 'uploads/profile_photos/default_profile.png';
$current_photo_url = $path_valid . "?t=" . time();

/******************************************************************************
 * LOGOUT - HAPUS SESSION DAN REDIRECT KE LOGIN
 ******************************************************************************/
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

/******************************************************************************
 * PENGATURAN BULAN DAN TAHUN UNTUK KALENDER
 * 
 * Logika: 
 * 1. Ambil parameter bulan dan tahun dari URL (GET)
 * 2. Jika tidak ada, gunakan bulan dan tahun saat ini
 * 3. Validasi agar bulan selalu antara 1-12
 ******************************************************************************/
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Validasi bulan - jika tidak valid, reset ke bulan saat ini
if ($bulan < 1 || $bulan > 12) {
    $bulan = date('n');
    $tahun = date('Y');
}

/******************************************************************************
 * HITUNG BULAN SEBELUMNYA DAN SELANJUTNYA UNTUK NAVIGASI KALENDER
 * 
 * Contoh: 
 * - Bulan Januari (1) -> Sebelumnya: Desember (12) tahun sebelumnya
 * - Bulan Desember (12) -> Selanjutnya: Januari (1) tahun berikutnya
 ******************************************************************************/
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

/******************************************************************************
 * TENTUKAN RENTANG TANGGAL UNTUK FILTER NOTULEN
 * 
 * Format: 
 * - Tanggal awal: Hari pertama bulan (contoh: 2025-12-01)
 * - Tanggal akhir: Hari terakhir bulan (contoh: 2025-12-31)
 ******************************************************************************/
$tanggal_awal = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$tanggal_akhir = date('Y-m-t', strtotime("$tahun-$bulan-01"));

/******************************************************************************
 * AMBIL DATA NOTULEN BERDASARKAN ROLE USER
 * 
 * Logika perbedaan query:
 * 1. ADMIN: Ambil SEMUA notulen aktif tanpa batasan notulis
 * 2. NOTULIS: Ambil notulen yang:
 *    a. Dibuat oleh user tersebut (created_by_user_id = ?)
 *    b. Atau user tersebut adalah peserta (peserta_notulen.user_id = ?)
 * 
 * Status notulen yang diambil: draft, sent, final
 * Urutkan berdasarkan tanggal dan jam mulai
 ******************************************************************************/
if ($user_role === 'admin') {
    // QUERY UNTUK ADMIN - SEMUA NOTULEN AKTIF
    $sql_notulen = "SELECT DISTINCT n.id, n.judul, n.tanggal, n.jam_mulai, n.tempat, 
                    n.penanggung_jawab, n.status, u.full_name as notulis_name
                    FROM notulen n
                    LEFT JOIN user u ON n.created_by_user_id = u.user_id
                    WHERE DATE(n.tanggal) BETWEEN ? AND ?
                    AND n.status IN ('draft', 'sent', 'final')
                    ORDER BY n.tanggal ASC, n.jam_mulai ASC";
    $stmt = $conn->prepare($sql_notulen);
    $stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
} else {
    // QUERY UNTUK NOTULIS - HANYA NOTULEN YANG TERKAIT
    $sql_notulen = "SELECT DISTINCT n.id, n.judul, n.tanggal, n.jam_mulai, n.tempat, 
                    n.penanggung_jawab, n.status, u.full_name as notulis_name
                    FROM notulen n
                    LEFT JOIN peserta_notulen pn ON n.id = pn.notulen_id
                    LEFT JOIN user u ON n.created_by_user_id = u.user_id
                    WHERE (n.created_by_user_id = ? OR pn.user_id = ?)
                    AND DATE(n.tanggal) BETWEEN ? AND ?
                    AND n.status IN ('draft', 'sent', 'final')
                    ORDER BY n.tanggal ASC, n.jam_mulai ASC";
    $stmt = $conn->prepare($sql_notulen);
    $stmt->bind_param("iiss", $user_id, $user_id, $tanggal_awal, $tanggal_akhir);
}

// Eksekusi query dan ambil hasilnya
$stmt->execute();
$result_notulen = $stmt->get_result();

/******************************************************************************
 * KELOMPOKKAN NOTULEN BERDASARKAN HARI UNTUK KALENDER
 * 
 * Struktur array: 
 * $notulen_per_hari[hari] = array(
 *     [0] => array(id, judul, tanggal, ...),
 *     [1] => array(id, judul, tanggal, ...)
 * )
 ******************************************************************************/
$notulen_per_hari = [];
$total_notulen_aktif = 0;
while ($notulen = $result_notulen->fetch_assoc()) {
    $hari = date('j', strtotime($notulen['tanggal'])); // Ambil tanggal (1-31)
    if (!isset($notulen_per_hari[$hari])) {
        $notulen_per_hari[$hari] = [];
    }
    $notulen_per_hari[$hari][] = $notulen;
    $total_notulen_aktif++;
}
$stmt->close();

/******************************************************************************
 * FUNGSI: GET NAMA BULAN DALAM BAHASA INDONESIA
 * 
 * Parameter: $bulan (angka 1-12)
 * Return: Nama bulan dalam Bahasa Indonesia
 ******************************************************************************/
function getNamaBulan($bulan) {
    $nama_bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $nama_bulan[$bulan];
}

/******************************************************************************
 * HITUNG INFORMASI KALENDER
 * 
 * 1. Jumlah hari dalam bulan (28-31)
 * 2. Hari pertama bulan (1=Senin, 7=Minggu)
 * 3. Offset untuk mengatur posisi hari pertama di grid kalender
 ******************************************************************************/
$jumlah_hari = date('t', strtotime("$tahun-$bulan-01")); // t = jumlah hari dalam bulan
$hari_pertama = date('N', strtotime("$tahun-$bulan-01")); // N = hari (1=Senin, 7=Minggu)

// Sesuaikan offset untuk kalender (Minggu = 0, Senin = 1, ..., Sabtu = 6)
$offset = $hari_pertama - 1;

/******************************************************************************
 * TENTUKAN URL DASHBOARD DAN JUDUL HALAMAN BERDASARKAN ROLE
 * 
 * Admin: arahkan ke admin.php
 * Notulis: arahkan ke notulis.php
 ******************************************************************************/
if ($user_role === 'admin') {
    $dashboard_url = "admin.php";
} else {
    $dashboard_url = "notulis.php";
}

// Atur judul halaman dan teks sambutan berdasarkan role
$page_title = $user_role === 'admin' ? "Jadwal Rapat - Admin" : "Jadwal Rapat - Notulis";
$welcome_text = $user_role === 'admin' 
    ? "Berikut adalah jadwal rapat semua notulen aktif. Klik pada teks notulen untuk melihat detail." 
    : "Berikut adalah jadwal rapat yang terkait dengan Anda. Klik pada teks notulen untuk melihat detail.";
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <title><?php echo $page_title; ?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="jadwal-style.css">
</head>

<body>
  <!-- 
    SIDEBAR NAVIGASI 
    Menampilkan menu berbeda untuk admin dan notulis
    Berisi: 
    1. Logo 
    2. Menu utama berdasarkan role
    3. Menu sekunder (profil, logout)
    4. Profil user yang login
  -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-area">
        <a href="<?php echo $dashboard_url; ?>" class="header-logo">
          <img src="if.png" alt="Politeknik Negeri Batam">
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
      <!-- 
        MENU UTAMA - BERBEDA UNTUK ADMIN DAN NOTULIS 
        Admin: Dashboard, Jadwal Rapat, Daftar Pengguna
        Notulis: Dashboard, Notulen Rapat, Jadwal Rapat
      -->
      <ul class="nav-list primary-nav">
        <?php if ($user_role === 'admin'): ?>
          <!-- Menu untuk Admin -->
          <li class="nav-item">
            <a href="admin.php" class="nav-link">
              <i class="fas fa-th-large nav-icon"></i>
              <span class="nav-label">Dashboard</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="jadwal_rapat.php" class="nav-link active">
              <i class="fas fa-calendar-alt nav-icon"></i>
              <span class="nav-label">Jadwal Rapat</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="user_management.php" class="nav-link">
              <i class="fas fa-users nav-icon"></i>
              <span class="nav-label">Daftar Pengguna</span>
            </a>
          </li>
        <?php else: ?>
          <!-- Menu untuk Notulis -->
          <li class="nav-item">
            <a href="notulis.php" class="nav-link">
              <i class="fas fa-th-large nav-icon"></i>
              <span class="nav-label">Dashboard</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="notulen_rapat.php" class="nav-link">
              <i class="fas fa-file-alt nav-icon"></i>
              <span class="nav-label">Notulen Rapat</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="jadwal_rapat.php" class="nav-link active">
              <i class="fas fa-calendar-alt nav-icon"></i>
              <span class="nav-label">Jadwal Rapat</span>
            </a>
          </li>
        <?php endif; ?>
      </ul>

      <!-- 
        MENU SEKUNDER - SAMA UNTUK SEMUA ROLE 
        Berisi: Profil Saya, Logout, dan tampilan profil user
      -->
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="profile.php" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profil Saya</span>
          </a>
        </li>
        <li class="nav-item">
          <!-- Logout menggunakan parameter GET action=logout -->
          <a href="<?php echo $dashboard_url; ?>?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Keluar</span>
          </a>
        </li>

        <!-- TAMPILAN PROFIL USER YANG SEDANG LOGIN -->
        <li class="nav-item profile-user">
          <img src="<?php echo $current_photo_url; ?>?v=<?php echo time(); ?>" class="profile-avatar">
          <div class="profile-info">
            <span class="profile-name">
              <?= htmlspecialchars($userLogin['full_name']); ?>
            </span>
            <span class="profile-role">
              <?= ucfirst($userLogin['role']); ?>
            </span>
          </div>
        </li>
      </ul>
    </nav>
  </div>

  <!-- 
    KONTEN UTAMA - KALENDER JADWAL RAPAT
    Berisi:
    1. Header dengan judul dan statistik (untuk admin)
    2. Navigasi kalender (bulan sebelumnya/selanjutnya)
    3. Grid kalender dengan hari dan notulen
    4. Legend warna untuk status notulen
  -->
  <div class="main-content">
    <div class="dashboard-header">
      <div>
        <h1><?php echo $page_title; ?></h1>
        <p><?php echo $welcome_text; ?></p>
        <?php if ($user_role === 'admin'): ?>
          <!-- STATISTIK HANYA UNTUK ADMIN -->
          <div class="admin-stats">
            <span class="stat-badge">
              <i class="fas fa-file-alt"></i> Total Notulen Aktif: <?php echo $total_notulen_aktif; ?>
            </span>
            <span class="stat-badge">
              <i class="fas fa-calendar-check"></i> Periode: <?= getNamaBulan($bulan) . ' ' . $tahun ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
      <div class="time-info">
        <!-- JAM DAN TANGGAL LIVE (DIUPDATE DENGAN JAVASCRIPT) -->
        <div class="live-clock">
          <i class="fas fa-clock"></i>
          <span id="liveTime">Loading...</span>
        </div>
        <div class="current-date" id="currentDate"></div>
      </div>
    </div>

    <div class="calendar-container">
      <!-- NAVIGASI KALENDER - BULAN SEBELUMNYA/SELANJUTNYA -->
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

      <!-- 
        KALENDER GRID
        Struktur:
        1. Baris header hari (Senin-Minggu)
        2. Sel kosong untuk offset (sesuai hari pertama bulan)
        3. Sel untuk setiap hari (1-31)
        4. Sel kosong untuk melengkapi grid 6x7
      -->
      <div class="calendar">
        <!-- HEADER HARI DALAM MINGGU -->
        <div class="calendar-weekdays">
          <div class="weekday">Senin</div>
          <div class="weekday">Selasa</div>
          <div class="weekday">Rabu</div>
          <div class="weekday">Kamis</div>
          <div class="weekday">Jumat</div>
          <div class="weekday">Sabtu</div>
          <div class="weekday">Minggu</div>
        </div>

        <!-- GRID HARI DALAM BULAN -->
        <div class="calendar-days">
          <!-- SEL KOSONG UNTUK OFFSET (HARI SEBELUM BULAN DIMULAI) -->
          <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="calendar-day empty"></div>
          <?php endfor; ?>

          <!-- 
            LOOP UNTUK SETIAP HARI DALAM BULAN (1-31)
            Logika per sel:
            1. Tentukan apakah hari ini (today)
            2. Tentukan apakah ada notulen (has-events)
            3. Tentukan class status untuk mobile
          -->
          <?php for ($hari = 1; $hari <= $jumlah_hari; $hari++): ?>
            <?php
              // Format tanggal lengkap untuk atribut data-date
              $tanggal_lengkap = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
              
              // Tentukan class CSS untuk sel
              $is_today = ($tanggal_lengkap == date('Y-m-d')) ? 'today' : '';
              $has_notulen = isset($notulen_per_hari[$hari]) ? 'has-events' : '';
              
              // Tentukan class status untuk mobile
              $status_class = '';
              if (isset($notulen_per_hari[$hari])) {
                  $jumlah_notulen = count($notulen_per_hari[$hari]);
                  if ($jumlah_notulen == 1) {
                      // Jika hanya satu notulen, gunakan statusnya sebagai class
                      $status_class = $notulen_per_hari[$hari][0]['status'];
                  } else {
                      // Jika multiple notulen, gunakan class 'multi'
                      $status_class = 'multi';
                  }
              }
            ?>

            <!-- SEL KALENDER UNTUK SETIAP HARI -->
            <div class="calendar-day <?= $is_today ?> <?= $has_notulen ?> <?= $status_class ?>" data-date="<?= $tanggal_lengkap ?>">
              <div class="day-number"><?= $hari ?></div>

              <!-- TAMPILKAN NOTULEN JIKA ADA DI HARI INI -->
              <?php if (isset($notulen_per_hari[$hari])): ?>
                <div class="day-events">
                  <?php 
                    $jumlah_notulen = count($notulen_per_hari[$hari]);
                    
                    // JIKA HANYA 1 NOTULEN DI HARI INI
                    if ($jumlah_notulen == 1): 
                      $notulen = $notulen_per_hari[$hari][0];
                      $status_class_link = $notulen['status'];
                      
                      // Potong judul jika terlalu panjang
                      $judul_pendek = strlen($notulen['judul']) > 20 ? 
                          substr($notulen['judul'], 0, 20) . '...' : $notulen['judul'];
                      
                      // Siapkan tooltip dengan info lengkap
                      $tooltip_title = htmlspecialchars($notulen['judul']);
                      if ($user_role === 'admin' && !empty($notulen['notulis_name'])) {
                          $tooltip_title .= " (Notulis: " . htmlspecialchars($notulen['notulis_name']) . ")";
                      }
                  ?>
                    <!-- LINK KE DETAIL NOTULEN -->
                    <a href="<?= $dashboard_url ?>?notulen_id=<?= $notulen['id'] ?>"
                      class="notulen-text-link <?= $status_class_link ?>" 
                      title="<?= $tooltip_title ?>">
                      <?= htmlspecialchars($judul_pendek) ?>
                      
                      <!-- BADGE NOTULIS UNTUK ADMIN -->
                      <?php if ($user_role === 'admin' && $jumlah_notulen == 1): ?>
                        <span class="notulis-badge"><?= substr($notulen['notulis_name'], 0, 3) ?>...</span>
                      <?php endif; ?>
                    </a>
                  <?php 
                    // JIKA LEBIH DARI 1 NOTULEN DI HARI INI
                    else: 
                      $tanggal_param = date('Y-m-d', strtotime($tanggal_lengkap));
                      
                      // HITUNG JUMLAH NOTULEN PER STATUS UNTUK TOOLTIP
                      $status_counts = [];
                      foreach ($notulen_per_hari[$hari] as $n) {
                          $status_counts[$n['status']] = isset($status_counts[$n['status']]) ? 
                              $status_counts[$n['status']] + 1 : 1;
                      }
                      
                      // FORMAT TOOLTIP: "3 notulen: 1 draft, 2 sent"
                      $tooltip = "$jumlah_notulen notulen: ";
                      foreach ($status_counts as $status => $count) {
                          $tooltip .= "$count $status, ";
                      }
                      $tooltip = rtrim($tooltip, ', ');
                  ?>
                    <!-- LINK KE LIST NOTULEN PER TANGGAL -->
                    <a href="<?= $dashboard_url ?>?tanggal=<?= $tanggal_param ?>" 
                      class="notulen-count-text"
                      title="<?= $tooltip ?>">
                      <?= $jumlah_notulen ?> Notulen
                      
                      <!-- ICON MULTIPLE UNTUK ADMIN -->
                      <?php if ($user_role === 'admin'): ?>
                        <span class="multi-badge"><i class="fas fa-users"></i></span>
                      <?php endif; ?>
                    </a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endfor; ?>

          <!-- 
            SEL KOSONG UNTUK MELENGKAPI GRID 6x7 (42 SEL)
            Menghitung sisa sel setelah hari-hari bulan
          -->
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

      <!-- 
        LEGENDA WARNA STATUS NOTULEN
        Menjelaskan arti warna setiap status
      -->
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
        <?php if ($user_role === 'admin'): ?>
        <div class="legend-item">
          <span class="legend-color" style="background-color: #9b59b6;"></span>
          <span class="legend-text">Multiple Notulen</span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <!-- JAVASCRIPT UNTUK LIVE CLOCK DAN FUNGSI TAMBAHAN -->
  <script src="jadwal-rapat.js"></script>
</body>
</html>

<?php
/******************************************************************************
 * TUTUP KONEKSI DATABASE UNTUK MENGHEMAT RESOURCE
 ******************************************************************************/
if (isset($conn)) {
    $conn->close();
}
