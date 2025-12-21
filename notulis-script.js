// ================= DASHBOARD NOTULIS =================
document.addEventListener("DOMContentLoaded", function () {
  /* ================= SIDEBAR ================= */
  const sidebar = document.querySelector(".sidebar");
  const toggler = document.querySelector(".toggler");

  if (sidebar && toggler) {
    // Load state dari localStorage
    if (localStorage.getItem("sidebarCollapsed") === "true") {
      sidebar.classList.add("collapsed");
      updateTogglerIcon();
    }

    toggler.addEventListener("click", function () {
      sidebar.classList.toggle("collapsed");
      localStorage.setItem("sidebarCollapsed", sidebar.classList.contains("collapsed"));
      updateTogglerIcon();
    });
  }

  function updateTogglerIcon() {
    const icon = toggler.querySelector("span");
    if (!icon) return;

    if (sidebar.classList.contains("collapsed")) {
      icon.classList.remove("fa-chevron-left");
      icon.classList.add("fa-chevron-right");
    } else {
      icon.classList.remove("fa-chevron-right");
      icon.classList.add("fa-chevron-left");
    }
  }

  /* ================= MODAL & NOTULEN ================= */
  const modal = document.getElementById("notulenModal");
  const tambahBtn = document.getElementById("tambahNotulenBtn");
  const closeBtn = document.getElementById("closeModal");
  const cancelBtn = document.getElementById("cancelBtn");
  const notulenForm = document.getElementById("notulenForm");
  const fileInput = document.getElementById("lampiran");

  const modalTitle = document.getElementById("modalTitle");
  const formAction = document.getElementById("formAction");
  const notulenId = document.getElementById("notulenId");
  const submitBtn = document.getElementById("submitBtn");
  const currentFile = document.getElementById("currentFile");
  const currentFileName = document.getElementById("currentFileName");

  /* ===== EVENT LISTENER ===== */
  if (tambahBtn) {
    tambahBtn.addEventListener("click", () => {
      resetModal();
      showModal();
    });
  }

  if (closeBtn) closeBtn.addEventListener("click", hideModal);
  if (cancelBtn) cancelBtn.addEventListener("click", hideModal);

  window.addEventListener("click", (e) => {
    if (e.target === modal) hideModal();
  });

  if (notulenForm) {
    notulenForm.addEventListener("submit", handleFormSubmit);
  }

  if (fileInput) {
    fileInput.addEventListener("change", validateFile);
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && modal?.style.display === "flex") {
      hideModal();
    }
  });

  /* ================= FUNCTION ================= */

  function showModal() {
    modal.style.display = "flex";
    document.body.style.overflow = "hidden";
  }

  function hideModal() {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }

  function resetModal() {
    notulenForm?.reset();
    if (fileInput) fileInput.value = "";
    if (currentFile) currentFile.style.display = "none";

    if (formAction) {
      formAction.name = "simpan_notulen";
      formAction.value = "1";
    }

    if (notulenId) notulenId.value = "";
    if (modalTitle) modalTitle.textContent = "Tambah Notulen";
    if (submitBtn) submitBtn.textContent = "Simpan Notulen";

    setDefaultDate();
  }

  function setDefaultDate() {
    const dateInput = document.getElementById("tanggalRapat");
    if (dateInput && !notulenId?.value) {
      dateInput.value = new Date().toISOString().split("T")[0];
    }
  }

  function handleFormSubmit(e) {
    e.preventDefault();

    const judul = document.getElementById("judulRapat")?.value.trim();
    const tanggal = document.getElementById("tanggalRapat")?.value;
    const isi = document.getElementById("isiNotulen")?.value.trim();

    if (!judul || !tanggal || !isi) {
      showNotification("Lengkapi semua field wajib!", "error");
      return;
    }

    notulenForm.submit();
  }

  function validateFile() {
    const file = fileInput.files[0];
    if (!file) return;

    const allowed = ["pdf", "doc", "docx", "jpg", "jpeg", "png"];
    const ext = file.name.split(".").pop().toLowerCase();

    if (!allowed.includes(ext) || file.size > 5 * 1024 * 1024) {
      showNotification("File tidak valid (max 5MB)", "error");
      fileInput.value = "";
    }
  }

  function showNotification(msg, type = "info") {
    const notif = document.createElement("div");
    notif.className = `notification ${type}`;
    notif.textContent = msg;
    document.body.appendChild(notif);

    setTimeout(() => notif.remove(), 4000);
  }
});
