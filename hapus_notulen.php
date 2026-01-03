<?php
session_start();
require_once 'koneksi.php';

// Proteksi login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Anda harus login']);
    exit();
}

// Proteksi role - izinkan tamu dan notulis
if ($_SESSION['role'] !== 'tamu' && $_SESSION['role'] !== 'notulis') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notulen_id = isset($_POST['notulen_id']) ? intval($_POST['notulen_id']) : 0;

if ($notulen_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID notulen tidak valid']);
    exit();
}

// Untuk notulis, juga izinkan menghapus notulen yang dibuatnya
$sql_check = "SELECT n.id, n.status, n.created_by_user_id
              FROM notulen n
              LEFT JOIN peserta_notulen pn ON n.id = pn.notulen_id AND pn.user_id = ?
              WHERE n.id = ? 
              AND n.status = 'final'
              AND (pn.user_id = ? OR n.created_by_user_id = ?)";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("iiii", $user_id, $notulen_id, $user_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Notulen tidak ditemukan atau tidak dapat dihapus']);
    $stmt_check->close();
    exit();
}

$notulen = $result_check->fetch_assoc();
$stmt_check->close();

// Mulai transaksi
$conn->begin_transaction();

try {
    // Hapus kehadiran terkait
    $sql1 = "DELETE FROM kehadiran WHERE notulen_id = ?";
    $stmt1 = $conn->prepare($sql1);
    $stmt1->bind_param("i", $notulen_id);
    $stmt1->execute();
    $stmt1->close();

    // Hapus peserta notulen
    $sql2 = "DELETE FROM peserta_notulen WHERE notulen_id = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $notulen_id);
    $stmt2->execute();
    $stmt2->close();

    // Hapus notulen
    $sql3 = "DELETE FROM notulen WHERE id = ?";
    $stmt3 = $conn->prepare($sql3);
    $stmt3->bind_param("i", $notulen_id);
    $stmt3->execute();
    $stmt3->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Notulen berhasil dihapus']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal menghapus notulen: ' . $e->getMessage()]);
}

$conn->close();
?>