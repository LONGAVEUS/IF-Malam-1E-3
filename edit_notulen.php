<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$notulen_id = $_GET['id'] ?? 0;

$sql = "SELECT 
            Id, 
            judul, 
            hari, 
            tanggal, 
            TIME(jam_mulai) as jam_mulai, 
            TIME(jam_selesai) as jam_selesai,
            Tempat, 
            notulis, 
            jurusan, 
            Pembahasan, 
            Hasil_akhir, 
            penanggung_jawab, 
            status, 
            lampiran 
        FROM notulen 
        WHERE Id = ? AND created_by_user_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $notulen_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $notulen = $result->fetch_assoc();
    
    // Format jam dengan benar
    if (!empty($notulen['jam_mulai'])) {
        $notulen['jam_mulai'] = date('H:i', strtotime($notulen['jam_mulai']));
    }
    
    if (!empty($notulen['jam_selesai'])) {
        $notulen['jam_selesai'] = date('H:i', strtotime($notulen['jam_selesai']));
    }
    
    // Decode lampiran jika ada
    $notulen['lampiran_files'] = [];
    if ($notulen['lampiran'] && $notulen['lampiran'] != 'null') {
        $notulen['lampiran_files'] = json_decode($notulen['lampiran'], true);
    }
    
    // Ambil peserta
    $sql_peserta = "SELECT u.user_id as id, u.nim, u.full_name as name, u.role 
                    FROM peserta_notulen pn 
                    JOIN user u ON pn.user_id = u.user_id 
                    WHERE pn.notulen_id = ?";
    $stmt_peserta = $conn->prepare($sql_peserta);
    $stmt_peserta->bind_param("i", $notulen_id);
    $stmt_peserta->execute();
    $peserta = $stmt_peserta->get_result()->fetch_all(MYSQLI_ASSOC);
    $notulen['peserta_details'] = $peserta;
    
    echo json_encode([
        'success' => true,
        'data' => $notulen
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Notulen tidak ditemukan'
    ]);
}

$stmt->close();
$conn->close();
?>