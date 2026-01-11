document.addEventListener("DOMContentLoaded", function () {
    try {
        // ================= SIDEBAR FUNCTIONALITY =================
        const sidebar = document.querySelector(".sidebar");
        const toggler = document.querySelector(".toggler");
        const sidebarNav = document.querySelector(".sidebar-nav");

        // Validasi elemen sidebar
        if (!sidebar || !toggler || !sidebarNav) {
            console.warn("Sidebar elements not found");
            return;
        }

        /**
         * Menangani klik pada tombol toggler untuk menu mobile
         */
        toggler.addEventListener("click", function (event) {
            event.stopPropagation();

            // Hanya aktif di mobile (lebar <= 768px)
            if (window.innerWidth <= 768) {
                sidebarNav.classList.toggle("active");
            }
        });

        /**
         * Menutup dropdown sidebar saat klik di luar area sidebar (mobile only)
         */
        document.addEventListener("click", function (event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target)) {
                    sidebarNav.classList.remove("active");
                }
            }
        });

        /**
         * Reset sidebar saat resize ke desktop mode
         */
        window.addEventListener("resize", function () {
            if (window.innerWidth > 768) {
                sidebarNav.classList.remove("active");
            }
        });

        // ================= LIVE CLOCK FUNCTIONALITY =================
        
        /**
         * Memperbarui tampilan jam dan tanggal secara real-time
         */
        function updateClock() {
            try {
                const now = new Date();
                
                // Format waktu: HH:MM:SS
                const timeString = now.toLocaleTimeString("id-ID", {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit"
                });
                
                // Format tanggal: Hari, DD Month YYYY
                const dateString = now.toLocaleDateString("id-ID", {
                    weekday: "long",
                    year: "numeric",
                    month: "long",
                    day: "numeric"
                });

                const timeElement = document.getElementById("liveTime");
                const dateElement = document.getElementById("currentDate");

                // Update elemen waktu jika ada
                if (timeElement) {
                    timeElement.textContent = timeString;
                    timeElement.classList.add("updated");
                    setTimeout(() => timeElement.classList.remove("updated"), 500);
                }

                // Update elemen tanggal jika ada
                if (dateElement) {
                    dateElement.textContent = dateString;
                }
            } catch (error) {
                console.error("Error updating clock:", error);
            }
        }

        // Jalankan clock saat pertama kali load
        updateClock();
        const clockInterval = setInterval(updateClock, 1000);

        /**
         * Membersihkan interval clock saat page di-unload
         */
        window.addEventListener("beforeunload", function () {
            clearInterval(clockInterval);
        });

    } catch (error) {
        console.error("Error initializing page:", error);
    }
});
