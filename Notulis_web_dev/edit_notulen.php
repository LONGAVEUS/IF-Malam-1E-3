<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $sql = "SELECT id, judul, tanggal, isi, penanggung_jawab, lampiran FROM notulen WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $notulen = $result->fetch_assoc();
        
        // Tampilkan nama file asli (tanpa uniqid)
        if ($notulen['lampiran']) {
            $notulen['nama_file_asli'] = substr($notulen['lampiran'], strpos($notulen['lampiran'], '_') + 1);
        }
        
        header('Content-Type: application/json');
        echo json_encode($notulen);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Notulen tidak ditemukan']);
    }
    
    $stmt->close();
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID tidak valid']);
}

$conn->close();
?>