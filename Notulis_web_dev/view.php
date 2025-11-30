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
        $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Tentukan MIME type berdasarkan ekstensi file
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        $mimeType = $mimeTypes[$fileExtension] ?? 'application/octet-stream';
        
        // Set header untuk menampilkan file di browser
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: public, must-revalidate');
        header('Pragma: public');
        
        readfile($filepath);
        exit;
    } else {
        // Jika file tidak ditemukan, tampilkan halaman error
        echo "<!DOCTYPE html>
        <html lang='id'>
        <head>
            <title>File Tidak Ditemukan</title>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                }
                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    text-align: center;
                    color: #333;
                }
                .error-container h1 {
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #3498db;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='error-container'>
                <h1><i class='fas fa-exclamation-triangle'></i> File Tidak Ditemukan</h1>
                <p>File yang Anda cari tidak ditemukan di server.</p>
                <a href='notulis.php' class='btn'>Kembali ke Dashboard</a>
            </div>
        </body>
        </html>";
    }
} else {
    echo "Parameter file tidak valid.";
}
?>