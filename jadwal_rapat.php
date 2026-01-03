<?php
require_once 'koneksi.php';

/* ==================================================
  FOTO PROFIL 
================================================== */
$foto_sekarang = $userLogin['photo'];
$path_valid = (!empty($userLogin['photo'])) ? $userLogin['photo'] : 'uploads/profile_photos/default_profile.png';
$current_photo_url = $path_valid . "?t=" . time();

// Ambil parameter bulan dan tahun dari URL, default ke bulan dan tahun saat ini
$bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : date('n');
$tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');

// Validasi bulan
if ($bulan < 1 || $bulan > 12) {
    $bulan = date('n');
    $tahun = date('Y');
}

// Hitung bulan sebelumnya dan selanjutnya
$bulan_sebelumnya = $bulan - 1;
$tahun_sebelumnya = $tahun;
if ($bulan_sebelumnya < 1) {
    $bulan_sebelumnya = 12;
    $tahun_sebelumnya = $tahun - 1;
}

$bulan_selanjutnya = $bulan + 1;
$tahun_selanjutnya = $tahun;
if ($bulan_selanjutnya > 12) {
    $bulan_selanjutnya = 1;
    $tahun_selanjutnya = $tahun + 1;
}

// Ambil semua notulen untuk bulan dan tahun yang dipilih (hanya yang status sent)
$tanggal_awal = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$tanggal_akhir = date('Y-m-t', strtotime("$tahun-$bulan-01"));

$sql_notulen = "SELECT id, judul, tanggal, penanggung_jawab, status FROM notulen 
                WHERE DATE(tanggal) BETWEEN ? AND ? AND status = 'sent'
                ORDER BY tanggal ASC";
$stmt = $conn->prepare($sql_notulen);
$stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
$stmt->execute();
$result_notulen = $stmt->get_result();

$notulen_per_hari = [];
while ($notulen = $result_notulen->fetch_assoc()) {
    $hari = date('j', strtotime($notulen['tanggal']));
    if (!isset($notulen_per_hari[$hari])) {
        $notulen_per_hari[$hari] = [];
    }
    $notulen_per_hari[$hari][] = $notulen;
}
$stmt->close();

// Fungsi untuk mendapatkan nama bulan dalam Bahasa Indonesia
function getNamaBulan($bulan) {
    $nama_bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $nama_bulan[$bulan];
}

// Hitung hari dalam bulan
$jumlah_hari = date('t', strtotime("$tahun-$bulan-01"));
$hari_pertama = date('N', strtotime("$tahun-$bulan-01")); // 1=Senin, 7=Minggu

// Sesuaikan untuk kalender (Minggu = 0, Senin = 1, ..., Sabtu = 6)
$offset = $hari_pertama - 1;
?>

<!DOCTYPE html>
<html lang="id">

