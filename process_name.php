<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$new_name = trim($_POST['new_full_name']);

if ($new_name == "") {
    header("Location: profile.php?error=Nama tidak boleh kosong");
    exit();
}

$sql = "UPDATE user SET full_name=? WHERE user_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $new_name, $user_id);

if ($stmt->execute()) {
    $_SESSION['username'] = $new_name;
    header("Location: profile.php?success=Nama berhasil diubah");
    exit();
}

header("Location: profile.php?error=Gagal update nama");
exit();
?>
