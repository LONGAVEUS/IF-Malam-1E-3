// Dashboard Notulis - JavaScript Eksternal
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard Notulis loaded successfully!');
    
    // Elements
    const sidebar = document.getElementById('sidebar');
    const sidebarToggler = document.getElementById('sidebarToggler');
    const modal = document.getElementById('notulenModal');
    const tambahBtn = document.getElementById('tambahNotulenBtn');
    const closeBtn = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const notulenForm = document.getElementById('notulenForm');
    const fileInput = document.getElementById('lampiran');
    
    // Modal elements
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const notulenId = document.getElementById('notulenId');
    const submitBtn = document.getElementById('submitBtn');
    const currentFile = document.getElementById('currentFile');
    const currentFileName = document.getElementById('currentFileName');
    
    // Initialize
    initDashboard();
    
    function initDashboard() {
        const sidebarState = localStorage.getItem('sidebarCollapsed');
        if (sidebarState === 'true') {
            sidebar.classList.add('collapsed');
            updateTogglerIcon();
        }
        
        setupEventListeners();
        setDefaultDate();
    }
    
    function setupEventListeners() {
        // Sidebar toggler (sama dengan admin.js)
        if (sidebarToggler && sidebar) {
            sidebarToggler.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
                updateTogglerIcon();
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            });
        }
        
        // Modal event listeners (tetap sama)
        if (tambahBtn) {
            tambahBtn.addEventListener('click', function() {
                resetModal();
                showModal();
            });
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', hideModal);
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', hideModal);
        }
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                hideModal();
            }
        });
        
        if (notulenForm) {
            notulenForm.addEventListener('submit', handleFormSubmit);
        }
        
        // File input validation
        if (fileInput) {
            fileInput.addEventListener('change', validateFile);
        }
        
        // Event delegation untuk action buttons
        document.addEventListener('click', function(event) {
            const target = event.target;
            
            if (target.closest('.action-btn.edit')) {
                handleEditNotulen(target.closest('.action-btn.edit'));
            } else if (target.closest('.action-btn.send')) {
                handleSendNotulen(target.closest('.action-btn.send'));
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'flex') {
                hideModal();
            }
            
            if (event.ctrlKey && event.key === 'n') {
                event.preventDefault();
                resetModal();
                showModal();
            }
        });
    }
    
    // Fungsi toggle sidebar yang lebih sederhana (sama dengan admin.js)
    function updateTogglerIcon() {
        const icon = sidebarToggler.querySelector('span');
        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        } else {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-left');
        }
    }
    
    // Fungsi lainnya tetap sama...
    function validateFile() {
        const file = fileInput.files[0];
        if (!file) return;
        
        const allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedExtensions.includes(fileExtension)) {
            showNotification('Format file tidak diizinkan. Hanya PDF, DOC, DOCX, JPG, JPEG, PNG, TXT.', 'error');
            fileInput.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            showNotification('Ukuran file melebihi 5MB.', 'error');
            fileInput.value = '';
            return;
        }
        
        showNotification(`File "${file.name}" siap diunggah`, 'info');
    }
    
    function showModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        setTimeout(() => {
            const firstInput = document.getElementById('judulRapat');
            if (firstInput) firstInput.focus();
        }, 100);
    }
    
    function hideModal() {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    function resetModal() {
        if (notulenForm) {
            notulenForm.reset();
            const fileInput = document.getElementById('lampiran');
            if (fileInput) {
                fileInput.value = '';
            }
            currentFile.style.display = 'none';
            formAction.name = 'simpan_notulen';
            formAction.value = '1';
            notulenId.value = '';
            modalTitle.textContent = 'Tambah Notulen Baru';
            submitBtn.textContent = 'Simpan Notulen';
        }
        setDefaultDate();
    }
    
    function setDefaultDate() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('tanggalRapat');
        if (dateInput && !notulenId.value) {
            dateInput.value = today;
        }
    }
    
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const judul = document.getElementById('judulRapat').value.trim();
        const tanggal = document.getElementById('tanggalRapat').value;
        const isi = document.getElementById('isiNotulen').value.trim();
        
        if (!judul || !tanggal || !isi) {
            showNotification('Harap lengkapi semua field yang wajib diisi!', 'error');
            return;
        }
        
        notulenForm.submit();
    }
    
    function handleEditNotulen(button) {
        const notulenIdValue = button.getAttribute('data-id');
        
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch(`edit_notulen.php?id=${notulenIdValue}`)
            .then(response => response.json())
            .then(notulen => {
                if (notulen.error) {
                    showNotification(notulen.error, 'error');
                    return;
                }

                document.getElementById('judulRapat').value = notulen.judul;
                document.getElementById('tanggalRapat').value = notulen.tanggal;
                document.getElementById('penanggungJawab').value = notulen.penanggung_jawab;
                document.getElementById('isiNotulen').value = notulen.isi;
                
                if (notulen.lampiran) {
                    currentFileName.textContent = notulen.nama_file_asli || notulen.lampiran;
                    currentFile.style.display = 'block';
                } else {
                    currentFile.style.display = 'none';
                }
                
                formAction.name = 'update_notulen';
                formAction.value = '1';
                notulenId.value = notulen.id;
                modalTitle.textContent = 'Edit Notulen';
                submitBtn.textContent = 'Update Notulen';
                
                showModal();
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat memuat data notulen.', 'error');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-edit"></i>';
            });
    }
    
    function handleSendNotulen(button) {
        const notulenId = button.getAttribute('data-id');
        const notulenItem = document.querySelector(`.notulen-item[data-id="${notulenId}"]`);
        const judul = notulenItem.querySelector('.notulen-title').textContent;
        const statusElement = notulenItem.querySelector('.notulen-status');
        
        if (confirm(`Kirim notulen "${judul}" ke peserta rapat?`)) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            setTimeout(() => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-paper-plane"></i>';
                statusElement.textContent = 'Terkirim';
                statusElement.className = 'notulen-status sent';
                
                showNotification('Notulen berhasil dikirim ke peserta!', 'success');
            }, 1500);
        }
    }
    
    function showNotification(message, type = 'info') {
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notif => notif.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                
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
        toggleSidebar,
        showModal,
        hideModal,
        showNotification,
        resetModal
    };
