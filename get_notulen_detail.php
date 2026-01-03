<?php
session_start();
require_once 'koneksi.php';

// Cek login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Cek apakah tamu atau notulis
if ($_SESSION['role'] !== 'tamu' && $_SESSION['role'] !== 'notulis') {
    echo json_encode(['error' => 'Access denied']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notulen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($notulen_id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

// Ambil data notulen dan status kehadiran user
$sql = "SELECT n.*, 
        k.status as user_status_kehadiran,
        k.waktu_konfirmasi as user_waktu_konfirmasi,
        n.created_by_user_id
        FROM notulen n
        LEFT JOIN peserta_notulen pn ON n.id = pn.notulen_id AND pn.user_id = ?
        LEFT JOIN kehadiran k ON n.id = k.notulen_id AND k.user_id = ?
        WHERE n.id = ? 
        AND n.status IN ('sent', 'final')
        AND (pn.user_id = ? OR n.created_by_user_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $user_id, $user_id, $notulen_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Notulen tidak ditemukan atau Anda tidak memiliki akses']);
    $stmt->close();
    exit();
}

$notulen = $result->fetch_assoc();
$stmt->close();

// Ambil status kehadiran user dan role
$user_status_kehadiran = $notulen['user_status_kehadiran'];
$user_role = $_SESSION['role'];
$is_creator = ($notulen['created_by_user_id'] == $user_id);

// Default data yang akan dikirim
$data = [
    'notulen' => [
        'id' => $notulen['id'],
        'judul' => $notulen['judul'],
        'tanggal' => $notulen['tanggal'],
        'jam_mulai' => $notulen['jam_mulai'],
        'jam_selesai' => $notulen['jam_selesai'],
        'tempat' => $notulen['tempat'],
        'penanggung_jawab' => $notulen['penanggung_jawab'],
        'notulis' => $notulen['notulis'],
        'jurusan' => $notulen['jurusan'],
        'status' => $notulen['status'],
        'created_by_user_id' => $notulen['created_by_user_id']
    ],
    'user_status_kehadiran' => $user_status_kehadiran,
    'user_waktu_konfirmasi' => $notulen['user_waktu_konfirmasi'],
    'user_role' => $user_role,
    'is_creator' => $is_creator,
    'access_denied' => false
];

// Jika tamu tidak hadir, batasi informasi yang dikirim
if ($user_role === 'tamu' && $user_status_kehadiran !== 'hadir') {
    $data['access_denied'] = true;
    $data['message'] = 'Anda tidak hadir dalam rapat ini, sehingga tidak dapat mengakses detail lengkap.';
    
    // Kirim data terbatas
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

// Jika tamu hadir, notulis yang membuat notulen, atau notulis yang hadir, kirim semua data
// Notulis yang membuat notulen selalu dapat akses penuh
// Notulis yang hanya peserta harus hadir untuk akses penuh
if ($user_role === 'notulis' && !$is_creator && $user_status_kehadiran !== 'hadir') {
    $data['access_denied'] = true;
    $data['message'] = 'Sebagai peserta, Anda harus hadir untuk mengakses detail lengkap notulen.';
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit();
}

// Jika tamu hadir, notulis yang membuat notulen, atau notulis yang hadir, kirim semua data
// Ambil data peserta (hanya untuk yang memiliki akses)
$sql_peserta = "SELECT u.full_name, u.nim, u.role, 
                k.status as status_kehadiran, k.waktu_konfirmasi
                FROM peserta_notulen pn
                INNER JOIN user u ON pn.user_id = u.user_id
                LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id
                WHERE pn.notulen_id = ?
                ORDER BY u.full_name";
$stmt_peserta = $conn->prepare($sql_peserta);
$stmt_peserta->bind_param("i", $notulen_id);
$stmt_peserta->execute();
$peserta = $stmt_peserta->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_peserta->close();

// Hitung statistik kehadiran
$total_peserta = count($peserta);
$total_hadir = 0;
foreach ($peserta as $p) {
    if ($p['status_kehadiran'] === 'hadir') {
        $total_hadir++;
    }
}

// Ambil data lampiran
$lampiran_files = [];
if (!empty($notulen['lampiran'])) {
    $lampiran_data = json_decode($notulen['lampiran'], true);
    if (is_array($lampiran_data)) {
        $lampiran_files = $lampiran_data;
    }
}

// Tambahkan data lengkap
$data['notulen']['pembahasan'] = $notulen['pembahasan'];
$data['notulen']['hasil_akhir'] = $notulen['hasil_akhir'];
$data['peserta'] = $peserta;
$data['lampiran_files'] = $lampiran_files;
$data['total_peserta'] = $total_peserta;
$data['total_hadir'] = $total_hadir;
$data['access_denied'] = false;

header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE);

$conn->close();
?>