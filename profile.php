<?php
session_start();
require_once 'koneksi.php';

/* ================== CEK LOGIN ================== */
if (!isset($_SESSION['logged_in'])) {
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

/* ================== DASHBOARD ROLE ================== */
$dashboard = ($role === 'admin') ? 'admin.php'
           : (($role === 'notulis') ? 'Notulis.php'
           : 'tamu.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile</title>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- External CSS -->
    <link rel="stylesheet" href="profile.css">
</head>

<body>

<!-- ================= SIDEBAR ================= -->
<div class="sidebar">

    <div class="sidebar-header">
        <div class="logo-area">
            <a href="#" class="header-logo">
                <img src="poltek1.png" alt="Politeknik Negeri Batam">
            </a>
        </div>

        <button class="toggler">
            <span class="fas fa-chevron-left"></span>
        </button>
    </div>

    <nav class="sidebar-nav">

        <!-- ===== PRIMARY NAV ===== -->
        <ul class="nav-list primary-nav">

            <li>
                <a href="<?= $dashboard ?>" class="nav-link">
                    <i class="fas fa-th-large nav-icon"></i>
                    <span class="nav-label">Dashboard</span>
                </a>
            </li>

            <?php if ($role === 'admin'): ?>

                <li>
                    <a href="jadwal_rapat.php" class="nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-label">Jadwal Rapat</span>
                    </a>
                </li>

                <li>
                    <a href="notulen_list_admin.php" class="nav-link">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span class="nav-label">Notulen Rapat</span>
                    </a>
                </li>

                <li>
                    <a href="user_management.php" class="nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-label">User Management</span>
                    </a>
                </li>

            <?php elseif ($role === 'notulis'): ?>

                <li>
                    <a href="notulis.php" class="nav-link">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span class="nav-label">Notulen Rapat</span>
                    </a>
                </li>

                <li>
                    <a href="jadwal_rapat.php" class="nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-label">Jadwal Rapat</span>
                    </a>
                </li>

                <li>
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell nav-icon"></i>
                        <span class="nav-label">Notifikasi</span>
                    </a>
                </li>

            <?php elseif ($role === 'tamu'): ?>

                <li>
                    <a href="tamu.php" class="nav-link">
                        <i class="fas fa-file-alt nav-icon"></i>
                        <span class="nav-label">Notulen Rapat</span>
                    </a>
                </li>

                <li>
                    <a href="jadwal_rapat_tamu.php" class="nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-label">Jadwal Rapat</span>
                    </a>
                </li>

            <?php endif; ?>

        </ul>

        <!-- ===== SECONDARY NAV ===== -->
        <ul class="nav-list secondary-nav">

            <li>
                <a href="profile.php" class="nav-link active">
                    <i class="fas fa-user-circle nav-icon"></i>
                    <span class="nav-label">Profil Saya</span>
                </a>
            </li>

            <li>
                <a href="login.php?action=logout" class="nav-link"
                   onclick="return confirm('Yakin ingin logout?');">
                    <i class="fas fa-sign-out-alt nav-icon"></i>
                    <span class="nav-label">Keluar</span>
                </a>
            </li>

            <!-- PROFIL LOGIN -->
            <li class="nav-item profile-user">
                <img src="<?= $fotoProfil ?>" class="profile-avatar" alt="Foto Profil">

                <div class="profile-info">
                    <span class="profile-name">
                        <?= htmlspecialchars($userLogin['full_name']) ?>
                    </span>
                    <span class="profile-role">
                        <?= ucfirst($userLogin['role']) ?>
                    </span>
                </div>
            </li>

        </ul>

    </nav>
</div>

<!-- ================= MAIN CONTENT ================= -->
<div class="main-content">

    <div class="dashboard-header">
        <h1>Profile</h1>
    </div>

    <div class="profile-container">

        <!-- GANTI FOTO -->
        <div class="profile-section">
            <form action="proses_photo.php" method="POST" enctype="multipart/form-data">
                <h4>Ganti Foto Profil</h4>

                <div class="profile-picture">
                    <img src="<?= $current_photo_url ?>" alt="Foto Profil"
                         style="width:150px;height:150px;border-radius:50%;object-fit:cover;">
                </div>

                <input type="file" name="new_file_profile_pic" accept="image/*" required>
                <button type="submit" name="upload_btn">Ganti Foto</button>
            </form>
        </div>

        <!-- GANTI NAMA -->
        <div class="profile-section">
            <form action="process_name.php" method="POST">
                <h4>Ganti Nama Lengkap</h4>

                <label for="full_name">Nama Lengkap Baru</label>
                <input type="text" id="full_name" name="new_full_name" required
                       value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>">

                <button type="submit" name="update_name_btn">Simpan Nama</button>
            </form>
        </div>

        <!-- GANTI PASSWORD -->
        <div class="profile-section">
            <form action="process_password.php" method="POST">
                <h4>Ganti Password</h4>

                <label>Password Lama</label>
                <input type="password" name="old_password" required>

                <label>Password Baru</label>
                <input type="password" name="new_password" required>

                <label>Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" required>

                <button type="submit" name="update_password_btn">
                    Ganti Password
                </button>
            </form>
        </div>

    </div>
</div>

<script src="admin.js"></script>

</body>
</html>

<?php
if (isset($conn)) {
    $conn->close();
}
?>
