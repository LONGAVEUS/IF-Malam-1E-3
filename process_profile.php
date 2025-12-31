<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_POST['save_profile_btn'])) {
    header("Location: profile.php");
    exit();
}

$nim = $_SESSION['nim'];

$new_name = trim($_POST['new_full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$old_pass = $_POST['old_password'] ?? '';
$new_pass = $_POST['new_password'] ?? '';
$confirm  = $_POST['confirm_password'] ?? '';

/* ================= UPDATE NAMA & EMAIL ================= */
if ($new_name !== '' && $email !== '') {

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid";
        header("Location: profile.php");
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE user SET full_name = ?, email = ? WHERE nim = ?"
    );
    $stmt->bind_param("sss", $new_name, $email, $nim);
    $stmt->execute();
    $stmt->close();

    $_SESSION['full_name'] = $new_name;
    $_SESSION['email']    = $email;
}

/* ================= UPDATE PASSWORD (OPSIONAL) ================= */
if ($old_pass || $new_pass || $confirm) {

    if ($new_pass !== $confirm) {
        $_SESSION['error'] = "Konfirmasi password tidak cocok";
        header("Location: profile.php");
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT password FROM user WHERE nim = ?"
    );
    $stmt->bind_param("s", $nim);
    $stmt->execute();
    $stmt->bind_result($db_password);
    $stmt->fetch();
    $stmt->close();

    if ($old_pass !== $db_password) {
        $_SESSION['error'] = "Password lama salah";
        header("Location: profile.php");
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE user SET password = ? WHERE nim = ?"
    );
    $stmt->bind_param("ss", $new_pass, $nim);
    $stmt->execute();
    $stmt->close();
}

/* ================= SUCCESS ================= */
$_SESSION['success_msg'] = "Perubahan berhasil disimpan";
header("Location: profile.php");
exit();

