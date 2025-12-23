[file name]: generate_pdf.php
[file content begin]
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
        if ($this->PageNo() > 1) {
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 5, $this->headerTitle . ' - ' . $this->headerDate, 0, 0, 'L');
            $this->Cell(0, 5, 'Halaman ' . $this->PageNo(), 0, 1, 'R');
            $this->Ln(3);
        }
    }
    
    // Footer halaman
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 5, 'Dokumen ini digenerate otomatis oleh Sistem Notulen Politeknik Negeri Batam', 0, 0, 'C');
    }
}

function generateNotulenPDF($notulen_id) {
    require_once 'koneksi.php';
    
    // Get notulen data
    $sql = "SELECT n.*, u.full_name as creator_name, u.nim as creator_nim
            FROM notulen n 
            JOIN user u ON n.created_by_user_id = u.user_id 
            WHERE n.Id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notulen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notulen = $result->fetch_assoc();
    
    if (!$notulen) return false;
    
    // Format tanggal
    $tanggal_format = date('d F Y', strtotime($notulen['tanggal']));
    $bulan_indo = array(
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
        'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
        'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
    );
    $tanggal_format = str_replace(array_keys($bulan_indo), array_values($bulan_indo), $tanggal_format);
    
    // Buat PDF
    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->SetHeaderInfo($notulen['judul'], $tanggal_format);
    $pdf->SetMargins(20, 15, 20);
    $pdf->SetAutoPageBreak(true, 20);
    
    // ============ HALAMAN UTAMA ============
    $pdf->AddPage();
    
    // Header Kop Surat
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'POLITEKNIK NEGERI BATAM', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Jl. Ahmad Yani, Batam Centre, Batam 29461', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Garis pembatas
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(1);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->SetLineWidth(0.5);
    $pdf->Line(20, $pdf->GetY() + 1, 190, $pdf->GetY() + 1);
    $pdf->Ln(8);
    
    // Judul Notulen
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, 'NOTULEN RAPAT', 0, 1, 'C');
    $pdf->SetFont('Arial', 'BU', 12);
    $pdf->Cell(0, 8, strtoupper($notulen['judul']), 0, 1, 'C');
    $pdf->Ln(8);
    
    // Informasi Rapat
    $pdf->SetFont('Arial', '', 11);
    
    $pdf->Cell(40, 6, 'Hari/Tanggal', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $notulen['hari'] . ', ' . $tanggal_format, 0, 1, 'L');
    
    $pdf->Cell(40, 6, 'Tempat', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $notulen['Tempat'], 0, 1, 'L');
    
    $pdf->Cell(40, 6, 'Pemimpin Rapat', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $notulen['penanggung_jawab'], 0, 1, 'L');
    
    $pdf->Cell(40, 6, 'Notulis', 0, 0, 'L');
    $pdf->Cell(5, 6, ':', 0, 0, 'L');
    $pdf->Cell(0, 6, $notulen['notulis'], 0, 1, 'L');
    
    $pdf->Ln(10);
    
    // Section: Pembahasan
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Pembahasan:', 0, 1, 'L');
    $pdf->Ln(2);
    
    if (empty(trim($notulen['Pembahasan']))) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, '-', 0, 1, 'L');
    } else {
        $pdf->SetFont('Arial', '', 10);
        
        $pembahasan_lines = explode("\n", $notulen['Pembahasan']);
        $counter = 1;
        
        foreach ($pembahasan_lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Cek apakah line sudah ada numberingnya
                if (preg_match('/^\d+\./', $line)) {
                    $pdf->Cell(10, 5, '', 0, 0);
                    $pdf->MultiCell(0, 5, $line);
                } else {
                    $pdf->Cell(10, 5, $counter . '.', 0, 0);
                    $pdf->MultiCell(0, 5, $line);
                    $counter++;
                }
            }
        }
    }
    
    $pdf->Ln(5);
    
    // Section: Hasil Keputusan
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, 'Hasil Keputusan:', 0, 1, 'L');
    $pdf->Ln(2);
    
    if (empty(trim($notulen['Hasil_akhir']))) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, '-', 0, 1, 'L');
    } else {
        $pdf->SetFont('Arial', '', 10);
        
        $hasil_lines = explode("\n", $notulen['Hasil_akhir']);
        $counter = 1;
        
        foreach ($hasil_lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                if (preg_match('/^\d+\./', $line)) {
                    $pdf->Cell(10, 5, '', 0, 0);
                    $pdf->MultiCell(0, 5, $line);
                } else {
                    $pdf->Cell(10, 5, $counter . '.', 0, 0);
                    $pdf->MultiCell(0, 5, $line);
                    $counter++;
                }
            }
        }
    }
    
    // Penutup
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, 'Demikian notulen rapat ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.');
    
    // Tanda tangan - disusun dalam dua kolom
    $pdf->Ln(20);
    
    // Kolom kiri: Pemimpin Rapat/Penanggung Jawab
    $pdf->Cell(95, 6, 'Pemimpin Rapat,', 0, 0, 'C');
    
    // Kolom kanan: Notulis
    $pdf->Cell(95, 6, 'Notulis,', 0, 1, 'C');
    
    // Spasi untuk tanda tangan
    $pdf->Ln(20);
    
    // Nama Pemimpin Rapat dengan garis bawah
    $pdf->SetFont('Arial', 'BU', 10);
    $pdf->Cell(95, 6, $notulen['penanggung_jawab'], 0, 0, 'C');
    
    // Nama Notulis dengan garis bawah
    $pdf->Cell(95, 6, $notulen['notulis'], 0, 1, 'C');
    
    // Tambahkan keterangan NIP/NIM jika tersedia (opsional)
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(95, 4, 'Penanggung Jawab', 0, 0, 'C');
    $pdf->Cell(95, 4, 'Notulis Rapat', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    
    // ============ HALAMAN DAFTAR HADIR ============
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
        
        // Header halaman
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'DAFTAR HADIR PESERTA RAPAT', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Info rapat
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(35, 6, 'Agenda Rapat', 0, 0, 'L');
        $pdf->Cell(5, 6, ':', 0, 0, 'L');
        $pdf->Cell(0, 6, $notulen['judul'], 0, 1, 'L');
        
        $pdf->Cell(35, 6, 'Hari/Tanggal', 0, 0, 'L');
        $pdf->Cell(5, 6, ':', 0, 0, 'L');
        $pdf->Cell(0, 6, $notulen['hari'] . ', ' . $tanggal_format, 0, 1, 'L');
        
        $pdf->Cell(35, 6, 'Tempat', 0, 0, 'L');
        $pdf->Cell(5, 6, ':', 0, 0, 'L');
        $pdf->Cell(0, 6, $notulen['Tempat'], 0, 1, 'L');
        
        $pdf->Cell(35, 6, 'Pemimpin Rapat', 0, 0, 'L');
        $pdf->Cell(5, 6, ':', 0, 0, 'L');
        $pdf->Cell(0, 6, $notulen['penanggung_jawab'], 0, 1, 'L');
        
        $pdf->Cell(35, 6, 'Notulis', 0, 0, 'L');
        $pdf->Cell(5, 6, ':', 0, 0, 'L');
        $pdf->Cell(0, 6, $notulen['notulis'], 0, 1, 'L');
        
        $pdf->Ln(8);
        
        // Header tabel
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(10, 8, 'No', 1, 0, 'C');
        $pdf->Cell(60, 8, 'Nama Peserta', 1, 0, 'C');
        $pdf->Cell(35, 8, 'NIM', 1, 0, 'C');
        $pdf->Cell(35, 8, 'Jabatan', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Kehadiran', 1, 1, 'C');
        
        // Data peserta
        $pdf->SetFont('Arial', '', 10);
        $no = 1;
        $total_hadir = 0;
        $total_tidak_hadir = 0;
        $total_belum_konfirmasi = 0;
        
        while ($peserta = $result_peserta->fetch_assoc()) {
            $status_text = '';
            switch($peserta['status']) {
                case 'hadir': 
                    $status_text = 'Hadir'; 
                    $total_hadir++; 
                    break;
                case 'tidak': 
                    $status_text = 'Tidak Hadir'; 
                    $total_tidak_hadir++; 
                    break;
                default: 
                    $status_text = 'Belum Konfirmasi';
                    $total_belum_konfirmasi++;
            }
            
            $pdf->Cell(10, 7, $no, 1, 0, 'C');
            $pdf->Cell(60, 7, $peserta['full_name'], 1, 0, 'L');
            $pdf->Cell(35, 7, $peserta['nim'], 1, 0, 'C');
            $pdf->Cell(35, 7, ucfirst($peserta['role']), 1, 0, 'C');
            $pdf->Cell(30, 7, $status_text, 1, 1, 'C');
            
            $no++;
        }
        
        // Statistik kehadiran
        $total_peserta = $total_hadir + $total_tidak_hadir + $total_belum_konfirmasi;
        
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, 'Rekap Kehadiran:', 0, 1, 'L');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, 'Total Peserta: ' . $total_peserta . ' orang', 0, 1, 'L');
        $pdf->Cell(0, 6, 'Hadir: ' . $total_hadir . ' orang', 0, 1, 'L');
        $pdf->Cell(0, 6, 'Tidak Hadir: ' . $total_tidak_hadir . ' orang', 0, 1, 'L');
        $pdf->Cell(0, 6, 'Belum Konfirmasi: ' . $total_belum_konfirmasi . ' orang', 0, 1, 'L');
        
        if ($total_peserta > 0) {
            $persentase = round(($total_hadir / $total_peserta) * 100, 1);
            $pdf->Cell(0, 6, 'Persentase Kehadiran: ' . $persentase . '%', 0, 1, 'L');
        }
        
        // Footer informasi
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(0, 5, 'Dicetak pada: ' . date('d F Y, H:i') . ' WIB', 0, 1, 'L');
        $pdf->Cell(0, 5, 'Oleh: ' . $notulen['creator_name'] . ' (' . $notulen['creator_nim'] . ')', 0, 1, 'L');
    }
    
    // Simpan PDF
    $filename = 'notulen_' . $notulen_id . '_' . date('Ymd_His') . '.pdf';
    $filepath = 'pdf_files/' . $filename;
    
    if (!file_exists('pdf_files')) {
        mkdir('pdf_files', 0777, true);
    }
    
    $pdf->Output('F', $filepath);
    
    $stmt->close();
    if (isset($stmt_peserta)) $stmt_peserta->close();
    $conn->close();
    
    return $filepath;
}

// Handle download langsung
if (isset($_GET['id']) && isset($_GET['download'])) {
    $notulen_id = intval($_GET['id']);
    $filepath = generateNotulenPDF($notulen_id);
    
    if ($filepath && file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        echo "Error: Gagal membuat PDF";
    }
}
?>
[file content end]