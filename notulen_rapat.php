<?php
session_start();
require_once 'koneksi.php';
// Proteksi login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Proteksi role
if ($_SESSION['role'] !== 'notulis') {
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
$success_msg = '';
$error_msg = '';

// Handle form submission untuk buat notulen baru
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_notulen'])) {
        $judul = $_POST['judul'] ?? '';
        $hari = $_POST['hari'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $tempat = $_POST['tempat'] ?? '';
        $notulis = $_POST['notulis'] ?? '';
        $penanggung_jawab = $_POST['penanggung_jawab'] ?? '';
        $pembahasan = $_POST['pembahasan'] ?? '';
        $hasil_akhir = $_POST['hasil_akhir'] ?? '';
        $peserta_ids = isset($_POST['peserta_ids']) ? explode(',', $_POST['peserta_ids']) : [];
        
        // Validasi
        if (empty($judul) || empty($hari) || empty($tanggal) || empty($tempat) || empty($notulis) || empty($penanggung_jawab)) {
            $error_msg = "Field wajib diisi!";
        } else {
            // Konversi format tanggal
            $tanggal_formatted = date('Y-m-d', strtotime($tanggal));
            
            // Handle file upload
            $lampiran_files = [];
            if (!empty($_FILES['lampiran']['name'][0])) {
                $upload_dir = 'uploads/lampiran/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['lampiran']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['lampiran']['name'][$key];
                    $file_tmp = $_FILES['lampiran']['tmp_name'][$key];
                    $file_size = $_FILES['lampiran']['size'][$key];
                    $file_error = $_FILES['lampiran']['error'][$key];
                    
                    // Validasi file
                    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'zip', 'rar', 'ppt', 'pptx'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed_ext) && $file_size <= 10 * 1024 * 1024 && $file_error == 0) {
                        $new_file_name = time() . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.]/', '', $file_name);
                        $destination = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $lampiran_files[] = [
                                'file_name' => $new_file_name,
                                'original_name' => $file_name
                            ];
                        }
                    }
                }
            }
            
            // Mulai transaction
            $conn->begin_transaction();
            
            try {
                // Insert notulen dengan field baru
                $sql_notulen = "INSERT INTO notulen (judul, hari, tanggal, Tempat, notulis, Pembahasan, Hasil_akhir, penanggung_jawab, status, lampiran, created_by_user_id, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW())";
                $stmt_notulen = $conn->prepare($sql_notulen);
                
                $lampiran_json = !empty($lampiran_files) ? json_encode($lampiran_files) : NULL;
                $stmt_notulen->bind_param("sssssssssi", $judul, $hari, $tanggal_formatted, $tempat, $notulis, $pembahasan, $hasil_akhir, $penanggung_jawab, $lampiran_json, $user_id);
                
                if ($stmt_notulen->execute()) {
                    $notulen_id = $stmt_notulen->insert_id;
                    
                    // Insert peserta notulen
                    if (!empty($peserta_ids)) {
                        $sql_peserta = "INSERT INTO peserta_notulen (notulen_id, user_id) VALUES (?, ?)";
                        $stmt_peserta = $conn->prepare($sql_peserta);
                        
                        foreach ($peserta_ids as $peserta_id) {
                            if (!empty($peserta_id)) {
                                $stmt_peserta->bind_param("ii", $notulen_id, $peserta_id);
                                $stmt_peserta->execute();
                            }
                        }
                        $stmt_peserta->close();
                    }
                    
                    $conn->commit();
                    $success_msg = "Notulen berhasil dibuat!";
                    
                    // Jika langsung dikirim, update status jadi 'sent'
                    if (isset($_POST['action']) && $_POST['action'] == 'send') {
                        $sql_update = "UPDATE notulen SET status = 'sent' WHERE Id = ?";
                        $stmt_update = $conn->prepare($sql_update);
                        $stmt_update->bind_param("i", $notulen_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $success_msg = "Notulen berhasil dibuat dan dikirim ke peserta!";
                    }
                    
                } else {
                    throw new Exception("Gagal menyimpan notulen: " . $conn->error);
                }
                
                $stmt_notulen->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Error: " . $e->getMessage();
            }
        }
        // Handle form submission untuk edit notulen
    } elseif (isset($_POST['edit_notulen'])) {
        $notulen_id = $_POST['notulen_id'] ?? 0;
        $judul = $_POST['judul'] ?? '';
        $hari = $_POST['hari'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $tempat = $_POST['tempat'] ?? '';
        $notulis = $_POST['notulis'] ?? '';
        $penanggung_jawab = $_POST['penanggung_jawab'] ?? '';
        $pembahasan = $_POST['pembahasan'] ?? '';
        $hasil_akhir = $_POST['hasil_akhir'] ?? '';
        $peserta_ids = isset($_POST['peserta_ids']) ? explode(',', $_POST['peserta_ids']) : [];
        
        // Validasi
        if (empty($judul) || empty($hari) || empty($tanggal) || empty($tempat) || empty($notulis) || empty($penanggung_jawab) || $notulen_id == 0) {
            $error_msg = "Field wajib diisi!";
        } else {
            // Konversi format tanggal
            $tanggal_formatted = date('Y-m-d', strtotime($tanggal));
            
            // Handle new file uploads
            $new_lampiran_files = [];
            if (!empty($_FILES['lampiran']['name'][0])) {
                $upload_dir = 'uploads/lampiran/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['lampiran']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['lampiran']['name'][$key];
                    $file_tmp = $_FILES['lampiran']['tmp_name'][$key];
                    $file_size = $_FILES['lampiran']['size'][$key];
                    $file_error = $_FILES['lampiran']['error'][$key];
                    
                    // Validasi file
                    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'zip', 'rar', 'ppt', 'pptx'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed_ext) && $file_size <= 10 * 1024 * 1024 && $file_error == 0) {
                        $new_file_name = time() . '_' . uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.]/', '', $file_name);
                        $destination = $upload_dir . $new_file_name;
                        
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $new_lampiran_files[] = [
                                'file_name' => $new_file_name,
                                'original_name' => $file_name
                            ];
                        }
                    }
                }
            }
            
            // Mulai transaction
            $conn->begin_transaction();
            
            try {
                // Get existing lampiran
                $sql_check_status = "SELECT status FROM notulen WHERE Id = ? AND created_by_user_id = ?";
                $stmt_check = $conn->prepare($sql_check_status);
                $stmt_check->bind_param("ii", $notulen_id, $user_id);
                $stmt_check->execute();
                $stmt_check->bind_result($current_status);
                $stmt_check->fetch();
                $stmt_check->close();
                
                // Combine existing and new lampiran
                $combined_lampiran = [];
                if ($existing_lampiran_json) {
                    $existing_lampiran = json_decode($existing_lampiran_json, true);
                    if (is_array($existing_lampiran)) {
                        $combined_lampiran = $existing_lampiran;
                    }
                }
                
                if (!empty($new_lampiran_files)) {
                    $combined_lampiran = array_merge($combined_lampiran, $new_lampiran_files);
                }
                
                $lampiran_json = !empty($combined_lampiran) ? json_encode($combined_lampiran) : NULL;
                
                // Update notulen dengan field baru
                $sql_update = "UPDATE notulen 
                              SET judul = ?, hari = ?, tanggal = ?, Tempat = ?, notulis = ?, 
                                  Pembahasan = ?, Hasil_akhir = ?, penanggung_jawab = ?, lampiran = ?
                              WHERE Id = ? AND created_by_user_id = ? AND status = 'draft'";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sssssssssii", $judul, $hari, $tanggal_formatted, $tempat, 
                                        $notulis, $pembahasan, $hasil_akhir, $penanggung_jawab, $lampiran_json, $notulen_id, $user_id);
                
                if ($stmt_update->execute()) {
                    // Delete existing peserta
                    $sql_delete_peserta = "DELETE FROM peserta_notulen WHERE notulen_id = ?";
                    $stmt_delete = $conn->prepare($sql_delete_peserta);
                    $stmt_delete->bind_param("i", $notulen_id);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                    
                    // Insert new peserta
                    if (!empty($peserta_ids)) {
                        $sql_peserta = "INSERT INTO peserta_notulen (notulen_id, user_id) VALUES (?, ?)";
                        $stmt_peserta = $conn->prepare($sql_peserta);
                        
                        foreach ($peserta_ids as $peserta_id) {
                            if (!empty($peserta_id)) {
                                $stmt_peserta->bind_param("ii", $notulen_id, $peserta_id);
                                $stmt_peserta->execute();
                            }
                        }
                        $stmt_peserta->close();
                    }
                    
                    $conn->commit();
                    $success_msg = "Notulen berhasil diperbarui!";
                    
                    // Redirect untuk menghindari resubmission
                    header("Location: notulen_rapat.php?success=" . urlencode($success_msg));
                    exit();
                    
                } else {
                    throw new Exception("Gagal memperbarui notulen: " . $conn->error);
                }
                
                $stmt_update->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle aksi notulen (delete, send, finalize)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $notulen_id = intval($_GET['id']);
    
    if ($_GET['action'] == 'delete') {
        // Hapus notulen
        $sql_delete = "DELETE FROM notulen WHERE Id = ? AND created_by_user_id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $notulen_id, $user_id);
        
        if ($stmt_delete->execute()) {
            // Hapus peserta notulen
            $sql_delete_peserta = "DELETE FROM peserta_notulen WHERE notulen_id = ?";
            $stmt_delete_peserta = $conn->prepare($sql_delete_peserta);
            $stmt_delete_peserta->bind_param("i", $notulen_id);
            $stmt_delete_peserta->execute();
            $stmt_delete_peserta->close();
            
            // Hapus kehadiran
            $sql_delete_kehadiran = "DELETE FROM kehadiran WHERE notulen_id = ?";
            $stmt_delete_kehadiran = $conn->prepare($sql_delete_kehadiran);
            $stmt_delete_kehadiran->bind_param("i", $notulen_id);
            $stmt_delete_kehadiran->execute();
            $stmt_delete_kehadiran->close();
            
            $success_msg = "Notulen berhasil dihapus!";
        } else {
            $error_msg = "Gagal menghapus notulen!";
        }
        $stmt_delete->close();
        
        // Redirect untuk menghindari resubmission
        header("Location: notulen_rapat.php?success=" . urlencode($success_msg));
        exit();
        
    } elseif ($_GET['action'] == 'send') {
        // Kirim notulen ke peserta
        $sql_update = "UPDATE notulen SET status = 'sent' WHERE Id = ? AND created_by_user_id = ? AND status = 'draft'";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $notulen_id, $user_id);
        
        if ($stmt_update->execute()) {
            $success_msg = "Notulen berhasil dikirim ke peserta!";
        } else {
            $error_msg = "Gagal mengirim notulen!";
        }
        $stmt_update->close();
        
        // Redirect untuk menghindari resubmission
        header("Location: notulen_rapat.php?success=" . urlencode($success_msg));
        exit();
        
   } elseif ($_GET['action'] == 'finalize') {
    // Finalize notulen dan generate PDF
    $sql_update = "UPDATE notulen SET status = 'final' WHERE Id = ? AND created_by_user_id = ? AND status = 'sent'";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $notulen_id, $user_id);
    
    try {
        if ($stmt_update->execute()) {
            // Set peserta yang belum konfirmasi sebagai tidak hadir
            $sql_peserta = "SELECT pn.user_id FROM peserta_notulen pn 
                           LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id 
                           WHERE pn.notulen_id = ? AND k.user_id IS NULL";
            $stmt_peserta = $conn->prepare($sql_peserta);
            $stmt_peserta->bind_param("i", $notulen_id);
            $stmt_peserta->execute();
            $result_peserta = $stmt_peserta->get_result();
            
            while ($peserta = $result_peserta->fetch_assoc()) {
                $sql_insert_kehadiran = "INSERT INTO kehadiran (notulen_id, user_id, status) VALUES (?, ?, 'tidak')";
                $stmt_insert = $conn->prepare($sql_insert_kehadiran);
                $stmt_insert->bind_param("ii", $notulen_id, $peserta['user_id']);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            $stmt_peserta->close();
            
            // Generate PDF
            if (file_exists('generate_pdf.php')) {
                require_once 'generate_pdf.php';
                $pdf_path = generateNotulenPDF($notulen_id);
                
                if ($pdf_path) {
                    $success_msg = "Notulen berhasil difinalisasi! PDF telah dibuat.";
                } else {
                    $success_msg = "Notulen berhasil difinalisasi, tetapi gagal membuat PDF.";
                }
            } else {
                $success_msg = "Notulen berhasil difinalisasi.";
            }
        } else {
            // Tambahkan penanganan error spesifik
            if (strpos($stmt_update->error, 'Data truncated') !== false) {
                $error_msg = "Error: Nilai status 'final' tidak didukung. Periksa struktur tabel notulen.";
            } else {
                $error_msg = "Gagal memfinalisasi notulen: " . $stmt_update->error;
            }
        }
        $stmt_update->close();
        
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
    
    // Redirect untuk menghindari resubmission
    if (isset($success_msg)) {
        header("Location: notulen_rapat.php?success=" . urlencode($success_msg));
    } else {
        header("Location: notulen_rapat.php?error=" . urlencode($error_msg));
    }
    exit();
}
}

