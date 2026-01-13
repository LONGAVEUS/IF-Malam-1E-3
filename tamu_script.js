// tamu_script.js

document.addEventListener("DOMContentLoaded", function () {
  /* ================= SIDEBAR MOBILE ================= */
  const sidebar = document.querySelector(".sidebar");
  const toggler = document.querySelector(".toggler");
  const sidebarNav = document.querySelector(".sidebar-nav");

  if (!sidebar || !toggler || !sidebarNav) return;

  toggler.addEventListener("click", function (e) {
    e.stopPropagation();
    if (window.innerWidth <= 768) {
      sidebarNav.classList.toggle("active");
    }
  });

  document.addEventListener("click", function (e) {
    if (window.innerWidth <= 768) {
      if (!sidebar.contains(e.target)) {
        sidebarNav.classList.remove("active");
      }
    }
  });

  window.addEventListener("resize", function () {
    if (window.innerWidth > 768) {
      sidebarNav.classList.remove("active");
    }
  });

  /* ================= GLOBAL VARIABLES ================= */
  let signaturePad = null; // Variable untuk Signature Pad
  const detailModal = document.getElementById("detailModal");
  const closeDetailBtn = document.getElementById("closeDetailModal");
  const detailModalContent = document.getElementById("detailModalContent");
  const searchInput = document.getElementById("searchInput");
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  const clearBtn = document.getElementById("clearBtn");

  /* ===== EVENT LISTENER MODAL DETAIL ===== */
  if (closeDetailBtn) closeDetailBtn.addEventListener("click", () => hideModal(detailModal));

  window.addEventListener("click", (e) => {
    if (e.target === detailModal) hideModal(detailModal);
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (detailModal?.style.display === "flex") hideModal(detailModal);
    }
  });

  if (searchInput && startDate && endDate && clearBtn) {
    setupSearchFilter();
  }

  /* ===== EVENT DELEGATION UNTUK TOMBOL ===== */
  document.addEventListener("click", function (e) {
    if (e.target.closest(".action-btn.view")) {
      const button = e.target.closest(".action-btn.view");
      const notulenId = button.getAttribute("data-notulen-id");
      if (notulenId && notulenId !== "0") {
        showNotulenDetail(notulenId);
      }
    }

    // Tombol konfirmasi 
    if (e.target.closest(".action-btn.konfirmasi")) {
      const button = e.target.closest(".action-btn.konfirmasi");
      const notulenId = button.getAttribute("data-notulen-id");
      if (notulenId && notulenId !== "0") {
        showNotulenDetail(notulenId);
      }
    }
  });

  /* ================= FUNGSI SEARCH & FILTER ================= */
  function setupSearchFilter() {
    let searchTimeout;
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        applyFilters();
      }, 500);
    });
    startDate.addEventListener("change", applyFilters);
    endDate.addEventListener("change", applyFilters);
    clearBtn.addEventListener("click", function () {
      window.location.href = "tamu.php?page=1";
    });
  }

  function applyFilters() {
    const params = new URLSearchParams();
    if (searchInput.value) params.append("search", searchInput.value);
    if (startDate.value) params.append("start_date", startDate.value);
    if (endDate.value) params.append("end_date", endDate.value);
    params.append("page", 1);
    window.location.href = "tamu.php?" + params.toString();
  }

  /* ================= FUNGSI DETAIL NOTULEN ================= */
  function showNotulenDetail(id) {
    if (!detailModal || !detailModalContent) return;

    detailModalContent.innerHTML = '<div class="loading-modal"><i class="fas fa-spinner fa-spin"></i> Memuat detail notulen...</div>';
    showModal(detailModal);

    fetch(`get_notulen_detail.php?id=${id}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.error) {
          detailModalContent.innerHTML = `<div class="error-message">${escapeHtml(data.error)}</div>`;
          return;
        }

        const n = data.notulen;
        const canViewDetails = data.user_status_kehadiran === "hadir";
        const tanggal = new Date(n.tanggal).toLocaleDateString("id-ID", {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        });

        // ---  TANDA TANGAN ---
        let userKehadiranSection = "";
        if (data.user_status_kehadiran) {
          const statusClass = data.user_status_kehadiran === "hadir" ? "hadir" : "tidak-hadir";
          userKehadiranSection = `
            <div class="section-card">
              <div class="kehadiran-status-display ${statusClass}">
                <i class="fas fa-check-circle"></i>
                <span>Status Anda: <strong>${data.user_status_kehadiran.toUpperCase()}</strong></span>
              </div>
            </div>`;
        } else if (n.status === "sent") {
          userKehadiranSection = `
            <div class="section-card">
              <div class="section-header"><h4><i class="fas fa-pen-nib"></i> Konfirmasi & Tanda Tangan</h4></div>
              <div class="signature-wrapper" style="text-align:center; padding:15px;">
                <p>Silakan tanda tangan di bawah untuk konfirmasi kehadiran:</p>
                <canvas id="signature-pad" style="border:2px dashed #ccc; background:#fff; border-radius:8px; width:100%; max-width:400px; height:200px; touch-action:none;"></canvas>
                <div class="modal-actions" style="justify-content:center; margin-top:15px; gap:10px;">
                  <button type="button" class="confirm-btn" id="clear-signature" style="background:#6c757d; color:#fff;">Hapus</button>
                  <button type="button" class="confirm-btn hadir" onclick="prosesSimpanTtd(${n.id})">Simpan & Hadir</button>
                </div>
              </div>
            </div>`;

          // Inisialisasi Signature Pad setelah HTML dirender
          setTimeout(() => {
            const canvas = document.getElementById("signature-pad");
            if (canvas) {
              signaturePad = new SignaturePad(canvas, { backgroundColor: "rgb(255, 255, 255)" });
              document.getElementById("clear-signature").addEventListener("click", () => signaturePad.clear());
            }
          }, 100);
        }

        
        detailModalContent.innerHTML = `
          <div class="detail-container">
            <div class="detail-header">
              <h3 class="detail-title">${escapeHtml(n.judul)}</h3>
              <div class="detail-status-container">
                <span class="notulen-status status-${n.status}">${n.status === "sent" ? "Terkirim" : "Final"}</span>
                <span class="info-badge"><i class="fas fa-calendar"></i> ${tanggal}</span>

                <span class="info-badge" style="background: var(--info-color);">
                  <i class="fas fa-user-tag"></i> Tamu
                </span>

              </div>
            </div>
            
            <div class="info-grid">
               <div class="info-item"><div class="info-label"><i class="fas fa-clock"></i> Waktu</div><div class="info-value">${
                 n.jam_mulai
               } - ${n.jam_selesai}</div></div>
               <div class="info-item"><div class="info-label"><i class="fas fa-map-marker-alt"></i> Tempat</div><div class="info-value">${escapeHtml(
                 n.tempat
               )}</div></div>
               <div class="info-item"><div class="info-label"><i class="fas fa-user-tie"></i> Penanggung Jawab</div><div class="info-value">${escapeHtml(
                 n.penanggung_jawab
               )}</div></div>
            </div>

            ${userKehadiranSection}

            ${
              canViewDetails
                ? `
              <div class="section-card">
                <div class="section-header"><h4><i class="fas fa-comments"></i> Pembahasan</h4></div>
                <div class="content-text">${escapeHtml(n.pembahasan || "Tidak ada data").replace(/\n/g, "<br>")}</div>
              </div>
              <div class="modal-actions center-actions">
                ${
                  n.status === "final"
                    ? `<a href="generate_pdf.php?id=${n.id}&download=1" class="confirm-btn hadir"><i class="fas fa-download"></i> Download PDF</a>`
                    : ""
                }
              </div>
            `
                : `<div class="access-restricted"><i class="fas fa-lock"></i> Detail lengkap hanya untuk peserta yang hadir.</div>`
            }
          </div>`;
      });
  }

  /* =================  PROSES SIMPAN TTD ================= */
  window.prosesSimpanTtd = function (notulenId) {
    if (!signaturePad || signaturePad.isEmpty()) {
      showNotification("Silakan tanda tangan terlebih dahulu!", "error");
      return;
    }

    if (confirm("Kirim tanda tangan dan konfirmasi hadir?")) {
      const signatureData = signaturePad.toDataURL("image/png");
      const formData = new FormData();
      formData.append("notulen_id", notulenId);
      formData.append("status", "hadir");
      formData.append("signature", signatureData); 
      fetch("konfirmasi_kehadiran.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            showNotification(data.message, "success");
            hideModal(detailModal);
            setTimeout(() => location.reload(), 1000);
          } else {
            showNotification(data.message, "error");
          }
        })
        .catch(() => showNotification("Terjadi kesalahan jaringan", "error"));
    }
  };

  /* ================= HELPER FUNCTIONS ================= */
  function showModal(modal) {
    if (modal) {
      modal.style.display = "flex";
      document.body.style.overflow = "hidden";
    }
  }
  function hideModal(modal) {
    if (modal) {
      modal.style.display = "none";
      document.body.style.overflow = "auto";
    }
  }
  function escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function showNotification(msg, type = "info") {
    const notif = document.createElement("div");
    notif.style.cssText = `position:fixed; top:20px; right:20px; padding:15px 20px; background:${
      type === "success" ? "#4CAF50" : "#f44336"
    }; color:white; border-radius:5px; z-index:9999; box-shadow:0 4px 6px rgba(0,0,0,0.1); animation: slideIn 0.3s ease;`;
    notif.innerHTML = `<i class="fas ${type === "success" ? "fa-check-circle" : "fa-exclamation-circle"}"></i> ${msg}`;
    document.body.appendChild(notif);
    setTimeout(() => {
      notif.remove();
    }, 3000);
  }
});
