<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informasi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="informasi-style.css">
</head>
<body>
    <!--Ini bagian Navbar-->
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
          <a href="#" class="nav-link">
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
          <a href="user.php" class="nav-link">
            <i class="fas fa-users nav-icon"></i>
            <span class="nav-label">User Management</span>
          </a>
        </li>
      </ul>

      <!-- Secondary bottom nav -->
      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="Profile.php" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profile</span>
          </a>
        </li>
        <li class="nav-item">
          <!-- Logout menggunakan PHP - tidak perlu file terpisah -->
          <a href="admin.php?action=logout" class="nav-link" onclick="return confirm('Yakin ingin logout?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span class="nav-label">Logout</span>
          </a>
        </li>
      </ul>
    </nav>
</div>
<div class="modal">
    <h2>FAQ<h2>
        <table align="center">
            <tr>
                <td>Aturan dan ketentuan mengelolah akses</td>
                <td>Tata cara penggunaan Akses, sesuai dengan ketentuan ITE 2024</td>
                <td>Langka   

</body>
</html>