<?php
session_start();

// Pastikan sudah login
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

require_once 'koneksi.php';

$role = $_SESSION['role'];      
$username = $_SESSION['username'];
$photo = $_SESSION['photo'] ?? "default_profile.png";
$current_photo_url = (!empty($_SESSION['photo'])) 
    ? $_SESSION['photo'] 
    : "uploads/profile_photos/default_profile.png";

// Tentukan dashboard berdasarkan role
$dashboard = ($role === 'admin') ? 'admin.php' :
             (($role === 'notulis') ? 'Notulis.php' :
             'tamu.php');

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <title>notulenrapat</title>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- External CSS -->
  <link rel="stylesheet" href="profile.css">
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

      <!-- Dashboard sesuai role -->
      <ul class="nav-list primary-nav">
        <?php if ($role === 'admin'): ?>
        <li><a href="<?= $dashboard ?>" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="jadwal_rapat.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Rapat</a></li>
        <li><a href="notulen_list_admin.php" class="nav-link"><i class="fas fa-file-alt"></i> Notulen Rapat</a></li>
        <li><a href="user_management.php" class="nav-link"><i class="fas fa-users"></i> User Management</a></li>

        <?php elseif ($role === 'notulis'): ?>
        <li><a href="<?= $dashboard ?>" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="notulis.php" class="nav-link"><i class="fas fa-file-alt"></i> Notulen Rapat</a></li>
        <li><a href="jadwal_rapat.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Rapat</a></li>
        <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-bell nav-icon"></i><span class="nav-label">Notifikasi</span></a></li>
        <li class="nav-item"><a href="#" class="nav-link"><i class="fas fa-info-circle nav-icon"></i><span class="nav-label">Informasi</span></a></li>

        <?php elseif ($role === 'tamu'): ?>
        <li><a href="<?= $dashboard ?>" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="tamu.php" class="nav-link"><i class="fas fa-file-alt"></i> Notulen Rapat</a></li>
        <li><a href="jadwal_rapat_tamu.php" class="nav-link"><i class="fas fa-calendar-alt"></i> Jadwal Rapat</a></li>
        <?php endif; ?>

      </ul>

      <!-- Menu Profil & Logout -->
      <ul class="nav-list secondary-nav">
        <li><a href="profile.php" class="nav-link active">
            <i class="fas fa-user-circle"></i> Profile
          </a></li>

        <li><a href="login.php?action=logout" class="nav-link">
            <i class="fas fa-sign-out-alt"></i> Logout
          </a></li>
      </ul>

    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="dashboard-header">
      <h1>Profile</h1>
    </div>

    <div class="profile-container">

      <div class="profile-section">
        <form action="proses_photo.php" method="POST" enctype="multipart/form-data">
          <h4>Ganti Foto Profil</h4>
          <div class="profile-picture">
            <img src="<?php echo $current_photo_url; ?>" alt="Foto Profil"
              style="width:150px; height:150px; border-radius:50%; object-fit:cover;">
          </div>
          <input type="file" name="new_file_profile_pic" accept="image/*" required>
          <button type="submit" name="upload_btn">Ganti Foto</button>
        </form>
      </div>

      <div class="profile-section">
        <form action="process_name.php" method="POST">
          <h4>Ganti Nama Lengkap</h4>
          <label for="full_name">Nama Lengkap Baru:</label>
          <input type="text" id="full_name" name="new_full_name" required
            value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
          <button type="submit" name="update_name_btn">Simpan Nama</button>
        </form>
      </div>

      <div class="profile-section">
        <form action="process_password.php" method="POST">
          <h4>Ganti Password</h4>
          <label for="old_password">Password Lama:</label>
          <input type="password" id="old_password" name="old_password" required>

          <label for="new_password">Password Baru:</label>
          <input type="password" id="new_password" name="new_password" required>

          <label for="confirm_password">Konfirmasi Password Baru:</label>
          <input type="password" id="confirm_password" name="confirm_password" required>

          <button type="submit" name="update_password_btn">Ganti Password</button>
        </form>
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