<?php
session_start();
require_once 'koneksi.php';

// Proteksi login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Proteksi role tamu
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

// ================== LOGOUT ==================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ================== HANDLE SEARCH PARAMETERS ==================
$search_judul = isset($_GET['search_judul']) ? trim($_GET['search_judul']) : '';
$search_tanggal = isset($_GET['search_tanggal']) ? trim($_GET['search_tanggal']) : '';
$search_waktu = isset($_GET['search_waktu']) ? trim($_GET['search_waktu']) : '';

// ================== STATISTIK DASHBOARD ==================
// Query untuk total notulen dengan filter
$sql_notulen_count = "SELECT COUNT(DISTINCT n.id) AS total 
                     FROM notulen n
                     INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                     WHERE pn.user_id = ? AND n.status IN ('sent', 'final')";

// Query untuk notulen hari ini dengan filter
$today = date('Y-m-d');
$sql_notulen_hari_ini = "SELECT COUNT(DISTINCT n.id) AS total 
                        FROM notulen n
                        INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                        WHERE pn.user_id = ? AND DATE(n.tanggal) = ? AND n.status IN ('sent', 'final')";

// Query untuk notulen bulan ini dengan filter
$current_month = date('Y-m');
$sql_notulen_bulan_ini = "SELECT COUNT(DISTINCT n.id) AS total 
                         FROM notulen n
                         INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                         WHERE pn.user_id = ? AND DATE_FORMAT(n.tanggal, '%Y-%m') = ? AND n.status IN ('sent', 'final')";

// Prepare dan execute query statistik
$stmt_count = $conn->prepare($sql_notulen_count);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$result_notulen_count = $stmt_count->get_result();
$total_notulen = $result_notulen_count ? $result_notulen_count->fetch_assoc()['total'] : 0;

$stmt_hari_ini = $conn->prepare($sql_notulen_hari_ini);
$stmt_hari_ini->bind_param("is", $user_id, $today);
$stmt_hari_ini->execute();
$result_notulen_hari_ini = $stmt_hari_ini->get_result();
$notulen_hari_ini = $result_notulen_hari_ini ? $result_notulen_hari_ini->fetch_assoc()['total'] : 0;

$stmt_bulan_ini = $conn->prepare($sql_notulen_bulan_ini);
$stmt_bulan_ini->bind_param("is", $user_id, $current_month);
$stmt_bulan_ini->execute();
$result_notulen_bulan_ini = $stmt_bulan_ini->get_result();
$notulen_bulan_ini = $result_notulen_bulan_ini ? $result_notulen_bulan_ini->fetch_assoc()['total'] : 0;

// ================== QUERY NOTULEN DENGAN FILTER ==================
$sql_notulens = "SELECT DISTINCT n.*, k.status as status_kehadiran, k.waktu_konfirmasi 
                FROM notulen n
                INNER JOIN peserta_notulen pn ON n.id = pn.notulen_id
                LEFT JOIN kehadiran k ON n.id = k.notulen_id AND k.user_id = ?
                WHERE pn.user_id = ? AND n.status IN ('sent', 'final')";

$params = [$user_id, $user_id];
$types = "ii";

// Tambahkan kondisi pencarian jika ada
if (!empty($search_judul)) {
    $sql_notulens .= " AND n.judul LIKE ?";
    $params[] = "%" . $search_judul . "%";
    $types .= "s";
}

if (!empty($search_tanggal)) {
    $sql_notulens .= " AND DATE(n.tanggal) = ?";
    $params[] = $search_tanggal;
    $types .= "s";
}

if (!empty($search_waktu)) {
    $sql_notulens .= " AND TIME(n.tanggal) = ?";
    $params[] = $search_waktu . ":00"; // Format waktu HH:MM:SS
    $types .= "s";
}

$sql_notulens .= " ORDER BY n.tanggal DESC";

