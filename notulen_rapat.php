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
$foto_sekarang = $userLogin['photo'];
$path_valid = (!empty($userLogin['photo'])) ? $userLogin['photo'] : 'uploads/profile_photos/default_profile.png';
$current_photo_url = $path_valid . "?t=" . time();

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

/* ================== PAGINATION ================== */
$limit = 5; // Notulen per halaman
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Hitung total notulen untuk pagination
$sql_count = "SELECT COUNT(*) as total FROM notulen WHERE created_by_user_id = ?";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_notulens = $result_count->fetch_assoc()['total'];
$stmt_count->close();

// Hitung total halaman
$total_pages = ceil($total_notulens / $limit);

// Validasi page number
if ($page > $total_pages && $total_pages > 0) {
    header("Location: notulen_rapat.php?page=" . $total_pages);
    exit();
}

// Handle form submission untuk buat notulen baru
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_notulen'])) {
        $judul = $_POST['judul'] ?? '';
        $hari = $_POST['hari'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';
        $tempat = $_POST['tempat'] ?? '';
        $notulis = $_POST['notulis'] ?? '';
        $jurusan = $_POST['jurusan'] ?? '';
        $penanggung_jawab = $_POST['penanggung_jawab'] ?? '';
        $pembahasan = $_POST['pembahasan'] ?? '';
        $hasil_akhir = $_POST['hasil_akhir'] ?? '';
        $peserta_ids = isset($_POST['peserta_ids']) ? explode(',', $_POST['peserta_ids']) : [];
        
        // Validasi
        if (empty($judul) || empty($hari) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai) || 
            empty($tempat) || empty($notulis) || empty($penanggung_jawab) || empty($jurusan)) {
            $error_msg = "Field wajib diisi!";
        } else {
            // Konversi format tanggal
            $tanggal_formatted = date('Y-m-d', strtotime($tanggal));
            
            // Mulai transaction
            $conn->begin_transaction();

            try {
                // Insert notulen dengan field baru termasuk jurusan dan jam
                $sql_notulen = "INSERT INTO notulen (judul, hari, tanggal, jam_mulai, jam_selesai, 
                tempat, notulis, jurusan, pembahasan, hasil_akhir, penanggung_jawab, status, created_by_user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW())";
                
                $stmt_notulen = $conn->prepare($sql_notulen);
                $stmt_notulen->bind_param("sssssssssssi", $judul, $hari, $tanggal_formatted, 
                                        $jam_mulai, $jam_selesai, $tempat, $notulis, $jurusan, 
                                        $pembahasan, $hasil_akhir, $penanggung_jawab, $user_id);
                                
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
                        $sql_update = "UPDATE notulen SET status = 'sent' WHERE id = ?";
                        $stmt_update = $conn->prepare($sql_update);
                        $stmt_update->bind_param("i", $notulen_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                        $success_msg = "Notulen berhasil dibuat dan dikirim ke peserta!";
                    }
                    
                    // REDIRECT untuk menghindari duplikasi saat refresh
                    $_SESSION['temp_success_msg'] = $success_msg;
                    header("Location: notulen_rapat.php?page=" . $page);
                    exit();
                    
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
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';
        $tempat = $_POST['tempat'] ?? '';
        $notulis = $_POST['notulis'] ?? '';
        $jurusan = $_POST['jurusan'] ?? '';
        $penanggung_jawab = $_POST['penanggung_jawab'] ?? '';
        $pembahasan = $_POST['pembahasan'] ?? '';
        $hasil_akhir = $_POST['hasil_akhir'] ?? '';
        $peserta_ids = isset($_POST['peserta_ids']) ? explode(',', $_POST['peserta_ids']) : [];
        
        // Validasi
        if (empty($judul) || empty($hari) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai) || 
            empty($tempat) || empty($notulis) || empty($penanggung_jawab) || empty($jurusan) || $notulen_id == 0) {
            $error_msg = "Field wajib diisi!";
        } else {
            // Konversi format tanggal
            $tanggal_formatted = date('Y-m-d', strtotime($tanggal));
            
            $conn->begin_transaction();
            
            try {
                // Update notulen dengan field baru termasuk jurusan dan jam
                $sql_update = "UPDATE notulen 
                                SET judul = ?, hari = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?, 
                                    tempat = ?, notulis = ?, jurusan = ?,
                                    pembahasan = ?, hasil_akhir = ?, penanggung_jawab = ?
                                WHERE id = ? AND created_by_user_id = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sssssssssssii", $judul, $hari, $tanggal_formatted, 
                                        $jam_mulai, $jam_selesai, $tempat, $notulis, $jurusan, 
                                        $pembahasan, $hasil_akhir, $penanggung_jawab, 
                                        $notulen_id, $user_id);
            
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
                    
                    $conn->commit(); // <-- INI SUDAH ADA
                    $success_msg = "Notulen berhasil diperbarui!";
                    
                    // REDIRECT
                    $_SESSION['temp_success_msg'] = $success_msg;
                    header("Location: notulen_rapat.php?page=" . $page);
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

// Handle aksi notulen (delete, send, finalize, send_email)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $notulen_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    // Clear any previous session messages
    unset($_SESSION['temp_success_msg']);
    unset($_SESSION['temp_error_msg']);
    
    if ($action == 'delete') {
        // Hapus notulen
        $sql_delete = "DELETE FROM notulen WHERE id = ? AND created_by_user_id = ?";
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
            
            $_SESSION['temp_success_msg'] = "Notulen berhasil dihapus!";
        } else {
            $_SESSION['temp_error_msg'] = "Gagal menghapus notulen!";
        }
        $stmt_delete->close();
        
        // Redirect untuk menghindari resubmission
        header("Location: notulen_rapat.php?page=" . $page);
        exit();
        
    } elseif ($action == 'send') {
        // Kirim notulen ke peserta
        $sql_update = "UPDATE notulen SET status = 'sent' WHERE id = ? AND created_by_user_id = ? AND status = 'draft'";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $notulen_id, $user_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['temp_success_msg'] = "Notulen berhasil dikirim ke peserta!";
        } else {
            $_SESSION['temp_error_msg'] = "Gagal mengirim notulen!";
        }
        $stmt_update->close();
        
        // Redirect untuk menghindari resubmission
        header("Location: notulen_rapat.php?page=" . $page);
        exit();
        
    } elseif ($action == 'send_email') {
        // Kirim email undangan ke peserta
        require_once 'send_email_.php'; 
        
        if (sendEmailToPeserta($notulen_id, $conn)) {
            $_SESSION['temp_success_msg'] = "Email undangan berhasil dikirim ke peserta!";
        } else {
            $_SESSION['temp_error_msg'] = "Gagal mengirim email undangan!";
        }
        
        // Redirect untuk menghindari resubmission
        header("Location: notulen_rapat.php?page=" . $page);
        exit();
        
    } elseif ($action == 'finalize') {
        // Finalize notulen dan generate PDF
        $sql_update = "UPDATE notulen SET status = 'final' WHERE id = ? AND created_by_user_id = ? AND status = 'sent'";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $notulen_id, $user_id);
        
        try {
            if ($stmt_update->execute()) {
                // Set peserta yang belum konfirmasi sebagai tidak hadir
                $sql_peserta = "SELECT pn.user_id FROM peserta_notulen pn 
                            LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id 
                            WHERE pn.notulen_id = ? AND (k.status IS NULL OR k.status = 'belum')";
                $stmt_peserta = $conn->prepare($sql_peserta);
                $stmt_peserta->bind_param("i", $notulen_id);
                $stmt_peserta->execute();
                $result_peserta = $stmt_peserta->get_result();
                
                while ($peserta = $result_peserta->fetch_assoc()) {
                    $sql_insert_kehadiran = "INSERT INTO kehadiran (notulen_id, user_id, status) 
                                            VALUES (?, ?, 'tidak_hadir')
                                            ON DUPLICATE KEY UPDATE status = 'tidak_hadir'";
                    $stmt_insert = $conn->prepare($sql_insert_kehadiran);
                    $stmt_insert->bind_param("ii", $notulen_id, $peserta['user_id']);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
                $stmt_peserta->close();
                
                // Generate PDF dengan format baru
                if (file_exists('generate_pdf.php')) {
                    require_once 'generate_pdf.php';
                    // KIRIMKAN KONEKSI KE FUNGSI
                    $pdf_path = generateNotulenPDF($notulen_id, $conn);
                    
                    if ($pdf_path) {
                        $_SESSION['temp_success_msg'] = "Notulen berhasil difinalisasi! PDF telah dibuat dengan format baru.";
                    } else {
                        $_SESSION['temp_success_msg'] = "Notulen berhasil difinalisasi, tetapi gagal membuat PDF.";
                    }
                } else {
                    $_SESSION['temp_success_msg'] = "Notulen berhasil difinalisasi.";
                }
            } else {
                $_SESSION['temp_error_msg'] = "Gagal memfinalisasi notulen: " . $stmt_update->error;
            }
            $stmt_update->close();
            
        } catch (Exception $e) {
            $_SESSION['temp_error_msg'] = "Error: " . $e->getMessage();
        }
        
        header("Location: notulen_rapat.php?page=" . $page);
        exit();
    }
}

