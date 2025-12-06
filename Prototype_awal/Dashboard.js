document.addEventListener("DOMContentLoaded", function () {
  // 1. Dapatkan elemen menggunakan querySelector berdasarkan class
  const toggler = document.querySelector(".toggler");
  const sidebar = document.querySelector(".sidebar");

  // Pastikan elemen ditemukan sebelum menambahkan listener
  if (toggler && sidebar) {
    toggler.addEventListener("click", () => {
      // A. Toggling Kelas Sidebar
      sidebar.classList.toggle("collapsed");

      // B. Toggling Icon (Chevron)
      const icon = toggler.querySelector("span");

      // Logika untuk mengubah icon:
      // Jika sidebar DITUTUP (memiliki class 'collapsed')
      if (sidebar.classList.contains("collapsed")) {
        // Tampilkan icon panah kanan (untuk membuka)
        icon.classList.remove("fa-chevron-left");
        icon.classList.add("fa-chevron-right");
      } else {
        // Tampilkan icon panah kiri (untuk menutup)
        icon.classList.remove("fa-chevron-right");
        icon.classList.add("fa-chevron-left");
      }
    });
  } else {
    console.error("Elemen toggler atau sidebar tidak ditemukan!");
  }

  // --- MOBILE TOGGLER ---
  const mobileToggler = document.querySelector('.mobile-toggler');
  const mobileOverlay = document.querySelector('.mobile-overlay');

  if (mobileToggler) {
    mobileToggler.addEventListener('click', () => {
      sidebar.classList.toggle('active');
      mobileOverlay.classList.toggle('active');
    });
  }

  // Tutup sidebar mobile ketika klik overlay
  if (mobileOverlay) {
    mobileOverlay.addEventListener('click', () => {
      sidebar.classList.remove('active');
      mobileOverlay.classList.remove('active');
    });
  }

  // Tutup sidebar mobile ketika klik link navigasi di sidebar
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        sidebar.classList.remove('active');
        mobileOverlay.classList.remove('active');
      }
    });
  });

  // Handle resize window
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      // Di desktop, pastikan sidebar terlihat dan overlay hilang
      sidebar.classList.remove('active');
      mobileOverlay.classList.remove('active');
    }
  });

  // --- Elemen DOM Utama ---
  const totalNotulenElement = document.getElementById("totalNotulen");
  const notulenList = document.getElementById("notulenList"); // List Notulen Privat
  const notulenPublicList = document.getElementById("notulenPublicList"); // List Notulen Publik
  const searchInput = document.getElementById("searchInput");
  const searchBtn = document.getElementById("searchBtn");
  const filterButtons = document.querySelectorAll(".filter-btn");

  // --- Modal Privat ---
  const addNotulenHeaderBtn = document.getElementById("addNotulenHeaderBtn");
  const notulenModal = document.getElementById("notulenModal");
  const closePrivateModalBtn = notulenModal.querySelector(".close-modal");
  const cancelPrivateBtn = document.getElementById("cancelBtn");
  const notulenForm = document.getElementById("notulenForm");

  // --- Modal Publik ---
  const addNotulenPublikBtn = document.getElementById("addNotulenPublikBtn");
  const notulenPublikModal = document.getElementById("notulenPublikModal");
  const closePublikModalBtn = document.getElementById("closePublikModal");
  const cancelPublikBtn = document.getElementById("cancelPublikBtn");
  const notulenPublikForm = document.getElementById("notulenPublikForm");

  // --- Modal Hapus ---
  const deleteModal = document.getElementById("deleteModal");
  const deleteMessage = document.getElementById("deleteMessage");
  const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
  const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
  let notulenToDelete = null;

  // --- Popup Login Privat ---
  const loginPopup = document.getElementById("loginPopup");
  const popupId = document.getElementById("popupId");
  const popupPw = document.getElementById("popupPw");
  const cancelLogin = document.getElementById("cancelLogin");
  const confirmLogin = document.getElementById("confirmLogin");
  const loginMsg = document.getElementById("loginMsg");

  // ID & Password privat 
  const VALID_ID = "admin01";
  const VALID_PW = "akses123";

  // variabel untuk menyimpan aksi tombol (lihat / edit)
  let pendingAction = null;
  let pendingId = null;

  // --- Data Notulen (Gabungan Privat & Publik) ---
  let notulens = [
    {
      id: 1,
      judul: "Rapat Perencanaan Proyek Akhir",
      tanggal: "2025-10-28",
      waktuMulai: "09:00",
      waktuSelesai: "11:30",
      isi: "Presentasi progress dan diskusi kebutuhan tambahan dari klien.",
      tipe: "private",
    },
    {
      id: 2,
      judul: "Diskusi Desain UI/UX V.2",
      tanggal: "2025-10-29",
      waktuMulai: "14:00",
      waktuSelesai: "15:00",
      isi: "Perencanaan kampanye produk baru dan penugasan pembuatan materi promosi.",
      tipe: "private",
    },
    {
      id: 3,
      judul: "Rapat Umum Pemegang Saham",
      tanggal: "2025-10-30",
      waktuMulai: "10:00",
      waktuSelesai: "12:00",
      isi: "Pengesahan laporan keuangan dan rencana strategis tahun depan.",
      tipe: "public",
    },
    {
      id: 4,
      judul: "Konferensi Pers Peluncuran Produk",
      tanggal: "2025-11-01",
      waktuMulai: "13:00",
      waktuSelesai: "14:00",
      isi: "Mempublikasikan fitur dan harga produk baru kepada media.",
      tipe: "public",
    },
  ];

  // --- FUNGSI UTILITY ---

  /**
   * Menampilkan notifikasi pop-up.
   */
  function showNotification(message, type) {
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      background: ${type === "success" ? "#d4edda" : "#f8d7da"};
      color: ${type === "success" ? "#155724" : "#721c24"};
      border: 1px solid ${type === "success" ? "#c3e6cb" : "#f5c6cb"};
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      z-index: 3000;
      transition: all 0.3s ease;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.opacity = "0";
      setTimeout(() => {
        document.body.removeChild(notification);
      }, 300);
    }, 3000);
  }

  /**
   * Memformat tanggal YYYY-MM-DD menjadi DD Bulan YYYY (Indo).
   */
  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const options = {
      year: "numeric",
      month: "short",
      day: "numeric"
    };
    date.setDate(date.getDate() + 1);
    return date.toLocaleDateString("id-ID", options);
  };

  /**
   * Update total notulen count
   */
  function updateTotalNotulen() {
    document.getElementById('totalNotulen').textContent = notulens.length;
  }

  /**
   * Membuat elemen card notulen.
   */
  const createNotulenCard = (notulen) => {
    const formattedDate = formatDate(notulen.tanggal);
    const privacyBadgeClass = notulen.tipe === "public" ? "public" : "private";
    const privacyLabel = notulen.tipe === "public" ? "Publik" : "Privat";

    return `
      <div class="notulen-card ${privacyBadgeClass}" data-id="${notulen.id}">
        <div class="notulen-privacy ${privacyBadgeClass}">${privacyLabel}</div>
        <h3>${notulen.judul}</h3>
        <div class="notulen-meta">
          <span>📅 ${formattedDate}</span>
          <span>⌚ ${notulen.waktuMulai} - ${notulen.waktuSelesai}</span>
        </div>
        <p>${notulen.isi.substring(0, 100)}...</p>
        <div class="notulen-actions">
          <button class="btn btn-info view-btn" data-id="${notulen.id}">Lihat</button>
          <button class="btn btn-warning edit-btn" data-id="${notulen.id}">Edit</button>
          <button class="btn btn-danger delete-btn" data-id="${notulen.id}">Hapus</button>
        </div>
      </div>
    `;
  };

  /**
   * Menampilkan modal.
   */
  const showModal = (modalElement) => {
    modalElement.style.display = "flex";
  };

  /**
   * Menyembunyikan modal dan mereset form.
   */
  const hideModal = (modalElement, formElement) => {
    modalElement.style.display = "none";
    if (formElement) formElement.reset();
  };

  // --- FUNGSI POPUP LOGIN ---

  /**
   * Buka popup login untuk aksi tertentu
   */
  function openLoginPopup(action, id) {
    loginPopup.style.display = 'flex';
    popupId.value = '';
    popupPw.value = '';
    loginMsg.style.display = 'none';
    pendingAction = action;
    pendingId = id;
  }

  /**
   * Tutup popup login
   */
  function closeLoginPopup() {
    loginPopup.style.display = 'none';
    pendingAction = null;
    pendingId = null;
  }

  // --- FUNGSI UTAMA RENDERING & Aksi ---

  /**
   * Mengupdate daftar notulen di UI.
   */
  const renderNotulens = (currentList = notulens) => {
    const privateNotulens = currentList.filter((n) => n.tipe === "private");
    const publicNotulens = currentList.filter((n) => n.tipe === "public");

    // Render Privat
    if (privateNotulens.length > 0) {
      notulenList.innerHTML = privateNotulens.map(createNotulenCard).join("");
    } else {
      notulenList.innerHTML =
        `<p style="color:#777; text-align:center;">Tidak ada notulen privat yang ditemukan.</p>`;
    }

    // Render Publik
    if (publicNotulens.length > 0) {
      notulenPublicList.innerHTML = publicNotulens.map(createNotulenCard).join("");
    } else {
      notulenPublicList.innerHTML =
        `<p style="color:#777; text-align:center;">Tidak ada notulen publik yang ditemukan.</p>`;
    }

    // Update total notulen count
    updateTotalNotulen();

    // Pasang listener untuk tombol-tombol aksi
    attachCardActionListeners();
  };

  /**
   * Menangani penyerahan formulir notulen (Privat/Publik).
   */
  const handleNotulenSubmit = (e, type, form, modal) => {
    e.preventDefault();

    // Tentukan ID yang sesuai berdasarkan tipe
    const suffix = type === "public" ? "Publik" : "";
    const judul = form.querySelector(`#judulRapat${suffix}`).value;
    const tanggal = form.querySelector(`#tanggalRapat${suffix}`).value;
    const waktuMulai = form.querySelector(`#waktuMulai${suffix}`).value;
    const waktuSelesai = form.querySelector(`#waktuSelesai${suffix}`).value;
    const isi = form.querySelector(`#isiNotulen${suffix}`).value;

    const newNotulen = {
      id: Date.now(), // ID unik sederhana
      judul,
      tanggal, // Format YYYY-MM-DD
      waktuMulai,
      waktuSelesai,
      isi,
      tipe: type,
    };

    notulens.unshift(newNotulen);
    hideModal(modal, form);
    renderNotulens();
    showNotification(
      `Notulen ${type === "public" ? "Publik" : "Privat"} "${judul}" berhasil ditambahkan!`,
      "success");
  };

  /**
   * Menangani tombol aksi (Lihat, Edit, Hapus) pada card notulen.
   */
  const handleCardAction = (e) => {
    const button = e.target;
    const id = parseInt(button.dataset.id);
    const action = button.classList[2].split("-")[0]; // view, edit, delete
    const notulen = notulens.find((n) => n.id === id);
    if (!notulen) return;

    // Jika notulen privat dan aksinya adalah lihat atau edit, buka popup login
    if (notulen.tipe === "private" && (action === "view" || action === "edit")) {
      openLoginPopup(action, id);
    } else {
      // Untuk notulen publik atau aksi hapus, langsung jalankan
      executeAction(action, notulen);
    }
  };

  /**
   * Menjalankan aksi setelah verifikasi login (jika diperlukan)
   */
  function executeAction(action, notulen) {
    switch (action) {
      case "view":
        showNotification(`Membuka notulen: ${notulen.judul}`, "info");
        // Di aplikasi nyata, akan navigasi ke halaman detail notulen
        break;
      case "edit":
        showNotification(`Mengedit notulen: ${notulen.judul}`, "warning");
        // Di aplikasi nyata, akan membuka form edit
        break;
      case "delete":
        notulenToDelete = notulen.id;
        deleteMessage.textContent =
          `Apakah Anda yakin ingin menghapus notulen "${notulen.judul}" (${notulen.tipe})? Tindakan ini tidak dapat dibatalkan.`;
        showModal(deleteModal);
        break;
    }
  }

  /**
   * Melampirkan event listener ke tombol aksi card (perlu dipanggil setelah render).
   */
  const attachCardActionListeners = () => {
    // Hapus event listener lama
    document.querySelectorAll(".notulen-actions button").forEach((button) => {
      button.removeEventListener("click", handleCardAction);
    });

    // Tambahkan event listener baru
    document.querySelectorAll(".notulen-actions button").forEach((button) => {
      button.addEventListener("click", handleCardAction);
    });
  };

  // --- FUNGSI PENCARIAN & FILTER ---

  /**
   * Menangani Logika Pencarian.
   */
  const handleSearch = () => {
    const query = searchInput.value.toLowerCase().trim();
    if (!query) {
      // Jika query kosong, tampilkan semua
      renderNotulens();
      return;
    }

    const filteredNotulen = notulens.filter(
      (notulen) =>
      notulen.judul.toLowerCase().includes(query) ||
      notulen.tanggal.toLowerCase().includes(query) ||
      notulen.isi.toLowerCase().includes(query)
    );

    renderNotulens(filteredNotulen);
  };

  /**
   * Menangani Logika Filter Waktu ("Minggu Ini").
   */
  const handleFilter = (filterType) => {
    let filteredList = notulens;

    if (filterType === "week") {
      const today = new Date();
      const startOfWeek = new Date(today);
      startOfWeek.setDate(today.getDate() - today.getDay());
      const endOfWeek = new Date(startOfWeek);
      endOfWeek.setDate(startOfWeek.getDate() + 6);

      const startOfDay = (date) => new Date(date.setHours(0, 0, 0, 0));
      const endOfDay = (date) => new Date(date.setHours(23, 59, 59, 999));

      const start = startOfDay(startOfWeek);
      const end = endOfDay(endOfWeek);

      filteredList = notulens.filter((notulen) => {
        const notulenDate = new Date(notulen.tanggal);
        notulenDate.setDate(notulenDate.getDate() + 1);

        return notulenDate >= start && notulenDate <= end;
      });

      renderNotulens(filteredList);
      showNotification("Menampilkan notulen minggu ini.", "info");
    } else {
      // 'all'
      renderNotulens(notulens);
      showNotification("Menampilkan semua notulen.", "info");
    }
  };

  // --- EVENT LISTENERS PADA ELEMEN UTAMA ---

  // 2. Modal Privat
  addNotulenHeaderBtn.addEventListener("click", () => showModal(notulenModal));
  closePrivateModalBtn.addEventListener("click", () => hideModal(notulenModal, notulenForm));
  cancelPrivateBtn.addEventListener("click", () => hideModal(notulenModal, notulenForm));
  notulenForm.addEventListener("submit", (e) => handleNotulenSubmit(e, "private", notulenForm,
    notulenModal));

  // 3. Modal Publik
  addNotulenPublikBtn.addEventListener("click", () => showModal(notulenPublikModal));
  closePublikModalBtn.addEventListener("click", () => hideModal(notulenPublikModal,
    notulenPublikForm));
  cancelPublikBtn.addEventListener("click", () => hideModal(notulenPublikModal, notulenPublikForm));
  notulenPublikForm.addEventListener("submit", (e) => handleNotulenSubmit(e, "public",
    notulenPublikForm, notulenPublikModal));

  // 4. Modal Hapus
  confirmDeleteBtn.addEventListener("click", () => {
    if (notulenToDelete !== null) {
      const deletedNotulen = notulens.find((n) => n.id === notulenToDelete);
      notulens = notulens.filter((n) => n.id !== notulenToDelete);

      showNotification(`Notulen "${deletedNotulen.judul}" berhasil dihapus!`, "success");

      notulenToDelete = null;
      hideModal(deleteModal);
      renderNotulens();
    }
  });

  cancelDeleteBtn.addEventListener("click", () => hideModal(deleteModal));

  // 5. Pencarian
  searchBtn.addEventListener("click", handleSearch);
  searchInput.addEventListener("keyup", (e) => {
    if (e.key === "Enter") handleSearch();
    if (searchInput.value.trim() === "") renderNotulens();
  });

  // 6. Filter Waktu
  filterButtons.forEach((button) => {
    button.addEventListener("click", function () {
      filterButtons.forEach((btn) => btn.classList.remove("active"));
      this.classList.add("active");

      const filter = this.dataset.filter;
      handleFilter(filter);
    });
  });

  // 7. Popup Login
  cancelLogin.addEventListener('click', closeLoginPopup);

  confirmLogin.addEventListener('click', () => {
    const idVal = popupId.value.trim();
    const pwVal = popupPw.value.trim();

    if (idVal === VALID_ID && pwVal === VALID_PW) {
      loginMsg.style.display = 'none';
      closeLoginPopup();

      // Jalankan aksi yang tertunda
      const notulen = notulens.find((n) => n.id === pendingId);
      if (notulen) {
        executeAction(pendingAction, notulen);
      }
    } else {
      loginMsg.style.display = 'block';
    }
  });

  // Tutup modal saat klik di luar
  document.addEventListener("click", (e) => {
    if (e.target === notulenModal) hideModal(notulenModal, notulenForm);
    if (e.target === notulenPublikModal) hideModal(notulenPublikModal, notulenPublikForm);
    if (e.target === deleteModal) hideModal(deleteModal);
    if (e.target === loginPopup) closeLoginPopup();
  });

  // --- INISIALISASI ---
  renderNotulens();
});