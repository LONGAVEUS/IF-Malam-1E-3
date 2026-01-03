<?php
require('fpdf/fpdf.php');

class PDF extends FPDF {
    private $headerTitle = '';
    private $headerDate = '';
    
    function SetHeaderInfo($title, $date) {
        $this->headerTitle = $title;
        $this->headerDate = $date;
    }
    
    // Header halaman
    function Header() {
        // Hanya tampilkan header di halaman pertama
        if ($this->PageNo() == 1) {
            // Logo atau kop surat
            $this->SetFont('Times', 'B', 14);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 6, 'POLITEKNIK NEGERI BATAM', 0, 1, 'C');
            $this->SetFont('Times', '', 10);
            $this->Cell(0, 4, 'Jl. Ahmad Yani, Batam Centre, Batam 29461', 0, 1, 'C');
            
            // Garis pemisah
            $this->SetY($this->GetY() + 2);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.5);
            $this->Line(25, $this->GetY(), 185, $this->GetY());
            $this->SetY($this->GetY() + 8);
        }
    }
    
    // Footer halaman
    function Footer() {
        $this->SetY(-15);
        
        // Garis footer
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(25, $this->GetY() - 2, 185, $this->GetY() - 2);
        
        // Nomor halaman
        $this->SetFont('Times', 'I', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
    }
    
    // Function untuk menampilkan teks dengan format rapi
    function MultiLineText($txt, $align = 'L', $bullet = false) {
        $txt = str_replace("\r", "", $txt);
        $lines = explode("\n", $txt);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Cek jika line sudah memiliki bullet/numbering
                if (preg_match('/^(\d+\.|\-|\*|>) /', $line)) {
                    $this->MultiCell(0, 5, $line, 0, $align);
                } elseif ($bullet) {
                    $this->MultiCell(0, 5, '• ' . $line, 0, $align);
                } else {
                    $this->MultiCell(0, 5, $line, 0, $align);
                }
            } else {
                $this->Ln(3);
            }
        }
    }
    
    // Function untuk menampilkan gambar dengan ukuran 80x80
    function DisplayImage80x80($filepath, $description = '') {
        if (!file_exists($filepath)) {
            return false;
        }
        
        // Get image dimensions
        list($width, $height, $type) = getimagesize($filepath);
        
        if ($width == 0 || $height == 0) {
            return false;
        }
        
        // Calculate ratio to fit within 80x80
        $maxWidth = 80;
        $maxHeight = 80;
        $ratio = $width / $height;
        
        if ($ratio > 1) {
            // Landscape
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $ratio;
        } else {
            // Portrait or square
            $newHeight = $maxHeight;
            $newWidth = $maxHeight * $ratio;
        }
        
        // Add image description if provided
        if (!empty($description)) {
            $this->SetFont('Times', 'B', 10);
            $this->Cell(0, 5, $description, 0, 1, 'C');
            $this->Ln(2);
        }
        
        // Center the image horizontally
        $xPos = (210 - $newWidth) / 2; // A4 width = 210mm
        $this->SetX($xPos);
        
        // Display the image
        $y = $this->GetY();
        $this->Image($filepath, $xPos, $y, $newWidth, $newHeight);
        $this->SetY($y + $newHeight + 10);
        
        return true;
    }
    
    // Function untuk membuat garis pembatas
    function DrawSeparator() {
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Line(25, $this->GetY(), 185, $this->GetY());
        $this->Ln(5);
    }
}

// ================== FUNGSI UTAMA UNTUK GENERATE PDF ==================

/**
 * Fungsi untuk generate notulen PDF
 * @param int $notulen_id ID notulen
 * @param mysqli $conn Koneksi database
 * @return string|false Path file PDF yang dihasilkan atau false jika gagal
 */
