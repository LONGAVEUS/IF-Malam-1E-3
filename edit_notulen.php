<?php
error_reporting(0); 

session_start();
require_once 'koneksi.php';

// Set header JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $notulen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($notulen_id <= 0) {
        throw new Exception('ID notulen tidak valid');
    }
    
    // Query database
    $sql = "SELECT * FROM notulen WHERE Id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notulen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Notulen tidak ditemukan');
    }
    
    $notulen = $result->fetch_assoc();
    
    // Ambil data peserta
    $sql_peserta = "SELECT u.user_id, u.full_name, u.nim, u.role 
                   FROM peserta_notulen pn 
                   JOIN user u ON pn.user_id = u.user_id 
                   WHERE pn.notulen_id = ?";
    $stmt_peserta = $conn->prepare($sql_peserta);
    $stmt_peserta->bind_param("i", $notulen_id);
    $stmt_peserta->execute();
    $peserta_result = $stmt_peserta->get_result();
    
    $peserta_details = [];
    while ($peserta = $peserta_result->fetch_assoc()) {
        $peserta_details[] = $peserta;
    }
    
    // Parse lampiran jika ada
    $lampiran_files = [];
    if (!empty($notulen['lampiran']) && $notulen['lampiran'] !== 'null') {
        $lampiran_files = json_decode($notulen['lampiran'], true);
        if (!is_array($lampiran_files)) {
            $lampiran_files = [];
        }
    }
    
    
    // Siapkan response
    $response = [
        'success' => true,
        'data' => [
            'Id' => $notulen['Id'],
            'judul' => $notulen['judul'],
            'hari' => $notulen['hari'],
            'tanggal' => $notulen['tanggal'],
            'jam_mulai' => $notulen['jam_mulai'],
            'jam_selesai' => $notulen['jam_selesai'],
            'Tempat' => $notulen['Tempat'],
            'notulis' => $notulen['notulis'],
            'jurusan' => $notulen['jurusan'],
            'penanggung_jawab' => $notulen['penanggung_jawab'],
            'Pembahasan' => $notulen['Pembahasan'],
            'Hasil_akhir' => $notulen['Hasil_akhir'],
            'peserta_details' => $peserta_details,
            'lampiran_files' => $lampiran_files
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
exit();
?>
