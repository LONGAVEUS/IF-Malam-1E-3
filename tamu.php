<?php
session_start();
require_once 'koneksi.php';

if ($_SESSION['role'] !== 'tamu') {
    header("Location: login.php");
    exit();
}

/* ================== DATA USER LOGIN ================== */
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

/* ================== FOTO PROFIL ================== */
$fotoProfil = (!empty($userLogin['photo']) && file_exists($userLogin['photo']))
    ? $userLogin['photo']
    : 'uploads/profile_photos/default_profile.png';

/* ================== SESSION DATA ================== */
$role = $_SESSION['role'];
$username = $_SESSION['username'];
$photo = $_SESSION['photo'] ?? "default_profile.png";

$current_photo_url = (!empty($_SESSION['photo']))
    ? $_SESSION['photo']
    : "uploads/profile_photos/default_profile.png";


// Query untuk mengambil semua notulen yang sudah dikirim (status sent)
$sql_notulens = "SELECT id, judul, tanggal, isi, penanggung_jawab, status, lampiran FROM isinotulen WHERE status = 'sent' ORDER BY tanggal DESC";
$result_notulens = $conn->query($sql_notulens);

// Query untuk statistik dashboard (hanya yang sudah dikirim)
$sql_notulen_count = "SELECT COUNT(id) AS total FROM isinotulen WHERE status = 'sent'";
$result_notulen_count = $conn->query($sql_notulen_count);
$total_notulen = $result_notulen_count ? $result_notulen_count->fetch_assoc()['total'] : 0;

$today = date('Y-m-d');
$sql_notulen_hari_ini = "SELECT COUNT(id) AS total FROM isinotulen WHERE DATE(tanggal) = '$today' AND status = 'sent'";
$result_notulen_hari_ini = $conn->query($sql_notulen_hari_ini);
$notulen_hari_ini = $result_notulen_hari_ini ? $result_notulen_hari_ini->fetch_assoc()['total'] : 0;

$current_month = date('Y-m');
$sql_notulen_bulan_ini = "SELECT COUNT(id) AS total FROM isinotulen WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$current_month' AND status = 'sent'";
$result_notulen_bulan_ini = $conn->query($sql_notulen_bulan_ini);
$notulen_bulan_ini = $result_notulen_bulan_ini ? $result_notulen_bulan_ini->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <title>Portal Tamu - Notulen Rapat</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="tamu-style.css">
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
          <a href="tamu.php" class="nav-link active">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-label">Dashboard</span>
          </a>
        </li>
        
        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
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
  <div class="main-content" id="mainContent">
    <div class="dashboard-header">
      <h1>Dashboard Tamu</h1>
      <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        (<?php echo htmlspecialchars($_SESSION['role'] ); ?>) Anda telah berada di halaman</p>
    </div>

        <div class="user-stats-grid">
            <div class="user-count-box">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="count-number"><?php echo $total_notulen; ?></div>
                <div class="count-label">Notulen tersedia</div>
            </div>
            <div class="user-count-box daily-notes">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="count-number"><?php echo $total_notulen; ?></div>
                <div class="count-label">Notulen Hari Ini</div>
            </div>
            <div class="user-count-box monthly-notes">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="count-number"><?php echo $total_notulen; ?></div>
                <div class="count-label">Notulen Bulan Ini</div>
            </div>
        </div>
    

    <div class="content-section">
      <div class="section-header">
        <h2><i class="fas fa-file-alt"></i> Notulen Rapat yang Tersedia</h2>
        <div class="filter-info">
          <i class="fas fa-info-circle"></i> Hanya menampilkan notulen yang sudah dikirim
        </div>
      </div>

      <div class="notulen-list" id="notulenList">
        <?php
        if ($result_notulens && $result_notulens->num_rows > 0) {
          while ($notulen = $result_notulens->fetch_assoc()) {
            $tanggal_formatted = date('d M Y', strtotime($notulen['tanggal']));
            $waktu_formatted = date('H:i', strtotime($notulen['tanggal']));
            $isi_pendek = strlen($notulen['isi']) > 150 ? substr($notulen['isi'], 0, 150) . '...' : $notulen['isi'];
            
            // Tampilkan nama file asli (tanpa uniqid)
            $nama_file_asli = $notulen['lampiran'] ? substr($notulen['lampiran'], strpos($notulen['lampiran'], '_') + 1) : '';
            ?>
        <div class="notulen-item" data-id="<?php echo $notulen['id']; ?>">
          <div class="notulen-main">
            <h3 class="notulen-title"><?php echo htmlspecialchars($notulen['judul']); ?></h3>
            <p class="notulen-preview"><?php echo htmlspecialchars($isi_pendek); ?></p>
            <div class="notulen-meta">
              <span class="notulen-date"><i class="fas fa-calendar"></i> <?php echo $tanggal_formatted; ?></span>
              <span class="notulen-time"><i class="fas fa-clock"></i> <?php echo $waktu_formatted; ?></span>
              <span class="notulen-penanggung-jawab"><i class="fas fa-user"></i>
                <?php echo htmlspecialchars($notulen['penanggung_jawab']); ?></span>
              <?php if (!empty($notulen['lampiran'])): ?>
              <span class="notulen-lampiran">
                <i class="fas fa-paperclip"></i>
                <a href="view.php?file=<?php echo urlencode($notulen['lampiran']); ?>" target="_blank"
                  title="<?php echo htmlspecialchars($nama_file_asli); ?>">
                  Lampiran
                </a>
              </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="notulen-actions">
            <button class="action-btn view" title="Lihat Detail" onclick="showNotulenDetail(<?php echo $notulen['id']; ?>)">
              <i class="fas fa-eye"></i>
            </button>
            <?php if (!empty($notulen['lampiran'])): ?>
            <a href="view.php?file=<?php echo urlencode($notulen['lampiran']); ?>" class="action-btn download" target="_blank" title="Unduh Lampiran">
              <i class="fas fa-download"></i>
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php
          }
        } else {
          echo '<div class="empty-state">
                  <i class="fas fa-file-alt"></i>
                  <h3>Tidak ada notulen tersedia</h3>
                  <p>Belum ada notulen rapat yang diterbitkan untuk dilihat.</p>
                </div>';
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

  <script src="tamu-script.js"></script>
</body>

</html>
<?php
if (isset($conn)) {
    $conn->close();
}
