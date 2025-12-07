<?php
session_start();
require_once 'koneksi.php'; 

// Cek session & role admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = ""; 
$error = ""; 

// ====== Tambah User ======
if (isset($_POST['add_user'])) {
    
    $nim        = $_POST['nim'];
    $full_name  = $_POST['full_name'];
    $password   = $_POST['password']; 
    $role       = $_POST['role'];
    $is_active  = 1;
    $photo      = "uploads/profile_photos/default_profile.png"; // default foto

    $stmt = $conn->prepare("
        INSERT INTO user (nim, password, role, full_name, is_active, creat_at, photo)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->bind_param("ssssis", 
        $nim, 
        $password, 
        $role, 
        $full_name, 
        $is_active,
        $photo
    );

    if ($stmt->execute()) {
        $message = "User baru berhasil ditambahkan!";
    } else {
        $error = "Error: " . $stmt->error;
    }

    $stmt->close();
}

// ====== Edit User ======
if (isset($_POST['edit_user'])) {

    $full_name  = $_POST['edit_full_name'];
    $role       = $_POST['edit_role'];
    $is_active  = $_POST['edit_is_active'];
    $user_id    = $_POST['user_id'];

    $stmt = $conn->prepare("
    UPDATE user SET full_name = ?, role = ?, is_active = ? 
    WHERE user_id = ?
    ");

    $stmt->bind_param("ssii", 
        $full_name,
        $role,
        $is_active,
        $user_id
    );

    if ($stmt->execute()) {
        $message = "User berhasil diperbarui!";
    } else {
        $error = "Gagal update user: " . $stmt->error;
    }
}
// ====== Hapus User ======
if (isset($_POST['hapus_user'])) {

    $user_id = $_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $message = "User berhasil dihapus!";
    } else {
        $error = "Gagal menghapus user: " . $stmt->error;
    }

    


    $stmt->close();
}

// Ambil data user untuk ditampilkan
$users = $conn->query("SELECT * FROM user");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Dashboard Admin</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="userm.css">
</head>

<body>

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
                        <a href="user_management.php" class="nav-link">
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
                        <a href="admin.php?action=logout" class="nav-link"
                            onclick="return confirm('Yakin ingin logout?');">
                            <i class="fas fa-sign-out-alt nav-icon"></i>
                            <span class="nav-label">Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h1>User Management</h1>
            </div>


            <?php if ($message) echo "<p style='color:green;'>$message</p>"; ?>
            <?php if ($error)   echo "<p style='color:red;'>$error</p>"; ?>

            <!-- FORM TAMBAH USER -->
                <form method="POST" class="add-user"> 
                    <h3>Tambah User Baru</h3>

                    <input type="text" name="nim" placeholder="NIM" required>
                    <input type="text" name="full_name" placeholder="Nama Lengkap" required>
                    <input type="text" name="password" placeholder="Password" required>

                    <select name="role" required>
                        <option value="admin">Admin</option>
                        <option value="notulis">Notulis</option>
                        <option value="tamu">Tamu</option>
                    </select>

                    <button type="submit" name="add_user">Tambah User</button>
                </form>

            <br><hr><br>

            <!-- LIST USER -->
            <h3>Daftar User</h3>


            <table class="table-user">
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="aksi">Aksi</th>
                </tr>

                <?php while ($u = $users->fetch_assoc()) { ?>
                <tr>
                    <td><?= $u['nim'] ?></td>
                    <td><?= $u['full_name'] ?></td>
                    <td><?= $u['role'] ?></td>
                    <td><?= $u['is_active'] == 1 ? "Aktif" : "Nonaktif" ?></td>

                    <td class="aksi">
                        <form method="POST" class="aksi-form"> <input type="text" name="edit_full_name"
                            value="<?= htmlspecialchars($u['full_name'] ?? '') ?>" required>

                            <select name="edit_role">
                                <option value="admin" <?= $u['role']=="admin" ? "selected" : "" ?>>Admin</option>
                                <option value="notulis" <?= $u['role']=="notulis" ? "selected" : "" ?>>Notulis</option>
                                <option value="tamu" <?= $u['role']=="tamu" ? "selected" : "" ?>>Tamu</option>
                            </select>

                            <select name="edit_is_active">
                                <option value="1" <?= $u['is_active']==1 ? "selected" : "" ?>>Aktif</option>
                                <option value="0" <?= $u['is_active']==0 ? "selected" : "" ?>>Nonaktif</option>
                            </select>

                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <button type="submit" name="edit_user" class="btn-update">Update</button>
                            <button type="submit" name="hapus_user" class="btn-hapus">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php }?>

            </table>
            <!-- External JavaScript -->
            <script src="admin.js"></script>


    </body>

</html>
