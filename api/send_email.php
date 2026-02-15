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
    $mail->Username   = 'notulensie3@gmail.com';
    $mail->Password   = 'tjansczundpangzt';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('notulensie3@gmail.com', 'Undangan Rapat');
    $mail->isHTML(true);
    $mail->Subject = 'Undangan Rapat: ' . $notulen['judul'];

    while ($row = $peserta->fetch_assoc()) {
        $mail->addAddress($row['email'], $row['full_name']);
    }

    $mail->Body = "
        <h3>Undangan Resmi Pertemuan / Rapat</h3>
        <p>Dengan hormat,</p>
        <p>Bersama surat ini, kami mengundang Bapak/Ibu untuk menghadiri rapat yang akan dilaksanakan pada:</p>
    
        <table style='border-collapse: collapse;'>
            <tr><td style='width: 100px;'><b>Agenda</b></td><td>: {$notulen['judul']}</td></tr>
            <tr><td><b>Tanggal</b></td><td>: " . date('d F Y', strtotime($notulen['tanggal'])) . "</td></tr>
            <tr><td><b>Waktu</b></td><td>: {$notulen['jam_mulai']} - {$notulen['jam_selesai']} WIB</td></tr>
            <tr><td><b>Tempat</b></td><td>: {$notulen['tempat']}</td></tr>
        </table>

       <p>Mengingat pentingnya pembahasan dalam agenda ini, kami mengharapkan kehadiran Bapak/Ibu tepat pada waktunya.</p>
    
       <p>Mohon melakukan konfirmasi kehadiran melalui tautan/situs yang telah disediakan sebelum waktu pelaksanaan.</p>
    
       <p>Demikian undangan ini kami sampaikan. Atas perhatian dan kerjasamanya, kami ucapkan terima kasih.</p>
       <br>
       <p>Hormat kami,<br><b>Sekretariat/Panitia Rapat</b></p>";

    $mail->send();


    echo json_encode(['success' => true, 'message' => 'Email berhasil dikirim']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
