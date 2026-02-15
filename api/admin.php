<?php
session_start();
require_once 'koneksi.php'; 

// Proteksi login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Proteksi role admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// ================== DATA USER LOGIN ==================
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

//foto
$foto_sekarang = $userLogin['photo'];
$path_valid = (!empty($userLogin['photo'])) ? $userLogin['photo'] : 'uploads/profile_photos/default_profile.png';
$current_photo_url = $path_valid . "?t=" . time();

// ================== LOGOUT ==================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

// 1. Ambil Total Pengguna
$sql_user_count = "SELECT COUNT(user_id) AS total FROM user WHERE is_active = 1";
$result_user_count = $conn->query($sql_user_count);
$total_pengguna = $result_user_count ? $result_user_count->fetch_assoc()['total'] : 0;

// 2. Ambil Total Notulen Aktif (semua status)
$sql_notulen_aktif = "SELECT COUNT(id) AS total FROM notulen";
$result_notulen_aktif = $conn->query($sql_notulen_aktif);
$total_notulen_aktif = $result_notulen_aktif ? $result_notulen_aktif->fetch_assoc()['total'] : 0;

// 3. Ambil Total Notulen Hari Ini (tanggal = hari ini)
$today = date('Y-m-d');
$sql_notulen_hari_ini = "SELECT COUNT(id) AS total FROM notulen WHERE DATE(tanggal) = '$today'";
$result_notulen_hari_ini = $conn->query($sql_notulen_hari_ini);
$notulen_hari_ini = $result_notulen_hari_ini ? $result_notulen_hari_ini->fetch_assoc()['total'] : 0;

// 4. Ambil statistik jumlah pengguna per jurusan untuk diagram batang
$sql_unit = "SELECT jurusan, COUNT(*) as total FROM user GROUP BY jurusan ORDER BY total DESC";
$result_unit = $conn->query($sql_unit);

// 5. Ambil statistik notulen untuk diagram pie
$sql_notulen_stats = "SELECT 
    SUM(CASE WHEN status = 'final' THEN 1 ELSE 0 END) as final,
    SUM(CASE WHEN status IN ('draft', 'sent') THEN 1 ELSE 0 END) as draft
    FROM notulen";
$result_notulen_stats = $conn->query($sql_notulen_stats);
$notulen_stats = $result_notulen_stats ? $result_notulen_stats->fetch_assoc() : ['final' => 0, 'draft' => 0];

// Hitung persentase untuk diagram pie
$total_notulen_all = $notulen_stats['final'] + $notulen_stats['draft'];
$persentase_final = $total_notulen_all > 0 ? ($notulen_stats['final'] / $total_notulen_all) * 100 : 0;
$persentase_draft = $total_notulen_all > 0 ? ($notulen_stats['draft'] / $total_notulen_all) * 100 : 0;

