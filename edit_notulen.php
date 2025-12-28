<?php
// edit_notulen.php
require_once 'koneksi.php';

$notulen_id = $_GET['id'] ?? 0;

// PERBAIKAN: Gunakan query terpisah untuk notulen dan peserta
$sql_notulen = "SELECT 
    n.Id, n.judul, n.hari, n.tanggal, n.Tempat, n.notulis, 
    n.jurusan, n.penanggung_jawab, n.Pembahasan, n.Hasil_akhir, 
    n.status, n.lampiran, n.created_by_user_id
    FROM notulen n
    WHERE n.Id = ?";

$stmt = $conn->prepare($sql_notulen);
$stmt->bind_param("i", $notulen_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $notulen_data = $row;
    
    // Decode lampiran files
    $notulen_data['lampiran_files'] = [];
    if (!empty($row['lampiran']) && $row['lampiran'] !== 'null') {
        $decoded = json_decode($row['lampiran'], true);
        if (is_array($decoded)) {
            $notulen_data['lampiran_files'] = $decoded;
        }
    }
    
    // Ambil peserta secara terpisah
    $sql_peserta = "SELECT 
        u.user_id, u.full_name, u.nim, u.role
        FROM peserta_notulen pn
        JOIN user u ON pn.user_id = u.user_id
        WHERE pn.notulen_id = ?
        ORDER BY u.full_name";
    
    $stmt_peserta = $conn->prepare($sql_peserta);
    $stmt_peserta->bind_param("i", $notulen_id);
    $stmt_peserta->execute();
    $result_peserta = $stmt_peserta->get_result();
    
    $peserta_details = [];
    while ($peserta = $result_peserta->fetch_assoc()) {
        $peserta_details[] = $peserta;
    }
    $stmt_peserta->close();
    
    $notulen_data['peserta_details'] = $peserta_details;
    
    echo json_encode([
        'success' => true,
        'data' => $notulen_data
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