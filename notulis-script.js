// notulis-script.js - VERSI TERBARU DENGAN FITUR TANDA TANGAN

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

  /* ===== SEARCH & FILTER EVENT LISTENERS ===== */
  if (searchInput && startDate && endDate && clearBtn) {
    setupSearchFilter();
  }

  /* ===== EVENT DELEGATION UNTUK TOMBOL ===== */
  document.addEventListener("click", function (e) {
    // Tombol lihat detail
    if (e.target.closest(".action-btn.view")) {
      const button = e.target.closest(".action-btn.view");
      const notulenId = button.getAttribute("data-notulen-id");
      if (notulenId && notulenId !== "0") {
        showNotulenDetail(notulenId);
      }
    }
    
    // Tombol konfirmasi kehadiran (jika ada)
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
      searchInput.value = '';
      startDate.value = '';
      endDate.value = '';
      window.location.href = "notulis.php?page=1";
    });
  }

  function applyFilters() {
    const params = new URLSearchParams();
    
    if (searchInput.value) {
      params.append("search", searchInput.value);
    }
    
    if (startDate.value) {
      params.append("start_date", startDate.value);
    }
    
    if (endDate.value) {
      params.append("end_date", endDate.value);
    }
    
    params.append("page", 1);
    window.location.href = "notulis.php?" + params.toString();
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
        const peserta = data.peserta || [];
        const lampiran = data.lampiran_files || [];
        const totalPeserta = data.total_peserta || 0;
        const totalHadir = data.total_hadir || 0;
        const isCreator = data.is_creator;
        
        // Format tanggal
        const tanggal = new Date(n.tanggal).toLocaleDateString("id-ID", {
          weekday: "long",
          year: "numeric",
          month: "long",
          day: "numeric",
        });
        
        // Hitung persentase kehadiran
        const persentaseHadir = totalPeserta > 0 ? Math.round((totalHadir / totalPeserta) * 100) : 0;

        // --- TANDA TANGAN UNTUK NOTULIS ---
        // Notulis bisa menandatangani notulen yang dibuatnya (status sent/final)
        let tandaTanganSection = "";
        if (isCreator && (n.status === 'sent' || n.status === 'final')) {
          tandaTanganSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-pen-nib"></i> Tanda Tangan Notulis</h4>
              </div>
              <div class="signature-wrapper" style="text-align:center; padding:15px;">
                ${n.ttd_notulis ? `
                  <p>Tanda tangan sudah tersimpan:</p>
                  <img src="${n.ttd_notulis}" style="max-width:300px; border:1px solid #ddd; padding:10px; background:#fff;" />
                ` : `
                  <p>Silakan tanda tangan di bawah sebagai notulis:</p>
                  <canvas id="signature-pad" style="border:2px dashed #ccc; background:#fff; border-radius:8px; width:100%; max-width:400px; height:200px; touch-action:none;"></canvas>
                  <div class="modal-actions" style="justify-content:center; margin-top:15px; gap:10px;">
                    <button type="button" class="confirm-btn" id="clear-signature" style="background:#6c757d; color:#fff;">Hapus</button>
                    <button type="button" class="confirm-btn hadir" onclick="simpanTandaTanganNotulis(${n.id})">Simpan Tanda Tangan</button>
                  </div>
                `}
              </div>
            </div>`;

          // Inisialisasi Signature Pad jika belum ada tanda tangan
          setTimeout(() => {
            if (!n.ttd_notulis) {
              const canvas = document.getElementById("signature-pad");
              if (canvas) {
                signaturePad = new SignaturePad(canvas, { backgroundColor: "rgb(255, 255, 255)" });
                document.getElementById("clear-signature").addEventListener("click", () => signaturePad.clear());
              }
            }
          }, 100);
        }

        // Section peserta
        let pesertaSection = '';
        if (peserta.length > 0) {
          pesertaSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-users"></i> Daftar Peserta</h4>
                <span class="section-count">${totalPeserta} peserta</span>
              </div>
              
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
            </div>
          `;
        } else {
          pesertaSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-users"></i> Daftar Peserta</h4>
              </div>
              <div class="empty-section">
                <i class="fas fa-users-slash"></i>
                <p>Tidak ada data peserta</p>
              </div>
            </div>
          `;
        }

        // Section lampiran
        let lampiranSection = '';
        if (lampiran && lampiran.length > 0) {
          const lampiranHtml = lampiran.map((file, index) => {
            try {
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
          }).join('');
          
          lampiranSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-paperclip"></i> Lampiran</h4>
                <span class="section-count">${lampiran.length} file</span>
              </div>
              <div class="attachments-grid">
                ${lampiranHtml}
              </div>
            </div>
          `;
        } else {
          lampiranSection = `
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-paperclip"></i> Lampiran</h4>
              </div>
              <div class="empty-section">
                <i class="fas fa-file"></i>
                <p>Tidak ada lampiran</p>
              </div>
            </div>
          `;
        }

        // Tombol download PDF
        let downloadButtonSection = '';
        if (n.status === 'final') {
          downloadButtonSection = `
            <div class="modal-actions center-actions">
              <a href="generate_pdf.php?id=${n.id}&download=1" 
                class="confirm-btn hadir download-full" 
                target="_blank">
                <i class="fas fa-download"></i> Download Notulen (PDF)
              </a>
            </div>
          `;
        }

        // HTML untuk modal dengan layout lengkap
        detailModalContent.innerHTML = `
          <div class="detail-container">
            <!-- Header Notulen -->
            <div class="detail-header">
              <h3 class="detail-title">${escapeHtml(n.judul)}</h3>
              <div class="detail-status-container">
                <span class="notulen-status status-${n.status}">
                  ${n.status === 'sent' ? 'Terkirim' : n.status === 'final' ? 'Final' : 'Draft'}
                </span>
                <span class="info-badge">
                  <i class="fas fa-calendar"></i> ${tanggal}
                </span>
                ${isCreator ? '<span class="info-badge pembuat"><i class="fas fa-user-edit"></i> Pembuat</span>' : ''}
              </div>
            </div>
            
            <!-- Info Grid -->
            <div class="info-grid">
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-clock"></i> Waktu
                </div>
                <div class="info-value">${n.jam_mulai} - ${n.jam_selesai}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-map-marker-alt"></i> Tempat
                </div>
                <div class="info-value">${escapeHtml(n.tempat)}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-user-tie"></i> Penanggung Jawab
                </div>
                <div class="info-value">${escapeHtml(n.penanggung_jawab)}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-user-edit"></i> Notulis
                </div>
                <div class="info-value">${escapeHtml(n.notulis)}</div>
              </div>
              
              <div class="info-item">
                <div class="info-label">
                  <i class="fas fa-graduation-cap"></i> Jurusan
                </div>
                <div class="info-value">${escapeHtml(n.jurusan)}</div>
              </div>
              
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
            </div>
            
            ${tandaTanganSection}
            
            <!-- Pembahasan -->
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-comments"></i> Pembahasan Rapat</h4>
              </div>
              <div class="content-text">
                ${n.pembahasan ? escapeHtml(n.pembahasan).replace(/\n/g, '<br>') : 
                  '<div class="empty-content">Tidak ada pembahasan</div>'}
              </div>
            </div>
            
            <!-- Hasil Akhir -->
            <div class="section-card">
              <div class="section-header">
                <h4><i class="fas fa-check-circle"></i> Hasil Akhir Rapat</h4>
              </div>
              <div class="content-text">
                ${n.hasil_akhir ? escapeHtml(n.hasil_akhir).replace(/\n/g, '<br>') : 
                  '<div class="empty-content">Tidak ada hasil akhir</div>'}
              </div>
            </div>
            
            ${pesertaSection}
            ${lampiranSection}
            ${downloadButtonSection}
          </div>
        `;
      })
      .catch(error => {
        console.error('Error:', error);
        detailModalContent.innerHTML = '<div class="error-message"><i class="fas fa-exclamation-triangle"></i> Gagal memuat data notulen</div>';
      });
  }

  /* ================= FUNGSI SIMPAN TANDA TANGAN NOTULIS ================= */
  window.simpanTandaTanganNotulis = function(notulenId) {
    if (!signaturePad || signaturePad.isEmpty()) {
      showNotification("Silakan tanda tangan terlebih dahulu!", "error");
      return;
    }

    if (confirm("Simpan tanda tangan sebagai notulis?")) {
      const signatureData = signaturePad.toDataURL("image/png");
      const formData = new FormData();
      formData.append("notulen_id", notulenId);
      formData.append("ttd_notulis", signatureData);
      
      fetch("simpan_ttd_notulis.php", {
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

  function showNotification(msg, type = "info") {
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
