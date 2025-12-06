<?php
session_start();
require_once 'koneksi.php';


if (!isset($_SESSION['logged_in'], $_POST['upload_btn'])) {
header("Location: login.php");
exit();
}


$nim = $_SESSION['nim'];
$target_dir = "uploads/profile_photos/";


if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);


$target_file = $target_dir . basename($_FILES['new_file_profile_pic']['name']);
move_uploaded_file($_FILES['new_file_profile_pic']['tmp_name'], $target_file);


$stmt = $conn->prepare("UPDATE user SET photo=? WHERE nim=?");
$stmt->bind_param("ss", $target_file, $nim);
$stmt->execute();
$stmt->close();


$_SESSION['photo'] = $target_file;
header("Location: profile.php?status=photo_success");
exit();
?>