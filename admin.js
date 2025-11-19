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

      // C. Animasi Toggler (Dipindahkan ke CSS)
      // Hapus kode rotasi JS, karena lebih baik menggunakan CSS
      // untuk animasi yang bergantung pada state (collapsed/tidak)
    });
  } else {
    console.error("Elemen toggler atau sidebar tidak ditemukan!");
  }
});
