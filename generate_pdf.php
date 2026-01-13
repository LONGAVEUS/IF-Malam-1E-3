<?php
require('fpdf/fpdf.php');

class PDF extends FPDF {
    private $headerTitle = '';
    private $headerDate = '';
    
    function SetHeaderInfo($title, $date) {
        $this->headerTitle = $title;
        $this->headerDate = $date;
    }
    
    function Header() {
        if ($this->PageNo() == 1) {
            $this->SetFont('Times', 'B', 14);
            $this->Cell(0, 6, 'POLITEKNIK NEGERI BATAM', 0, 1, 'C');
            $this->SetFont('Times', '', 10);
            $this->Cell(0, 4, 'Jl. Ahmad Yani, Batam Centre, Batam 29461', 0, 1, 'C');
            
            $this->SetY($this->GetY() + 2);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.5);
            $this->Line(25, $this->GetY(), 185, $this->GetY());
            $this->SetY($this->GetY() + 8);
        }
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(25, $this->GetY() - 2, 185, $this->GetY() - 2);
        $this->SetFont('Times', 'I', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'Halaman ' . $this->PageNo(), 0, 0, 'R');
    }
    
    function MultiLineText($txt, $align = 'L', $bullet = false) {
        $txt = str_replace("\r", "", $txt);
        $lines = explode("\n", $txt);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
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
    
    function DisplayImage80x80($filepath, $description = '') {
        if (!file_exists($filepath)) return false;
        list($width, $height) = getimagesize($filepath);
        if ($width == 0 || $height == 0) return false;
        
        $maxWidth = 80;
        $maxHeight = 80;
        $ratio = $width / $height;
        
        if ($ratio > 1) {
            $newWidth = $maxWidth;
            $newHeight = $maxWidth / $ratio;
        } else {
            $newHeight = $maxHeight;
            $newWidth = $maxHeight * $ratio;
        }
        
        if (!empty($description)) {
            $this->SetFont('Times', 'B', 10);
            $this->Cell(0, 5, $description, 0, 1, 'C');
            $this->Ln(2);
        }
        
        $xPos = (210 - $newWidth) / 2;
        $y = $this->GetY();
        $this->Image($filepath, $xPos, $y, $newWidth, $newHeight);
        $this->SetY($y + $newHeight + 10);
        return true;
    }
    
    function DrawSeparator() {
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.3);
        $this->Line(25, $this->GetY(), 185, $this->GetY());
        $this->Ln(5);
    }
}

function generateNotulenPDF($notulen_id, $conn = null) {
    if ($conn === null) require_once 'koneksi.php';
    if ($conn === null) return false;

    try {
        // 1. Ambil Data Notulen
        $sql = "SELECT n.*, u.full_name as creator_name, u.nim as creator_nim FROM notulen n 
                JOIN user u ON n.created_by_user_id = u.user_id WHERE n.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notulen_id);
        $stmt->execute();
        $notulen = $stmt->get_result()->fetch_assoc();
        if (!$notulen) throw new Exception("Notulen tidak ditemukan");

        // Format Tanggal
        $tanggal_format = date('d F Y', strtotime($notulen['tanggal']));
        $bulan_indo = ['January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus','September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'];
        $tanggal_format = str_replace(array_keys($bulan_indo), array_values($bulan_indo), $tanggal_format);

        $pdf = new PDF('P', 'mm', 'A4');
        $pdf->SetMargins(25, 25, 20);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();
        
        // Judul
        $pdf->SetFont('Times', 'B', 16);
        $pdf->Cell(0, 8, 'NOTULEN RAPAT', 0, 1, 'C');
        $pdf->SetFont('Times', 'B', 14);
        $pdf->Cell(0, 6, strtoupper($notulen['judul']), 0, 1, 'C');
        $pdf->Ln(8);

        // Info Rapat
        $pdf->SetFont('Times', '', 11);
        $info = [
            'Hari/Tanggal' => $notulen['hari'] . ', ' . $tanggal_format,
            'Waktu' => $notulen['jam_mulai'] . ' - ' . $notulen['jam_selesai'],
            'Tempat' => $notulen['tempat'],
            'Pemimpin Rapat' => $notulen['penanggung_jawab'],
            'Notulis' => $notulen['notulis'],
            'Jurusan' => $notulen['jurusan']
        ];
        foreach($info as $key => $val) {
            $pdf->Cell(40, 7, $key, 0, 0, 'L');
            $pdf->Cell(5, 7, ':', 0, 0, 'L');
            $pdf->Cell(0, 7, $val, 0, 1, 'L');
        }
        $pdf->Ln(5);
        $pdf->DrawSeparator();

        // Isi
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 8, 'Pembahasan:', 0, 1, 'L');
        $pdf->SetFont('Times', '', 11);
        $pdf->MultiLineText($notulen['pembahasan'] ?: '- Tidak ada pembahasan -', 'J');

        $pdf->Ln(5);
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 8, 'Hasil Akhir:', 0, 1, 'L');
        $pdf->SetFont('Times', '', 11);
        $pdf->MultiLineText($notulen['hasil_akhir'] ?: '- Tidak ada hasil akhir -', 'J');

        // --- 1. PROSES DATA TANDA TANGAN (Query sudah benar) ---
