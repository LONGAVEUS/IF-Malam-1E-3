// tamu_script.js - VERSI TERBARU MENGIKUTI STRUKTUR NOTULIS

document.addEventListener("DOMContentLoaded", function () {
  /* ================= SIDEBAR MOBILE ================= */
  const sidebar = document.querySelector(".sidebar");
  const toggler = document.querySelector(".toggler");
  const sidebarNav = document.querySelector(".sidebar-nav");

  if (!sidebar || !toggler || !sidebarNav) return;

  toggler.addEventListener("click", function (e) {
    e.stopPropagation();

    /* ================= MOBILE ONLY ================= */
    if (window.innerWidth <= 768) {
      sidebarNav.classList.toggle("active");
    }
  });

  /* ================= CLOSE DROPDOWN SAAT KLIK DI LUAR (MOBILE) ================= */
  document.addEventListener("click", function (e) {
    if (window.innerWidth <= 768) {
      if (!sidebar.contains(e.target)) {
        sidebarNav.classList.remove("active");
      }
    }
  });

  /* ================= RESET SAAT RESIZE KE DESKTOP ================= */
  window.addEventListener("resize", function () {
    if (window.innerWidth > 768) {
      sidebarNav.classList.remove("active");
    }
  });

  /* ================= MODAL DETAIL ================= */
  // ... (sisa kode modal detail tetap sama)
  const detailModal = document.getElementById("detailModal");
  const closeDetailBtn = document.getElementById("closeDetailModal");
  const detailModalContent = document.getElementById("detailModalContent");

  /* ================= SEARCH & FILTER ================= */
  const searchInput = document.getElementById("searchInput");
  const startDate = document.getElementById("startDate");
  const endDate = document.getElementById("endDate");
  const clearBtn = document.getElementById("clearBtn");

  /* ===== EVENT LISTENER MODAL DETAIL ===== */
  if (closeDetailBtn) closeDetailBtn.addEventListener("click", () => hideModal(detailModal));

  // Tutup modal saat klik di luar
  window.addEventListener("click", (e) => {
    if (e.target === detailModal) hideModal(detailModal);
  });

  // Tutup modal dengan ESC key
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (detailModal?.style.display === "flex") hideModal(detailModal);
    }
  });

  /* ===== SEARCH & FILTER EVENT LISTENERS ===== */
  if (searchInput && startDate && endDate && clearBtn) {
    setupSearchFilter();
  }

  /* ===== EVENT DELEGATION UNTUK TOMBOL ===== */
  document.addEventListener('click', function(e) {
    // Tombol lihat detail
    if (e.target.closest('.action-btn.view')) {
      const button = e.target.closest('.action-btn.view');
      const notulenId = button.getAttribute('data-notulen-id');
      if (notulenId && notulenId !== '0') {
        showNotulenDetail(notulenId);
      }
    }
    
    // Tombol konfirmasi kehadiran dari list
    if (e.target.closest('.action-btn.konfirmasi')) {
      const button = e.target.closest('.action-btn.konfirmasi');
      const notulenId = button.getAttribute('data-notulen-id');
      if (notulenId && notulenId !== '0') {
        konfirmasiKehadiranModal(notulenId);
      }
    }
  });

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

  /* ================= FUNGSI SEARCH & FILTER ================= */
  function setupSearchFilter() {
    // Search dengan debounce
    let searchTimeout;
    searchInput.addEventListener("input", function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        applyFilters();
      }, 500);
    });
    
    // Filter tanggal
    startDate.addEventListener("change", applyFilters);
    endDate.addEventListener("change", applyFilters);
    
    // Clear filter
    clearBtn.addEventListener("click", function() {
      searchInput.value = '';
      startDate.value = '';
      endDate.value = '';
      
      // Redirect ke halaman 1 tanpa filter
      window.location.href = 'tamu.php?page=1';
    });
  }

  function applyFilters() {
    // Build query parameters
    const params = new URLSearchParams();
    
    if (searchInput.value) {
      params.append('search', searchInput.value);
    }
    
    if (startDate.value) {
      params.append('start_date', startDate.value);
    }
    
    if (endDate.value) {
      params.append('end_date', endDate.value);
    }
    
    // Always go to page 1 when filtering
    params.append('page', 1);
    
    // Redirect to filtered page
    window.location.href = 'tamu.php?' + params.toString();
  }

  /* ================= FUNGSI DETAIL NOTULEN ================= */
  function showNotulenDetail(id) {
    if (!detailModal || !detailModalContent) return;
    
    detailModalContent.innerHTML = '<div class="loading-modal"><i class="fas fa-spinner fa-spin"></i> Memuat detail notulen...</div>';
    showModal(detailModal);
    
    fetch(`get_notulen_detail.php?id=${id}`)
      .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
      })
      .then(data => {
        if (data.error) {
          detailModalContent.innerHTML = `<div class="error-message">${escapeHtml(data.error)}</div>`;
          return;
        }
        
        const notulen = data.notulen;
        const peserta = data.peserta || [];
        const lampiran = data.lampiran_files || [];
        const totalPeserta = data.total_peserta || 0;
        const totalHadir = data.total_hadir || 0;
        const userStatusKehadiran = data.user_status_kehadiran;
        const userWaktuKonfirmasi = data.user_waktu_konfirmasi;
        const userRole = data.user_role;
        const isCreator = data.is_creator;
        
        // Format tanggal
        const tanggal = new Date(notulen.tanggal).toLocaleDateString('id-ID', {
          weekday: 'long',
          year: 'numeric',
          month: 'long',
          day: 'numeric'
        });
        
        // Hitung persentase kehadiran
        const persentaseHadir = totalPeserta > 0 ? Math.round((totalHadir / totalPeserta) * 100) : 0;
        
        // Tentukan apakah user bisa melihat detail lengkap
        const canViewDetails = userStatusKehadiran === 'hadir';
        
        // Tentukan status kehadiran user
        let userKehadiranSection = '';
        if (userStatusKehadiran) {
          const statusText = userStatusKehadiran === 'hadir' ? 'Hadir' : 'Tidak Hadir';
          const statusClass = userStatusKehadiran === 'hadir' ? 'hadir' : 'tidak-hadir';
          const waktu = userWaktuKonfirmasi ? 
            ` pada ${new Date(userWaktuKonfirmasi).toLocaleString('id-ID')}` : '';
          
          userKehadiranSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-user-check"></i> Status Kehadiran Anda</h4>
              </div>
              <div class="kehadiran-info">
                <div class="kehadiran-status-display ${statusClass}">
                  <i class="fas fa-user-check"></i>
                  <span>Anda sudah konfirmasi: <strong>${statusText}</strong>${waktu}</span>
                </div>
                ${!canViewDetails ? `
                  <div class="access-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Karena Anda tidak hadir, akses ke detail notulen dibatasi.</p>
                  </div>
                ` : ''}
              </div>
            </div>
          `;
        } else if (notulen.status === 'sent') {
          userKehadiranSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-user-check"></i> Konfirmasi Kehadiran</h4>
              </div>
              <div class="kehadiran-info">
                <p>Silakan konfirmasi kehadiran Anda untuk mengakses detail lengkap:</p>
                <div class="modal-actions">
                  <button class="confirm-btn hadir" onclick="konfirmasiKehadiranModal(${notulen.id})">
                    <i class="fas fa-check"></i> Konfirmasi Hadir
                  </button>
                </div>
              </div>
            </div>
          `;
        }
        
        // Section peserta (hanya untuk yang hadir)
        let pesertaSection = '';
        if (canViewDetails) {
          pesertaSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-users"></i> Daftar Peserta</h4>
                <span class="section-count">${totalPeserta} peserta</span>
              </div>
              
              ${peserta.length > 0 ? `
                <div class="participants-container">
                  <div class="participants-header">
                    <div class="col-name">Nama</div>
                    <div class="col-nim">NIM</div>
                    <div class="col-role">Role</div>
                    <div class="col-status">Status Kehadiran</div>
                  </div>
                  
                  <div class="participants-list">
                    ${peserta.map((p, index) => {
                      let statusClass = 'belum';
                      let statusText = 'Belum Konfirmasi';
                      let waktuText = '';
                      
                      if (p.status_kehadiran === 'hadir') {
                        statusClass = 'hadir';
                        statusText = 'Hadir';
                        if (p.waktu_konfirmasi) {
                          waktuText = `<div class="waktu-konfirmasi">${new Date(p.waktu_konfirmasi).toLocaleString('id-ID')}</div>`;
                        }
                      } else if (p.status_kehadiran === 'tidak_hadir') {
                        statusClass = 'tidak-hadir';
                        statusText = 'Tidak Hadir';
                      }
                      
                      return `
                        <div class="participant-item ${index % 2 === 0 ? 'even' : 'odd'}">
                          <div class="col-name">
                            <div class="participant-avatar">
                              ${p.full_name.charAt(0).toUpperCase()}
                            </div>
                            <div class="participant-info">
                              <div class="participant-name">${escapeHtml(p.full_name)}</div>
                            </div>
                          </div>
                          <div class="col-nim">${escapeHtml(p.nim || '-')}</div>
                          <div class="col-role">${escapeHtml(p.role)}</div>
                          <div class="col-status">
                            <span class="kehadiran-status ${statusClass}">
                              <i class="fas fa-user-check"></i> ${statusText}
                            </span>
                            ${waktuText}
                          </div>
                        </div>
                      `;
                    }).join('')}
                  </div>
                </div>
              ` : `
                <div class="empty-section">
                  <i class="fas fa-users-slash"></i>
                  <p>Tidak ada data peserta</p>
                </div>
              `}
            </div>
          `;
        } else {
          pesertaSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-users"></i> Daftar Peserta</h4>
              </div>
              <div class="access-restricted">
                <i class="fas fa-lock"></i>
                <p>Akses ke daftar peserta hanya tersedia untuk peserta yang hadir.</p>
              </div>
            </div>
          `;
        }
        
        // Section pembahasan (hanya untuk yang hadir)
        let pembahasanSection = '';
        if (canViewDetails) {
          pembahasanSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-comments"></i> Pembahasan Rapat</h4>
              </div>
              <div class="content-text">
                ${notulen.pembahasan ? escapeHtml(notulen.pembahasan).replace(/\n/g, '<br>') : 
                  '<div class="empty-content">Tidak ada pembahasan</div>'}
              </div>
            </div>
          `;
        } else {
          pembahasanSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-comments"></i> Pembahasan Rapat</h4>
              </div>
              <div class="access-restricted">
                <i class="fas fa-lock"></i>
                <p>Konten pembahasan hanya dapat diakses oleh peserta yang hadir.</p>
              </div>
            </div>
          `;
        }
        
        // Section hasil akhir (hanya untuk yang hadir)
        let hasilAkhirSection = '';
        if (canViewDetails) {
          hasilAkhirSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-check-circle"></i> Hasil Akhir Rapat</h4>
              </div>
              <div class="content-text">
                ${notulen.hasil_akhir ? escapeHtml(notulen.hasil_akhir).replace(/\n/g, '<br>') : 
                  '<div class="empty-content">Tidak ada hasil akhir</div>'}
              </div>
            </div>
          `;
        } else {
          hasilAkhirSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-check-circle"></i> Hasil Akhir Rapat</h4>
              </div>
              <div class="access-restricted">
                <i class="fas fa-lock"></i>
                <p>Hasil akhir rapat hanya dapat diakses oleh peserta yang hadir.</p>
              </div>
            </div>
          `;
        }
        
        // Section lampiran (hanya untuk yang hadir)
        let lampiranSection = '';
        if (canViewDetails) {
          let lampiranHtml = '';
          if (lampiran && lampiran.length > 0) {
            lampiranHtml = `
              <div class="attachments-grid">
                ${lampiran.map((file, index) => {
                  try {
                    // Handle berbagai format data lampiran
                    let fileName = '';
                    let filePath = '';
                    
                    if (typeof file === 'string') {
                      fileName = file;
                      if (fileName.includes('_')) {
                        fileName = fileName.split('_').slice(1).join('_');
                      }
                      filePath = `uploads/${file}`;
                    } else if (typeof file === 'object' && file !== null) {
                      fileName = file.original_name || file.file_name || 'file';
                      filePath = file.file_path || `uploads/${file.file_name || file}`;
                    }
                    
                    const fileExt = fileName.split('.').pop().toLowerCase();
                    const fileIcon = getFileIcon(fileExt);
                    
                    return `
                      <div class="attachment-card">
                        <div class="attachment-icon">
                          <i class="fas ${fileIcon}"></i>
                        </div>
                        <div class="attachment-info">
                          <div class="attachment-name" title="${escapeHtml(fileName)}">
                            ${escapeHtml(fileName.length > 40 ? fileName.substring(0, 37) + '...' : fileName)}
                          </div>
                        </div>
                        <div class="attachment-actions">
                          <a href="${filePath}" target="_blank" class="btn-view" title="Lihat file">
                            <i class="fas fa-eye"></i>
                          </a>
                          <a href="${filePath}" download="${fileName}" class="btn-download" title="Download file">
                            <i class="fas fa-download"></i>
                          </a>
                        </div>
                      </div>
                    `;
                  } catch (error) {
                    return `<div class="error-message">Error loading file</div>`;
                  }
                }).join('')}
              </div>
            `;
          } else {
            lampiranHtml = `
              <div class="empty-section">
                <i class="fas fa-file"></i>
                <p>Tidak ada lampiran</p>
              </div>
            `;
          }
          
          lampiranSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-paperclip"></i> Lampiran</h4>
                <span class="section-count">${lampiran.length} file</span>
              </div>
              ${lampiranHtml}
            </div>
          `;
        } else {
          lampiranSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-paperclip"></i> Lampiran</h4>
              </div>
              <div class="access-restricted">
                <i class="fas fa-lock"></i>
                <p>Lampiran hanya dapat diakses oleh peserta yang hadir.</p>
              </div>
            </div>
          `;
        }
        
        // Tombol download PDF (hanya untuk yang hadir dan notulen final)
        let downloadButtonSection = '';
        if (notulen.status === 'final' && canViewDetails) {
          downloadButtonSection = `
            <div class="modal-actions center-actions">
              <a href="generate_pdf.php?id=${notulen.id}&download=1" 
                class="confirm-btn hadir download-full" 
                target="_blank">
                <i class="fas fa-download"></i> Download Notulen (PDF)
              </a>
            </div>
          `;
        }
        
        // HTML untuk modal dengan layout yang rapi (sama seperti notulis)
        const html = `
          <div class="detail-container">
            <!-- Header Notulen -->
            <div class="detail-header">
              <h3 class="detail-title">${escapeHtml(notulen.judul)}</h3>
              <div class="detail-status-container">
                <span class="notulen-status status-${notulen.status}">
                  ${notulen.status === 'sent' ? 'Terkirim' : 'Akhir'}
                </span>
                <span class="info-badge">
                  <i class="fas fa-calendar"></i> ${tanggal}
                </span>
                <span class="info-badge" style="background: var(--info-color);">
                  <i class="fas fa-user-tag"></i> Tamu
                </span>
              </div>
            </div>
            
            <!-- Info Grid -->
            <div class="info-grid">
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-clock"></i> Waktu
                </div>
                <div class="info-value">${notulen.jam_mulai} - ${notulen.jam_selesai}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-map-marker-alt"></i> Tempat
                </div>
                <div class="info-value">${escapeHtml(notulen.tempat)}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-user-tie"></i> Penanggung Jawab
                </div>
                <div class="info-value">${escapeHtml(notulen.penanggung_jawab)}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-user-edit"></i> Notulis
                </div>
                <div class="info-value">${escapeHtml(notulen.notulis)}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-graduation-cap"></i> Jurusan
                </div>
                <div class="info-value">${escapeHtml(notulen.jurusan)}</div>
              </div>
              
              ${canViewDetails ? `
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-users"></i> Kehadiran
                </div>
                <div class="info-value">
                  <span class="attendance-progress">
                    ${totalHadir}/${totalPeserta} peserta (${persentaseHadir}%)
                  </span>
                </div>
              </div>
              ` : ''}
            </div>
            
            ${userKehadiranSection}
            ${pesertaSection}
            ${pembahasanSection}
            ${hasilAkhirSection}
            ${lampiranSection}
            ${downloadButtonSection}
          </div>
        `;
        
        detailModalContent.innerHTML = html;
      })
      .catch(error => {
        console.error('Error:', error);
        detailModalContent.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data notulen</div>';
      });
}

  /* ================= FUNGSI KONFIRMASI KEHADIRAN ================= */
  window.konfirmasiKehadiranModal = function(notulenId) {
    if (confirm("Konfirmasi kehadiran: Hadir?")) {
      konfirmasiKehadiran(notulenId, 'hadir');
    }
  };

  function konfirmasiKehadiran(notulenId, status = 'hadir') {
    const formData = new FormData();
    formData.append('notulen_id', notulenId);
    formData.append('status', status);
    
    fetch('konfirmasi_kehadiran.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification(data.message, 'success');
        // Tutup modal dan reload halaman
        hideModal(detailModal);
        setTimeout(() => location.reload(), 1000);
      } else {
        showNotification('Gagal: ' + data.message, 'error');
      }
    })
    .catch(error => {
      showNotification('Terjadi kesalahan jaringan', 'error');
    });
  }

  /* ================= HELPER FUNCTIONS ================= */
  function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function getFileIcon(ext) {
    const icons = {
      pdf: 'fa-file-pdf',
      doc: 'fa-file-word',
      docx: 'fa-file-word',
      xls: 'fa-file-excel',
      xlsx: 'fa-file-excel',
      ppt: 'fa-file-powerpoint',
      pptx: 'fa-file-powerpoint',
      jpg: 'fa-file-image',
      jpeg: 'fa-file-image',
      png: 'fa-file-image',
      gif: 'fa-file-image',
      zip: 'fa-file-archive',
      rar: 'fa-file-archive',
      txt: 'fa-file-alt',
      mp3: 'fa-file-audio',
      mp4: 'fa-file-video',
      mov: 'fa-file-video',
      avi: 'fa-file-video',
    };
    return icons[ext] || 'fa-file';
  }

  function showNotification(msg, type = 'info') {
    // Hapus notifikasi sebelumnya jika ada
    const existingNotif = document.querySelector('.custom-notification');
    if (existingNotif) {
      existingNotif.remove();
    }
    
    // Buat elemen notifikasi
    const notif = document.createElement('div');
    notif.className = `custom-notification notification-${type}`;
    notif.innerHTML = `
      <div style="display: flex; align-items: center; gap: 10px;">
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${msg}</span>
      </div>
    `;
    
    // Style untuk notifikasi
    notif.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
      color: white;
      border-radius: 5px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      z-index: 9999;
      animation: slideIn 0.3s ease;
    `;
    
    // Tambahkan style untuk animasi jika belum ada
    if (!document.querySelector('#notification-styles')) {
      const style = document.createElement('style');
      style.id = 'notification-styles';
      style.textContent = `
        @keyframes slideIn {
          from { transform: translateX(100%); opacity: 0; }
          to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
          from { transform: translateX(0); opacity: 1; }
          to { transform: translateX(100%); opacity: 0; }
        }
      `;
      document.head.appendChild(style);
    }
    
    // Tambahkan ke body
    document.body.appendChild(notif);
    
    // Hapus setelah 3 detik
    setTimeout(() => {
      if (notif.parentNode) {
        notif.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
          if (notif.parentNode) {
            notif.parentNode.removeChild(notif);
          }
        }, 300);
      }
    }, 3000);
  }
});
