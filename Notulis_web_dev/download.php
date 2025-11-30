<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $filepath = 'uploads/' . $filename;
    
    if (file_exists($filepath)) {
        // Tampilkan nama file asli (tanpa uniqid)
        $nama_file_asli = substr($filename, strpos($filename, '_') + 1);
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nama_file_asli . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        echo "File tidak ditemukan.";
    }
} else {
    echo "Parameter file tidak valid.";
}
?>