$sql_ttd = "SELECT u.full_name, k.signature_path 
            FROM kehadiran k 
            JOIN user u ON k.user_id = u.user_id 
            WHERE k.notulen_id = ? AND (u.full_name = ? OR u.full_name = ?)";

$stmt_ttd = $conn->prepare($sql_ttd);
$stmt_ttd->bind_param("iss", $notulen_id, $notulen['penanggung_jawab'], $notulen['notulis']);
$stmt_ttd->execute();
$res_ttd = $stmt_ttd->get_result();

$ttd_images = []; // Variabel penampung hasil query
while($row = $res_ttd->fetch_assoc()) {
    $ttd_images[$row['full_name']] = $row['signature_path']; 
}

// --- 2. TAMPILAN JABATAN ---
$pdf->SetX(25);
$pdf->Cell(70, 6, 'Pemimpin Rapat,', 0, 0, 'C');
$pdf->SetX(115);
$pdf->Cell(70, 6, 'Notulis Rapat,', 0, 1, 'C');

// --- 3. LOGIKA GAMBAR TTD (Gunakan $ttd_images) ---
$y_img = $pdf->GetY(); 

// Gambar TTD Pemimpin Rapat
if (isset($ttd_images[$notulen['penanggung_jawab']])) {
    $path_p = 'uploads/' . $ttd_images[$notulen['penanggung_jawab']];
    if (file_exists($path_p)) {
        $pdf->Image($path_p, 45, $y_img, 30, 15);
    }
}

// Gambar TTD Notulis Rapat
if (isset($ttd_images[$notulen['notulis']])) {
    $path_n = 'uploads/' . $ttd_images[$notulen['notulis']];
    if (file_exists($path_n)) {
        $pdf->Image($path_n, 135, $y_img, 30, 15);
    }
}

        $pdf->Ln(18);
        $pdf->SetFont('Times', 'BU', 11);
        $pdf->SetX(25);
        $pdf->Cell(70, 6, $notulen['penanggung_jawab'], 0, 0, 'C');
        $pdf->SetX(115);
        $pdf->Cell(70, 6, $notulen['notulis'], 0, 1, 'C');

        // --- HALAMAN 2: DAFTAR HADIR ---
        $sql_peserta = "SELECT u.full_name, u.nim, u.role, k.status, k.signature_path 
                        FROM peserta_notulen pn 
                        JOIN user u ON pn.user_id = u.user_id 
                        LEFT JOIN kehadiran k ON pn.notulen_id = k.notulen_id AND pn.user_id = k.user_id 
                        WHERE pn.notulen_id = ? ORDER BY u.full_name";
        $stmt_p = $conn->prepare($sql_peserta);
        $stmt_p->bind_param("i", $notulen_id);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result();

        if ($res_p->num_rows > 0) {
            $pdf->AddPage();
            $pdf->SetFont('Times', 'B', 14);
            $pdf->Cell(0, 8, 'DAFTAR HADIR PESERTA', 0, 1, 'C');
            $pdf->Ln(5);
            
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
            $pdf->Cell(70, 8, 'Nama', 1, 0, 'C', true);
            $pdf->Cell(30, 8, 'Status', 1, 0, 'C', true);
            $pdf->Cell(55, 8, 'Tanda Tangan', 1, 1, 'C', true);

            $no = 1;
            $pdf->SetFont('Times', '', 10);
            while($p = $res_p->fetch_assoc()) {
                $x = $pdf->GetX();
                $y = $pdf->GetY();
                $pdf->Cell(10, 12, $no++, 1, 0, 'C');
                $pdf->Cell(70, 12, $p['full_name'], 1, 0, 'L');
                $pdf->Cell(30, 12, ucfirst($p['status'] ?? '-'), 1, 0, 'C');
                
                $x_ttd = $pdf->GetX();
                $pdf->Cell(55, 12, '', 1, 1, 'C');
                if (($p['status'] ?? '') == 'hadir' && !empty($p['signature_path'])) {
                    $img = 'uploads/' . $p['signature_path'];
                    if (file_exists($img)) $pdf->Image($img, $x_ttd + 15, $y + 1, 25, 10);
                }
            }
        }

        // Simpan
        if (!file_exists('pdf_files')) mkdir('pdf_files', 0777, true);
        $filepath = 'pdf_files/notulen_' . $notulen_id . '.pdf';
        $pdf->Output('F', $filepath);
        return $filepath;

    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}

// HANDLE DOWNLOAD
if (isset($_GET['id']) && isset($_GET['download'])) {
    require_once 'koneksi.php';
    $filepath = generateNotulenPDF(intval($_GET['id']), $conn);
    if ($filepath && file_exists($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
        readfile($filepath);
        exit;
    }
}
?>
