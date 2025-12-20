<?php
session_start();
if ($_SESSION['role'] !== 'notulis') {
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
    
    session_destroy();
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Pastikan user adalah admin
if ($_SESSION['role'] !== 'notulis') {
    die("Mohon Melakukan Login Terlebih dahulu.");
}  
require_once 'koneksi.php';

$user_id = $_SESSION['user_id'];

// Query untuk mengambil notulen yang sudah dikirim (status sent/final) dan user termasuk sebagai peserta
$sql_notulens = "SELECT n.*, k.status as status_kehadiran, k.waktu_konfirmasi 
                FROM notulen n
                INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                LEFT JOIN kehadiran k ON n.id = k.notulen_id AND k.user_id = ?
                WHERE pn.user_id = ? AND n.status IN ('sent', 'final')
                ORDER BY n.tanggal DESC";
$stmt_notulens = $conn->prepare($sql_notulens);
$stmt_notulens->bind_param("ii", $user_id, $user_id);
$stmt_notulens->execute();
$result_notulens = $stmt_notulens->get_result();

// Statistik dashboard
$sql_notulen_count = "SELECT COUNT(n.id) AS total 
                     FROM notulen n
                     INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                     WHERE pn.user_id = ? AND n.status IN ('sent', 'final')";
$stmt_count = $conn->prepare($sql_notulen_count);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$result_notulen_count = $stmt_count->get_result();
$total_notulen = $result_notulen_count ? $result_notulen_count->fetch_assoc()['total'] : 0;

// Hitung notulen hari ini
$today = date('Y-m-d');
$sql_notulen_hari_ini = "SELECT COUNT(n.id) AS total 
                        FROM notulen n
                        INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                        WHERE pn.user_id = ? AND DATE(n.tanggal) = ? AND n.status IN ('sent', 'final')";
$stmt_hari_ini = $conn->prepare($sql_notulen_hari_ini);
$stmt_hari_ini->bind_param("is", $user_id, $today);
$stmt_hari_ini->execute();
$result_notulen_hari_ini = $stmt_hari_ini->get_result();
$notulen_hari_ini = $result_notulen_hari_ini ? $result_notulen_hari_ini->fetch_assoc()['total'] : 0;

// Hitung notulen bulan ini
$current_month = date('Y-m');
$sql_notulen_bulan_ini = "SELECT COUNT(n.id) AS total 
                         FROM notulen n
                         INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                         WHERE pn.user_id = ? AND DATE_FORMAT(n.tanggal, '%Y-%m') = ? AND n.status IN ('sent', 'final')";
                         
$stmt_bulan_ini = $conn->prepare($sql_notulen_bulan_ini);
$stmt_bulan_ini->bind_param("is", $user_id, $current_month);
$stmt_bulan_ini->execute();
$result_notulen_bulan_ini = $stmt_bulan_ini->get_result();
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
    <!-- Sidebar (sama seperti sebelumnya) -->
     <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo-area">
        <a href="#" class="header-logo">
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
          <a href="jadwal_rapat.php" class="nav-link">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Jadwal Rapat</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="notulen_rapat.php" class="nav-link">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="fas fa-bell nav-icon"></i>
            <span class="nav-label">Notifikasi</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="fas fa-info-circle nav-icon"></i>
            <span class="nav-label">Informasi</span>
          </a>
        </li>
      </ul>

      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="profil.php" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profil</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="notulis.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Keluar</span>
          </a>
        </li>
      </ul>
    </nav>
  </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="dashboard-header">
            <h1>Dashboard Notulis</h1>
            <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                (<?php echo htmlspecialchars($_SESSION['role']); ?>)</p>
        </div>

        <div class="user-stats-grid">
            <div class="user-count-box">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="count-number"><?php echo $total_notulen; ?></div>
                <div class="count-label">Notulen tersedia</div>
            </div>
            <div class="user-count-box daily-notes">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="count-number"><?php echo $notulen_hari_ini; ?></div>
                <div class="count-label">Notulen Hari Ini</div>
            </div>
            <div class="user-count-box monthly-notes">
                <div class="stat-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="count-number"><?php echo $notulen_bulan_ini; ?></div>
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
                        $tanggal_formatted = date('d M Y H:i', strtotime($notulen['tanggal']));
                        $isi_pendek = strlen($notulen['isi']) > 150 ? substr($notulen['isi'], 0, 150) . '...' : $notulen['isi'];
                        $nama_file_asli = $notulen['lampiran'] ? substr($notulen['lampiran'], strpos($notulen['lampiran'], '_') + 1) : '';
                        
                        // Tentukan status kehadiran
                        $kehadiran_status = '';
                        $kehadiran_class = '';
                        if ($notulen['status'] === 'sent') {
                            if ($notulen['status_kehadiran'] === 'hadir') {
                                $kehadiran_status = 'Sudah Konfirmasi Hadir';
                                $kehadiran_class = 'hadir';
                            } else {
                                $kehadiran_status = 'Belum Konfirmasi';
                                $kehadiran_class = 'belum';
                            }
                        } elseif ($notulen['status'] === 'final') {
                            if ($notulen['status_kehadiran'] === 'hadir') {
                                $kehadiran_status = 'Hadir';
                                $kehadiran_class = 'hadir';
                            } else {
                                $kehadiran_status = 'Tidak Hadir';
                                $kehadiran_class = 'tidak-hadir';
                            }
                        }
                        ?>
                        <div class="notulen-item" data-id="<?php echo $notulen['id']; ?>" data-status="<?php echo $notulen['status']; ?>">
                            <div class="notulen-main">
                                <div class="notulen-header">
                                    <h3 class="notulen-title"><?php echo htmlspecialchars($notulen['judul']); ?></h3>
                                    <span class="notulen-status status-<?php echo $notulen['status']; ?>">
                                        <?php echo ucfirst($notulen['status']); ?>
                                    </span>
                                </div>
                                <p class="notulen-preview"><?php echo htmlspecialchars($isi_pendek); ?></p>
                                <div class="notulen-meta">
                                    <span class="notulen-date"><i class="fas fa-calendar"></i> <?php echo $tanggal_formatted; ?></span>
                                    <span class="notulen-penanggung-jawab"><i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($notulen['penanggung_jawab']); ?>
                                    </span>
                                    <span class="kehadiran-status <?php echo $kehadiran_class; ?>">
                                        <i class="fas fa-user-check"></i> <?php echo $kehadiran_status; ?>
                                    </span>
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
                                
                                <?php if ($notulen['status'] === 'sent' && $notulen['status_kehadiran'] !== 'hadir'): ?>
                                    <button class="action-btn konfirmasi" title="Konfirmasi Kehadiran" onclick="konfirmasiKehadiran(<?php echo $notulen['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($notulen['status'] === 'final'): ?>
                                    <a href="download_notulen.php?id=<?php echo $notulen['id']; ?>" class="action-btn download" title="Download Notulen">
                                        <i class="fas fa-download"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!empty($notulen['lampiran'])): ?>
                                <a href="view.php?file=<?php echo urlencode($notulen['lampiran']); ?>" class="action-btn folder" target="_blank" title="Lihat Lampiran">
                                    <i class="fas fa-folder-open"></i>
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
                            <p>Belum ada notulen rapat yang diterbitkan untuk Anda.</p>
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

                </div>
            </div>
        </div>
    </div>

    <script src="tamu-script.js"></script>
    <script>
    function konfirmasiKehadiran(notulenId) {
        if (confirm('Konfirmasi kehadiran untuk notulen ini?')) {
            fetch('konfirmasi_kehadiran.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notulen_id=' + notulenId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Kehadiran berhasil dikonfirmasi!');
                    location.reload();
                } else {
                    alert('Gagal mengkonfirmasi kehadiran: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengkonfirmasi kehadiran');
            });
        }
    }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>
