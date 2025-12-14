<?php
session_start();
require_once 'koneksi.php';

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
        $message = "User berhasil diperbarui";
    } else {
        $error = $stmt->error;
    }

    $stmt->close();
}

/* ================== HAPUS USER ================== */
if (isset($_POST['hapus_user'])) {

    $user_id = $_POST['user_id'];

    $stmt = $conn->prepare("DELETE FROM user WHERE user_id=?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $message = "User berhasil dihapus";
    } else {
        $error = $stmt->error;
    }

    $stmt->close();
}

/* ================== AMBIL DATA USER ================== */
$users = $conn->query("SELECT * FROM user");
if (!$users) {
    die("Query error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
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
                        <span class="nav-label">User Management</span>
                    </a>
                </li>
            </ul>

            <!-- Secondary bottom nav -->
            <ul class="nav-list secondary-nav">
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
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

    <!-- ================= MAIN CONTENT ================= -->
    <div class="main-content">
        <h1>User Management</h1>

        <?php if ($message): ?><p><?= $message ?></p><?php endif; ?>
        <?php if ($error): ?><p style="color:red"><?= $error ?></p><?php endif; ?>

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
                        )">Edit
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
    </div>

    <!-- ================= MODAL ================= -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <form method="POST" class="modal-body">
                <h2>Edit Data Mahasiswa</h2>
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

            const toggler = document.querySelector(".toggler");
            const sidebar = document.querySelector(".sidebar");

            if (!toggler || !sidebar) {
                console.error("Elemen toggler atau sidebar tidak ditemukan!");
                return;
            }

            toggler.addEventListener("click", function () {

                // Toggle sidebar
                sidebar.classList.toggle("collapsed");

                // Ambil icon chevron
                const icon = toggler.querySelector("span");

                if (!icon) return;

                // Ubah arah icon
                if (sidebar.classList.contains("collapsed")) {
                    icon.classList.remove("fa-chevron-left");
                    icon.classList.add("fa-chevron-right");
                } else {
                    icon.classList.remove("fa-chevron-right");
                    icon.classList.add("fa-chevron-left");
                }
            });
        });
    </script>
</body>

</html>