<head>
  <title>Jadwal Rapat - Portal Tamu</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="jadwal-style.css">
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <div class="logo-area">
        <a href="#" class="header-logo">
          <img src="if.png" alt="Politeknik Negeri Batam">
        </a>
      </div>

      <button class="toggler">
        <span class="dekstop-icon"></span>
        <div class="hamburger-icon">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </button>
    </div>

    <nav class="sidebar-nav">
      <ul class="nav-list primary-nav">
        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-th-large nav-icon"></i>
            <span class="nav-label">Dashboard</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="jadwal_rapat_tamu.php" class="nav-link active">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Jadwal Rapat</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="tamu.php" class="nav-link">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Notulen Rapat</span>
          </a>
        </li>
      </ul>

      <ul class="nav-list secondary-nav">
        <li class="nav-item">
          <a href="#" class="nav-link">
            <i class="fas fa-user-circle nav-icon"></i>
            <span class="nav-label">Profil Saya</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="login.php" class="nav-link">
            <i class="fas fa-sign-in-alt nav-icon"></i>
            <span class="nav-label">Keluar</span>
          </a>
        </li>

        <!-- PROFIL LOGIN -->
        <li class="nav-item profile-user">
          <img src="<?php echo $current_photo_url; ?>?v=<?php echo time(); ?>" class="profile-avatar">

          <div class="profile-info">
            <span class="profile-name">
              <?= htmlspecialchars($userLogin['full_name']); ?>
            </span>
            <span class="profile-role">
              <?= ucfirst($userLogin['role']); ?>
            </span>
          </div>
        </li>

      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="main-content" id="mainContent">
    <div class="dashboard-header">
      <h1>Jadwal Rapat - Portal Tamu</h1>
      <p>Berikut adalah jadwal rapat yang sudah direncanakan. Anda dapat melihat detail notulen setelah rapat selesai.
      </p>
    </div>

    <div class="calendar-container">
      <div class="calendar-header">
        <div class="calendar-navigation">
          <a href="jadwal_rapat_tamu.php?bulan=<?php echo $bulan_sebelumnya; ?>&tahun=<?php echo $tahun_sebelumnya; ?>"
            class="nav-btn">
            <i class="fas fa-chevron-left"></i> Bulan Sebelumnya
          </a>
          <h2><?php echo getNamaBulan($bulan) . ' ' . $tahun; ?></h2>
          <a href="jadwal_rapat_tamu.php?bulan=<?php echo $bulan_selanjutnya; ?>&tahun=<?php echo $tahun_selanjutnya; ?>"
            class="nav-btn">
            Bulan Selanjutnya <i class="fas fa-chevron-right"></i>
          </a>
        </div>
        <div class="calendar-actions">
          <a href="jadwal_rapat_tamu.php" class="btn btn-today">Hari Ini</a>
        </div>
      </div>

      <div class="calendar">
        <div class="calendar-weekdays">
          <div class="weekday">Senin</div>
          <div class="weekday">Selasa</div>
          <div class="weekday">Rabu</div>
          <div class="weekday">Kamis</div>
          <div class="weekday">Jumat</div>
          <div class="weekday">Sabtu</div>
          <div class="weekday">Minggu</div>
        </div>

        <div class="calendar-days">
          <?php
          // Tambah sel kosong untuk hari sebelum bulan dimulai
          for ($i = 0; $i < $offset; $i++) {
              echo '<div class="calendar-day empty"></div>';
          }

          // Tampilkan hari dalam bulan
          for ($hari = 1; $hari <= $jumlah_hari; $hari++) {
              $tanggal_lengkap = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
              $is_today = ($tanggal_lengkap == date('Y-m-d')) ? 'today' : '';
              $has_notulen = isset($notulen_per_hari[$hari]) ? 'has-events' : '';
              
              echo '<div class="calendar-day ' . $is_today . ' ' . $has_notulen . '" data-date="' . $tanggal_lengkap . '">';
              echo '<div class="day-number">' . $hari . '</div>';
              
              if (isset($notulen_per_hari[$hari])) {
                  echo '<div class="day-events">';
                  foreach ($notulen_per_hari[$hari] as $notulen) {
                      $status_class = 'sent'; // Untuk tamu, hanya menampilkan yang sent
                      echo '<div class="event-item ' . $status_class . '" data-id="' . $notulen['id'] . '">';
                      echo '<div class="event-title">' . htmlspecialchars($notulen['judul']) . '</div>';
                      echo '<div class="event-time">' . date('H:i', strtotime($notulen['tanggal'])) . '</div>';
                      echo '</div>';
                  }
                  echo '</div>';
              }
              
              echo '</div>';
          }

          // Tambah sel kosong untuk hari setelah bulan berakhir
          $total_cells = $offset + $jumlah_hari;
          $remaining_cells = 42 - $total_cells; // 6 baris x 7 hari = 42 sel
          if ($remaining_cells > 0) {
              for ($i = 0; $i < $remaining_cells; $i++) {
                  echo '<div class="calendar-day empty"></div>';
              }
          }
          ?>
        </div>
      </div>

      <div class="calendar-legend">
        <div class="legend-item">
          <span class="legend-color" style="background-color: #fff3cd;"></span>
          <span class="legend-text">Hari Ini</span>
        </div>
        <div class="legend-item">
          <span class="legend-color" style="background-color: #27ae60;"></span>
          <span class="legend-text">Rapat Terjadwal</span>
        </div>
      </div>
    </div>

    <!-- Modal Detail Notulen -->
    <div class="modal-overlay" id="detailModal">
      <div class="modal-container">
        <div class="modal-header">
          <h2 id="modalTitle">Detail Notulen</h2>
          <button class="modal-close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
          <div id="modalContent">
            <!-- Konten akan diisi oleh JavaScript -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Jadwal Rapat - JavaScript Eksternal
    document.addEventListener("DOMContentLoaded", function () {
      const sidebar = document.querySelector(".sidebar");
      const toggler = document.querySelector(".toggler");
      const sidebarNav = document.querySelector(".sidebar-nav");

      if (!sidebar || !toggler || !sidebarNav) return;

      toggler.addEventListener("click", function (e) {
        e.stopPropagation();

        /* ================= MOBILE ONLY ================= */
        if (window.innerWidth <= 768) {
          sidebarNav.classList.toggle("active");
        }
      });

      /* ================= CLOSE DROPDOWN SAAT KLIK DI LUAR (MOBILE) ================= */
      document.addEventListener("click", function (e) {
        if (window.innerWidth <= 768) {
          if (!sidebar.contains(e.target)) {
            sidebarNav.classList.remove("active");
          }
        }
      });

      /* ================= RESET SAAT RESIZE KE DESKTOP ================= */
      window.addEventListener("resize", function () {
        if (window.innerWidth > 768) {
          sidebarNav.classList.remove("active");
        }
      });
      // Initialize
      initJadwalRapat();

      function initJadwalRapat() {
        const sidebarState = localStorage.getItem("sidebarCollapsed");
        if (sidebarState === "true") {
          sidebar.classList.add("collapsed");
          updateTogglerIcon();
        }

        setupEventListeners();
        highlightToday();
      }

      function setupEventListeners() {
        // Sidebar toggler
        if (sidebarToggler && sidebar) {
          sidebarToggler.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");
            updateTogglerIcon();
            localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
          });
        }

        // Modal event listeners
        if (closeBtn) {
          closeBtn.addEventListener("click", hideModal);
        }

        window.addEventListener("click", function (event) {
          if (event.target === modal) {
            hideModal();
          }
        });

        // Event delegation untuk calendar days dan events - PERBAIKAN DI SINI
        document.addEventListener("click", function (event) {
          const target = event.target;

          // Klik pada event item (notulen individual)
          if (target.closest(".event-item")) {
            const eventItem = target.closest(".event-item");
            const notulenId = eventItem.getAttribute("data-id");
            console.log("Klik event item dengan ID:", notulenId);
            showNotulenDetail(notulenId);
            return;
          }

          // Klik pada calendar day yang memiliki events
          if (target.closest(".calendar-day.has-events")) {
            const calendarDay = target.closest(".calendar-day.has-events");
            const date = calendarDay.getAttribute("data-date");
            console.log("Klik calendar day dengan date:", date);
            showNotulenForDate(date);
          }
        });

        // Keyboard shortcuts
        document.addEventListener("keydown", function (event) {
          if (event.key === "Escape" && modal.style.display === "flex") {
            hideModal();
          }
        });
      }

      function updateTogglerIcon() {
        const icon = sidebarToggler.querySelector("span");
        if (sidebar.classList.contains("collapsed")) {
          icon.classList.remove("fa-chevron-left");
          icon.classList.add("fa-chevron-right");
        } else {
          icon.classList.remove("fa-chevron-right");
          icon.classList.add("fa-chevron-left");
        }
      }

      function highlightToday() {
        const today = new Date().toISOString().split("T")[0];
        const todayElement = document.querySelector(`.calendar-day[data-date="${today}"]`);
        if (todayElement) {
          todayElement.scrollIntoView({
            behavior: "smooth",
            block: "center",
          });
        }
      }

      function showNotulenForDate(date) {
        const dateObj = new Date(date);
        const options = {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        };
        const dateString = dateObj.toLocaleDateString("id-ID", options);

        modalTitle.textContent = `Notulen Tanggal ${dateString}`;
        modalContent.innerHTML =
          '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Memuat notulen...</div>';

        showModal();

        fetch(`get_notulen_by_date.php?date=${date}`)
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok");
            }
            return response.json();
          })
          .then((notulens) => {
            if (notulens.error) {
              modalContent.innerHTML = `<div class="error-message">${notulens.error}</div>`;
              return;
            }

            if (notulens.length === 0) {
              modalContent.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h3>Tidak Ada Notulen</h3>
                            <p>Tidak ada notulen rapat pada tanggal ${dateString}.</p>
                        </div>
                    `;
              return;
            }

            let html = `<div class="notulen-list-date">`;
            notulens.forEach((notulen) => {
              const waktu = new Date(notulen.tanggal).toLocaleTimeString("id-ID", {
                hour: "2-digit",
                minute: "2-digit",
              });
              const statusClass = notulen.status === "draft" ? "draft" : "sent";
              const statusText = notulen.status === "draft" ? "Draft" : "Terkirim";

              html += `
                        <div class="notulen-item-date">
                            <div class="notulen-header">
                                <h4 class="notulen-title">${escapeHtml(notulen.judul)}</h4>
                                <span class="notulen-time">${waktu}</span>
                            </div>
                            <div class="notulen-preview">${escapeHtml(notulen.isi.substring(0, 100))}...</div>
                            <div class="notulen-meta">
                                <span class="notulen-penanggung-jawab">
                                    <i class="fas fa-user"></i> ${escapeHtml(notulen.penanggung_jawab)}
                                </span>
                                <span class="notulen-status ${statusClass}">${statusText}</span>
                            </div>
                            <div class="notulen-actions">
                                <button class="btn btn-view" onclick="window.showNotulenDetail(${notulen.id})">
                                    <i class="fas fa-eye"></i> Lihat Detail
                                </button>
                            </div>
                        </div>
                    `;
            });
            html += `</div>`;

            modalContent.innerHTML = html;
          })
          .catch((error) => {
            console.error("Error:", error);
            modalContent.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Terjadi kesalahan saat memuat data notulen.</p>
                    </div>
                `;
          });
      }

      function showNotulenDetail(notulenId) {
        console.log("Menampilkan detail notulen ID:", notulenId);
        modalTitle.textContent = "Detail Notulen";
        modalContent.innerHTML =
          '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Memuat detail notulen...</div>';

        showModal();

        fetch(`edit_notulen.php?id=${notulenId}`)
          .then((response) => {
            if (!response.ok) {
              throw new Error("Network response was not ok");
            }
            return response.json();
          })
          .then((notulen) => {
            console.log("Data notulen:", notulen);
            if (notulen.error) {
              modalContent.innerHTML = `<div class="error-message">${notulen.error}</div>`;
              return;
            }

            const tanggal = new Date(notulen.tanggal).toLocaleDateString("id-ID", {
              weekday: "long",
              year: "numeric",
              month: "long",
              day: "numeric",
            });

            const waktu = new Date(notulen.tanggal).toLocaleTimeString("id-ID", {
              hour: "2-digit",
              minute: "2-digit",
            });

            let html = `
                    <div class="notulen-detail">
                        <div class="detail-item">
                            <div class="detail-label">Judul Rapat</div>
                            <div class="detail-value">${escapeHtml(notulen.judul)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tanggal & Waktu</div>
                            <div class="detail-value">${tanggal} - ${waktu}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Penanggung Jawab</div>
                            <div class="detail-value">${escapeHtml(notulen.penanggung_jawab)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Isi Notulen</div>
                            <div class="detail-value">${escapeHtml(notulen.isi).replace(/\n/g, "<br>")}</div>
                        </div>
                `;

            if (notulen.lampiran) {
              const namaFileAsli = notulen.nama_file_asli || notulen.lampiran;
              html += `
                        <div class="detail-item">
                            <div class="detail-label">Lampiran</div>
                            <div class="detail-value">
                                <a href="view.php?file=${encodeURIComponent(notulen.lampiran)}" target="_blank" class="file-link">
                                    <i class="fas fa-paperclip"></i> ${escapeHtml(namaFileAsli)}
                                </a>
                            </div>
                        </div>
                    `;
            }

            html += `
                        <div class="detail-actions">
                            <a href="notulis.php" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit Notulen
                            </a>
                            <a href="notulis.php?hapus=${notulen.id}" class="btn btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus notulen ini?')">
                                <i class="fas fa-trash"></i> Hapus Notulen
                            </a>
                        </div>
                    </div>
                `;

            modalContent.innerHTML = html;
          })
          .catch((error) => {
            console.error("Error:", error);
            modalContent.innerHTML = `
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Terjadi kesalahan saat memuat detail notulen.</p>
                        <p>Error: ${error.message}</p>
                    </div>
                `;
          });
      }

      function showModal() {
        modal.style.display = "flex";
        document.body.style.overflow = "hidden";
      }

      function hideModal() {
        modal.style.display = "none";
        document.body.style.overflow = "auto";
      }

      function escapeHtml(unsafe) {
        if (!unsafe) return "";
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      }

      // Expose functions to global scope
      window.showNotulenDetail = showNotulenDetail;
      window.hideModal = hideModal;
    });
  </script>
</body>

</html>

<?php
if (isset($conn)) {
    $conn->close();
}

