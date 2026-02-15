<?php
require('fpdf/fpdf.php');

class PDF extends FPDF {
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
    
    function MultiLineText($txt, $align = 'L') {
        $txt = str_replace("\r", "", $txt);
        $lines = explode("\n", $txt);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $this->MultiCell(0, 5, $line, 0, $align);
            } else {
                $this->Ln(3);
            }
        }
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
        // Pastikan kita mengambil 'created_by_user_id'
        $sql = "SELECT * FROM notulen WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notulen_id);
        $stmt->execute();
        $notulen = $stmt->get_result()->fetch_assoc();
        if (!$notulen) throw new Exception("Notulen tidak ditemukan");

        // Simpan ID Pembuat Notulen (Notulis)
        $id_pembuat_notulen = $notulen['created_by_user_id'];

        // Format Tanggal
        $tanggal_format = date('d F Y', strtotime($notulen['tanggal']));
        $bulan_indo = [
            'January'=>'Januari','February'=>'Februari','March'=>'Maret','April'=>'April',
            'May'=>'Mei','June'=>'Juni','July'=>'Juli','August'=>'Agustus',
            'September'=>'September','October'=>'Oktober','November'=>'November','December'=>'Desember'
        ];
        $tanggal_format = str_replace(array_keys($bulan_indo), array_values($bulan_indo), $tanggal_format);

        // Inisialisasi PDF
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

        // Isi Notulen
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 8, 'Pembahasan:', 0, 1, 'L');
        $pdf->SetFont('Times', '', 11);
        $pdf->MultiLineText($notulen['pembahasan'] ?: '- Tidak ada pembahasan -', 'J');

        $pdf->Ln(5);
        $pdf->SetFont('Times', 'B', 12);
        $pdf->Cell(0, 8, 'Hasil Akhir:', 0, 1, 'L');
        $pdf->SetFont('Times', '', 11);
        $pdf->MultiLineText($notulen['hasil_akhir'] ?: '- Tidak ada hasil akhir -', 'J');

        // ==========================================================
        // PROSES PENGAMBILAN TANDA TANGAN (LOGIKA BY CREATOR ID)
        // ==========================================================
        
        // Ambil data kehadiran, TERMASUK user_id nya untuk dicocokkan
        $sql_ttd = "SELECT k.user_id, u.full_name, k.signature_path 
                    FROM kehadiran k 
                    JOIN user u ON k.user_id = u.user_id 
                    WHERE k.notulen_id = ? AND k.status = 'hadir' AND k.signature_path IS NOT NULL";

        $stmt_ttd = $conn->prepare($sql_ttd);
        $stmt_ttd->bind_param("i", $notulen_id);
        $stmt_ttd->execute();
        $res_ttd = $stmt_ttd->get_result();

        $path_ttd_pemimpin = null;
        $path_ttd_notulis = null;

        // Siapkan nama pemimpin untuk pencocokan string (fuzzy)
        $target_pemimpin = strtolower(trim($notulen['penanggung_jawab']));

        while($row = $res_ttd->fetch_assoc()) {
            $current_user_id = $row['user_id'];
            $signer_name = strtolower(trim($row['full_name']));
            $signature_path = $row['signature_path'];

            // 1. CEK NOTULIS (BY ID) - Logika paling akurat
            // Jika ID user yang tanda tangan == ID pembuat notulen, ambil TTD-nya
            if ($current_user_id == $id_pembuat_notulen) {
                $path_ttd_notulis = $signature_path;
            }

            // 2. CEK PEMIMPIN RAPAT (BY NAME MATCHING)
            // Pemimpin rapat biasanya diketik manual, jadi pakai pencocokan nama
            if (strpos($signer_name, $target_pemimpin) !== false || strpos($target_pemimpin, $signer_name) !== false) {
                $path_ttd_pemimpin = $signature_path;
            }
        }
        $stmt_ttd->close();

        // ==========================================================
        // LAYOUT TANDA TANGAN
        // ==========================================================
        
        $pdf->Ln(15);
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
        }

        $y_blok_awal = $pdf->GetY(); 

        // Judul Jabatan
        $pdf->SetFont('Times', '', 11);
        $pdf->SetX(25);
        $pdf->Cell(70, 6, 'Pemimpin Rapat,', 0, 0, 'C'); 
        $pdf->SetX(115);
        $pdf->Cell(70, 6, 'Notulis Rapat,', 0, 1, 'C');
        
        // Posisi Y Gambar
        $y_img = $y_blok_awal + 6; 

        // GAMBAR PEMIMPIN (KIRI)
        if ($path_ttd_pemimpin) {
            $file_p = 'uploads/' . $path_ttd_pemimpin;
            if (file_exists($file_p)) {
                $pdf->Image($file_p, 45, $y_img, 30, 15); 
            }
        }

        // GAMBAR NOTULIS (KANAN)
        if ($path_ttd_notulis) {
            $file_n = 'uploads/' . $path_ttd_notulis;
            if (file_exists($file_n)) {
                $pdf->Image($file_n, 135, $y_img, 30, 15);
            }
        }

        // Nama Terang
        $pdf->SetY($y_blok_awal + 25); 
        $pdf->SetFont('Times', 'BU', 11);
        $pdf->SetX(25);
        $pdf->Cell(70, 6, $notulen['penanggung_jawab'], 0, 0, 'C');
        $pdf->SetX(115);
        $pdf->Cell(70, 6, $notulen['notulis'], 0, 1, 'C');

        // ==========================================================
        // HALAMAN 2: DAFTAR HADIR
        // ==========================================================
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
                $y_baris = $pdf->GetY();
                $x_baris = $pdf->GetX();
                $tinggi_baris = 15; 

                $pdf->Cell(10, $tinggi_baris, $no++, 1, 0, 'C');
                $pdf->Cell(70, $tinggi_baris, $p['full_name'], 1, 0, 'L');
                
                $statusText = $p['status'] ? ucfirst($p['status']) : 'Belum';
                if ($p['status'] == 'tidak_hadir') $statusText = 'Tidak Hadir';
                $pdf->Cell(30, $tinggi_baris, $statusText, 1, 0, 'C');
                
                $x_ttd_col = $pdf->GetX();
                $pdf->Cell(55, $tinggi_baris, '', 1, 1, 'C'); 
                
                if (($p['status'] ?? '') == 'hadir' && !empty($p['signature_path'])) {
                    $img = 'uploads/' . $p['signature_path'];
                    if (file_exists($img)) {
                        $pdf->Image($img, $x_ttd_col + 15, $y_baris + 2, 25, 11);
                    }
                }
            }
        }

        if (!file_exists('pdf_files')) mkdir('pdf_files', 0777, true);
        $filepath = 'pdf_files/notulen_' . $notulen_id . '.pdf';
        $pdf->Output('F', $filepath);
        return $filepath;

    } catch (Exception $e) {
        return false;
    }
}

if (isset($_GET['id']) && isset($_GET['download'])) {
    require_once 'koneksi.php';
    $filepath = generateNotulenPDF(intval($_GET['id']), $conn);
    if ($filepath && file_exists($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
        readfile($filepath);
        exit;
    } else {
        echo "PDF gagal dibuat.";
    }
}
?>
