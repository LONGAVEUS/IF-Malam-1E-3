<?php
session_start();
require_once 'koneksi.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    // Check session
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        throw new Exception('Unauthorized access');
    }
    
    // Check role
    if ($_SESSION['role'] !== 'notulis') {
        throw new Exception('Access denied');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Get notulen ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('Notulen ID tidak valid');
    }
    
    $notulen_id = intval($_GET['id']);
    
    // Query untuk mendapatkan data notulen
    // PERUBAHAN: Hanya izinkan edit untuk status draft dan sent
    $sql = "SELECT n.*, 
            (SELECT COUNT(*) FROM peserta_notulen pn WHERE pn.notulen_id = n.id) as jumlah_peserta
            FROM notulen n 
            WHERE n.id = ? AND n.created_by_user_id = ? AND n.status IN ('draft', 'sent')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param("ii", $notulen_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Notulen tidak ditemukan, Anda tidak memiliki akses, atau notulen sudah final dan tidak dapat diedit');
    }
    
    $notulen = $result->fetch_assoc();
    $stmt->close();
    
    // Query untuk mendapatkan peserta
    $sql_peserta = "SELECT u.user_id, u.nim, u.full_name, u.role 
                    FROM peserta_notulen pn 
                    JOIN user u ON pn.user_id = u.user_id 
                    WHERE pn.notulen_id = ? 
                    ORDER BY u.full_name";
    
    $stmt_peserta = $conn->prepare($sql_peserta);
    $stmt_peserta->bind_param("i", $notulen_id);
    $stmt_peserta->execute();
    $peserta_result = $stmt_peserta->get_result();
    
    $peserta_details = [];
    while ($peserta = $peserta_result->fetch_assoc()) {
        $peserta_details[] = $peserta;
    }
    $stmt_peserta->close();
    
    // Format tanggal jika perlu
    if (!empty($notulen['tanggal'])) {
        $notulen['tanggal'] = date('Y-m-d', strtotime($notulen['tanggal']));
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'id' => $notulen['id'],
            'judul' => $notulen['judul'] ?? '',
            'hari' => $notulen['hari'] ?? '',
            'tanggal' => $notulen['tanggal'] ?? '',
            'jam_mulai' => $notulen['jam_mulai'] ?? '',
            'jam_selesai' => $notulen['jam_selesai'] ?? '',
            'tempat' => $notulen['tempat'] ?? '',
            'notulis' => $notulen['notulis'] ?? '',
            'jurusan' => $notulen['jurusan'] ?? '',
            'penanggung_jawab' => $notulen['penanggung_jawab'] ?? '',
            'pembahasan' => $notulen['pembahasan'] ?? '',
            'hasil_akhir' => $notulen['hasil_akhir'] ?? '',
            'peserta_details' => $peserta_details
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Edit Notulen Error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
