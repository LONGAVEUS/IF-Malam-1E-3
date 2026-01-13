// notulen-rapat.js - VERSI BAHARU DENGAN ATURAN EDIT DAN HAPUS
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const notulenModal = document.getElementById('notulenModal');
    const kehadiranModal = document.getElementById('kehadiranModal');
    const editNotulenModal = document.getElementById('editNotulenModal');
    const openNotulenModalBtn = document.getElementById('openNotulenModal');
    const closeNotulenModalBtn = document.getElementById('closeNotulenModal');
    const closeKehadiranModalBtn = document.getElementById('closeKehadiranModal');
    const closeEditModalBtn = document.getElementById('closeEditModal');
    const cancelNotulenFormBtn = document.getElementById('cancelNotulenForm');
    const notulenForm = document.getElementById('notulenForm');
    const pesertaSearch = document.getElementById('pesertaSearch');
    const searchResults = document.getElementById('searchResults');
    const selectedPesertaList = document.getElementById('selectedPesertaList');
    const pesertaCount = document.getElementById('pesertaCount');
    const pesertaIdsInput = document.getElementById('pesertaIds');
    const formActionInput = document.getElementById('formAction');
    
    // State untuk create modal
    let selectedPeserta = [];
    let allUsers = [];
    let hasUnsavedChanges = false;
    let currentNotulenId = null; // Untuk edit modal
    
    // State untuk edit modal
    window.selectedPesertaEdit = [];
    
    // Initialize
    init();
    
    function init() {
        loadAllUsers();
        setupEventListeners();
        setDefaultDateAndDay();
        setupEditModal();
    }
    
    function loadAllUsers() {
        // Ambil dari kedua modal: create dan edit
        const userItemsCreate = document.querySelectorAll('#searchResults .search-result-item');
        const userItemsEdit = document.querySelectorAll('#editSearchResults .search-result-item');
        
        const allItems = [...userItemsCreate, ...userItemsEdit];
        
        // Gunakan Map untuk menghindari duplikat berdasarkan ID
        const userMap = new Map();
        
        allItems.forEach(item => {
            const id = item.dataset.id;
            if (!userMap.has(id)) {
                userMap.set(id, {
                    id: item.dataset.id,
                    name: item.dataset.name,
                    nim: item.dataset.nim,
                    role: item.dataset.role,
                    email: item.dataset.email || '' // Tambahkan email jika ada
                });
            }
        });
        
        allUsers = Array.from(userMap.values());
        console.log('Total users loaded:', allUsers.length);
    }
    
    function setupEventListeners() {
        // Modal togglers
        openNotulenModalBtn?.addEventListener('click', openNotulenModal);
        closeNotulenModalBtn?.addEventListener('click', () => closeNotulenModal(true));
        closeKehadiranModalBtn?.addEventListener('click', closeKehadiranModal);
        closeEditModalBtn?.addEventListener('click', closeEditModal);
        cancelNotulenFormBtn?.addEventListener('click', () => closeNotulenModal(true));
        
        // Close modal ketika klik di luar overlay
        document.addEventListener('click', function(event) {
            if (event.target === notulenModal) {
                if (hasUnsavedChanges) {
                    const confirmClose = confirm('Ada perubahan yang belum disimpan. Tutup modal?');
                    if (!confirmClose) return;
                }
                closeNotulenModal(true);
            }
            if (event.target === kehadiranModal) {
                closeKehadiranModal();
            }
            if (event.target === editNotulenModal) {
                closeEditModal();
            }
        });
        
        // Escape key untuk menutup modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (notulenModal.classList.contains('active')) {
                    if (hasUnsavedChanges) {
                        const confirmClose = confirm('Ada perubahan yang belum disimpan. Tutup modal?');
                        if (!confirmClose) return;
                    }
                    closeNotulenModal(true);
                }
                if (kehadiranModal.classList.contains('active')) {
                    closeKehadiranModal();
                }
                if (editNotulenModal.classList.contains('active')) {
                    closeEditModal();
                }
            }
        });
        
        // Peserta search untuk create modal
        pesertaSearch?.addEventListener('input', handlePesertaSearch);
        pesertaSearch?.addEventListener('focus', showSearchResults);
        
        // Peserta selection untuk create modal
        searchResults?.addEventListener('click', handlePesertaSelection);
        selectedPesertaList?.addEventListener('click', handlePesertaRemoval);
        
        // Form submission
        notulenForm?.addEventListener('submit', handleFormSubmit);
        
        // Tutup dropdown search ketika klik di luar
        document.addEventListener('click', function(event) {
            const searchContainer = document.querySelector('.peserta-search-container');
            if (searchContainer && !searchContainer.contains(event.target) && 
                event.target !== pesertaSearch) {
                hideSearchResults();
            }
        });
        
        // Deteksi perubahan form untuk menandai ada perubahan yang belum disimpan
        if (notulenForm) {
            notulenForm.addEventListener('input', function() {
                hasUnsavedChanges = true;
            });
        }
        
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
            
            // Handle email invitation button
            if (event.target.closest('.btn-email') || event.target.closest('.action-btn.email')) {
                const button = event.target.closest('.btn-email') || event.target.closest('.action-btn.email');
                const notulenId = button.dataset.id;
                sendEmailInvitation(notulenId);
            }
            
            // Handle konfirmasi hapus untuk status sent
            if (event.target.closest('.action-btn.delete')) {
                const button = event.target.closest('.action-btn.delete');
                const notulenItem = button.closest('.notulen-item');
                const notulenId = notulenItem.dataset.id;
                
                // Cek status notulen dari elemen status badge
                const statusBadge = notulenItem.querySelector('.notulen-status');
                const status = statusBadge ? statusBadge.textContent.toLowerCase().trim() : '';
                
                // Jika status sent, tampilkan pesan khusus
                if (status === 'sent') {
                    event.preventDefault();
                    showToast('Notulen dengan status "sent" tidak dapat dihapus!', 'error');
                    return false;
                }
            }
        });
    }
    
    // ================== FUNGSI CREATE MODAL ==================
    function openNotulenModal() {
        notulenModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        hasUnsavedChanges = false;
        setDefaultDateAndDay();
    }
    
    function closeNotulenModal(force = false) {
        notulenModal.classList.remove('active');
        document.body.style.overflow = 'auto';
        resetNotulenForm();
    }
    
    function closeKehadiranModal() {
        kehadiranModal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    function closeEditModal() {
        editNotulenModal.classList.remove('active');
        document.body.style.overflow = 'auto';
        resetEditForm();
    }
    
    function setDefaultDateAndDay() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        
        // Set tanggal default untuk form create
        const dateInput = document.querySelector('#notulenModal input[name="tanggal"]');
        if (dateInput && !dateInput.value) {
            dateInput.value = `${year}-${month}-${day}`;
        }
        
        // Set hari default berdasarkan tanggal
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const dayName = days[now.getDay()];
        const hariSelect = document.querySelector('#notulenModal select[name="hari"]');
        if (hariSelect && !hariSelect.value) {
            hariSelect.value = dayName;
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
        
        setDefaultDateAndDay();
        hasUnsavedChanges = false;
    }
    
    function showSearchResults() {
        if (searchResults) {
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
                               user.role.toLowerCase().includes(searchTerm) ||
                               (user.email && user.email.toLowerCase().includes(searchTerm));
                
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
        
        if (!selectedPeserta.some(p => p.id === userId)) {
            selectedPeserta.push({
                id: userId,
                name: userName,
                nim: userNim,
                role: userRole
            });
            
            updateSelectedPesertaList();
            hasUnsavedChanges = true;
        }
        
        addButton.disabled = true;
        addButton.innerHTML = '<i class="fas fa-check"></i>';
        addButton.style.background = '#95a5a6';
    }
    
    function handlePesertaRemoval(event) {
        const removeButton = event.target.closest('.peserta-remove');
        if (!removeButton) return;
        
        const pesertaItem = removeButton.closest('.peserta-item');
        const userId = pesertaItem.dataset.id;
        
        selectedPeserta = selectedPeserta.filter(p => p.id !== userId);
        updateSelectedPesertaList();
        hasUnsavedChanges = true;
        
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
        
        pesertaCount.textContent = `${selectedPeserta.length} peserta`;
        pesertaIdsInput.value = selectedPeserta.map(p => p.id).join(',');
        
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
    
    // ================== FUNGSI EDIT MODAL ==================
    function setupEditModal() {
        const editForm = document.getElementById('editNotulenForm');
        const editPesertaSearch = document.getElementById('editPesertaSearch');
        const editSearchResults = document.getElementById('editSearchResults');
        const editSelectedPesertaList = document.getElementById('editSelectedPesertaList');
        const editPesertaCount = document.getElementById('editPesertaCount');
        const editPesertaIdsInput = document.getElementById('editPesertaIds');
        const cancelEditFormBtn = document.getElementById('cancelEditForm');
        
        if (!editForm || !editPesertaSearch) {
            console.log('Elemen edit modal tidak ditemukan, coba lagi nanti...');
            setTimeout(setupEditModal, 100);
            return;
        }
        
        console.log('Setup edit modal berhasil');
        
        // Pastikan window.selectedPesertaEdit diinisialisasi
        if (!window.selectedPesertaEdit) {
            window.selectedPesertaEdit = [];
        }
        
        // Event listeners untuk edit modal
        cancelEditFormBtn?.addEventListener('click', closeEditModal);
        
        // Peserta search untuk edit modal - FIXED
        editPesertaSearch.addEventListener('input', function(event) {
            handleEditPesertaSearch(event);
        });
        
        editPesertaSearch.addEventListener('click', function(event) {
            event.stopPropagation(); // Mencegah event bubbling
            showEditSearchResults();
        });
        
        editPesertaSearch.addEventListener('focus', function(event) {
            event.stopPropagation();
            showEditSearchResults();
        });
        
        // Peserta selection untuk edit modal
        editSearchResults?.addEventListener('click', function(event) {
            event.stopPropagation(); // Mencegah event bubbling
            handleEditPesertaSelection(event);
        });
        
        editSelectedPesertaList?.addEventListener('click', function(event) {
            event.stopPropagation();
            handleEditPesertaRemoval(event);
        });
        
        // Form submission untuk edit
        editForm.addEventListener('submit', handleEditFormSubmit);
        
        // Update hari berdasarkan tanggal di edit modal
        const editTanggalInput = editForm.querySelector('input[name="tanggal"]');
        if (editTanggalInput) {
            editTanggalInput.addEventListener('change', function() {
                const date = new Date(this.value);
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const dayName = days[date.getDay()];
                
                const editHariSelect = editForm.querySelector('select[name="hari"]');
                if (editHariSelect) {
                    editHariSelect.value = dayName;
                }
            });
        }
        
        // TUTUP DROPDOWN HANYA JIKA KLIK DI LUAR SEARCH CONTAINER
        document.addEventListener('click', function(event) {
            const editSearchContainer = editPesertaSearch.closest('.peserta-search-container');
            const isClickInsideEditSearch = editSearchContainer && editSearchContainer.contains(event.target);
            const isClickOnSearchInput = event.target === editPesertaSearch;
            
            // Jika klik di luar search container, tutup dropdown
            if (!isClickInsideEditSearch && !isClickOnSearchInput) {
                hideEditSearchResults();
            }
        });
        
        // TAMBAHKAN KEYDOWN EVENT UNTUK ESCAPE
        editPesertaSearch.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideEditSearchResults();
            }
        });
        
        // Inisialisasi awal
        updateEditSelectedPesertaList();
        updateEditSearchResults();
    }
    
    function handleEditPesertaSearch(event) {
        const searchTerm = event.target.value.toLowerCase().trim();
        const editSearchResults = document.getElementById('editSearchResults');
        
        if (!searchTerm) {
            const allItems = editSearchResults.querySelectorAll('.search-result-item');
            allItems.forEach(item => {
                item.style.display = 'flex';
            });
            return;
        }
        
        allUsers.forEach(user => {
            const item = document.querySelector(`#editSearchResults .search-result-item[data-id="${user.id}"]`);
            if (item) {
                const matches = user.name.toLowerCase().includes(searchTerm) || 
                               user.nim.toLowerCase().includes(searchTerm) ||
                               user.role.toLowerCase().includes(searchTerm) ||
                               (user.email && user.email.toLowerCase().includes(searchTerm));
                
                item.style.display = matches ? 'flex' : 'none';
            }
        });
    }
    
    function showEditSearchResults() {
        const editSearchResults = document.getElementById('editSearchResults');
        if (editSearchResults) {
            editSearchResults.style.display = 'block';
            updateEditSearchResults(); // Pastikan status tombol diperbarui
        }
    }
    
    function hideEditSearchResults() {
        const editSearchResults = document.getElementById('editSearchResults');
        if (editSearchResults) {
            editSearchResults.style.display = 'none';
        }
    }
    
    function handleEditPesertaSelection(event) {
        const addButton = event.target.closest('.result-add');
        if (!addButton) return;
        
        const resultItem = addButton.closest('.search-result-item');
        const userId = resultItem.dataset.id;
        const userName = resultItem.dataset.name;
        const userNim = resultItem.dataset.nim;
        const userRole = resultItem.dataset.role;
        
        // Pastikan window.selectedPesertaEdit ada
        if (!window.selectedPesertaEdit) {
            window.selectedPesertaEdit = [];
        }
        
        // Cek apakah peserta sudah dipilih
        if (!window.selectedPesertaEdit.some(p => p.id === userId)) {
            window.selectedPesertaEdit.push({
                id: userId,
                name: userName,
                nim: userNim,
                role: userRole
            });
            
            updateEditSelectedPesertaList();
            updateEditSearchResults(); // Update status tombol di search results
        }
        
        // Update tombol
        addButton.disabled = true;
        addButton.innerHTML = '<i class="fas fa-check"></i>';
        addButton.style.background = '#95a5a6';
    }
    
    function handleEditPesertaRemoval(event) {
        const removeButton = event.target.closest('.peserta-remove');
        if (!removeButton) return;
        
        const pesertaItem = removeButton.closest('.peserta-item');
        const userId = pesertaItem.dataset.id;
        
        // Hapus dari selectedPesertaEdit
        if (window.selectedPesertaEdit) {
            window.selectedPesertaEdit = window.selectedPesertaEdit.filter(p => p.id !== userId);
            updateEditSelectedPesertaList();
            updateEditSearchResults(); // Update status tombol di search results
        }
    }
    
    function updateEditSelectedPesertaList() {
        const editSelectedPesertaList = document.getElementById('editSelectedPesertaList');
        const editPesertaCount = document.getElementById('editPesertaCount');
        const editPesertaIdsInput = document.getElementById('editPesertaIds');
        
        if (!editSelectedPesertaList || !window.selectedPesertaEdit) return;
        
        if (window.selectedPesertaEdit.length === 0) {
            editSelectedPesertaList.innerHTML = '<div class="no-participants">Belum ada peserta yang ditambahkan</div>';
            editPesertaCount.textContent = '0 peserta';
            editPesertaIdsInput.value = '';
            return;
        }
        
        editPesertaCount.textContent = `${window.selectedPesertaEdit.length} peserta`;
        editPesertaIdsInput.value = window.selectedPesertaEdit.map(p => p.id).join(',');
        
        editSelectedPesertaList.innerHTML = window.selectedPesertaEdit.map(peserta => `
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
    
    function updateEditSearchResults() {
        const editSearchResults = document.getElementById('editSearchResults');
        if (!editSearchResults || !window.selectedPesertaEdit) return;
        
        const resultItems = editSearchResults.querySelectorAll('.search-result-item');
        resultItems.forEach(item => {
            const userId = item.dataset.id;
            const addButton = item.querySelector('.result-add');
            const isSelected = window.selectedPesertaEdit.some(p => p.id == userId);
            
            if (addButton) {
                if (isSelected) {
                    addButton.disabled = true;
                    addButton.innerHTML = '<i class="fas fa-check"></i>';
                    addButton.style.background = '#95a5a6';
                } else {
                    addButton.disabled = false;
                    addButton.innerHTML = '<i class="fas fa-plus"></i>';
                    addButton.style.background = '';
                }
            }
        });
    }
    
    // Fungsi openEditModal
    window.openEditModal = async function(notulenId) {
        try {
            console.log('Membuka modal edit untuk notulen ID:', notulenId);
            
            // Show loading overlay
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.add('active');
            }
            
            // Save notulen ID
            currentNotulenId = notulenId;
            
            // Open modal
            const editModal = document.getElementById('editNotulenModal');
            if (!editModal) {
                throw new Error('Edit modal tidak ditemukan');
            }
            
            editModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Show loading state in modal
            const modalBody = editModal.querySelector('.modal-body');
            if (!modalBody) {
                throw new Error('Modal body tidak ditemukan');
            }
            
            const originalContent = modalBody.innerHTML;
            modalBody.innerHTML = `
                <div class="loading" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin fa-2x" style="margin-bottom: 15px;"></i>
                    <div style="font-size: 16px; color: #3498db;">Memuat data notulen...</div>
                </div>
            `;
            
            // Fetch data dengan timeout dan error handling
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
            
            const response = await fetch(`edit_notulen.php?id=${notulenId}`, {
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            // Check if response is OK
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server response error:', errorText);
                throw new Error(`Server error: ${response.status} ${response.statusText}`);
            }
            
            // Parse response as JSON
            const result = await response.json();
            
            console.log('Response dari server:', result);
            
            // Check if response is successful
            if (!result.success) {
                throw new Error(result.error || 'Gagal memuat data notulen');
            }
            
            // Restore modal content
            modalBody.innerHTML = originalContent;
            
            // Setup ulang event listeners untuk modal edit
            setTimeout(() => {
                setupEditModal();
            }, 100);
            
            const data = result.data;
            
            // Set nilai ke form fields
            const setFieldValue = (id, value) => {
                const field = document.getElementById(id);
                if (field) {
                    field.value = value || '';
                }
            };
            
            setFieldValue('editNotulenId', data.id);
            setFieldValue('editJudul', data.judul);
            setFieldValue('editHari', data.hari);
            setFieldValue('editTanggal', data.tanggal);
            setFieldValue('editJamMulai', data.jam_mulai);
            setFieldValue('editJamSelesai', data.jam_selesai);
            setFieldValue('editTempat', data.tempat);
            setFieldValue('editNotulis', data.notulis);
            setFieldValue('editJurusan', data.jurusan);
            setFieldValue('editPenanggungJawab', data.penanggung_jawab);
            setFieldValue('editPembahasan', data.pembahasan);
            setFieldValue('editHasilAkhir', data.hasil_akhir);
            
            // Set peserta
            if (data.peserta_details && Array.isArray(data.peserta_details)) {
                window.selectedPesertaEdit = data.peserta_details.map(p => ({
                    id: String(p.user_id || p.id),
                    name: p.full_name || p.name || '',
                    nim: p.nim || '',
                    role: p.role || '',
                    email: p.email || '' // Tambahkan email jika ada
                })).filter(p => p.id && p.name); // Filter out invalid entries
            } else {
                window.selectedPesertaEdit = [];
            }
            
            // Update peserta display
            updateEditSelectedPesertaList();
            updateEditSearchResults();
            
            // Reset search
            const editPesertaSearch = document.getElementById('editPesertaSearch');
            if (editPesertaSearch) {
                editPesertaSearch.value = '';
            }
            
            // Hide loading overlay
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active');
            }
            
        } catch (error) {
            console.error('Error loading edit modal:', error);
            
            // Show error message
            let errorMessage = 'Gagal memuat data notulen. ';
            
            if (error.name === 'AbortError') {
                errorMessage += 'Waktu permintaan habis. Silakan coba lagi.';
            } else if (error.message.includes('JSON')) {
                errorMessage += 'Format data tidak valid.';
            } else {
                errorMessage += error.message;
            }
            
            showToast(errorMessage, 'error');
            
            // Close modal
            const editModal = document.getElementById('editNotulenModal');
            if (editModal) {
                editModal.classList.remove('active');
            }
            
            // Hide loading
            const loadingOverlay = document.getElementById('loadingOverlay');
            if (loadingOverlay) {
                loadingOverlay.classList.remove('active');
            }
            
            document.body.style.overflow = 'auto';
        }
    };
    
    function resetEditForm() {
        window.selectedPesertaEdit = [];
        currentNotulenId = null;
        
        const editForm = document.getElementById('editNotulenForm');
        if (editForm) {
            editForm.reset();
        }
        
        const editSearchResults = document.getElementById('editSearchResults');
        if (editSearchResults) {
            const addButtons = editSearchResults.querySelectorAll('.result-add');
            addButtons.forEach(button => {
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-plus"></i>';
                button.style.background = '';
            });
        }
        
        const editPesertaSearch = document.getElementById('editPesertaSearch');
        if (editPesertaSearch) {
            editPesertaSearch.value = '';
        }
        
        hideEditSearchResults();
    }
    
    // ================== FORM SUBMISSION HANDLERS ==================
    function handleFormSubmit(event) {
        const submitter = event.submitter;
        if (submitter && submitter.dataset.action) {
            formActionInput.value = submitter.dataset.action;
        }
        
        // Validasi semua field required
        const requiredFields = notulenForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('form-error');
            } else {
                field.classList.remove('form-error');
            }
        });
        
        if (selectedPeserta.length === 0) {
            isValid = false;
            showToast('Harap pilih minimal 1 peserta!', 'error');
            const pesertaSearchContainer = document.querySelector('.peserta-search-container');
            if (pesertaSearchContainer) {
                pesertaSearchContainer.style.border = '2px solid #e74c3c';
                pesertaSearchContainer.style.borderRadius = '6px';
                pesertaSearchContainer.style.padding = '5px';
            }
        }
        
        if (!isValid) {
            event.preventDefault();
            showToast('Harap isi semua field yang wajib diisi!', 'error');
        } else {
            hasUnsavedChanges = false;
        }
    }
    
    function handleEditFormSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Show loading
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        submitBtn.disabled = true;
        
        // Validate peserta
        if (!window.selectedPesertaEdit || window.selectedPesertaEdit.length === 0) {
            showToast('Pilih minimal 1 peserta!', 'error');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            return;
        }
        
        const formData = new FormData(form);
        
        fetch('notulen_rapat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            console.log('Full server response:', data);
            
            // Cek berbagai pola respons
            if (data.includes('Notulen berhasil diperbarui!') || 
                data.includes('berhasil') || 
                data.includes('success')) {
                showToast('Notulen berhasil diperbarui!', 'success');
                
                setTimeout(() => {
                    closeEditModal();
                    window.location.reload();
                }, 1500);
            } else {
                // Try to extract error message
                let errorMsg = 'Terjadi kesalahan saat menyimpan!';
                
                // Cek pola error umum
                const errorMatch = data.match(/class="alert alert-danger"[^>]*>.*?<i[^>]*><\/i>\s*(.*?)<\/div>/s);
                if (errorMatch && errorMatch[1]) {
                    errorMsg = errorMatch[1].trim();
                } else if (data.includes('SQLSTATE') || data.includes('SQL syntax')) {
                    errorMsg = 'Error database. Silakan hubungi administrator.';
                } else if (data.includes('Allowed memory size')) {
                    errorMsg = 'File terlalu besar. Kurangi ukuran file.';
                }
                
                showToast(errorMsg, 'error');
                
                // Log full error untuk debugging
                console.error('Error response:', data);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
            showToast('Gagal terhubung ke server!', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }
    
    // ================== FUNGSI KIRIM EMAIL UNDANGAN ==================
    async function sendEmailInvitation(notulenId) {
        console.log('sendEmailInvitation called with notulenId:', notulenId);
        
        if (!notulenId || notulenId === 0) {
            console.error('Invalid notulenId:', notulenId);
            showToast('Error: ID Notulen tidak valid', 'error');
            return;
        }
        
        if (!confirm('Kirim email undangan ke semua peserta?')) {
            return;
        }
        
        try {
            showLoading();
            
            console.log('Sending email for notulen ID:', notulenId);
            
            const formData = new FormData();
            formData.append('notulen_id', notulenId);
            
            // Kirim request ke server
            const response = await fetch('send_email.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            // Dapatkan teks respons
            const responseText = await response.text();
            console.log('Response text (first 500 chars):', responseText.substring(0, 500));
            
            // Coba parse sebagai JSON
            try {
                const result = JSON.parse(responseText);
                console.log('Parsed result:', result);
                
                if (result.success) {
                    showToast(result.message || 'Email undangan berhasil dikirim!', 'success');
                    
                    // Optional: reload halaman setelah beberapa detik
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showToast(result.error || 'Gagal mengirim email undangan', 'error');
                }
            } catch (jsonError) {
                console.error('JSON Parse Error:', jsonError);
                console.error('Full Response:', responseText);
                
                // Tampilkan error yang lebih informatif
                showToast('Terjadi kesalahan pada server. Lihat console untuk detail.', 'error');
            }
        } catch (error) {
            console.error('Network Error:', error);
            showToast('Gagal terhubung ke server: ' + error.message, 'error');
        } finally {
            hideLoading();
        }
    } 
    
    // ================== FUNGSI BANTU LAINNYA ==================
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
                    } else if (item.status === 'tidak_hadir') {
                        statusClass = 'tidak_hadir';
                        statusText = 'Tidak Hadir';
                    }
                    
                    const waktuKonfirmasi = item.waktu_konfirmasi 
                        ? new Date(item.waktu_konfirmasi).toLocaleString('id-ID')
                        : '-';
                    
                    html += `
                        <div class="peserta-detail-item">
                            <div class="peserta-number">${index + 1}.</div>
                            <div class="peserta-name">${item.full_name}</div>
                            <div class="peserta-nim">${item.nim}</div>
                            <div class="peserta-role">${item.role}</div>
                            <div class="peserta-status ${statusClass}">
                                ${statusText}
                                ${item.waktu_konfirmasi ? `<br><small>${waktuKonfirmasi}</small>` : ''}
                            </div>
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
    
    // Fungsi toast notification
    function showToast(message, type = 'info') {
        // Hapus toast yang sudah ada
        const existingToasts = document.querySelectorAll('.toast-notification');
        existingToasts.forEach(toast => {
            toast.classList.add('hiding');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        });
        
        // Buat toast baru
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        
        // Icon berdasarkan type
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        if (type === 'error') icon = 'exclamation-triangle';
        if (type === 'warning') icon = 'exclamation-circle';
        
        toast.innerHTML = `
            <i class="fas fa-${icon} toast-icon"></i>
            <span class="toast-message">${message}</span>
        `;
        
        // Tambahkan ke body
        document.body.appendChild(toast);
        
        // Hapus toast setelah 5 detik
        setTimeout(() => {
            toast.classList.add('hiding');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 4700);
    }
    
    // Fungsi loading
    function showLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.add('active');
        }
    }
    
    function hideLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('active');
        }
    }
    
    // Event listener untuk update hari berdasarkan tanggal di create modal
    const tanggalInput = notulenForm?.querySelector('input[name="tanggal"]');
    if (tanggalInput) {
        tanggalInput.addEventListener('change', function() {
            const date = new Date(this.value);
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const dayName = days[date.getDay()];
            
            const hariSelect = notulenForm.querySelector('select[name="hari"]');
            if (hariSelect) {
                hariSelect.value = dayName;
            }
        });
    }
    
    // Fungsi konfirmasi aksi
    window.confirmAction = function(message) {
        return confirm(message);
    };
    
    // Validasi peserta sebelum submit
    function validatePeserta() {
        if (selectedPeserta.length === 0) {
            showToast('Harap pilih minimal 1 peserta!', 'error');
            return false;
        }
        return true;
    }
    
    if (notulenForm) {
        notulenForm.addEventListener('submit', function(e) {
            if (!validatePeserta()) {
                e.preventDefault();
                return false;
            }
        });
    }
});
