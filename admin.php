<?php
session_start();
if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}


// Handle logout request
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    // Hapus semua data session
    $_SESSION = array();
    
    // Hapus session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hancurkan session
    session_destroy();
    
    // Redirect ke halaman login
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Pastikan user adalah admin
if ($_SESSION['role'] !== 'admin') {
    die("Akses ditolak. Halaman ini hanya untuk administrator.");
}

require_once 'koneksi.php'; 

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

// 4. Ambil Daftar 5 Pengguna Terbaru (dari tabel 'pengguna')
$sql_users = "SELECT user_id, full_name, role FROM user WHERE is_active = 1 ORDER BY user_id DESC LIMIT 10";
$result_users = $conn->query($sql_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
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
          <!-- Ganti dengan path logo yang benar -->
          <img src="poltek1.png" alt="Politeknik Negeri Batam" />
        </a>
      </div>
      <button class="toggler">
        <span class="fas fa-chevron-left"></span>
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
            <span class="nav-label">User Management</span>
          </a>
        </li>
      </ul>

      <!-- Secondary bottom nav -->
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="profile.php" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profile</span>
          </a>
        </li>
        <li class="nav-item">
          <!-- Logout menggunakan PHP - tidak perlu file terpisah -->
          <a href="admin.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Logout</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="dashboard-header">
      <h1>Dashboard</h1>
      <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
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
        <h3>👥 Daftar Pengguna Terbaru</h3>
        <div class="user-list" id="user-data-list">
          <?php
          if ($result_users && $result_users->num_rows > 0) {
            while ($user = $result_users->fetch_assoc()) {
              ?>
                <div class='user-item'>
                  <div class='user-name'><?php echo htmlspecialchars($user['full_name']); ?></div>
                    <div class='user-status <?php echo ($user['role'] == 'admin')  ?>'>
                    <?php echo htmlspecialchars($user['role']); ?>
                  </div>
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
  <script src="admin.js"></script>
  
  
</body>

</html>
<?php
// Tutup koneksi di akhir file
if (isset($conn)) {
    $conn->close();
}
?>