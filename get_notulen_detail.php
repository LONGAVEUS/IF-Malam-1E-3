<?php
require_once 'koneksi.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $sql = "SELECT id, judul, tanggal, isi, penanggung_jawab, status, lampiran FROM notulen WHERE id = ? AND status = 'sent'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $notulen = $result->fetch_assoc();
        
        // Tambahkan nama file asli tanpa uniqid
        if ($notulen['lampiran']) {
            $notulen['nama_file_asli'] = substr($notulen['lampiran'], strpos($notulen['lampiran'], '_') + 1);
        }
        
        header('Content-Type: application/json');
        echo json_encode($notulen);
    } else {
        echo json_encode(['error' => 'Notulen tidak ditemukan atau belum diterbitkan']);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'ID notulen tidak valid']);
}

if (isset($conn)) {
    $conn->close();
}