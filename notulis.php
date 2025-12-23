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



// Handle form submission untuk menyimpan notulen dengan lampiran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan_notulen'])) {
        // Tambah notulen baru
        $judul = $_POST['judul'];
        $tanggal = $_POST['tanggal'];
        $isi = $_POST['isi'];
        $penanggung_jawab = $_POST['penanggung_jawab'] ?? 'Belum ditentukan';
        $status = 'draft';
        $lampiran = null;

        // Handle file upload
        if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
            $lampiran = handleFileUpload($_FILES['lampiran']);
            if (isset($_SESSION['error_message'])) {
                header("Location: admin.php");
                exit();
            }
        }

        // Simpan ke database
        $sql = "INSERT INTO notulen (judul, hari, tanggal, Tempat, penanggung_jawab, notulis, Pembahasan, Hasil_akhir, status, lampiran, created_by_user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare("
    INSERT INTO notulen 
    (judul, hari, tanggal, Tempat, penanggung_jawab, notulis, Pembahasan, Hasil_akhir, status, lamptran, created_by_user_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
  );

$stmt->bind_param("ssssssssssi", $judul, $hari, $tanggal, $tempat, $penanggung_jawab, $notulis_name, $isi,  $status, $lamptan,  
);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Notulen berhasil disimpan!";
            header("Location: notulis.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    elseif (isset($_POST['update_notulen'])) {
        // Update notulen yang sudah ada
        $id = intval($_POST['id']);
        $judul = $_POST['judul'];
        $tanggal = $_POST['tanggal'];
        $isi = $_POST['isi'];
        $penanggung_jawab = $_POST['penanggung_jawab'] ?? 'Belum ditentukan';
        $lampiran = null;

        // Handle file upload jika ada file baru
        if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
            // Hapus file lama jika ada
            $sql_select = "SELECT lampiran FROM notulen WHERE id = ?";
            $stmt_select = $conn->prepare($sql_select);
            $stmt_select->bind_param("i", $id);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            
            if ($result->num_rows > 0) {
                $notulen_lama = $result->fetch_assoc();
                if ($notulen_lama['lampiran']) {
                    $filepath = 'uploads/' . $notulen_lama['lampiran'];
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            }
            $stmt_select->close();

            // Upload file baru
            $lampiran = handleFileUpload($_FILES['lampiran']);
            if (isset($_SESSION['error_message'])) {
                header("Location: notulis.php");
                exit();
            }

            // Update dengan lampiran baru
            $sql = "UPDATE notulen SET judul=?, tanggal=?, isi=?, penanggung_jawab=?, lampiran=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $judul, $tanggal, $isi, $penanggung_jawab, $lampiran, $id);
        } else {
            // Update tanpa mengubah lampiran
            $sql = "UPDATE notulen SET judul=?, tanggal=?, isi=?, penanggung_jawab=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $judul, $tanggal, $isi, $penanggung_jawab, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Notulen berhasil diupdate!";
            header("Location: notulis.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle hapus notulen
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    
    // Hapus file lampiran jika ada
    $sql_select = "SELECT lampiran FROM notulen WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    
    if ($result->num_rows > 0) {
        $notulen = $result->fetch_assoc();
        if ($notulen['lampiran']) {
            $filepath = 'uploads/' . $notulen['lampiran'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
    $stmt_select->close();

    $sql = "DELETE FROM notulen WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Notulen berhasil dihapus!";
    } else {
        $_SESSION['error_message'] = "Error menghapus notulen: " . $stmt->error;
    }
    $stmt->close();
    header("Location: notulis.php");
    exit();
}

// Fungsi untuk handle upload file
function handleFileUpload($file) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $file['tmp_name'];
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $fileType = $file['type'];
        $notulis_name = $userLogin['full_name'] ?? $_SESSION['username'];

        // Validasi ekstensi file
        $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedExtensions)) {
            // Validasi ukuran file (5MB)
            if ($fileSize <= 5 * 1024 * 1024) {
                // Generate nama file unik untuk menghindari konflik
                $newFileName = uniqid() . '_' . $fileName;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    return $newFileName;
                } else {
                    $_SESSION['error_message'] = "Terjadi kesalahan saat mengunggah file.";
                }
            } else {
                $_SESSION['error_message'] = "Ukuran file melebihi 5MB.";
            }
        } else {
            $_SESSION['error_message'] = "Format file tidak diizinkan. Hanya PDF, DOC, DOCX, JPG, JPEG, PNG, TXT.";
        }
    }
    return null;
}

// Query untuk statistik dashboard
$sql_notulen_count = "SELECT COUNT(id) AS total FROM notulen";
$result_notulen_count = $conn->query($sql_notulen_count);
$total_notulen = $result_notulen_count ? $result_notulen_count->fetch_assoc()['total'] : 0;

$today = date('Y-m-d');
$sql_notulen_hari_ini = "SELECT COUNT(id) AS total FROM notulen WHERE DATE(tanggal) = '$today'";
$result_notulen_hari_ini = $conn->query($sql_notulen_hari_ini);
$notulen_hari_ini = $result_notulen_hari_ini ? $result_notulen_hari_ini->fetch_assoc()['total'] : 0;

$current_month = date('Y-m');
$sql_notulen_bulan_ini = "SELECT COUNT(id) AS total FROM notulen WHERE DATE_FORMAT(tanggal, '%Y-%m') = '$current_month'";
$result_notulen_bulan_ini = $conn->query($sql_notulen_bulan_ini);
$notulen_bulan_ini = $result_notulen_bulan_ini ? $result_notulen_bulan_ini->fetch_assoc()['total'] : 0;

