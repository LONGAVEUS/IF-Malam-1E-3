<?php
session_start();
require_once 'SimpleXLSX.php'; 
require_once 'koneksi.php';


/* ================== PAGINATION ================== */
$limit = 20;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = ($page < 1) ? 1 : $page;

$offset = ($page - 1) * $limit;


use Shuchkin\SimpleXLSX;


// ================== DATA USER LOGIN ==================
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

// Jika foto kosong / tidak ada
$fotoProfil = (!empty($userLogin['photo']) && file_exists($userLogin['photo']))
    ? $userLogin['photo']
    : 'uploads/profile_photos/default_profile.png';


/* ================== INISIALISASI ================== */
$message = "";
$error   = "";

/* ================== CEK LOGIN ADMIN ================== */
if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: login.php");
    exit();
}

/* ================== TAMBAH USER ================== */
if (isset($_POST['add_user'])) {

    $nim        = $_POST['nim'];
    $full_name  = $_POST['full_name'];
    $jurusan    = $_POST['jurusan'];
    $angkatan   = $_POST['angkatan'];
    $password   = $_POST['password'];
    $role       = $_POST['role'];
    $is_active  = 1;
    $photo      = "uploads/profile_photos/default_profile.png";

    $stmt = $conn->prepare("
       INSERT INTO user 
       (nim, password, role, jurusan, angkatan, full_name, is_active, creat_at, photo)
       VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->bind_param(
        "ssssisis",
        $nim,
        $password,
        $role,
        $jurusan,
        $angkatan,
        $full_name,
        $is_active,
        $photo
    );
   


    if ($stmt->execute()) {
        $message = "User berhasil ditambahkan";
    } else {
        $error = $stmt->error;
    }

    $stmt->close();
}

/* ================== UPLOAD USER MASSAL (EXCEL) ================== */


if (isset($_POST['submit_excel'])) {
    if ($xlsx = SimpleXLSX::parse($_FILES['import_excel']['tmp_name'])) {
        
        $success_count = 0;
        $error_count = 0;
        
        // Ambil baris data (rows)
        $rows = $xlsx->rows();
        
        
        foreach ($rows as $index => $column) {
            if ($index == 0) continue; 

            $nim       = $column[0]; 
            $full_name = $column[1];
            $jurusan   = $column[2]; 
            $angkatan  = $column[3]; 
            $password  = $column[4]; 
            $role      = strtolower($column[5]); 
            $is_active = 1;
            $photo     = "uploads/profile_photos/default_profile.png";

            $stmt = $conn->prepare("INSERT INTO user (nim, password, role, jurusan, angkatan, full_name, is_active, creat_at, photo) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
            $stmt->bind_param("ssssisis", $nim, $password, $role, $jurusan, $angkatan, $full_name, $is_active, $photo);

            if ($stmt->execute()) {
                $success_count++;
            } else {
                $error_count++;
            }
            $stmt->close();
        }
        
        $_SESSION['success_msg'] = "$success_count user berhasil ditambahkan! ($error_count gagal)";

        header("Location: user_management.php");
        exit();

    } else {
        $error = SimpleXLSX::parseError();
    }
}

/* ================== EDIT USER ================== */
if (isset($_POST['edit_user'])) {

    $user_id   = $_POST['user_id'];
    $full_name = $_POST['edit_full_name'];
    $jurusan   = $_POST['edit_jurusan'];
    $angkatan  = $_POST['edit_angkatan'];
    $role      = $_POST['edit_role'];
    $is_active = $_POST['edit_is_active'];

    $stmt = $conn->prepare("
        UPDATE user 
        SET full_name=?, jurusan=?, angkatan=?, role=?, is_active=?
        WHERE user_id=?
    ");

    $stmt->bind_param(
        "ssisii",
        $full_name,
        $jurusan,
        $angkatan,
        $role,
        $is_active,
        $user_id
    );

    if ($stmt->execute()) {
       $_SESSION['success_msg'] = "User berhasil diperbarui!"; 
        header("Location: user_management.php");
        exit();
    }
}

/* ================== HAPUS USER ================== */
if (isset($_POST['hapus_user'])) {

    $user_id = $_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM user WHERE user_id=?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "User berhasil dihapus!"; 
        header("Location: user_management.php");
        exit();
    }
}

/* ================== AMBIL DATA USER + PENCARIAN ================== */

$is_search = false;

if (isset($_POST['submit']) && !empty($_POST['cari'])) {

    // MODE SEARCH (tanpa pagination)
    $cari = "%" . trim($_POST['cari']) . "%";

    $stmt = $conn->prepare("
        SELECT * FROM user
        WHERE 
            nim LIKE ? OR
            full_name LIKE ? OR
            jurusan LIKE ? OR
            angkatan LIKE ? OR
            role LIKE ?
        ORDER BY CAST(nim AS UNSIGNED) ASC
    ");

    $stmt->bind_param("sssss", $cari, $cari, $cari, $cari, $cari);
    $stmt->execute();
    $users = $stmt->get_result();

    $is_search = true;

} else {

    // MODE NORMAL (pagination aktif)
    $users = $conn->query("
        SELECT * FROM user
        ORDER BY CAST(nim AS UNSIGNED) ASC
        LIMIT $limit OFFSET $offset
    ");
}


$totalPages = 1;

if (!$is_search) {
    $totalQuery = $conn->query("SELECT COUNT(*) AS total FROM user");
    $totalData  = $totalQuery->fetch_assoc()['total'];
    $totalPages = ceil($totalData / $limit);
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>User Management</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="userm.css">
</head>

<body>

    <!-- Sidebar -->
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
                        <span class="nav-label">Daftar pengguna</span>
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

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">
        <h1>Daftar Pengguna</h1>

        <?php if (isset($_SESSION['success_msg'])): ?>
        <div id="auto-alert" class="alert alert-success">
            <i class="fas fa-check-circle alert-icon"></i>
            <span><?php echo $_SESSION['success_msg']; ?></span>
        </div>
        <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>


        <form method="POST" class="form-add-user">
            <input type="text" name="nim" placeholder="NIM" required>
            <input type="text" name="full_name" placeholder="Nama Lengkap" required>
            <input type="text" name="jurusan" placeholder="Jurusan" required>
            <input type="number" name="angkatan" placeholder="Angkatan" required>
            <input type="text" name="password" placeholder="Password" required>

            <select name="role">
                <option value="admin">Admin</option>
                <option value="notulis">Notulis</option>
                <option value="tamu">Tamu</option>
            </select>

            <button name="add_user">Tambah User</button>
        </form>

        <div class="import-box">
            <form method="POST" enctype="multipart/form-data">
                <strong> Import Massal (.xlsx) </strong>
                <input type="file" name="import_excel" id="file_input" accept=".xlsx, .xls, .csv" required>
                <button type="submit" name="submit_excel" class="btn-import"><i class="fas fa-upload"></i> Upload Excel
                </button>
            </form>
        </div>

        <div class="search-container">
            <form action="" method="POST" class="search-user">
                <input type="text" name="cari" placeholder="pencarian" autocomplete="off">
                <button type="submit" name="submit">Cari</button>
            </form>
        </div>


        <div class="table-wrapper">
            <table class="table-user">
                <tr>
                    <th>NIM</th>
                    <th>Nama</th>
                    <th>Jurusan</th>
                    <th>Angkatan</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>

                <?php while ($u = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= $u['nim'] ?></td>
                    <td><?= $u['full_name'] ?></td>

                    <td><?= $u['jurusan'] ?></td>

                    <td><?= $u['angkatan'] ?></td>

                    <td><?= ucfirst($u['role']) ?></td>

                    <?php 
                    $status_text = $u['is_active'] ? 'Aktif' : 'Nonaktif';
                    $status_class = $u['is_active'] ? 'status-aktif' : 'status-nonaktif';
                ?>
                    <td class="<?= $status_class ?>"><?= $status_text ?></td>


                    <td class="aksi">
                        <button class="btn-update" onclick="openEditModal(
                        '<?= htmlspecialchars($u['user_id']) ?>', 
                        '<?= htmlspecialchars($u['full_name']) ?>', 
                        '<?= htmlspecialchars($u['jurusan']) ?>', 
                        '<?= htmlspecialchars($u['angkatan']) ?>', 
                        '<?= htmlspecialchars($u['role']) ?>',
                        '<?= htmlspecialchars($u['is_active']) ?>'
                        )"><i class="fas fa-edit"></i>Edit
                        </button>

                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <button name="hapus_user" class="btn-hapus"
                                onclick="return confirm('Yakin ingin menghapus data <?= htmlspecialchars($u['full_name']) ?>?');">
                                Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php if (!$is_search && $totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">« Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Next »</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ================= MODAL ================= -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">

            <form method="POST" class="modal-body">
                <div class="modal-header-container">
                    <h2>Edit Data Mahasiswa</h2>
                    <span class="close-button" onclick="closeEditModal()">&times;</span>
                </div>

                <input type="hidden" id="modal_user_id" name="user_id">

                <label for="modal_full_name">Nama Mahasiswa</label>
                <input type="text" id="modal_full_name" name="edit_full_name" required>

                <label for="modal_jurusan">Jurusan</label>
                <input type="text" id="modal_jurusan" name="edit_jurusan" required>

                <label for="modal_angkatan">Angkatan</label>
                <input type="number" id="modal_angkatan" name="edit_angkatan" required>

                <label for="modal_angkatan">Role</label>
                <select name="edit_role" id="modal_role">
                    <option value="admin">Admin</option>
                    <option value="notulis">Notulis</option>
                    <option value="tamu">Tamu</option>
                </select>

                <label for="modal_angkatan">Status</label>
                <select name="edit_is_active" id="modal_is_active">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
                <button type="submit" name="edit_user" class="btn-update-modal">Update</button>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById("editUserModal");

        // 2. Fungsi buka modal (dipanggil dari onclick)
        function openEditModal(id, name, jurusan, angkatan, role, status) {
            if (!modal) {
                console.error("Modal element not found!");
                return;
            }

            modal.style.display = "block";

            document.getElementById("modal_user_id").value = id;
            document.getElementById("modal_full_name").value = name;
            document.getElementById("modal_jurusan").value = jurusan;
            document.getElementById("modal_angkatan").value = angkatan;
            document.getElementById("modal_role").value = role;
            document.getElementById("modal_is_active").value = status;
        }

        // 3. Fungsi tutup modal
        function closeEditModal() {
            if (modal) {
                modal.style.display = "none";
            }
        }

        // 4. Tutup modal jika klik di luar area modal
        window.onclick = function (e) {
            if (e.target === modal) {
                closeEditModal();
            }
        };




        document.addEventListener("DOMContentLoaded", function () {
            const sidebar = document.querySelector(".sidebar");
            const toggler = document.querySelector(".toggler");
            const sidebarNav = document.querySelector(".sidebar-nav");

            if (!sidebar || !toggler || !sidebarNav) return;

            toggler.addEventListener("click", function (e) {
                e.stopPropagation();

                /* ================= MOBILE ONLY ================= */
                if (window.innerWidth <= 768) {
                    sidebarNav.classList.toggle("active");
                }
            });

            /* ================= CLOSE DROPDOWN SAAT KLIK DI LUAR (MOBILE) ================= */
            document.addEventListener("click", function (e) {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(e.target)) {
                        sidebarNav.classList.remove("active");
                    }
                }
            });

            /* ================= RESET SAAT RESIZE KE DESKTOP ================= */
            window.addEventListener("resize", function () {
                if (window.innerWidth > 768) {
                    sidebarNav.classList.remove("active");
                }
            });
        });
        document.addEventListener("DOMContentLoaded", function () {
            const alertElement = document.getElementById('auto-alert');

            if (alertElement) {
                // Tunggu 3 detik (3000 ms) sebelum mulai menghilang
                setTimeout(function () {
                    // Beri efek transisi CSS (opacity)
                    alertElement.style.transition = "opacity 0.6s ease";
                    alertElement.style.opacity = "0";

                    // Setelah animasi pudar selesai (0.6 detik), hapus elemen dari layar
                    setTimeout(function () {
                        alertElement.remove();
                    }, 600);
                }, 3000);
            }
        });
    </script>
</body>

</html>
