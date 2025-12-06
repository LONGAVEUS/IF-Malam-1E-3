<?php
session_start();
require_once 'koneksi.php';

// Set header JSON
header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Cek apakah ada parameter id
if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID tidak ditemukan']);
    exit();
}

$id = intval($_GET['id']);

// PERBAIKAN: Query sesuai struktur tabel notulen
$sql = "SELECT id, jadwal_id, tanggal, isi_Notulen, peserta 
        FROM notulen 
        WHERE id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $notulen = $result->fetch_assoc();
    
    // Return data sebagai JSON
    echo json_encode([
        'id' => $notulen['id'],
        'jadwal_id' => $notulen['jadwal_id'],
        'tanggal' => $notulen['tanggal'],
        'isi' => $notulen['isi_Notulen'],
        'peserta' => $notulen['peserta']
    ]);
} else {
    echo json_encode(['error' => 'Notulen tidak ditemukan']);
}

$stmt->close();
$conn->close();
?>