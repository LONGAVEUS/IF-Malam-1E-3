document.addEventListener("DOMContentLoaded", function () {
  const toggler = document.querySelector(".toggler");
  const sidebar = document.querySelector(".sidebar");

  if (!toggler || !sidebar) {
    console.error("Elemen toggler atau sidebar tidak ditemukan!");
    return;
  }

  toggler.addEventListener("click", function () {
    // Toggle sidebar
    sidebar.classList.toggle("collapsed");

    // Ambil icon chevron
    const icon = toggler.querySelector("span");

    if (!icon) return;

    // Ubah arah icon
    if (sidebar.classList.contains("collapsed")) {
      icon.classList.remove("fa-chevron-left");
      icon.classList.add("fa-chevron-right");
    } else {
      icon.classList.remove("fa-chevron-right");
      icon.classList.add("fa-chevron-left");
    }
  });
});
