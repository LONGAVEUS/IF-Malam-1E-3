// Jadwal Rapat - JavaScript Eksternal
document.addEventListener("DOMContentLoaded", function () {
  console.log("Jadwal Rapat loaded successfully!");

  // Elements
  const sidebar = document.getElementById("sidebar");
  const sidebarToggler = document.getElementById("sidebarToggler");
  const modal = document.getElementById("detailModal");
  const closeBtn = document.getElementById("closeModal");
  const modalContent = document.getElementById("modalContent");
  const modalTitle = document.getElementById("modalTitle");

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
      todayElement.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function showNotulenForDate(date) {
    const dateObj = new Date(date);
    const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
    const dateString = dateObj.toLocaleDateString("id-ID", options);

    modalTitle.textContent = `Notulen Tanggal ${dateString}`;
    modalContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Memuat notulen...</div>';

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
    modalContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Memuat detail notulen...</div>';

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
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  // Expose functions to global scope
  window.showNotulenDetail = showNotulenDetail;
  window.hideModal = hideModal;
});
