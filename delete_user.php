<?php
// File: delete_user.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); 
require_once 'koneksi.php'; 

if (!isset($conn) || $conn->connect_error) {
    die("Koneksi database gagal dimuat atau terputus.");
}

$user_id_to_delete = $_GET['user_id'] ?? null;

if (is_null($user_id_to_delete) || !is_numeric($user_id_to_delete)) {
    header("Location: admin.php?status=fail&msg=ID Pengguna tidak valid.");
    exit();
}

$sql = "UPDATE user SET is_active = 0 WHERE user_id = ?";

if ($stmt = $conn->prepare($sql)) {

    $stmt->bind_param("i", $user_id_to_delete);
    
    if ($stmt->execute()) {

        $row_affected = $conn->affected_rows;

        if ($row_affected > 0) {
            header("Location: admin.php?status=success_delete");
            exit();
        } else {
            header("Location: admin.php?status=fail&msg=Pengguna tidak ditemukan atau sudah nonaktif.");
            exit();
        }

    } else {
        die("FATAL ERROR EKSEKUSI: " . htmlspecialchars($stmt->error));
    }
    
    $stmt->close();

} else {
    die("FATAL ERROR PREPARE: " . htmlspecialchars($conn->error));
}
?>
