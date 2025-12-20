// Tamu Portal - JavaScript Eksternal
document.addEventListener("DOMContentLoaded", function () {
  console.log("Portal Tamu loaded successfully!");

  // Elements
  const sidebar = document.getElementById("sidebar");
  const sidebarToggler = document.getElementById("sidebarToggler");
  const modal = document.getElementById("detailModal");
  const closeBtn = document.getElementById("closeModal");
  const modalContent = document.getElementById("modalContent");
  const modalTitle = document.getElementById("modalTitle");

  // Initialize
  initTamuPortal();

  function initTamuPortal() {
    const sidebarState = localStorage.getItem("sidebarCollapsed");
    if (sidebarState === "true") {
      sidebar.classList.add("collapsed");
      updateTogglerIcon();
    }

    setupEventListeners();
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

  function showNotulenDetail(notulenId) {
    console.log("Menampilkan detail notulen ID:", notulenId);
    modalTitle.textContent = "Detail Notulen";
    modalContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Memuat detail notulen...</div>';

    showModal();

    fetch(`get_notulen_detail.php?id=${notulenId}`)
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
                            <div class="detail-value" style="white-space: pre-line;">${escapeHtml(notulen.isi)}</div>
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
                            <a href="view.php?file=${encodeURIComponent(notulen.lampiran)}" target="_blank" class="btn btn-download" ${
          !notulen.lampiran ? 'style="display:none;"' : ""
        }>
                                <i class="fas fa-download"></i> Unduh Lampiran
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