// Cek pesan dari redirect
if (isset($_GET['success'])) {
    $success_msg = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error_msg = urldecode($_GET['error']);
}

// Ambil daftar notulen yang dibuat oleh notulis dengan field baru
$sql_notulens = "SELECT n.Id, n.judul, n.hari, n.tanggal, n.Tempat, n.notulis, n.Pembahasan, n.Hasil_akhir, n.penanggung_jawab, 
                n.status, n.lampiran, n.created_by_user_id,
                (SELECT COUNT(*) FROM peserta_notulen pn WHERE pn.notulen_id = n.Id) as jumlah_peserta,
                (SELECT COUNT(*) FROM kehadiran k WHERE k.notulen_id = n.Id AND k.status = 'hadir') as jumlah_hadir
                FROM notulen n 
                WHERE n.created_by_user_id = ? 
                ORDER BY n.tanggal DESC, n.created_at DESC";
$stmt_notulens = $conn->prepare($sql_notulens);
$stmt_notulens->bind_param("i", $user_id);
$stmt_notulens->execute();
$result_notulens = $stmt_notulens->get_result();

// Ambil daftar user (notulis dan tamu) untuk dropdown peserta
$sql_users = "SELECT user_id, nim, full_name, role FROM user WHERE role IN ('notulis', 'tamu') AND is_active = 1 ORDER BY full_name";
$result_users = $conn->query($sql_users);