// Ambil daftar notulen terbaru
$sql_notulens = "SELECT id, judul, tanggal, Pembahasan as isi, penanggung_jawab, status, lampiran as lampiran FROM notulen ORDER BY tanggal DESC, id DESC LIMIT 5";
$result_notulens = $conn->query($sql_notulens);
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
  <!-- Sidebar -->
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
  <div class="main-content" id="mainContent">
    <?php if (isset($_SESSION['success_message'])): ?>
    <!--notifikasi-->
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
        <div class="count-number"><?php echo $total_notulen; ?></div>
        <div class="count-label">Total Notulen</div>
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
        <h2><i class="fas fa-file-alt"></i> Notulen Terbaru</h2>
        <button class="btn btn-add" id="tambahNotulenBtn">
          <i class="fas fa-plus"></i> Tambah Notulen
        </button>
      </div>

      <div class="notulen-list" id="notulenList">
        <?php
        if ($result_notulens && $result_notulens->num_rows > 0) {
          while ($notulen = $result_notulens->fetch_assoc()) {
            $tanggal_formatted = date('d M Y', strtotime($notulen['tanggal']));
            $isi_pendek = strlen($notulen['isi']) > 100 ? substr($notulen['isi'], 0, 100) . '...' : $notulen['isi'];
            $status_class = $notulen['status'] === 'draft' ? 'draft' : 'sent';
            $status_text = $notulen['status'] === 'draft' ? 'Draft' : 'Terkirim';
            
            // Tampilkan nama file asli (tanpa uniqid)
            $nama_file_asli = $notulen['lampiran'] ? substr($notulen['lampiran'], strpos($notulen['lampiran'], '_') + 1) : '';
            ?>
        <div class="notulen-item" data-id="<?php echo $notulen['id']; ?>">
          <div class="notulen-main">
            <h3 class="notulen-title"><?php echo htmlspecialchars($notulen['judul']); ?></h3>
            <p class="notulen-preview"><?php echo htmlspecialchars($isi_pendek); ?></p>
            <div class="notulen-meta">
              <span class="notulen-date"><i class="fas fa-calendar"></i> <?php echo $tanggal_formatted; ?></span>
              <span class="notulen-penanggung-jawab"><i class="fas fa-user"></i>
                <?php echo htmlspecialchars($notulen['penanggung_jawab']); ?></span>
              <span class="notulen-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
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
            <button class="action-btn edit" title="Edit Notulen" data-id="<?php echo $notulen['id']; ?>">
              <i class="fas fa-edit"></i>
            </button>
            <a href="notulis.php?hapus=<?php echo $notulen['id']; ?>" class="action-btn delete" title="Hapus Notulen"
              onclick="return confirm('Apakah Anda yakin ingin menghapus notulen ini?')">
              <i class="fas fa-trash"></i>
            </a>
            <button class="action-btn send" title="Kirim ke Peserta" data-id="<?php echo $notulen['id']; ?>">
              <i class="fas fa-paper-plane"></i>
            </button>
            <?php if (!empty($notulen['lampiran'])): ?>
            <a href="view.php?file=<?php echo urlencode($notulen['lampiran']); ?>" class="action-btn download"
              target="_blank" title="Lihat Lampiran">
              <i class="fas fa-eye"></i>
            </a>
            <?php endif; ?>
          </div>
        </div>
        <?php
          }
        } else {
          echo '<div class="empty-state">
                  <i class="fas fa-file-alt"></i>
                  <h3>Tidak ada notulen</h3>
                  <p>Belum ada notulen yang dibuat. Klik tombol "Tambah Notulen" untuk membuat yang pertama.</p>
                </div>';
        }
        ?>
      </div>
    </div>
  </div>

  <!-- Modal Tambah/Edit Notulen -->
  <div class="modal-overlay" id="notulenModal">
    <div class="modal-container">
      <div class="modal-header">
        <h2 id="modalTitle">Tambah Notulen Baru</h2>
        <button class="modal-close" id="closeModal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="notulenForm" method="POST" action="notulis.php" enctype="multipart/form-data">
          <input type="hidden" name="simpan_notulen" value="1" id="formAction">
          <input type="hidden" name="id" id="notulenId">
          <div class="form-group">
            <label for="judulRapat">Judul Rapat *</label>
            <input type="text" id="judulRapat" name="judul" placeholder="Masukkan judul rapat" required>
          </div>
          <div class="form-group">
            <label for="tanggalRapat">Tanggal Rapat *</label>
            <input type="date" id="tanggalRapat" name="tanggal" required>
          </div>
          <div class="form-group">
            <label for="penanggungJawab">Penanggung Jawab</label>
            <input type="text" id="penanggungJawab" name="penanggung_jawab" placeholder="Nama penanggung jawab">
          </div>
          <div class="form-group">
            <label for="isiNotulen">Isi Notulen *</label>
            <textarea id="isiNotulen" name="isi" rows="6" placeholder="Tulis isi notulen rapat di sini..."
              required></textarea>
          </div>
          <div class="form-group">
            <label for="lampiran">Lampiran (Opsional)</label>
            <input type="file" id="lampiran" name="lampiran" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
            <small class="file-info">Format yang diizinkan: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT. Maksimal 5MB.</small>
            <div id="currentFile" class="current-file" style="margin-top: 5px; display: none;">
              <small>File saat ini: <span id="currentFileName"></span></small>
            </div>
          </div>
          <div class="form-actions">
            <button type="button" class="btn btn-cancel" id="cancelBtn">Batal</button>
            <button type="submit" class="btn btn-submit" id="submitBtn">Simpan Notulen</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="notulis-script.js"></script>
</body>

</html>
<?php
if (isset($conn)) {
    $conn->close();
}
