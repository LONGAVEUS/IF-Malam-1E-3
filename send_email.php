<?php
header('Content-Type: application/json');

session_start();
require_once 'koneksi.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';


if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'notulis') {
    echo json_encode(['success' => false, 'error' => 'Akses ditolak']);
    exit;
}

$notulen_id = intval($_POST['notulen_id'] ?? 0);
if ($notulen_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID notulen tidak valid']);
    exit;
}

try {
    // Ambil data notulen
    $stmt = $conn->prepare("
        SELECT judul, tanggal, jam_mulai, jam_selesai, tempat 
        FROM notulen WHERE id = ?
    ");
    $stmt->bind_param("i", $notulen_id);
    $stmt->execute();
    $notulen = $stmt->get_result()->fetch_assoc();

    if (!$notulen) {
        throw new Exception("Notulen tidak ditemukan");
    }

    // Ambil peserta
    $stmt = $conn->prepare("
        SELECT u.email, u.full_name
        FROM peserta_notulen pn
        JOIN user u ON pn.user_id = u.user_id
        WHERE pn.notulen_id = ?
    ");
    $stmt->bind_param("i", $notulen_id);
    $stmt->execute();
    $peserta = $stmt->get_result();

    if ($peserta->num_rows === 0) {
        throw new Exception("Tidak ada peserta");
    }

    // Setup email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'notulenE3@gmailcom';
    $mail->Password   = 'jultkoumjsqrrkgo';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('maysya2561@gmail.com', 'Sistem Notulen');
    $mail->isHTML(true);
    $mail->Subject = 'Undangan Rapat: ' . $notulen['judul'];

    while ($row = $peserta->fetch_assoc()) {
        $mail->addAddress($row['email'], $row['full_name']);
    }

    $mail->Body = "
        <h3>Undangan Rapat</h3>
        <p><b>Judul:</b> {$notulen['judul']}</p>
        <p><b>Tanggal:</b> {$notulen['tanggal']}</p>
        <p><b>Waktu:</b> {$notulen['jam_mulai']} - {$notulen['jam_selesai']}</p>
        <p><b>Tempat:</b> {$notulen['tempat']}</p>

        <p> anda telah di undang rapat segera konfirmasi kehadiran di situs yang telah disediakan <p>
    ";

    $mail->send();

    echo json_encode(['success' => true, 'message' => 'Email berhasil dikirim']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
