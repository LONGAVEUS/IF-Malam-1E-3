<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'notulis') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID tidak diberikan']);
    exit();
}

$id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Query untuk mengambil data notulen
$sql = "SELECT n.Id, n.judul, n.hari, n.tanggal, n.Tempat, n.notulis, 
               n.Pembahasan, n.Hasil_akhir, n.penanggung_jawab, 
               n.lampiran, n.status
        FROM notulen n
        WHERE n.Id = ? AND n.created_by_user_id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Notulen tidak ditemukan atau tidak memiliki akses.']);
    exit();
}

$notulen = $result->fetch_assoc();

// Format tanggal
$notulen['tanggal'] = date('Y-m-d', strtotime($notulen['tanggal']));

// Decode lampiran
$notulen['lampiran_files'] = [];
if ($notulen['lampiran'] && $notulen['lampiran'] !== 'null') {
    $lampiran_data = json_decode($notulen['lampiran'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($lampiran_data)) {
        $notulen['lampiran_files'] = $lampiran_data;
    }
}

// **PERBAIKAN PENTING: Ambil data peserta dengan benar**
$sql_peserta = "SELECT 
    u.user_id, 
    u.full_name, 
    u.nim, 
    u.role,
    COALESCE(k.status, 'belum') as status_kehadiran
FROM peserta_notulen pn
INNER JOIN user u ON pn.user_id = u.user_id
LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id
WHERE pn.notulen_id = ?
ORDER BY u.full_name";

$stmt_peserta = $conn->prepare($sql_peserta);
$stmt_peserta->bind_param("i", $id);
$stmt_peserta->execute();
$result_peserta = $stmt_peserta->get_result();

$peserta_details = [];
while ($peserta = $result_peserta->fetch_assoc()) {
    $peserta_details[] = [
        'user_id' => (int)$peserta['user_id'],
        'full_name' => $peserta['full_name'],
        'nim' => $peserta['nim'],
        'role' => $peserta['role'],
        'status' => $peserta['status_kehadiran']
    ];
}

$notulen['peserta_details'] = $peserta_details;

$stmt_peserta->close();
$stmt->close();

// Debug info untuk development
$debug_info = [
    'notulen_id' => $id,
    'peserta_count' => count($peserta_details),
    'peserta_ids' => array_column($peserta_details, 'user_id'),
    'lampiran_count' => count($notulen['lampiran_files'])
];

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $notulen,
    'debug' => $debug_info
]);

$conn->close();
?>
