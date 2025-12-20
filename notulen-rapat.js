// notulen-rapat.js
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const notulenModal = document.getElementById('notulenModal');
    const kehadiranModal = document.getElementById('kehadiranModal');
    const openNotulenModalBtn = document.getElementById('openNotulenModal');
    const closeNotulenModalBtn = document.getElementById('closeNotulenModal');
    const closeKehadiranModalBtn = document.getElementById('closeKehadiranModal');
    const cancelNotulenFormBtn = document.getElementById('cancelNotulenForm');
    const notulenForm = document.getElementById('notulenForm');
    const pesertaSearch = document.getElementById('pesertaSearch');
    const searchResults = document.getElementById('searchResults');
    const selectedPesertaList = document.getElementById('selectedPesertaList');
    const pesertaCount = document.getElementById('pesertaCount');
    const pesertaIdsInput = document.getElementById('pesertaIds');
    const formActionInput = document.getElementById('formAction');
    
    // State
    let selectedPeserta = [];
    let allUsers = [];
    let isClosingModal = false;
    
    // Initialize
    init();
    
    function init() {
        loadAllUsers();
        setupEventListeners();
        setDefaultDateTime();
    }
    
    function loadAllUsers() {
        const userItems = document.querySelectorAll('.search-result-item');
        allUsers = Array.from(userItems).map(item => ({
            id: item.dataset.id,
            name: item.dataset.name,
            nim: item.dataset.nim,
            role: item.dataset.role
        }));
    }
    
    function setupEventListeners() {
        // Modal togglers
        openNotulenModalBtn?.addEventListener('click', openNotulenModal);
        closeNotulenModalBtn?.addEventListener('click', closeNotulenModal);
        closeKehadiranModalBtn?.addEventListener('click', closeKehadiranModal);
        cancelNotulenFormBtn?.addEventListener('click', closeNotulenModal);
        
        // Close modals when clicking outside - PERBAIKAN: hanya tutup jika klik di overlay, bukan di dalam modal
        document.addEventListener('click', function(event) {
            if (event.target === notulenModal) {
                closeNotulenModal();
            }
            if (event.target === kehadiranModal) {
                closeKehadiranModal();
            }
        });
        
        // Escape key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeNotulenModal();
                closeKehadiranModal();
            }
        });
        
        // Peserta search
        pesertaSearch?.addEventListener('input', handlePesertaSearch);
        pesertaSearch?.addEventListener('focus', showSearchResults);
        
        // Peserta selection
        searchResults?.addEventListener('click', handlePesertaSelection);
        selectedPesertaList?.addEventListener('click', handlePesertaRemoval);
        
        // Form submission
        notulenForm?.addEventListener('submit', handleFormSubmit);
        
        // PERBAIKAN: Tutup dropdown search ketika klik di luar search container
        document.addEventListener('click', function(event) {
            const searchContainer = document.querySelector('.peserta-search-container');
            if (searchContainer && !searchContainer.contains(event.target)) {
                hideSearchResults();
            }
        });
        
        // Action buttons
        document.addEventListener('click', function(event) {
            // Handle kehadiran button click
            if (event.target.closest('.btn-kehadiran')) {
                const button = event.target.closest('.btn-kehadiran');
                const notulenId = button.dataset.id;
                showKehadiranModal(notulenId);
            }
            
            // Handle form action buttons
            if (event.target.closest('[data-action]')) {
                const button = event.target.closest('[data-action]');
                formActionInput.value = button.dataset.action;
            }
        });
    }
    
    function openNotulenModal() {
        notulenModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeNotulenModal() {
        // Tandai sedang menutup modal
        isClosingModal = true;
        
        notulenModal.classList.remove('active');
        document.body.style.overflow = 'auto';
        resetNotulenForm();
        
        // Reset flag setelah 100ms
        setTimeout(() => {
            isClosingModal = false;
        }, 100);
    }
    
    function closeKehadiranModal() {
        kehadiranModal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    function setDefaultDateTime() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        
        const datetimeInput = document.querySelector('input[name="tanggal_waktu"]');
        if (datetimeInput) {
            datetimeInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        }
    }
    
    function resetNotulenForm() {
        if (notulenForm) {
            notulenForm.reset();
        }
        selectedPeserta = [];
        updateSelectedPesertaList();
        hideSearchResults();
        
        // Reset semua tombol add di search results
        const addButtons = document.querySelectorAll('.result-add');
        addButtons.forEach(button => {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-plus"></i>';
            button.style.background = '';
        });
    }
    
    function showSearchResults() {
        if (searchResults && !isClosingModal) {
            searchResults.style.display = 'block';
        }
    }
    
    function hideSearchResults() {
        if (searchResults) {
            searchResults.style.display = 'none';
        }
    }
    
    function handlePesertaSearch(event) {
        const searchTerm = event.target.value.toLowerCase().trim();
        
        if (!searchTerm) {
            // Show all users when search is empty
            const allItems = searchResults.querySelectorAll('.search-result-item');
            allItems.forEach(item => {
                item.style.display = 'flex';
            });
            return;
        }
        
        allUsers.forEach(user => {
            const item = document.querySelector(`.search-result-item[data-id="${user.id}"]`);
            if (item) {
                const matches = user.name.toLowerCase().includes(searchTerm) || 
                               user.nim.toLowerCase().includes(searchTerm) ||
                               user.role.toLowerCase().includes(searchTerm);
                
                item.style.display = matches ? 'flex' : 'none';
            }
        });
    }
    
    function handlePesertaSelection(event) {
        const addButton = event.target.closest('.result-add');
        if (!addButton) return;
        
        const resultItem = addButton.closest('.search-result-item');
        const userId = resultItem.dataset.id;
        const userName = resultItem.dataset.name;
        const userNim = resultItem.dataset.nim;
        const userRole = resultItem.dataset.role;
        
        // Check if already selected
        if (!selectedPeserta.some(p => p.id === userId)) {
            selectedPeserta.push({
                id: userId,
                name: userName,
                nim: userNim,
                role: userRole
            });
            
            updateSelectedPesertaList();
        }
        
        // Visual feedback
        addButton.disabled = true;
        addButton.innerHTML = '<i class="fas fa-check"></i>';
        addButton.style.background = '#95a5a6';
    }
    
    function handlePesertaRemoval(event) {
        const removeButton = event.target.closest('.peserta-remove');
        if (!removeButton) return;
        
        const pesertaItem = removeButton.closest('.peserta-item');
        const userId = pesertaItem.dataset.id;
        
        // Remove from selected
        selectedPeserta = selectedPeserta.filter(p => p.id !== userId);
        updateSelectedPesertaList();
        
        // Re-enable add button in search results
        const resultItem = document.querySelector(`.search-result-item[data-id="${userId}"]`);
        if (resultItem) {
            const addButton = resultItem.querySelector('.result-add');
            addButton.disabled = false;
            addButton.innerHTML = '<i class="fas fa-plus"></i>';
            addButton.style.background = '';
        }
    }
    
    function updateSelectedPesertaList() {
        if (!selectedPesertaList) return;
        
        // Update count
        pesertaCount.textContent = `${selectedPeserta.length} peserta`;
        
        // Update hidden input
        pesertaIdsInput.value = selectedPeserta.map(p => p.id).join(',');
        
        // Update list display
        if (selectedPeserta.length === 0) {
            selectedPesertaList.innerHTML = '<div class="no-participants">Belum ada peserta yang ditambahkan</div>';
            return;
        }
        
        selectedPesertaList.innerHTML = selectedPeserta.map((peserta, index) => `
            <div class="peserta-item" data-id="${peserta.id}">
                <div class="peserta-info">
                    <div class="peserta-name">${peserta.name}</div>
                    <div class="peserta-nim">${peserta.nim}</div>
                    <div class="peserta-role">${peserta.role}</div>
                </div>
                <button type="button" class="peserta-remove" title="Hapus">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }
    
    async function showKehadiranModal(notulenId) {
        try {
            kehadiranModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            const response = await fetch(`get_kehadiran.php?notulen_id=${notulenId}`);
            const data = await response.json();
            
            let html = '<div class="peserta-detail-list">';
            
            if (data.length === 0) {
                html += '<p class="no-attachment">Belum ada data kehadiran.</p>';
            } else {
                data.forEach((item, index) => {
                    let statusClass = 'belum';
                    let statusText = 'Belum Konfirmasi';
                    
                    if (item.status === 'hadir') {
                        statusClass = 'hadir';
                        statusText = 'Hadir';
                    } else if (item.status === 'tidak') {
                        statusClass = 'tidak_hadir';
                        statusText = 'Tidak Hadir';
                    }
                    
                    html += `
                        <div class="peserta-detail-item">
                            <div class="peserta-number">${index + 1}.</div>
                            <div class="peserta-name">${item.full_name}</div>
                            <div class="peserta-nim">${item.nim}</div>
                            <div class="peserta-role">${item.role}</div>
                            <div class="peserta-status ${statusClass}">${statusText}</div>
                        </div>
                    `;
                });
            }
            
            html += '</div>';
            document.getElementById('kehadiranContent').innerHTML = html;
            
        } catch (error) {
            console.error('Error loading kehadiran:', error);
            document.getElementById('kehadiranContent').innerHTML = 
                '<div class="error-message">Gagal memuat data kehadiran. Silakan coba lagi.</div>';
        }
    }
    
    function handleFormSubmit(event) {
        // Set form action based on which button was clicked
        const submitter = event.submitter;
        if (submitter && submitter.dataset.action) {
            formActionInput.value = submitter.dataset.action;
        }
        
        // Validate required fields
        const requiredFields = notulenForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.style.borderColor = '#e74c3c';
                
                // Remove error style when user starts typing
                field.addEventListener('input', function() {
                    this.style.borderColor = '';
                });
            }
        });
        
        if (!isValid) {
            event.preventDefault();
            showNotification('Harap isi semua field yang wajib diisi!', 'error');
        }
    }
    
    function showNotification(message, type = 'info') {
        // Remove existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create new notification
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    // Handle file preview (optional enhancement)
    const fileInput = document.querySelector('input[name="lampiran[]"]');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                // You can add file preview functionality here if needed
                console.log(`${files.length} file(s) selected`);
            }
        });
    }
});