// Cek pesan dari session (setelah redirect)
if (isset($_SESSION['temp_success_msg'])) {
    $success_msg = $_SESSION['temp_success_msg'];
    unset($_SESSION['temp_success_msg']); // Clear setelah digunakan
}
if (isset($_SESSION['temp_error_msg'])) {
    $error_msg = $_SESSION['temp_error_msg'];
    unset($_SESSION['temp_error_msg']); // Clear setelah digunakan
}

// Ambil daftar notulen yang dibuat oleh notulis dengan pagination
$sql_notulens = "SELECT n.id, n.judul, n.hari, n.tanggal, n.jam_mulai, n.jam_selesai, n.tempat, n.notulis, n.jurusan, n.pembahasan, n.hasil_akhir, n.penanggung_jawab, 
                n.status, n.created_by_user_id,
                (SELECT COUNT(*) FROM peserta_notulen pn WHERE pn.notulen_id = n.id) as jumlah_peserta,
                (SELECT COUNT(*) FROM kehadiran k WHERE k.notulen_id = n.id AND k.status = 'hadir') as jumlah_hadir
                FROM notulen n 
                WHERE n.created_by_user_id = ? 
                ORDER BY n.tanggal DESC, n.created_at DESC
                LIMIT ? OFFSET ?";
$stmt_notulens = $conn->prepare($sql_notulens);
$stmt_notulens->bind_param("iii", $user_id, $limit, $offset);
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
            </ul>

            <ul class="nav-list secondary-nav">
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle nav-icon"></i>
                        <span class="nav-label">Profil Saya</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notulen_rapat.php?action=logout" class="nav-link"
                        onclick="return confirm('Yakin ingin logout?');">
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
    </div>>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="dashboard-header">
            <h1>Kelola Notulen Rapat</h1>
            <p>Buat dan kelala notulen rapat Anda di sini</p><br>
        </div>

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
                        $jam_mulai_formatted = date('H:i', strtotime($notulen['jam_mulai']));
                        $jam_selesai_formatted = date('H:i', strtotime($notulen['jam_selesai']));
                        ?>
                <div class="notulen-item clickable-item" data-id="<?php echo $notulen['id']; ?>">
                    <div class="notulen-main">
                        <div class="notulen-header">
                            <h3 class="notulen-title"><?php echo htmlspecialchars($notulen['judul']); ?></h3>
                            <span class="notulen-status status-<?php echo $notulen['status']; ?>">
                                <?php echo ucfirst($notulen['status']); ?>
                            </span>
                        </div>
                        <div class="notulen-meta">
                            <span class="notulen-meta-item">
                                <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($notulen['hari']); ?>,
                                <?php echo $tanggal_formatted; ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-clock"></i> <?php echo $jam_mulai_formatted; ?> -
                                <?php echo $jam_selesai_formatted; ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($notulen['tempat']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-user-edit"></i> <?php echo htmlspecialchars($notulen['notulis']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-graduation-cap"></i>
                                <?php echo htmlspecialchars($notulen['jurusan']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($notulen['penanggung_jawab']); ?>
                            </span>
                            <span class="notulen-meta-item">
                                <i class="fas fa-users"></i> <?php echo $notulen['jumlah_peserta']; ?> peserta
                            </span>
                            <?php if ($notulen['status'] == 'sent' || $notulen['status'] == 'final'): ?>
                            <span
                                class="kehadiran-status <?php echo $notulen['jumlah_hadir'] == $notulen['jumlah_peserta'] ? 'hadir' : 'belum'; ?>">
                                <i class="fas fa-user-check"></i>
                                <?php echo $notulen['jumlah_hadir']; ?> hadir
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notulen-actions">
                        <?php if ($notulen['status'] == 'draft'): ?>
                        <a href="#" class="action-btn edit" title="Edit"
                            onclick="openEditModal(<?php echo $notulen['id']; ?>); return false;">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="notulen_rapat.php?action=send&id=<?php echo $notulen['id']; ?>" class="action-btn send"
                            title="Kirim" onclick="return confirmAction('Kirim notulen ke peserta?')">
                            <i class="fas fa-paper-plane"></i>
                        </a>
                        <!-- Tombol Kirim Email Undangan -->
                        <button class="action-btn email" title="Kirim Email Undangan" 
                        data-id="<?php echo $notulen['id']; ?>">
                    <i class="fas fa-envelope"></i>
                </button>
                        </button>
                        <a href="notulen_rapat.php?action=delete&id=<?php echo $notulen['id']; ?>"
                            class="action-btn delete" title="Hapus"
                            onclick="return confirmAction('Yakin menghapus notulen?')">
                            <i class="fas fa-trash"></i>
                        </a>

                        <?php elseif ($notulen['status'] == 'sent'): ?>
                        <button class="action-btn view btn-kehadiran" title="Lihat Kehadiran"
                            data-id="<?php echo $notulen['id']; ?>">
                            <i class="fas fa-user-check"></i>
                        </button>
                        <!-- Tombol Kirim Email Undangan -->
                        <button class="action-btn email" title="Kirim Email Undangan"
                            data-id="<?php echo $notulen['id']; ?>"
                            onclick="sendEmailInvitation(<?php echo $notulen['id']; ?>)">
                            <i class="fas fa-envelope"></i>
                        </button>
                        <a href="notulen_rapat.php?action=finalize&id=<?php echo $notulen['id']; ?>"
                            class="action-btn konfirmasi" title="Finalisasi"
                            onclick="return confirmAction('Finalisasi notulen dan buat PDF?')">
                            <i class="fas fa-check-circle"></i>
                        </a>

                        <?php elseif ($notulen['status'] == 'final'): ?>
                        <button class="action-btn view btn-kehadiran" title="Lihat Kehadiran"
                            data-id="<?php echo $notulen['id']; ?>">
                            <i class="fas fa-user-check"></i>
                        </button>
                        <?php
                                    // Cari file PDF yang sudah digenerate
                                    $pdf_files = glob("pdf_files/notulen_{$notulen['id']}_*.pdf");
                                    if (!empty($pdf_files)) {
                                        $pdf_file = basename($pdf_files[0]);
                                        ?>
                        <a href="pdf_files/<?php echo $pdf_file; ?>" class="action-btn download" title="Download PDF"
                            target="_blank" download>
                            <i class="fas fa-download"></i>
                        </a>
                        <?php } else { ?>
                        <a href="generate_pdf.php?id=<?php echo $notulen['id']; ?>&download=1"
                            class="action-btn download" title="Buat PDF" target="_blank">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <?php } ?>
                        <a href="notulen_rapat.php?action=delete&id=<?php echo $notulen['id']; ?>"
                            class="action-btn delete" title="Hapus"
                            onclick="return confirmAction('Yakin menghapus notulen?')">
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination-info">
                    Menampilkan <?php echo ($offset + 1); ?>-<?php echo min($offset + $limit, $total_notulens); ?> dari
                    <?php echo $total_notulens; ?> notulen
                </div>

                <ul class="pagination">
                    <?php if ($page > 1): ?>
                    <li>
                        <a href="?page=1" title="Halaman pertama">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li>
                        <a href="?page=<?php echo $page - 1; ?>" title="Sebelumnya">
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
                    // Tampilkan 3 halaman sebelum dan sesudah halaman aktif
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li><a href="?page=1">1</a></li>';
                        if ($start_page > 2) echo '<li class="disabled"><span>...</span></li>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                    <li <?php echo ($i == $page) ? 'class="active"' : ''; ?>>
                        <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): 
                        if ($end_page < $total_pages - 1) echo '<li class="disabled"><span>...</span></li>';
                    ?>
                    <li>
                        <a href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                    </li>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                    <li>
                        <a href="?page=<?php echo $page + 1; ?>" title="Berikutnya">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li>
                        <a href="?page=<?php echo $total_pages; ?>" title="Halaman terakhir">
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
                            <label for="createJudul" class="required">Judul Rapat</label>
                            <input type="text" name="judul" id="createJudul" class="form-control"
                                placeholder="Masukkan judul rapat" required maxlength="35">
                        </div>

                        <div class="form-group">
                            <label for="createHari" class="required">Hari</label>
                            <select name="hari" id="createHari" class="form-control" required>
                                <option value="">Pilih Hari</option>
                                <option value="Senin" <?php echo ($default_hari == 'Senin') ? 'selected' : ''; ?>>Senin
                                </option>
                                <option value="Selasa" <?php echo ($default_hari == 'Selasa') ? 'selected' : ''; ?>>
                                    Selasa</option>
                                <option value="Rabu" <?php echo ($default_hari == 'Rabu') ? 'selected' : ''; ?>>Rabu
                                </option>
                                <option value="Kamis" <?php echo ($default_hari == 'Kamis') ? 'selected' : ''; ?>>Kamis
                                </option>
                                <option value="Jumat" <?php echo ($default_hari == 'Jumat') ? 'selected' : ''; ?>>Jumat
                                </option>
                                <option value="Sabtu" <?php echo ($default_hari == 'Sabtu') ? 'selected' : ''; ?>>Sabtu
                                </option>
                                <option value="Minggu" <?php echo ($default_hari == 'Minggu') ? 'selected' : ''; ?>>
                                    Minggu</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="createTanggal" class="required">Tanggal Rapat</label>
                            <input type="date" name="tanggal" id="createTanggal" class="form-control"
                                value="<?php echo $default_tanggal; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="createTempat" class="required">Tempat</label>
                            <input type="text" name="tempat" id="createTempat" class="form-control"
                                placeholder="Masukkan tempat rapat" required maxlength="55">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="createJamMulai" class="required">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="createJamMulai" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="createJamSelesai" class="required">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="createJamSelesai" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="createNotulis" class="required">Notulis</label>
                            <input type="text" name="notulis" id="createNotulis" class="form-control"
                                placeholder="Masukkan nama notulis" required maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="createJurusan" class="required">Jurusan</label>
                            <input type="text" name="jurusan" id="createJurusan" class="form-control"
                                placeholder="Masukkan jurusan" required maxlength="100">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="createPenanggungJawab" class="required">Penanggung Jawab</label>
                            <input type="text" name="penanggung_jawab" id="createPenanggungJawab" class="form-control"
                                placeholder="Nama penanggung jawab rapat" required maxlength="50">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="pesertaSearch" class="required">Peserta Rapat</label>
                        <div class="peserta-search-container">
                            <div class="search-wrapper">

                                <input type="text" id="pesertaSearch" class="peserta-search"
                                    placeholder="Cari peserta (nama atau NIM)..." aria-label="Cari peserta rapat">
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
                                        <div class="result-name"><?php echo htmlspecialchars($user['full_name']); ?>
                                        </div>
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
                        <label for="createPembahasan">Pembahasan</label>
                        <textarea name="pembahasan" id="createPembahasan" class="form-control"
                            placeholder="Tulis pembahasan rapat di sini..." rows="5"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="createHasilAkhir">Hasil Akhir</label>
                        <textarea name="hasil_akhir" id="createHasilAkhir" class="form-control"
                            placeholder="Tulis hasil akhir rapat di sini..." rows="5"></textarea>
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
                <form method="POST" id="editNotulenForm" action="notulen_rapat.php">
                    <input type="hidden" name="notulen_id" id="editNotulenId">
                    <input type="hidden" name="edit_notulen" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editJudul" class="required">Judul Rapat</label>
                            <input type="text" name="judul" id="editJudul" class="form-control"
                                placeholder="Masukkan judul rapat" required maxlength="35">
                        </div>

                        <div class="form-group">
                            <label for="editHari" class="required">Hari</label>
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
                            <label for="editTanggal" class="required">Tanggal Rapat</label>
                            <input type="date" name="tanggal" id="editTanggal" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="editTempat" class="required">Tempat</label>
                            <input type="text" name="tempat" id="editTempat" class="form-control"
                                placeholder="Masukkan tempat rapat" required maxlength="55">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editJamMulai" class="required">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="editJamMulai" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="editJamSelesai" class="required">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="editJamSelesai" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editNotulis" class="required">Notulis</label>
                            <input type="text" name="notulis" id="editNotulis" class="form-control"
                                placeholder="Masukkan nama notulis" required maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="editJurusan" class="required">Jurusan</label>
                            <input type="text" name="jurusan" id="editJurusan" class="form-control"
                                placeholder="Masukkan jurusan" required maxlength="100">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editPenanggungJawab" class="required">Penanggung Jawab</label>
                            <input type="text" name="penanggung_jawab" id="editPenanggungJawab" class="form-control"
                                placeholder="Nama penanggung jawab rapat" required maxlength="50">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editPesertaSearch" class="required">Peserta Rapat</label>
                        <div class="peserta-search-container">
                            <div class="search-wrapper">
                                <input type="text" id="editPesertaSearch" class="peserta-search"
                                    placeholder="Cari peserta (nama atau NIM)..." aria-label="Cari peserta rapat">
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
                                        <div class="result-name"><?php echo htmlspecialchars($user['full_name']); ?>
                                        </div>
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
                        <label for="editPembahasan">Pembahasan</label>
                        <textarea name="pembahasan" id="editPembahasan" class="form-control"
                            placeholder="Tulis pembahasan rapat di sini..." rows="5"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="editHasilAkhir">Hasil Akhir</label>
                        <textarea name="hasil_akhir" id="editHasilAkhir" class="form-control"
                            placeholder="Tulis hasil akhir rapat di sini..." rows="5"></textarea>
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

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    <script>
    // Inisialisasi setelah DOM siap
    document.addEventListener('DOMContentLoaded', function () {
        // Inisialisasi sidebar
        if (typeof initSidebar === 'function') {
            initSidebar();
        }

        // Tampilkan notifikasi dari PHP jika ada
        <?php if (!empty($success_msg)): ?>
            setTimeout(() => {
                if (typeof showToast === 'function') {
                    showToast('<?php echo addslashes($success_msg); ?>', 'success');
                }
            }, 300);
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            setTimeout(() => {
                if (typeof showToast === 'function') {
                    showToast('<?php echo addslashes($error_msg); ?>', 'error');
                }
            }, 300);
        <?php endif; ?>

        // Sembunyikan loading overlay setelah delay
        setTimeout(() => {
            if (typeof hideLoading === 'function') {
                hideLoading();
            }
        }, 500);
    });
</script>
    </script>

    <script src="notulen-rapat.js"></script>
</body>

</html>
<?php
// Close connections
if (isset($stmt_notulens) && $stmt_notulens) {
    $stmt_notulens->close();
}
