<?php
session_start();
require_once 'koneksi.php';

// Proteksi login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

/* ================== DATA USER LOGIN ================== */
$user_id = $_SESSION['user_id'];

$stmtUser = $conn->prepare("
    SELECT full_name, role, photo, email, jurusan
    FROM user
    WHERE user_id = ?
");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userLogin = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

/* ================== FOTO PROFIL ================== */
$foto_sekarang = $userLogin['photo'];
$path_valid = (!empty($userLogin['photo'])) ? $userLogin['photo'] : 'uploads/profile_photos/default_profile.png';
$current_photo_url = $path_valid . "?t=" . time();

/* ================== SESSION DATA ================== */
$role = $_SESSION['role'];
$username = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? 'User';

// ================== LOGOUT ==================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

/* ================== TENTUKAN HALAMAN AKTIF ================== */
$current_page = basename($_SERVER['PHP_SELF']); // Mendapatkan nama file saat ini

// Fungsi untuk menentukan apakah menu aktif
function isMenuActive($page_name) {
    global $current_page;
    
    $page_mapping = [
        'dashboard' => 'notulis.php',
        'notulen' => 'notulen_rapat.php',
        'jadwal' => 'jadwal_rapat.php',
        'profile' => 'profile.php'
    ];
    
    return isset($page_mapping[$page_name]) && $current_page === $page_mapping[$page_name];
}

/* ================== FILTER PARAMETERS ================== */
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date_filter = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date_filter = isset($_GET['end_date']) ? $_GET['end_date'] : '';

/* ================== PAGINATION ================== */
$limit = 5; // Notulen per halaman
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Query dasar untuk count dan data
// Notulis melihat notulen yang dibuatnya DAN yang diundang sebagai peserta
$sql_base = "SELECT DISTINCT n.*, k.status as status_kehadiran, k.waktu_konfirmasi 
            FROM notulen n
            LEFT JOIN peserta_notulen pn ON n.id = pn.notulen_id
            LEFT JOIN kehadiran k ON n.id = k.notulen_id AND k.user_id = ?
            WHERE (n.created_by_user_id = ? OR pn.user_id = ?) 
            AND n.status IN ('sent', 'final')";

// Tambahkan filter jika ada
$where_conditions = [];
$params = [$user_id, $user_id, $user_id];
$param_types = "iii";

if (!empty($search_filter)) {
    $sql_base .= " AND (n.judul LIKE ? OR n.tempat LIKE ? OR n.penanggung_jawab LIKE ?)";
    $search_term = "%" . $search_filter . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

if (!empty($start_date_filter)) {
    $sql_base .= " AND n.tanggal >= ?";
    $params[] = $start_date_filter;
    $param_types .= "s";
}

if (!empty($end_date_filter)) {
    $sql_base .= " AND n.tanggal <= ?";
    $params[] = $end_date_filter;
    $param_types .= "s";
}

// Hitung total notulen untuk pagination
$sql_count = $sql_base;
$stmt_count = $conn->prepare($sql_count);

// Bind parameters untuk count
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_notulens = $result_count->num_rows;
$stmt_count->close();

// Hitung total halaman
$total_pages = ceil($total_notulens / $limit);

// Validasi page number
if ($page > $total_pages && $total_pages > 0) {
    header("Location: notulis.php?page=" . $total_pages);
    exit();
}

// Query untuk data dengan pagination
$sql_data = $sql_base . " ORDER BY n.tanggal DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt_data = $conn->prepare($sql_data);
$stmt_data->bind_param($param_types, ...$params);
$stmt_data->execute();
$result_data = $stmt_data->get_result();

// Hitung statistik untuk notulis
$today = date('Y-m-d');
$current_month = date('Y-m');

// Statistik berdasarkan notulen yang dibuat oleh notulis ini
$sql_stats = "SELECT 
    COUNT(id) as total_all,
    SUM(CASE WHEN DATE(tanggal) = '$today' THEN 1 ELSE 0 END) as today_count,
    SUM(CASE WHEN DATE_FORMAT(tanggal, '%Y-%m') = '$current_month' THEN 1 ELSE 0 END) as month_count,
    SUM(CASE WHEN status = 'final' THEN 1 ELSE 0 END) as final_count,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count
    FROM notulen 
    WHERE created_by_user_id = ?";

$stmt_stats = $conn->prepare($sql_stats);
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

$total_notulen_all = $stats['total_all'] ?? 0;
$notulen_hari_ini = $stats['today_count'] ?? 0;
$notulen_bulan_ini = $stats['month_count'] ?? 0;
$notulen_final = $stats['final_count'] ?? 0;
$notulen_terkirim = $stats['sent_count'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <title>Dashboard Notulis</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="notulis-style.css">
</head>

<body>
  <!-- Sidebar Notulis -->
<div class="sidebar">
  <div class="sidebar-header">
    <div class="logo-area">
      <a href="#" class="header-logo">
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
    <ul class="nav-list primary-nav">
      <li class="nav-item">
        <a href="notulis.php" class="nav-link <?php echo isMenuActive('dashboard') ? 'active' : ''; ?>">
          <i class="fas fa-th-large nav-icon"></i>
          <span class="nav-label">Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="notulen_rapat.php" class="nav-link <?php echo isMenuActive('notulen') ? 'active' : ''; ?>">
          <i class="fas fa-file-alt nav-icon"></i>
          <span class="nav-label">Notulen Rapat</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="jadwal_rapat.php" class="nav-link <?php echo isMenuActive('jadwal') ? 'active' : ''; ?>">
          <i class="fas fa-calendar-alt nav-icon"></i>
          <span class="nav-label">Jadwal Rapat</span>
        </a>
      </li>
    </ul>

    <!-- Secondary bottom nav -->
    <ul class="nav-list secondary-nav">
      <li class="nav-item">
        <a href="profile.php" class="nav-link <?php echo isMenuActive('profile') ? 'active' : ''; ?>">
          <i class="fas fa-user-circle nav-icon"></i>
          <span class="nav-label">Profil Saya</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="notulis.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
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
  <div class="main-content" id="mainContent">
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="notification success">
      <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="notification error">
      <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <div class="dashboard-header">
      <h1>Dashboard Notulis</h1>
      <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
    </div>

    <div class="user-stats-grid">
      <div class="user-count-box">
        <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
        <div class="count-number"><?php echo $total_notulen_all; ?></div>
        <div class="count-label">Total Notulen</div>
      </div>
      <div class="user-count-box daily-notes">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="count-number"><?php echo $notulen_final; ?></div>
        <div class="count-label">Notulen Final</div>
      </div>
      <div class="user-count-box monthly-notes">
        <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
        <div class="count-number"><?php echo $notulen_bulan_ini; ?></div>
        <div class="count-label">Notulen Bulan Ini</div>
      </div>
      <div class="user-count-box">
        <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
        <div class="count-number"><?php echo $notulen_terkirim; ?></div>
        <div class="count-label">Notulen Terkirim</div>
      </div>
    </div>

    <div class="content-section">
      <div class="section-header">
        <h2><i class="fas fa-file-alt"></i> Notulen Rapat</h2>
        <div class="filter-info">
          <i class="fas fa-info-circle"></i> Menampilkan notulen yang sudah dikirim
        </div>
      </div>

      <!-- Search dan Filter -->
      <div class="search-bar">
        <div class="search-row">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Cari Judul, Tempat, atau Penanggung Jawab..." 
                   value="<?php echo htmlspecialchars($search_filter); ?>">
          </div>
          
          <div class="date-filter">
            <input type="date" id="startDate" placeholder="Tanggal Mulai" 
                   value="<?php echo htmlspecialchars($start_date_filter); ?>">
            <span> - </span>
            <input type="date" id="endDate" placeholder="Tanggal Selesai"
                   value="<?php echo htmlspecialchars($end_date_filter); ?>">
          </div>
          
          <button id="clearBtn" class="clear-btn">
            <i class="fas fa-times"></i> Clear
          </button>
        </div>
      </div>

      <div class="notulen-list" id="notulenList">
        <?php if ($result_data && $result_data->num_rows > 0): ?>
            <?php while ($notulen = $result_data->fetch_assoc()): ?>
                <?php
                $tanggal_formatted = date('d M Y', strtotime($notulen['tanggal']));
                $hari = date('l', strtotime($notulen['tanggal']));
                
                $hari_indonesia = [
                    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu',
                    'Sunday' => 'Minggu'
                ];
                $hari = $hari_indonesia[$hari] ?? $hari;
                
                // Hanya cek apakah user dapat mengkonfirmasi (untuk modal)
                $can_confirm = ($notulen['status'] === 'sent' && $notulen['status_kehadiran'] !== 'hadir');
                
                $sql_peserta = "SELECT COUNT(*) as total FROM peserta_notulen WHERE notulen_id = ?";
                $stmt_peserta = $conn->prepare($sql_peserta);
                $stmt_peserta->bind_param("i", $notulen['id']);
                $stmt_peserta->execute();
                $total_peserta = $stmt_peserta->get_result()->fetch_assoc()['total'];
                $stmt_peserta->close();
                
                $sql_hadir = "SELECT COUNT(*) as hadir FROM kehadiran WHERE notulen_id = ? AND status = 'hadir'";
                $stmt_hadir = $conn->prepare($sql_hadir);
                $stmt_hadir->bind_param("i", $notulen['id']);
                $stmt_hadir->execute();
                $total_hadir = $stmt_hadir->get_result()->fetch_assoc()['hadir'];
                $stmt_hadir->close();
                
                $has_lampiran = false;
                  $lampiran_count = 0;
                  if (!empty($notulen['lampiran'])) {
                      $lampiran = json_decode($notulen['lampiran'], true);
                      if (is_array($lampiran) && count($lampiran) > 0) {
                          $has_lampiran = true;
                          $lampiran_count = count($lampiran);
                      }
                  }
                ?>
                
                <div class="notulen-item" data-notulen-id="<?php echo $notulen['id']; ?>">
                    <div class="notulen-main">
                        <div class="notulen-header">
                            <h3 class="notulen-title"><?php echo htmlspecialchars($notulen['judul']); ?></h3>
                            <div class="status-container">
                                <span class="notulen-status status-<?php echo $notulen['status']; ?>">
                                    <?php echo ucfirst($notulen['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="notulen-meta">
                            <span class="notulen-meta-item">
                                <i class="fas fa-calendar"></i> <?php echo $hari . ', ' . $tanggal_formatted; ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-clock"></i> <?php echo $notulen['jam_mulai'] . ' - ' . $notulen['jam_selesai']; ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($notulen['tempat']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($notulen['penanggung_jawab']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($notulen['jurusan']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-users"></i> <?php echo $total_peserta; ?> peserta
                            </span>
                            
                            <?php if ($has_lampiran): ?>
                                <a href="#" 
                                  class="lampiran-badge" 
                                  onclick="showLampiran(<?php echo $notulen['id']; ?>)"
                                  title="Lihat Lampiran">
                                    <i class="fas fa-paperclip"></i> Lampiran (<?php echo $lampiran_count; ?>)
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="notulen-actions">
                        <button type="button" class="action-btn view" title="Lihat Detail" 
                                data-notulen-id="<?php echo $notulen['id']; ?>">
                            <i class="fas fa-eye"></i>
                        </button>
                        
                        <?php if ($notulen['status'] === 'final' && ($notulen['status_kehadiran'] === 'hadir' || $notulen['created_by_user_id'] == $user_id)): ?>
                            <a href="generate_pdf.php?id=<?php echo $notulen['id']; ?>&download=1" 
                              class="action-btn download" 
                              title="Download Notulen (PDF)" 
                              target="_blank">
                                <i class="fas fa-download"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>Tidak ada notulen tersedia</h3>
                <p><?php echo !empty($search_filter) || !empty($start_date_filter) || !empty($end_date_filter) 
                    ? 'Coba ubah kata kunci pencarian atau filter tanggal' 
                    : 'Belum ada notulen rapat yang tersedia untuk Anda.'; ?></p>
            </div>
        <?php endif; ?>
    </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
          <div class="pagination-info">
            Menampilkan <?php echo ($offset + 1); ?>-<?php echo min($offset + $limit, $total_notulens); ?> dari <?php echo $total_notulens; ?> notulen
          </div>
          
          <ul class="pagination">
            <?php if ($page > 1): ?>
              <li>
                <a href="?page=1<?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?><?php echo $start_date_filter ? '&start_date=' . $start_date_filter : ''; ?><?php echo $end_date_filter ? '&end_date=' . $end_date_filter : ''; ?>" title="Halaman pertama">
                  <i class="fas fa-angle-double-left"></i>
                </a>
              </li>
              <li>
                <a href="?page=<?php echo $page - 1; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?><?php echo $start_date_filter ? '&start_date=' . $start_date_filter : ''; ?><?php echo $end_date_filter ? '&end_date=' . $end_date_filter : ''; ?>" title="Sebelumnya">
                  <i class="fas fa-angle-left"></i>
                </a>
              </li>
            <?php else: ?>
              <li class="disabled">
                <span><i class="fas fa-angle-double-left"></i></span>
              </li>
              <li class="disabled">
                <span><i class="fas fa-angle-left"></i></span>
              </li>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
              echo '<li><a href="?page=1';
              if ($search_filter) echo '&search=' . urlencode($search_filter);
              if ($start_date_filter) echo '&start_date=' . $start_date_filter;
              if ($end_date_filter) echo '&end_date=' . $end_date_filter;
              echo '">1</a></li>';
              if ($start_page > 2) echo '<li class="disabled"><span>...</span></li>';
            }
            
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
              <li <?php echo ($i == $page) ? 'class="active"' : ''; ?>>
                <a href="?page=<?php echo $i; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?><?php echo $start_date_filter ? '&start_date=' . $start_date_filter : ''; ?><?php echo $end_date_filter ? '&end_date=' . $end_date_filter : ''; ?>">
                  <?php echo $i; ?>
                </a>
              </li>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): 
              if ($end_page < $total_pages - 1) echo '<li class="disabled"><span>...</span></li>';
            ?>
              <li>
                <a href="?page=<?php echo $total_pages; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?><?php echo $start_date_filter ? '&start_date=' . $start_date_filter : ''; ?><?php echo $end_date_filter ? '&end_date=' . $end_date_filter : ''; ?>">
                  <?php echo $total_pages; ?>
                </a>
              </li>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
              <li>
                <a href="?page=<?php echo $page + 1; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?><?php echo $start_date_filter ? '&start_date=' . $start_date_filter : ''; ?><?php echo $end_date_filter ? '&end_date=' . $end_date_filter : ''; ?>" title="Berikutnya">
                  <i class="fas fa-angle-right"></i>
                </a>
              </li>
              <li>
                <a href="?page=<?php echo $total_pages; ?><?php echo $search_filter ? '&search=' . urlencode($search_filter) : ''; ?><?php echo $start_date_filter ? '&start_date=' . $start_date_filter : ''; ?><?php echo $end_date_filter ? '&end_date=' . $end_date_filter : ''; ?>" title="Halaman terakhir">
                  <i class="fas fa-angle-double-right"></i>
                </a>
              </li>
            <?php else: ?>
              <li class="disabled">
                <span><i class="fas fa-angle-right"></i></span>
              </li>
              <li class="disabled">
                <span><i class="fas fa-angle-double-right"></i></span>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Modal Detail Notulen -->
  <div class="modal-overlay" id="detailModal">
    <div class="modal-container wide-modal">
      <div class="modal-header">
        <h2 id="detailModalTitle">
          <i class="fas fa-file-alt"></i> Detail Notulen Lengkap
        </h2>
        <button class="modal-close" id="closeDetailModal">&times;</button>
      </div>
      <div class="modal-body">
        <div id="detailModalContent">
          <!-- Konten akan diisi oleh JavaScript -->
        </div>
      </div>
    </div>
  </div>

  <script src="notulis-script.js"></script>
</body>

</html>
<?php
if (isset($stmt_data) && $stmt_data) {
    $stmt_data->close();
}
if (isset($conn)) {
    $conn->close();

}
