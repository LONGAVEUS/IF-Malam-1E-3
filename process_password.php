<?php
session_start();
require_once 'koneksi.php';

// Pastikan user login
if (!isset($_SESSION['nim'])) {
    header("Location: login.php");
    exit();
}

$nim = $_SESSION['nim'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $old_password = trim($_POST['old_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Ambil password lama dari database
    $query = "SELECT password FROM user WHERE nim = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $nim);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($db_password);
    $stmt->fetch();

    // 1. Password lama tidak cocok
    if ($old_password !== $db_password) {
        header("Location: profile.php?status=old_password_wrong");
        exit();
    }

    // 2. Password baru dan konfirmasi tidak cocok
    if ($new_password !== $confirm_password) {
        header("Location: profile.php?status=confirm_not_match");
        exit();
    }

    // 3. Update password baru (tanpa hashing)
    $update = "UPDATE user SET password = ? WHERE nim = ?";
    $stmt2 = $conn->prepare($update);
    $stmt2->bind_param("ss", $new_password, $nim);

    if ($stmt2->execute()) {
        header("Location: profile.php?status=password_updated");
        exit();
    } else {
        header("Location: profile.php?status=update_failed");
        exit();
    }
}
?>
