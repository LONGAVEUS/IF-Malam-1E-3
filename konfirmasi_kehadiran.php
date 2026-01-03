<?php
session_start();
require_once 'koneksi.php';

// Cek login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit();
}

// Izin untuk tamu dan notulis
if ($_SESSION['role'] !== 'tamu' && $_SESSION['role'] !== 'notulis') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notulen_id = $_POST['notulen_id'] ?? null;
$status = $_POST['status'] ?? null;

// Validasi
if (!$notulen_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

// Cek apakah user adalah peserta notulen atau pembuat notulen (untuk notulis)
$stmt = $conn->prepare("
    SELECT n.created_by_user_id, pn.user_id as peserta_id 
    FROM notulen n
    LEFT JOIN peserta_notulen pn ON n.id = pn.notulen_id AND pn.user_id = ?
    WHERE n.id = ?
");
$stmt->bind_param("ii", $user_id, $notulen_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

// User harus peserta atau pembuat notulen
if (!$data || (!$data['peserta_id'] && $data['created_by_user_id'] != $user_id)) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak terdaftar untuk notulen ini']);
    exit();
}

// Cek status notulen harus 'sent' untuk konfirmasi
$stmt = $conn->prepare("SELECT status FROM notulen WHERE id = ?");
$stmt->bind_param("i", $notulen_id);
$stmt->execute();
$notulen = $stmt->get_result()->fetch_assoc();

if ($notulen['status'] !== 'sent') {
    echo json_encode(['success' => false, 'message' => 'Notulen sudah final, tidak dapat dikonfirmasi']);
    exit();
}

// Update atau insert kehadiran
$stmt = $conn->prepare("
    INSERT INTO kehadiran (notulen_id, user_id, status, waktu_konfirmasi) 
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE 
    status = VALUES(status), 
    waktu_konfirmasi = NOW()
");
$stmt->bind_param("iis", $notulen_id, $user_id, $status);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Kehadiran berhasil dikonfirmasi']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengkonfirmasi kehadiran']);
}

$stmt->close();
$conn->close();
?>