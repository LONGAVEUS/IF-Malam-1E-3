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
    SELECT full_name, role, photo, jurusan 
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- External CSS -->
    <link rel="stylesheet" href="profile.css">
</head>

<body>

    <!-- ================= SIDEBAR ================= -->
    <div class="sidebar">

        <div class="sidebar-header">
            <div class="logo-area">
                <a href="#" class="header-logo">
                    <img src="if.png" alt="Politeknik Negeri Batam">
                </a>
            </div>

            <button class="toggler">
                <span class="dekstop-icon"></span>
                <div class="hamburger-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
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
                        <span class="nav-label">Daftar Pengguna</span>
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
                    <a href="login.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
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
                        <img src="<?= $current_photo_url ?>" alt="Foto Profil" id="priofile-img-zoom"
                            onclick="openModal(this.src)" style="cursor: zoom-in;">
                    </div>

                    <div id="imageModal" class="image-zoom-modal" onclick="closeModal()">
                        <span class="close-zoom">&times;</span>
                        <img class="modal-content-zoom" id="imgFull">
                    </div>

                    <div class="upload-wrapper">
                        <label for="file-upload" class="custom-file-upload">
                            <i class="fas fa-camera"></i> Ganti Foto
                        </label>
                        <input id="file-upload" type="file" name="new_file_profile_pic" accept="image/*"
                            onchange="updateFileName()">
                        <span id="file-name-display"></span>
                    </div>
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

            <!-- INFO ROLE -->
            <div class="profile-section">
                <h4>Role</h4>

                <input type="text" value="<?= htmlspecialchars($_SESSION['role'] ?? '') ?>" disabled>
            </div>

            <div class="profile-section">
                <h4>Jurusan</h4>
                <input type="text" value="<?= htmlspecialchars($userLogin['jurusan'] ?? 'Belum Diatur') ?>" disabled>
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

    <script>
        function updateFileName() {
            const input = document.getElementById('file-upload');
            const display = document.getElementById('file-name-display');
            const preview = document.querySelector(".profile-picture img");

            if (input && input.files.length > 0) {
                display.style.opacity = "1";

                const reader = new FileReader();
                reader.onload = function (e) {
                    if (preview) preview.src = e.target.result;

                    setTimeout(() => {
                        document.getElementById('profile-form').submit();
                    }, 500);
                };
                reader.readAsDataURL(input.files[0]);

            } else {
                if (display) display.textContent = "Belum ada file dipilih";
            }
        }

        function openModal(src) {
            const modal = document.getElementById("imageModal");
            const modalImg = document.getElementById("imgFull");
            modal.style.display = "block";
            modalImg.src = src;
        }

        function closeModal() {
            document.getElementById("imageModal").style.display = "none";
        }

        document.addEventListener("DOMContentLoaded", function () {
            const sidebar = document.querySelector(".sidebar");
            const toggler = document.querySelector(".toggler");
            const sidebarNav = document.querySelector(".sidebar-nav");

            if (!sidebar || !toggler || !sidebarNav) return;

            /* ================= TOGGLE MENU (MOBILE) ================= */
            toggler.addEventListener("click", function (e) {
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                    sidebarNav.classList.toggle("active");
                }
            });

            /* ================= CLOSE DROPDOWN SAAT KLIK DI LUAR ================= */
            document.addEventListener("click", function (e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target)) {
                        sidebarNav.classList.remove("active");
                    }
                }
            });

            /* ================= RESET SAAT RESIZE ================= */
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
if (isset($conn)) {
    $conn->close();
}
?>
