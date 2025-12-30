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

// Foto profil fallback
$fotoProfil = (!empty($userLogin['photo']) && file_exists($userLogin['photo']))
    ? $userLogin['photo']
    : 'uploads/profile_photos/default_profile.png';

// ================== LOGOUT ==================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}


// 1. Ambil Total Pengguna (dari tabel 'pengguna')
$sql_user_count = "SELECT COUNT(user_id) AS total FROM user WHERE is_active = 1";
$result_user_count = $conn->query($sql_user_count);
$total_pengguna = $result_user_count ? $result_user_count->fetch_assoc()['total'] : 0;

// 2. Ambil Total Jadwal Aktif (Asumsi: tabel 'jadwal', kolom 'status')
$sql_jadwal_aktif = "SELECT COUNT(id) AS total FROM jadwal WHERE status = 'Aktif'";
$result_jadwal_aktif = $conn->query($sql_jadwal_aktif);
$total_jadwal_aktif = $result_jadwal_aktif ? $result_jadwal_aktif->fetch_assoc()['total'] : 0;

// 3. Ambil Total Notulen Hari Ini (Asumsi: tabel 'notulen', kolom 'tanggal')
$today = date('Y-m-d');
$sql_notulen_hari_ini = "SELECT COUNT(id) AS total FROM notulen WHERE DATE(tanggal) = '$today'";
$result_notulen_hari_ini = $conn->query($sql_notulen_hari_ini);
$notulen_hari_ini = $result_notulen_hari_ini ? $result_notulen_hari_ini->fetch_assoc()['total'] : 0;

// 4. Ambil statistik jumlah pengguna per jurusan
$sql_unit = "SELECT jurusan, COUNT(*) as total FROM user GROUP BY jurusan ORDER BY total DESC";
$result_unit = $conn->query($sql_unit);
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
          <a href="#" class="nav-link">
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
          <img src="<?= $fotoProfil; ?>" class="profile-avatar">

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
        <div class="count-number"><?php echo $total_jadwal_aktif; ?></div>
        <div class="count-label">Jadwal Aktif</div>
      </div>
      <div class="user-count-box daily-notes">
        <div class="count-number"><?php echo $notulen_hari_ini; ?></div>
        <div class="count-label">Notulen Hari Ini</div>
      </div>
    </div>

    <div class="users-section-grid">
      <div class="card user-list-card">
        <h3><i class="fas fa-university"></i> Statistik Pengguna Per Unit</h3>
        <div class="user-list" id="user-data-list">
          <?php
          if ($result_unit && $result_unit->num_rows > 0) {
            while ($row = $result_unit->fetch_assoc()) {
              $nama_jurusan = !empty($row['jurusan']) ? $row['jurusan'] : 'Umum/Lainnya';
            ?>
          <div class='user-item'>
            <div class='user-name'><strong><?php echo htmlspecialchars($nama_jurusan); ?></strong></div>
            <div class='user-status'><?php echo $row['total']; ?> User </div>
          </div>
          <?php
            }
          } else {
            echo "<p style='text-align: center; padding: 20px; color: #666;'>Tidak ada pengguna yang ditemukan.</p>";
          }
          ?>
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