function generateNotulenPDF($notulen_id, $conn = null) {
    // Jika tidak ada koneksi yang diberikan, buat koneksi baru
    if ($conn === null) {
        require_once 'koneksi.php';
    }
    
    // Pastikan koneksi tersedia
    if ($conn === null) {
        error_log("Error: Koneksi database tidak tersedia");
        return false;
    }
    
    try {
        // Get notulen data with lampiran
        $sql = "SELECT n.*, u.full_name as creator_name, u.nim as creator_nim
                FROM notulen n 
                JOIN user u ON n.created_by_user_id = u.user_id 
                WHERE n.id = ?";
        
        if (!$stmt = $conn->prepare($sql)) {
            throw new Exception("Gagal menyiapkan query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $notulen_id);
        if (!$stmt->execute()) {
            throw new Exception("Gagal mengeksekusi query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $notulen = $result->fetch_assoc();
        
        if (!$notulen) {
            throw new Exception("Notulen dengan ID $notulen_id tidak ditemukan");
        }
        
        // Format tanggal
        $tanggal_format = date('d F Y', strtotime($notulen['tanggal']));
        $bulan_indo = array(
            'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
            'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
            'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
            'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
        );
        $tanggal_format = str_replace(array_keys($bulan_indo), array_values($bulan_indo), $tanggal_format);
        
        // Decode lampiran
        $lampiran_files = array();
        if (!empty($notulen['lampiran']) && $notulen['lampiran'] != 'null' && $notulen['lampiran'] != '') {
            $lampiran_data = json_decode($notulen['lampiran'], true);
            if (is_array($lampiran_data)) {
                $lampiran_files = $lampiran_data;
            }
        }
        
        // Buat PDF
        $pdf = new PDF('P', 'mm', 'A4');
        $pdf->SetHeaderInfo($notulen['judul'], $tanggal_format);
        
        // Set margin: kiri 2.5cm, atas 2.5cm, kanan 2cm, bawah 2cm
        $pdf->SetMargins(25, 25, 20);
        $pdf->SetAutoPageBreak(true, 25);
        
        // Set font default
        $pdf->SetFont('Times', '', 11);
        
        // ============ HALAMAN 1: NOTULEN UTAMA ============
        $pdf->AddPage();
        
        // Judul Notulen
        $pdf->SetFont('Times', 'B', 16);
        $pdf->Cell(0, 8, 'NOTULEN RAPAT', 0, 1, 'C');
        $pdf->SetFont('Times', 'B', 14);
        $pdf->Cell(0, 6, strtoupper($notulen['judul']), 0, 1, 'C');
        $pdf->Ln(8);
        
        // Informasi Rapat
        $pdf->SetFont('Times', '', 11);
        
        $pdf->Cell(40, 7, 'Hari/Tanggal', 0, 0, 'L');
        $pdf->Cell(5, 7, ':', 0, 0, 'L');
        $pdf->Cell(0, 7, $notulen['hari'] . ', ' . $tanggal_format, 0, 1, 'L');
        
        $pdf->Cell(40, 7, 'Waktu', 0, 0, 'L');
        $pdf->Cell(5, 7, ':', 0, 0, 'L');
        $pdf->Cell(0, 7, $notulen['jam_mulai'] . ' - ' . $notulen['jam_selesai'], 0, 1, 'L');
        
        $pdf->Cell(40, 7, 'Tempat', 0, 0, 'L');
        $pdf->Cell(5, 7, ':', 0, 0, 'L');
        $pdf->Cell(0, 7, $notulen['tempat'], 0, 1, 'L');
        
        $pdf->Cell(40, 7, 'Pemimpin Rapat', 0, 0, 'L');
        $pdf->Cell(5, 7, ':', 0, 0, 'L');
        $pdf->Cell(0, 7, $notulen['penanggung_jawab'], 0, 1, 'L');
        
        $pdf->Cell(40, 7, 'Notulis', 0, 0, 'L');
        $pdf->Cell(5, 7, ':', 0, 0, 'L');
        $pdf->Cell(0, 7, $notulen['notulis'], 0, 1, 'L');
        
        $pdf->Cell(40, 7, 'Jurusan', 0, 0, 'L');
        $pdf->Cell(5, 7, ':', 0, 0, 'L');
        $pdf->Cell(0, 7, $notulen['jurusan'], 0, 1, 'L');
        
        $pdf->Ln(8);
        
        // Garis pemisah
        $pdf->DrawSeparator();
        
        // Section: Pembahasan
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 8, 'pembahasan:', 0, 1, 'L');
        $pdf->Ln(2);
        
        if (empty(trim($notulen['pembahasan']))) {
            $pdf->SetFont('Times', 'I', 11);
            $pdf->Cell(0, 6, '- Tidak ada pembahasan -', 0, 1, 'L');
        } else {
            $pdf->SetFont('Times', '', 11);
            $pdf->MultiLineText($notulen['pembahasan'], 'J');
        }
        
        $pdf->Ln(8);
        
        // Section: Hasil Akhir
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 8, 'Hasil Akhir:', 0, 1, 'L');
        $pdf->Ln(2);
        
        if (empty(trim($notulen['hasil_akhir']))) {
            $pdf->SetFont('Times', 'I', 11);
            $pdf->Cell(0, 6, '- Tidak ada hasil akhir -', 0, 1, 'L');
        } else {
            $pdf->SetFont('Times', '', 11);
            $pdf->MultiLineText($notulen['hasil_akhir'], 'J');
        }
        
        $pdf->Ln(15);
        
        // Penutup
        $pdf->SetFont('Times', '', 11);
        $pdf->MultiCell(0, 6, 'Demikian notulen rapat ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.');
        
        // Tanda tangan
        $pdf->Ln(15);
        
        // Kolom kiri: Pemimpin Rapat
        $pdf->SetX(20);
        $pdf->Cell(70, 6, 'Pemimpin Rapat,', 0, 0, 'C');
        
        // Kolom kanan: Notulis
        $pdf->SetX(120);
        $pdf->Cell(70, 6, 'Notulis Rapat,', 0, 1, 'C');
        
        // Spasi untuk tanda tangan
        $pdf->Ln(20);
        
        // Nama Pemimpin Rapat
        $pdf->SetX(20);
        $pdf->SetFont('Times', 'BU', 11);
        $pdf->Cell(70, 6, $notulen['penanggung_jawab'], 0, 0, 'C');
        
        // Nama Notulis
        $pdf->SetX(120);
        $pdf->SetFont('Times', 'BU', 11);
        $pdf->Cell(70, 6, $notulen['notulis'], 0, 1, 'C');
        
        // Keterangan
        $pdf->SetX(20);
        $pdf->SetFont('Times', '', 11);
        $pdf->Cell(70, 4, 'Penanggung Jawab', 0, 0, 'C');
        $pdf->SetX(120);
        $pdf->Cell(70, 4, 'Notulis Rapat', 0, 1, 'C');
        
        // ============ HALAMAN 2: DAFTAR HADIR ============
        // Get peserta data
        $sql_peserta = "SELECT u.full_name, u.nim, u.role, k.status 
                        FROM peserta_notulen pn 
                        JOIN user u ON pn.user_id = u.user_id 
                        LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id 
                        WHERE pn.notulen_id = ? 
                        ORDER BY u.full_name";
        $stmt_peserta = $conn->prepare($sql_peserta);
        $stmt_peserta->bind_param("i", $notulen_id);
        $stmt_peserta->execute();
        $result_peserta = $stmt_peserta->get_result();
        
        if ($result_peserta && $result_peserta->num_rows > 0) {
            $pdf->AddPage();
            
            // Header daftar hadir
            $pdf->SetFont('Times', 'B', 16);
            $pdf->Cell(0, 8, 'DAFTAR HADIR PESERTA RAPAT', 0, 1, 'C');
            $pdf->SetFont('Times', 'B', 14);
            $pdf->Cell(0, 6, strtoupper($notulen['judul']), 0, 1, 'C');
            $pdf->Ln(5);
            
            // Info rapat
            $pdf->SetFont('Times', '', 10);
            
            $pdf->Cell(35, 6, 'Hari/Tanggal', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['hari'] . ', ' . $tanggal_format, 0, 1, 'L');
            
            $pdf->Cell(35, 6, 'Waktu', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['jam_mulai'] . ' - ' . $notulen['jam_selesai'], 0, 1, 'L');
            
            $pdf->Cell(35, 6, 'Tempat', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['tempat'], 0, 1, 'L');
            
            
            $pdf->Cell(35, 6, 'Pemimpin Rapat', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['penanggung_jawab'], 0, 1, 'L');
            
            $pdf->Cell(35, 6, 'Notulis', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['notulis'], 0, 1, 'L');
            
            $pdf->Ln(8);
            
            // Garis pemisah sebelum tabel
            $pdf->DrawSeparator();
            
            // Header tabel
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetFont('Times', 'B', 10);
            
            $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell(70, 8, 'Nama Peserta', 1, 0, 'C', true);
            $pdf->Cell(35, 8, 'NIM', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Jabatan', 1, 0, 'C', true);
            $pdf->Cell(25, 8, 'Kehadiran', 1, 1, 'C', true);
            
            // Data peserta
            $pdf->SetFont('Times', '', 10);
            $no = 1;
            $total_hadir = 0;
            $total_tidak_hadir = 0;
            $total_belum_konfirmasi = 0;
            
            $fill = false;
            while ($peserta = $result_peserta->fetch_assoc()) {
                $fill = !$fill;
                $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
                
                $status_text = '';
                switch($peserta['status']) {
                    case 'hadir': 
                        $status_text = 'Hadir'; 
                        $total_hadir++; 
                        $pdf->SetTextColor(0, 128, 0);
                        break;
                    case 'tidak_hadir': 
                        $status_text = 'Tidak Hadir'; 
                        $total_tidak_hadir++; 
                        $pdf->SetTextColor(255, 0, 0);
                        break;
                    default: 
                        $status_text = 'Belum Konfirmasi';
                        $total_belum_konfirmasi++;
                        $pdf->SetTextColor(128, 128, 128);
                }
                
                $pdf->Cell(10, 7, $no, 1, 0, 'C', $fill);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(70, 7, $peserta['full_name'], 1, 0, 'L', $fill);
                $pdf->Cell(35, 7, $peserta['nim'], 1, 0, 'C', $fill);
                $pdf->Cell(30, 7, ucfirst($peserta['role']), 1, 0, 'C', $fill);
                
                // Kembalikan warna untuk status
                switch($peserta['status']) {
                    case 'hadir': $pdf->SetTextColor(0, 128, 0); break;
                    case 'tidak_hadir': $pdf->SetTextColor(255, 0, 0); break;
                    default: $pdf->SetTextColor(128, 128, 128);
                }
                
                $pdf->Cell(25, 7, $status_text, 1, 1, 'C', $fill);
                
                $no++;
            }
            
            $pdf->SetTextColor(0, 0, 0);
            
            // Statistik kehadiran
            $total_peserta = $total_hadir + $total_tidak_hadir + $total_belum_konfirmasi;
            
            $pdf->Ln(10);
            $pdf->SetFont('Times', 'B', 11);
            $pdf->Cell(0, 8, 'Rekap Kehadiran:', 0, 1, 'L');
            $pdf->Ln(2);
            
            $pdf->SetFont('Times', '', 10);
            $pdf->Cell(0, 6, 'Total Peserta: ' . $total_peserta . ' orang', 0, 1, 'L');
            $pdf->Cell(0, 6, 'Hadir: ' . $total_hadir . ' orang', 0, 1, 'L');
            $pdf->Cell(0, 6, 'Tidak Hadir: ' . $total_tidak_hadir . ' orang', 0, 1, 'L');
            $pdf->Cell(0, 6, 'Belum Konfirmasi: ' . $total_belum_konfirmasi . ' orang', 0, 1, 'L');
            
            if ($total_peserta > 0) {
                $persentase = round(($total_hadir / $total_peserta) * 100, 1);
                $pdf->Cell(0, 6, 'Persentase Kehadiran: ' . $persentase . '%', 0, 1, 'L');
            }
            
            // Footer informasi
            $pdf->Ln(12);
            $pdf->SetFont('Times', 'I', 8);
            $pdf->Cell(0, 5, 'Dicetak pada: ' . date('d F Y, H:i') . ' WIB', 0, 1, 'L');
            $pdf->Cell(0, 5, 'Oleh: ' . $notulen['creator_name'] . ' (' . $notulen['creator_nim'] . ')', 0, 1, 'L');
        }
        
        // ============ HALAMAN TERAKHIR: LAMPIRAN ============
        $image_count = 0;
        if (!empty($lampiran_files)) {
            // Tambah halaman baru untuk lampiran
            $pdf->AddPage();
            
            // Header lampiran
            $pdf->SetFont('Times', 'B', 16);
            $pdf->Cell(0, 8, 'LAMPIRAN NOTULEN RAPAT', 0, 1, 'C');
            $pdf->SetFont('Times', 'B', 14);
            $pdf->Cell(0, 6, strtoupper($notulen['judul']), 0, 1, 'C');
            $pdf->Ln(5);
            
            // Info notulen
            $pdf->SetFont('Times', '', 10);
            $pdf->Cell(35, 6, 'Hari/Tanggal', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['hari'] . ', ' . $tanggal_format, 0, 1, 'L');
            
            $pdf->Cell(35, 6, 'Waktu', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['jam_mulai'] . ' - ' . $notulen['jam_selesai'], 0, 1, 'L');
            
            $pdf->Cell(35, 6, 'Tempat', 0, 0, 'L');
            $pdf->Cell(5, 6, ':', 0, 0, 'L');
            $pdf->Cell(0, 6, $notulen['tempat'], 0, 1, 'L');
            
            $pdf->Ln(10);
            
            // Garis pemisah
            $pdf->DrawSeparator();
            
            // Judul section lampiran
            $pdf->SetFont('Times', 'B', 12);
            $pdf->Cell(0, 8, 'Dokumen Lampiran:', 0, 1, 'L');
            $pdf->Ln(10);
            
            // Tampilkan semua lampiran gambar
            foreach ($lampiran_files as $index => $file) {
                $file_path = 'uploads/lampiran/' . $file['file_name'];
                
                // Check if file exists and is an image
                if (file_exists($file_path) && 
                    preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $file['original_name'])) {
                    
                    $image_count++;
                    
                    // Tampilkan gambar dengan ukuran 80x80
                    $pdf->DisplayImage80x80($file_path, 'Lampiran ' . $image_count . ': ' . $file['original_name']);
                    
                    // Tambah spasi antara gambar
                    if ($image_count < count($lampiran_files)) {
                        $pdf->Ln(15);
                    }
                }
            }
            
            // Jika ada lampiran non-gambar, tampilkan daftarnya
            $non_image_count = 0;
            foreach ($lampiran_files as $file) {
                $file_path = 'uploads/lampiran/' . $file['file_name'];
                
                if (!preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $file['original_name'])) {
                    if ($non_image_count == 0) {
                        $pdf->SetFont('Times', 'B', 12);
                        $pdf->Cell(0, 8, 'File Lampiran Lainnya:', 0, 1, 'L');
                        $pdf->Ln(5);
                    }
                    $non_image_count++;
                    $pdf->SetFont('Times', '', 10);
                    $pdf->Cell(0, 6, $non_image_count . '. ' . $file['original_name'], 0, 1, 'L');
                }
            }
            
            // Info sistem di bagian bawah
            $pdf->Ln(15);
            $pdf->SetFont('Times', 'I', 8);
            $pdf->Cell(0, 5, 'Dokumen ini digenerate otomatis oleh Sistem Notulen Politeknik Negeri Batam', 0, 0, 'C');
        }
        
        // Simpan PDF
        $clean_judul = preg_replace('/[^\p{L}\p{N}\s]/u', '', $notulen['judul']);
        $clean_judul = str_replace(' ', '_', $clean_judul);
        $clean_judul = preg_replace('/_+/', '_', $clean_judul);
        $clean_judul = trim($clean_judul, '_');
        $clean_judul = substr($clean_judul, 0, 50);
        
        $filename = $clean_judul . '.pdf';
        $filepath = 'pdf_files/' . $filename;
        
        if (!file_exists('pdf_files')) {
            mkdir('pdf_files', 0777, true);
        }
        
        $pdf->Output('F', $filepath);
        
        // Tutup statement
        if (isset($stmt) && $stmt) $stmt->close();
        if (isset($stmt_peserta) && $stmt_peserta) $stmt_peserta->close();
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("Error generating PDF: " . $e->getMessage());
        
        // Tutup statement jika ada
        if (isset($stmt) && $stmt) $stmt->close();
        if (isset($stmt_peserta) && $stmt_peserta) $stmt_peserta->close();
        
        return false;
    }
}

// ================== HANDLE DOWNLOAD LANGSUNG ==================
if (isset($_GET['id']) && isset($_GET['download'])) {
    // Untuk download langsung, perlu koneksi database
    require_once 'koneksi.php';
    $notulen_id = intval($_GET['id']);
    
    // Generate PDF
    $filepath = generateNotulenPDF($notulen_id, $conn);
    
    if ($filepath && file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        
        // Tutup koneksi
        if ($conn) $conn->close();
        exit;
    } else {
        echo "Error: Gagal membuat PDF";
        if ($conn) $conn->close();
    }
}
?>