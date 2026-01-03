<?php
session_start();
require_once 'koneksi.php';

// Proteksi login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$notulen_id = isset($_GET['notulen_id']) ? intval($_GET['notulen_id']) : 0;

if ($notulen_id == 0) {
    echo json_encode([]);
    exit();
}

// Ambil data kehadiran untuk notulen tertentu
$sql = "SELECT 
            u.full_name,
            u.nim,
            u.role,
            COALESCE(k.status, 'belum') as status,
            k.waktu_konfirmasi
        FROM peserta_notulen pn
        INNER JOIN user u ON pn.user_id = u.user_id
        LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id
        WHERE pn.notulen_id = ?
        ORDER BY u.full_name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $notulen_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);

$stmt->close();
$conn->close();
?>