// Persiapkan data jurusan untuk diagram batang
$jurusan_data = [];
$max_jurusan = 0; // Untuk skala diagram
if ($result_unit && $result_unit->num_rows > 0) {
    while ($row = $result_unit->fetch_assoc()) {
        $nama_jurusan = !empty($row['jurusan']) ? $row['jurusan'] : 'Umum/Lainnya';
        $total = $row['total'];
        $jurusan_data[] = [
            'nama' => $nama_jurusan,
            'total' => $total
        ];
        if ($total > $max_jurusan) {
            $max_jurusan = $total;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Dashboard Admin</title>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- External CSS -->
  <link rel="stylesheet" href="admin.css">
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo-area">
        <a href="#" class="header-logo">
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
      <!-- Primary top nav -->
      <ul class="nav-list primary-nav">
        <li class="nav-item">
          <a href="admin.php" class="nav-link active">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-label">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="jadwal_rapat.php" class="nav-link">
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
          <!-- Logout menggunakan PHP - tidak perlu file terpisah -->
          <a href="admin.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Keluar</span>
          </a>
        </li>

        <!-- PROFIL LOGIN -->
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

  <!-- Main Content -->
  <div class="main-content">
    <div class="dashboard-header">
      <h1>Dashboard Admin</h1>
      <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
    </div>

    <div class="user-stats-grid">
      <div class="user-count-box">
        <div class="count-number"><?php echo $total_pengguna; ?></div>
        <div class="count-label">Total Pengguna</div>
      </div>
      <div class="user-count-box active-schedule">
        <div class="count-number"><?php echo $total_notulen_aktif; ?></div>
        <div class="count-label">Notulen Aktif</div>
      </div>
      <div class="user-count-box daily-notes">
        <div class="count-number"><?php echo $notulen_hari_ini; ?></div>
        <div class="count-label">Notulen Hari Ini</div>
      </div>
    </div>

    <div class="charts-container">
      <!-- Diagram Batang untuk Statistik Pengguna per Jurusan -->
      <div class="chart-card">
        <h3><i class="fas fa-university"></i> Statistik Pengguna Per Jurusan</h3>
        <div class="bar-chart-horizontal">
          <?php if (!empty($jurusan_data)): ?>
            <?php foreach ($jurusan_data as $jurusan): ?>
              <?php 
              // Hitung persentase untuk lebar bar
              $width_percent = $max_jurusan > 0 ? ($jurusan['total'] / $max_jurusan) * 100 : 0;
              ?>
              <div class="bar-row">
                <div class="bar-label"><?php echo htmlspecialchars($jurusan['nama']); ?></div>
                <div class="bar-container">
                  <div class="bar-horizontal" style="width: <?php echo $width_percent; ?>%">
                    <span class="bar-value"><?php echo $jurusan['total']; ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="no-data">Tidak ada data jurusan</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Diagram Pie untuk Statistik Notulen -->
      <div class="chart-card">
        <h3><i class="fas fa-file-alt"></i> Status Notulen</h3>
        <div class="pie-chart-container">
          <!-- Diagram Pie dengan CSS conic-gradient (tanpa JavaScript) -->
          <div class="pie-chart" style="
            background: conic-gradient(
              #4CAF50 0% <?php echo $persentase_final; ?>%,
              #FF9800 <?php echo $persentase_final; ?>% 100%
            );
          "></div>
          
          <div class="pie-legend">
            <div class="legend-item">
              <span class="legend-color final"></span>
              <span class="legend-text">Final: <?php echo $notulen_stats['final']; ?> (<?php echo round($persentase_final, 1); ?>%)</span>
            </div>
            <div class="legend-item">
              <span class="legend-color draft"></span>
              <span class="legend-text">Draft: <?php echo $notulen_stats['draft']; ?> (<?php echo round($persentase_draft, 1); ?>%)</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- External JavaScript -->
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const sidebar = document.querySelector(".sidebar");
      const toggler = document.querySelector(".toggler");
      const sidebarNav = document.querySelector(".sidebar-nav");

      if (!sidebar || !toggler || !sidebarNav) return;

      toggler.addEventListener("click", function (e) {
        e.stopPropagation();

        /* ================= MOBILE ONLY ================= */
        if (window.innerWidth <= 768) {
          sidebarNav.classList.toggle("active");
        }
      });

      /* ================= CLOSE DROPDOWN SAAT KLIK DI LUAR (MOBILE) ================= */
      document.addEventListener("click", function (e) {
        if (window.innerWidth <= 768) {
          if (!sidebar.contains(e.target)) {
            sidebarNav.classList.remove("active");
          }
        }
      });

      /* ================= RESET SAAT RESIZE KE DESKTOP ================= */
      window.addEventListener("resize", function () {
        if (window.innerWidth > 768) {
          sidebarNav.classList.remove("active");
        }
      });
    });
  </script>

</body>

</html>
<?php
// Tutup koneksi di akhir file
if (isset($conn)) {
    $conn->close();
}
?>
