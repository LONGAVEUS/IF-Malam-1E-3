// Dashboard Notulis - JavaScript Eksternal
document.addEventListener("DOMContentLoaded", function () {
  console.log("Dashboard Notulis loaded successfully!");

  // Elements
  const sidebar = document.getElementById("sidebar");
  const sidebarToggler = document.getElementById("sidebarToggler");
  const modal = document.getElementById("notulenModal");
  const tambahBtn = document.getElementById("tambahNotulenBtn");
  const closeBtn = document.getElementById("closeModal");
  const cancelBtn = document.getElementById("cancelBtn");
  const notulenForm = document.getElementById("notulenForm");

  // Modal elements
  const modalTitle = document.getElementById("modalTitle");
  const formAction = document.getElementById("formAction");
  const notulenId = document.getElementById("notulenId");
  const submitBtn = document.getElementById("submitBtn");

  // Initialize
  initDashboard();

  function initDashboard() {
    const sidebarState = localStorage.getItem("sidebarCollapsed");
    if (sidebarState === "true") {
      sidebar.classList.add("collapsed");
      updateTogglerIcon();
    }

    setupEventListeners();
    setDefaultDate();
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
    if (tambahBtn) {
      tambahBtn.addEventListener("click", function () {
        resetModal();
        showModal();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", hideModal);
    }

    if (cancelBtn) {
      cancelBtn.addEventListener("click", hideModal);
    }

    window.addEventListener("click", function (event) {
      if (event.target === modal) {
        hideModal();
      }
    });

    if (notulenForm) {
      notulenForm.addEventListener("submit", handleFormSubmit);
    }

    // Event delegation untuk action buttons
    document.addEventListener("click", function (event) {
      const target = event.target;

      if (target.closest(".action-btn.edit")) {
        handleEditNotulen(target.closest(".action-btn.edit"));
      }
    });

    // Keyboard shortcuts
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && modal.style.display === "flex") {
        hideModal();
      }

      if (event.ctrlKey && event.key === "n") {
        event.preventDefault();
        resetModal();
        showModal();
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

  function showModal() {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";

    setTimeout(() => {
      const firstInput = document.getElementById("tanggalRapat");
      if (firstInput) firstInput.focus();
    }, 100);
  }

  function hideModal() {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }

  function resetModal() {
    if (notulenForm) {
      notulenForm.reset();
      formAction.name = "simpan_notulen";
      formAction.value = "1";
      notulenId.value = "";
      modalTitle.textContent = "Tambah Notulen Baru";
      submitBtn.textContent = "Simpan Notulen";
    }
    setDefaultDate();
  }

  function setDefaultDate() {
    const today = new Date().toISOString().split("T")[0];
    const dateInput = document.getElementById("tanggalRapat");
    if (dateInput && !notulenId.value) {
      dateInput.value = today;
    }
  }

  function handleFormSubmit(e) {
    e.preventDefault();

    const tanggal = document.getElementById("tanggalRapat").value;
    const isi = document.getElementById("isiNotulen").value.trim();

    if (!tanggal || !isi) {
      showNotification("Harap lengkapi tanggal dan isi notulen!", "error");
      return;
    }

    notulenForm.submit();
  }

  function handleEditNotulen(button) {
    const notulenIdValue = button.getAttribute("data-id");

    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch(`edit_notulen.php?id=${notulenIdValue}`)
      .then((response) => response.json())
      .then((notulen) => {
        if (notulen.error) {
          showNotification(notulen.error, "error");
          return;
        }

        // PERBAIKAN: Sesuaikan dengan field database
        document.getElementById("jadwalId").value = notulen.jadwal_id || '';
        document.getElementById("tanggalRapat").value = notulen.tanggal;
        document.getElementById("peserta").value = notulen.peserta || '';
        document.getElementById("isiNotulen").value = notulen.isi;

        formAction.name = "update_notulen";
        formAction.value = "1";
        notulenId.value = notulen.id;
        modalTitle.textContent = "Edit Notulen";
        submitBtn.textContent = "Update Notulen";

        showModal();
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification("Terjadi kesalahan saat memuat data notulen.", "error");
      })
      .finally(() => {
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-edit"></i>';
      });
  }

  function showNotification(message, type = "info") {
    const existingNotifications = document.querySelectorAll(".notification");
    existingNotifications.forEach((notif) => notif.remove());

    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
      if (notification.parentNode) {
        notification.style.opacity = "0";
        notification.style.transform = "translateX(100%)";

        setTimeout(() => {
          if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
          }
        }, 300);
      }
    }, 5000);
  }
});

window.dashboard = {
  showNotification,
};