$stmt_notulens = $conn->prepare($sql_notulens);
if ($types !== "ii") {
    $stmt_notulens->bind_param($types, ...$params);
} else {
    $stmt_notulens->bind_param($types, $user_id, $user_id);
}
$stmt_notulens->execute();
$result_notulens = $stmt_notulens->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Portal Tamu - Notulen Rapat</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="tamu-style.css">
    <style>
        /* === SEARCH CONTAINER === */
        .search-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-bar {
            flex: 1;
            display: flex;
            align-items: center;
            background: white;
            border-radius: 8px;
            padding: 0 15px;
            border: 1px solid #dee2e6;
        }

        .search-bar i {
            color: #6c757d;
            margin-right: 10px;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 0;
            border: none;
            outline: none;
            font-size: 0.95rem;
            background: transparent;
        }

        .date-time-filters {
            display: flex;
            gap: 10px;
        }

        .date-time-filters input {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .search-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .search-btn:hover {
            background: #2980b9;
        }

        .reset-btn {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .reset-btn:hover {
            background: #5a6268;
        }

        /* Responsive Design for Search */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-bar,
            .date-time-filters {
                width: 100%;
            }
            
            .date-time-filters {
                justify-content: space-between;
            }
            
            .search-btn,
            .reset-btn {
                justify-content: center;
            }
        }

        /* Additional styles for search results info */
        .search-info {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-info i {
            font-size: 1rem;
        }

        .kehadiran-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .kehadiran-status.hadir {
            background: #d4edda;
            color: #155724;
        }

        .kehadiran-status.belum {
            background: #fff3cd;
            color: #856404;
        }

        .kehadiran-status.tidak-hadir {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
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
            <p>Selamat Datang, <strong><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></strong>
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

            <!-- Search Bar -->
            <div class="search-container">
                <form method="GET" class="search-form">
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               name="search_judul" 
                               placeholder="Cari Judul Notulen" 
                               value="<?php echo isset($_GET['search_judul']) ? htmlspecialchars($_GET['search_judul']) : ''; ?>">
                    </div>
                    <div class="date-time-filters">
                        <input type="date" 
                               name="search_tanggal" 
                               value="<?php echo isset($_GET['search_tanggal']) ? htmlspecialchars($_GET['search_tanggal']) : ''; ?>">
                        <input type="time" 
                               name="search_waktu" 
                               value="<?php echo isset($_GET['search_waktu']) ? htmlspecialchars($_GET['search_waktu']) : ''; ?>">
                    </div>
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(isset($_GET['search_judul']) || isset($_GET['search_tanggal']) || isset($_GET['search_waktu'])): ?>
                        <a href="tamu.php" class="reset-btn">
                            <i class="fas fa-undo"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Search Results Info -->
            <?php if(!empty($search_judul) || !empty($search_tanggal) || !empty($search_waktu)): ?>
                <div class="search-info">
                    <i class="fas fa-filter"></i>
                    <span>Filter aktif: 
                        <?php 
                        $filters = [];
                        if(!empty($search_judul)) $filters[] = "Judul: \"$search_judul\"";
                        if(!empty($search_tanggal)) $filters[] = "Tanggal: " . date('d M Y', strtotime($search_tanggal));
                        if(!empty($search_waktu)) $filters[] = "Waktu: $search_waktu";
                        echo implode(', ', $filters);
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="notulen-list" id="notulenList">
                <?php
                if ($result_notulens && $result_notulens->num_rows > 0) {
                    while ($notulen = $result_notulens->fetch_assoc()) {
                        $tanggal_formatted = date('d M Y H:i', strtotime($notulen['tanggal']));
                        $isi_pendek = strlen($notulen['isi']) > 150 ? substr($notulen['isi'], 0, 150) . '...' : $notulen['isi'];
                        
                        // Tentukan status kehadiran
                        $kehadiran_status = '';
                        $kehadiran_class = '';
                        $can_confirm = false;
                        
                        if ($notulen['status'] === 'sent') {
                            if ($notulen['status_kehadiran'] === 'hadir') {
                                $kehadiran_status = 'Sudah Konfirmasi Hadir';
                                $kehadiran_class = 'hadir';
                            } else {
                                $kehadiran_status = 'Belum Konfirmasi';
                                $kehadiran_class = 'belum';
                                $can_confirm = true;
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
                                    <?php if (!empty($notulen['lampiran'])): 
                                        $files = json_decode($notulen['lampiran'], true);
                                        if (is_array($files)): ?>
                                            <span class="notulen-lampiran">
                                                <i class="fas fa-paperclip"></i>
                                                <a href="#" onclick="showLampiran(<?php echo $notulen['id']; ?>)" title="Lihat Lampiran">
                                                    <?php echo count($files); ?> lampiran
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notulen-actions">
                                <button class="action-btn view" title="Lihat Detail" onclick="showNotulenDetail(<?php echo $notulen['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($can_confirm): ?>
                                    <button class="action-btn konfirmasi" title="Konfirmasi Kehadiran" onclick="konfirmasiKehadiran(<?php echo $notulen['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($notulen['status'] === 'final'): ?>
                                    <a href="generate_pdf.php?id=<?php echo $notulen['id']; ?>&download=1" class="action-btn download" title="Download Notulen (PDF)" target="_blank">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <h3>Tidak ada notulen tersedia</h3>';
                    
                    if(!empty($search_judul) || !empty($search_tanggal) || !empty($search_waktu)) {
                        echo '<p>Tidak ditemukan notulen dengan filter yang Anda cari.</p>';
                    } else {
                        echo '<p>Belum ada notulen rapat yang diterbitkan untuk Anda.</p>';
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Modal Detail Notulen -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-container wide-modal">
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
    <script>
    function konfirmasiKehadiran(notulenId) {
        if (confirm('Apakah Anda benar-benar hadir dalam rapat ini?')) {
            showLoading();
            fetch('konfirmasi_kehadiran.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notulen_id=' + notulenId
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    alert('Kehadiran berhasil dikonfirmasi!');
                    location.reload();
                } else {
                    alert('Gagal mengkonfirmasi kehadiran: ' + data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengkonfirmasi kehadiran');
            });
        }
    }
    
    function showLampiran(notulenId) {
        window.open('view_lampiran.php?notulen_id=' + notulenId, '_blank');
    }
    
    function showLoading() {
        // Implement loading indicator
        document.body.style.cursor = 'wait';
    }
    
    function hideLoading() {
        // Implement hide loading indicator
        document.body.style.cursor = 'default';
    }
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}
?>