// Set default tanggal dan hari untuk form
$default_tanggal = date('Y-m-d');
$hari_list = array('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu');
$default_hari = $hari_list[date('N') - 1]; // N adalah 1 (Senin) hingga 7 (Minggu)
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Notulen Rapat - Portal Notulis</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="notulen-rapat.css">
</head>
<body>
    <!-- Sidebar -->
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
                    <a href="notulis.php" class="nav-link">
                        <i class="fas fa-th-large nav-icon"></i>
                        <span class="nav-label">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notulen_rapat.php" class="nav-link active">
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
                    <a href="profil.php" class="nav-link">
                        <i class="fas fa-user-circle nav-icon"></i>
                        <span class="nav-label">Profil</span>
                    </a>
                </li>
            </ul>

            <ul class="nav-list secondary-nav">
                <li class="nav-item">
                    <a href="notulen_rapat.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
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
            <h1>Kelola Notulen Rapat</h1>
            <p>Buat dan kelola notulen rapat Anda di sini</p>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Tombol Tambah Notulen -->
        <div class="section-header">
            <h2><i class="fas fa-file-alt"></i> Daftar Notulen Anda</h2>
            <button type="button" id="openNotulenModal" class="btn btn-add">
                <i class="fas fa-plus"></i> Tambah Notulen Baru
            </button>
        </div>

        <!-- Daftar Notulen -->
        <div class="content-section">
            <div class="notulen-list">
                <?php if ($result_notulens && $result_notulens->num_rows > 0): ?>
                    <?php while ($notulen = $result_notulens->fetch_assoc()): ?>
                        <?php
                        $tanggal_formatted = date('d M Y', strtotime($notulen['tanggal']));
                        // Gunakan pembahasan sebagai preview
                        $preview = strlen($notulen['Pembahasan']) > 150 ? substr($notulen['Pembahasan'], 0, 150) . '...' : $notulen['Pembahasan'];
                        ?>
                        <div class="notulen-item clickable-item" data-id="<?php echo $notulen['Id']; ?>">
                            <div class="notulen-main">
                                <div class="notulen-header">
                                    <h3 class="notulen-title"><?php echo htmlspecialchars($notulen['judul']); ?></h3>
                                    <span class="notulen-status status-<?php echo $notulen['status']; ?>">
                                        <?php echo ucfirst($notulen['status']); ?>
                                    </span>
                                </div>
                                <p class="notulen-preview"><?php echo htmlspecialchars($preview); ?></p>
                                <div class="notulen-meta">
                                    <span class="notulen-date">
                                        <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($notulen['hari']); ?>, <?php echo $tanggal_formatted; ?>
                                    </span>
                                    <span class="notulen-tempat">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($notulen['Tempat']); ?>
                                    </span>
                                    <span class="notulis">
                                        <i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($notulen['notulis']); ?>
                                    </span>
                                    <span class="notulen-penanggung-jawab">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($notulen['penanggung_jawab']); ?>
                                    </span>
                                    <span class="notulen-lampiran">
                                        <i class="fas fa-users"></i> <?php echo $notulen['jumlah_peserta']; ?> peserta
                                    </span>
                                    <?php if ($notulen['status'] == 'sent' || $notulen['status'] == 'final'): ?>
                                        <span class="kehadiran-status <?php echo $notulen['jumlah_hadir'] == $notulen['jumlah_peserta'] ? 'hadir' : 'belum'; ?>">
                                            <i class="fas fa-user-check"></i> 
                                            <?php echo $notulen['jumlah_hadir']; ?> hadir
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notulen-actions">
                                <?php if ($notulen['status'] == 'draft'): ?>
                                    <a href="#" class="action-btn edit" title="Edit" onclick="openEditModal(<?php echo $notulen['Id']; ?>); return false;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="notulen_rapat.php?action=send&id=<?php echo $notulen['Id']; ?>" class="action-btn send" title="Kirim" onclick="return confirm('Kirim notulen ke peserta?')">
                                        <i class="fas fa-paper-plane"></i>
                                    </a>
                                    <a href="notulen_rapat.php?action=delete&id=<?php echo $notulen['Id']; ?>" class="action-btn delete" title="Hapus" onclick="return confirm('Yakin menghapus notulen?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    
                                <?php elseif ($notulen['status'] == 'sent'): ?>
                                    <button class="action-btn view btn-kehadiran" title="Lihat Kehadiran" data-id="<?php echo $notulen['Id']; ?>">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                    <a href="notulen_rapat.php?action=finalize&id=<?php echo $notulen['Id']; ?>" class="action-btn konfirmasi" title="Finalisasi" onclick="return confirm('Finalisasi notulen dan buat PDF?')">
                                        <i class="fas fa-check-circle"></i>
                                    </a>
                                    <a href="notulen_rapat.php?action=delete&id=<?php echo $notulen['Id']; ?>" class="action-btn delete" title="Hapus" onclick="return confirm('Yakin menghapus notulen?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    
                                <?php elseif ($notulen['status'] == 'final'): ?>
                                    <button class="action-btn view btn-kehadiran" title="Lihat Kehadiran" data-id="<?php echo $notulen['Id']; ?>">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                    <?php
                                    // Cari file PDF yang sudah digenerate
                                    $pdf_files = glob("pdf_files/notulen_{$notulen['Id']}_*.pdf");
                                    if (!empty($pdf_files)) {
                                        $pdf_file = basename($pdf_files[0]);
                                        ?>
                                        <a href="pdf_files/<?php echo $pdf_file; ?>" 
                                           class="action-btn download" title="Download PDF" target="_blank" download>
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php } else { ?>
                                        <a href="generate_pdf.php?id=<?php echo $notulen['Id']; ?>&download=1" 
                                           class="action-btn download" title="Buat PDF" target="_blank">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php } ?>
                                    <a href="notulen_rapat.php?action=delete&id=<?php echo $notulen['Id']; ?>" class="action-btn delete" title="Hapus" onclick="return confirm('Yakin menghapus notulen?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <h3>Belum ada notulen</h3>
                        <p>Mulai dengan membuat notulen baru menggunakan tombol "Tambah Notulen Baru" di atas.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Form Notulen -->
    <div class="modal-overlay" id="notulenModal">
        <div class="modal-container wide-modal">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Buat Notulen Baru</h2>
                <button class="modal-close" id="closeNotulenModal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="notulenForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Judul Rapat</label>
                            <input type="text" name="judul" class="form-control" placeholder="Masukkan judul rapat" required maxlength="35">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Hari</label>
                            <select name="hari" class="form-control" required>
                                <option value="">Pilih Hari</option>
                                <option value="Senin" <?php echo ($default_hari == 'Senin') ? 'selected' : ''; ?>>Senin</option>
                                <option value="Selasa" <?php echo ($default_hari == 'Selasa') ? 'selected' : ''; ?>>Selasa</option>
                                <option value="Rabu" <?php echo ($default_hari == 'Rabu') ? 'selected' : ''; ?>>Rabu</option>
                                <option value="Kamis" <?php echo ($default_hari == 'Kamis') ? 'selected' : ''; ?>>Kamis</option>
                                <option value="Jumat" <?php echo ($default_hari == 'Jumat') ? 'selected' : ''; ?>>Jumat</option>
                                <option value="Sabtu" <?php echo ($default_hari == 'Sabtu') ? 'selected' : ''; ?>>Sabtu</option>
                                <option value="Minggu" <?php echo ($default_hari == 'Minggu') ? 'selected' : ''; ?>>Minggu</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Tanggal Rapat</label>
                            <input type="date" name="tanggal" class="form-control" value="<?php echo $default_tanggal; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Tempat</label>
                            <input type="text" name="tempat" class="form-control" placeholder="Masukkan tempat rapat" required maxlength="55">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Notulis</label>
                            <input type="text" name="notulis" class="form-control" placeholder="Masukkan nama notulis" required maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Penanggung Jawab</label>
                            <input type="text" name="penanggung_jawab" class="form-control" placeholder="Nama penanggung jawab rapat" required maxlength="50">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Peserta Rapat</label>
                        <div class="peserta-search-container">
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="pesertaSearch" class="peserta-search" placeholder="Cari peserta (nama atau NIM)...">
                            </div>
                            <div class="search-results" id="searchResults">
                                <?php 
                                // Reset pointer result users
                                $result_users->data_seek(0);
                                while ($user = $result_users->fetch_assoc()): ?>
                                    <div class="search-result-item" data-id="<?php echo $user['user_id']; ?>" 
                                         data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                         data-nim="<?php echo htmlspecialchars($user['nim']); ?>"
                                         data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                        <div class="user-info">
                                            <div class="result-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                            <div class="result-nim"><?php echo htmlspecialchars($user['nim']); ?></div>
                                            <div class="result-role"><?php echo ucfirst($user['role']); ?></div>
                                        </div>
                                        <button type="button" class="result-add" title="Tambahkan">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <div class="peserta-list-container">
                            <div class="peserta-list-header">
                                <span>Peserta Terpilih</span>
                                <span id="pesertaCount">0 peserta</span>
                            </div>
                            <div class="peserta-list" id="selectedPesertaList">
                                <div class="no-participants">Belum ada peserta yang ditambahkan</div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="peserta_ids" id="pesertaIds" value="">
                    </div>
                    
                    <div class="form-group">
                        <label>Pembahasan</label>
                        <textarea name="pembahasan" class="form-control" placeholder="Tulis pembahasan rapat di sini..." rows="5"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Hasil Akhir</label>
                        <textarea name="hasil_akhir" class="form-control" placeholder="Tulis hasil akhir rapat di sini..." rows="5"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Lampiran (Opsional)</label>
                        <input type="file" name="lampiran[]" class="form-control" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.zip,.rar,.ppt,.pptx">
                        <div class="file-hint">Format: PDF, DOC, DOCX, JPG, PNG, TXT, ZIP, RAR, PPT. Maks 10MB per file.</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="create_notulen" class="btn btn-submit" data-action="draft">
                            <i class="fas fa-save"></i> Simpan Draft
                        </button>
                        <button type="submit" name="create_notulen" class="btn btn-add" data-action="send">
                            <i class="fas fa-paper-plane"></i> Simpan dan Kirim
                        </button>
                        <button type="button" class="btn btn-cancel" id="cancelNotulenForm">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <input type="hidden" name="action" id="formAction" value="draft">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Kehadiran -->
    <div class="modal-overlay" id="kehadiranModal">
        <div class="modal-container">
            <div class="modal-header">
                <h2><i class="fas fa-user-check"></i> Daftar Kehadiran</h2>
                <button class="modal-close" id="closeKehadiranModal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="kehadiranContent">
                    <div class="loading">Memuat data kehadiran...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Notulen -->
    <div class="modal-overlay" id="editNotulenModal">
        <div class="modal-container wide-modal">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Notulen</h2>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="editNotulenForm" action="notulen_rapat.php">
                    <input type="hidden" name="notulen_id" id="editNotulenId">
                    <input type="hidden" name="edit_notulen" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Judul Rapat</label>
                            <input type="text" name="judul" id="editJudul" class="form-control" placeholder="Masukkan judul rapat" required maxlength="35">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Hari</label>
                            <select name="hari" id="editHari" class="form-control" required>
                                <option value="">Pilih Hari</option>
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                                <option value="Sabtu">Sabtu</option>
                                <option value="Minggu">Minggu</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Tanggal Rapat</label>
                            <input type="date" name="tanggal" id="editTanggal" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Tempat</label>
                            <input type="text" name="tempat" id="editTempat" class="form-control" placeholder="Masukkan tempat rapat" required maxlength="55">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="required">Notulis</label>
                            <input type="text" name="notulis" id="editNotulis" class="form-control" placeholder="Masukkan nama notulis" required maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Penanggung Jawab</label>
                            <input type="text" name="penanggung_jawab" id="editPenanggungJawab" class="form-control" placeholder="Nama penanggung jawab rapat" required maxlength="50">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Peserta Rapat</label>
                        <div class="peserta-search-container">
                            <div class="search-wrapper">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="editPesertaSearch" class="peserta-search" placeholder="Cari peserta (nama atau NIM)...">
                            </div>
                            <div class="search-results" id="editSearchResults">
                                <?php 
                                // Reset pointer result users
                                $result_users->data_seek(0);
                                while ($user = $result_users->fetch_assoc()): ?>
                                    <div class="search-result-item" data-id="<?php echo $user['user_id']; ?>" 
                                         data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                         data-nim="<?php echo htmlspecialchars($user['nim']); ?>"
                                         data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                        <div class="user-info">
                                            <div class="result-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                            <div class="result-nim"><?php echo htmlspecialchars($user['nim']); ?></div>
                                            <div class="result-role"><?php echo ucfirst($user['role']); ?></div>
                                        </div>
                                        <button type="button" class="result-add" title="Tambahkan">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <div class="peserta-list-container">
                            <div class="peserta-list-header">
                                <span>Peserta Terpilih</span>
                                <span id="editPesertaCount">0 peserta</span>
                            </div>
                            <div class="peserta-list" id="editSelectedPesertaList">
                                <div class="no-participants">Belum ada peserta yang ditambahkan</div>
                            </div>
                        </div>
                        
                        <input type="hidden" name="peserta_ids" id="editPesertaIds" value="">
                    </div>
                    
                    <div class="form-group">
                        <label>Pembahasan</label>
                        <textarea name="pembahasan" id="editPembahasan" class="form-control" placeholder="Tulis pembahasan rapat di sini..." rows="5"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Hasil Akhir</label>
                        <textarea name="hasil_akhir" id="editHasilAkhir" class="form-control" placeholder="Tulis hasil akhir rapat di sini..." rows="5"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Lampiran Saat Ini</label>
                        <div id="currentFiles" class="file-list"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tambahkan Lampiran Baru (Opsional)</label>
                        <input type="file" name="lampiran[]" class="form-control" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt,.zip,.rar,.ppt,.pptx">
                        <div class="file-hint">Format: PDF, DOC, DOCX, JPG, PNG, TXT, ZIP, RAR, PPT. Maks 10MB per file.</div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <button type="button" class="btn btn-cancel" id="cancelEditForm">
                            <i class="fas fa-times"></i> Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden form untuk set action -->
    <form id="actionForm" method="get" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="id" id="actionId">
    </form>
    <script src="notulen-rapat.js"></script>
</body>
</html>
<?php
// Close connections
if (isset($stmt_notulens) && $stmt_notulens) {
    $stmt_notulens->close();
}
if (isset($conn) && $conn) {
    $conn->close();
}
?>
