<?php
session_start();
require_once 'koneksi.php';

header('Content-Type: application/json');

/* ================= CEK LOGIN ================= */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu']);
    exit();
}

/* ================= CEK ROLE ================= */
if ($_SESSION['role'] !== 'tamu' && $_SESSION['role'] !== 'notulis') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

/* ================= AMBIL DATA ================= */
$user_id     = $_SESSION['user_id'];
$notulen_id  = $_POST['notulen_id'] ?? null;
$status      = $_POST['status'] ?? null;
$signature_data = $_POST['signature'] ?? null;

if (!$notulen_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit();
}

/* ================= CEK PESERTA / PEMBUAT ================= */
$stmt = $conn->prepare("
    SELECT n.created_by_user_id, pn.user_id AS peserta_id
    FROM notulen n
    LEFT JOIN peserta_notulen pn 
        ON n.id = pn.notulen_id AND pn.user_id = ?
    WHERE n.id = ?
");
$stmt->bind_param("ii", $user_id, $notulen_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data || (!$data['peserta_id'] && $data['created_by_user_id'] != $user_id)) {
    echo json_encode(['success' => false, 'message' => 'Anda tidak terdaftar sebagai peserta']);
    exit();
}

/* ================= CEK STATUS NOTULEN ================= */
$stmt = $conn->prepare("SELECT status FROM notulen WHERE id = ?");
$stmt->bind_param("i", $notulen_id);
$stmt->execute();
$notulen = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($notulen['status'] !== 'sent') {
    echo json_encode(['success' => false, 'message' => 'Notulen sudah final, tidak dapat dikonfirmasi']);
    exit();
}

/* ================= HAPUS DATA KEHADIRAN LAMA ================= */
$delete = $conn->prepare("
    DELETE FROM kehadiran 
    WHERE notulen_id = ? AND user_id = ?
");
$delete->bind_param("ii", $notulen_id, $user_id);
$delete->execute();
$delete->close();

/* ================= SIMPAN TANDA TANGAN ================= */
$file_name = null;
$db_path = null;

if ($status === 'hadir' && !empty($signature_data)) {

    $target_dir = "uploads/signatures/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $img = str_replace('data:image/png;base64,', '', $signature_data);
    $img = str_replace(' ', '+', $img);
    $data_img = base64_decode($img);

    
    $file_name = "ttd_u{$user_id}_n{$notulen_id}.png";
    $file_path = $target_dir . $file_name;

   
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    if (!file_put_contents($file_path, $data_img)) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan tanda tangan']);
        exit();
    }

    
    $db_path = "signatures/" . $file_name;
}

/* ================= INSERT KEHADIRAN  ================= */
$stmt = $conn->prepare("
    INSERT INTO kehadiran (notulen_id, user_id, status, signature_path)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("iiss", $notulen_id, $user_id, $status, $db_path);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Kehadiran dan tanda tangan berhasil disimpan'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan kehadiran'
    ]);
}

$stmt->close();
$conn->close();
