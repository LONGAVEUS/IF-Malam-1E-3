<?php
session_start();
require_once 'koneksi.php';

/* ================= CEK LOGIN ================= */
if (!isset($_SESSION['logged_in'], $_SESSION['nim'])) {
    header("Location: login.php");
    exit();
}


$nim = $_SESSION['nim'];
$target_dir = "uploads/profile_photos/";



/* ================= AMBIL DATA FORM ================= */
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

    $_SESSION['username'] = $new_name;
    $_SESSION['email']    = $email;
}

/* ================= UPDATE PASSWORD (OPSIONAL) ================= */
if ($old_pass !== '' || $new_pass !== '' || $confirm !== '') {

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

/* ================= UPLOAD FOTO PROFIL ================= */
if (isset($_FILES['new_file_profile_pic']) && $_FILES['new_file_profile_pic']['error'] === 0) {
    $target_dir = "uploads/profile_photos/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $file_extension = pathinfo($_FILES['new_file_profile_pic']['name'], PATHINFO_EXTENSION);
    $nama_file_baru = "profile_" . $nim . "_" . time() . "." . $file_extension;
    $target_file = "uploads/profile_photos/" . $nama_file_baru;

    if (move_uploaded_file($_FILES['new_file_profile_pic']['tmp_name'], $target_file)) {
       // 1. Update ke Database
       $stmt = $conn->prepare("UPDATE user SET photo = ? WHERE nim = ?");
       $stmt->bind_param("ss", $target_file, $nim);
       $stmt->execute();

       // 2. UPDATE SESSION 
       $_SESSION['photo'] = $target_file; 
    }
}

/* ================= SUCCESS ================= */
$_SESSION['success_msg'] = "Perubahan berhasil disimpan";
header("Location: profile.php");
exit();
