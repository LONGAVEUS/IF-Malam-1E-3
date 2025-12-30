document.addEventListener("DOMContentLoaded", function () {
  /* ================= ELEMENT ================= */
  const sidebar = document.querySelector(".sidebar");
  const toggler = document.querySelector(".toggler");
  const sidebarNav = document.querySelector(".sidebar-nav");

  const modal = document.getElementById("detailModal");
  const closeBtn = document.getElementById("closeModal");
  const modalContent = document.getElementById("modalContent");
  const modalTitle = document.getElementById("modalTitle");

  if (!sidebar || !toggler || !sidebarNav) {
    console.warn("Sidebar / Toggler tidak ditemukan");
    return;
  }

  /* ================= MOBILE HAMBURGER ================= */
  toggler.addEventListener("click", function (e) {
    e.stopPropagation();

    if (window.innerWidth <= 768) {
      sidebarNav.classList.toggle("active");
    }
  });

  /* === TUTUP DROPDOWN JIKA KLIK DI LUAR (MOBILE) === */
  document.addEventListener("click", function (e) {
    if (window.innerWidth <= 768 && !sidebar.contains(e.target)) {
      sidebarNav.classList.remove("active");
    }
  });

  /* === RESET SAAT RESIZE KE DESKTOP === */
  window.addEventListener("resize", function () {
    if (window.innerWidth > 768) {
      sidebarNav.classList.remove("active");
    }
  });

  /* ================= MODAL ================= */
  if (closeBtn) {
    closeBtn.addEventListener("click", hideModal);
  }

  window.addEventListener("click", function (e) {
    if (e.target === modal) {
      hideModal();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal.style.display === "flex") {
      hideModal();
    }
  });

  /* ================= NOTULEN DETAIL ================= */
  window.showNotulenDetail = function (id) {
    modalTitle.textContent = "Detail Notulen";
    modalContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';

    showModal();

    fetch(`get_notulen_detail.php?id=${id}`)
      .then((res) => res.json())
      .then((data) => {
        if (data.error) {
          modalContent.innerHTML = `<p>${data.error}</p>`;
          return;
        }

        const tanggal = new Date(data.tanggal).toLocaleDateString("id-ID", {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        });

        const waktu = new Date(data.tanggal).toLocaleTimeString("id-ID", {
          hour: "2-digit",
          minute: "2-digit",
        });

        modalContent.innerHTML = `
          <div class="notulen-detail">
            <div class="detail-item">
              <div class="detail-label">Judul</div>
              <div class="detail-value">${escapeHtml(data.judul)}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Tanggal & Waktu</div>
              <div class="detail-value">${tanggal} - ${waktu}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Penanggung Jawab</div>
              <div class="detail-value">${escapeHtml(data.penanggung_jawab)}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Isi Notulen</div>
              <div class="detail-value" style="white-space: pre-line;">
                ${escapeHtml(data.isi)}
              </div>
            </div>
          </div>
        `;
      })
      .catch(() => {
        modalContent.innerHTML = "<p>Gagal memuat data</p>";
      });
  };

  /* ================= HELPER ================= */
  function showModal() {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }

  function hideModal() {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }

  function escapeHtml(str) {
    if (!str) return "";
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }
});
