// notulen-rapat.js - VERSI DIPERBAIKI DENGAN FIELD BARU (HARI, TANGGAL, TEMPAT, NOTULIS)
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
    const editNotulenForm = document.getElementById('editNotulenForm');
    const pesertaSearch = document.getElementById('pesertaSearch');
    const editPesertaSearch = document.getElementById('editPesertaSearch');
    const searchResults = document.getElementById('searchResults');
    const editSearchResults = document.getElementById('editSearchResults');
    const selectedPesertaList = document.getElementById('selectedPesertaList');
    const editSelectedPesertaList = document.getElementById('editSelectedPesertaList');
    const pesertaCount = document.getElementById('pesertaCount');
    const editPesertaCount = document.getElementById('editPesertaCount');
    const pesertaIdsInput = document.getElementById('pesertaIds');
    const editPesertaIdsInput = document.getElementById('editPesertaIds');
    const formActionInput = document.getElementById('formAction');
    
    // State
    let selectedPeserta = [];
    let selectedPesertaEdit = [];
    let allUsers = [];
    let isClosingModal = false;
    let hasUnsavedChanges = false;
    
    // Initialize
    init();
    
    function init() {
        loadAllUsers();
        setupEventListeners();
        setDefaultDateAndDay();
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
        closeNotulenModalBtn?.addEventListener('click', () => closeNotulenModal(true));
        closeKehadiranModalBtn?.addEventListener('click', closeKehadiranModal);
        closeEditModalBtn?.addEventListener('click', closeEditModal);
        cancelNotulenFormBtn?.addEventListener('click', () => closeNotulenModal(true));
        
        // PERBAIKAN: Event delegation untuk tombol batal di modal edit
        editNotulenModal?.addEventListener('click', function(event) {
            // Cek jika yang diklik adalah tombol batal atau ikon di dalamnya
            if (event.target.id === 'cancelEditForm' || 
                event.target.closest('#cancelEditForm') ||
                (event.target.classList.contains('btn-cancel') && 
                 event.target.closest('#editNotulenModal'))) {
                closeEditModal();
            }
        });
        
        // Close modal when clicking outside overlay
        document.addEventListener('click', function(event) {
            if (event.target === notulenModal) {
                // Cek apakah ada perubahan yang belum disimpan
                if (hasUnsavedChanges) {
                    const confirmClose = confirm('Ada perubahan yang belum disimpan. Tutup modal?');
                    if (!confirmClose) {
                        return;
                    }
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
        
        // Escape key to close modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                if (notulenModal.classList.contains('active')) {
                    if (hasUnsavedChanges) {
                        const confirmClose = confirm('Ada perubahan yang belum disimpan. Tutup modal?');
                        if (!confirmClose) {
                            return;
                        }
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
        
        // Peserta search for create modal
        pesertaSearch?.addEventListener('input', handlePesertaSearch);
        pesertaSearch?.addEventListener('focus', showSearchResults);
        
        // Peserta search for edit modal
        editPesertaSearch?.addEventListener('input', handlePesertaSearchEdit);
        editPesertaSearch?.addEventListener('focus', showSearchResultsEdit);
        
        // Peserta selection for create modal
        searchResults?.addEventListener('click', handlePesertaSelection);
        selectedPesertaList?.addEventListener('click', handlePesertaRemoval);
        
        // Peserta selection for edit modal
        editSearchResults?.addEventListener('click', handlePesertaSelectionEdit);
        editSelectedPesertaList?.addEventListener('click', handlePesertaRemovalEdit);
        
        // Form submission
        notulenForm?.addEventListener('submit', handleFormSubmit);
        editNotulenForm?.addEventListener('submit', handleEditFormSubmit);
        
        // Tutup dropdown search ketika klik di luar search container
        document.addEventListener('click', function(event) {
            const searchContainer = document.querySelector('.peserta-search-container');
            const editSearchContainer = document.querySelectorAll('.peserta-search-container')[1];
            
            if (searchContainer && !searchContainer.contains(event.target) && 
                event.target !== pesertaSearch) {
                hideSearchResults();
            }
            
            if (editSearchContainer && !editSearchContainer.contains(event.target) && 
                event.target !== editPesertaSearch) {
                hideSearchResultsEdit();
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
        });
    }
    
    function openNotulenModal() {
        notulenModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        // Reset flag saat modal dibuka
        hasUnsavedChanges = false;
        // Set tanggal dan hari default
        setDefaultDateAndDay();
    }
    
    function closeNotulenModal(force = false) {
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
    
    function closeEditModal() {
        editNotulenModal.classList.remove('active');
        document.body.style.overflow = 'auto';
        resetEditForm();
    }
    
    // PERUBAHAN BESAR: Fungsi untuk set tanggal dan hari default
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
            // Cari opsi yang sesuai
            for (let i = 0; i < hariSelect.options.length; i++) {
                if (hariSelect.options[i].value === dayName) {
                    hariSelect.selectedIndex = i;
                    break;
                }
            }
        }
    }
    
    // PERUBAHAN: Update fungsi resetNotulenForm untuk field baru
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
        
        // Set ulang tanggal dan hari default
        setDefaultDateAndDay();
        
        // Reset flag perubahan
        hasUnsavedChanges = false;
    }
    
    function resetEditForm() {
        selectedPesertaEdit = [];
        updateSelectedPesertaListEdit();
        hideSearchResultsEdit();
        
        // Reset semua tombol add di search results edit
        const addButtons = document.querySelectorAll('#editSearchResults .result-add');
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
    
    function showSearchResultsEdit() {
        if (editSearchResults) {
            editSearchResults.style.display = 'block';
        }
    }
    
    function hideSearchResultsEdit() {
        if (editSearchResults) {
            editSearchResults.style.display = 'none';
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
    
    function handlePesertaSearchEdit(event) {
        const searchTerm = event.target.value.toLowerCase().trim();
        
        if (!searchTerm) {
            // Show all users when search is empty
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
            hasUnsavedChanges = true;
        }
        
        // Visual feedback
        addButton.disabled = true;
        addButton.innerHTML = '<i class="fas fa-check"></i>';
        addButton.style.background = '#95a5a6';
    }
    
    function handlePesertaSelectionEdit(event) {
        const addButton = event.target.closest('.result-add');
        if (!addButton) return;
        
        const resultItem = addButton.closest('.search-result-item');
        const userId = resultItem.dataset.id;
        const userName = resultItem.dataset.name;
        const userNim = resultItem.dataset.nim;
        const userRole = resultItem.dataset.role;
        
        // Check if already selected
        if (!selectedPesertaEdit.some(p => p.id === userId)) {
            selectedPesertaEdit.push({
                id: userId,
                name: userName,
                nim: userNim,
                role: userRole
            });
            
            updateSelectedPesertaListEdit();
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
        hasUnsavedChanges = true;
        
        // Re-enable add button in search results
        const resultItem = document.querySelector(`.search-result-item[data-id="${userId}"]`);
        if (resultItem) {
            const addButton = resultItem.querySelector('.result-add');
            addButton.disabled = false;
            addButton.innerHTML = '<i class="fas fa-plus"></i>';
            addButton.style.background = '';
        }
    }
    
    function handlePesertaRemovalEdit(event) {
        const removeButton = event.target.closest('.peserta-remove');
        if (!removeButton) return;
        
        const pesertaItem = removeButton.closest('.peserta-item');
        const userId = pesertaItem.dataset.id;
        
        // Remove from selected
        selectedPesertaEdit = selectedPesertaEdit.filter(p => p.id !== userId);
        updateSelectedPesertaListEdit();
        
        // Re-enable add button in search results
        const resultItem = document.querySelector(`#editSearchResults .search-result-item[data-id="${userId}"]`);
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
    
    function updateSelectedPesertaListEdit() {
        if (!editSelectedPesertaList) return;
        
        // Update count
        editPesertaCount.textContent = `${selectedPesertaEdit.length} peserta`;
        
        // Update hidden input
        editPesertaIdsInput.value = selectedPesertaEdit.map(p => p.id).join(',');
        
        // Update list display
        if (selectedPesertaEdit.length === 0) {
            editSelectedPesertaList.innerHTML = '<div class="no-participants">Belum ada peserta yang ditambahkan</div>';
            return;
        }
        
        editSelectedPesertaList.innerHTML = selectedPesertaEdit.map((peserta, index) => `
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
    
    // PERUBAHAN BESAR: Fungsi untuk membuka modal edit dengan field baru
    window.openEditModal = async function(notulenId) {
        try {
            // Tampilkan loading indicator di modal
            const modalBody = document.querySelector('#editNotulenModal .modal-body');
            const originalContent = modalBody.innerHTML;
            modalBody.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i> Memuat data notulen...
                </div>
            `;
            
            // Buka modal terlebih dahulu
            document.getElementById('editNotulenModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            const response = await fetch(`edit_notulen.php?id=${notulenId}`);
            const result = await response.json();
            
            // Restore modal content
            modalBody.innerHTML = originalContent;
            
            if (result.success) {
                const data = result.data;
                
                // Isi form dengan data notulen YANG BARU
                document.getElementById('editNotulenId').value = data.Id;
                document.getElementById('editJudul').value = data.judul;
                document.getElementById('editHari').value = data.hari;
                document.getElementById('editTanggal').value = data.tanggal;
                document.getElementById('editTempat').value = data.Tempat;
                document.getElementById('editNotulis').value = data.notulis;
                document.getElementById('editPenanggungJawab').value = data.penanggung_jawab;
                document.getElementById('editPembahasan').value = data.Pembahasan || '';
                document.getElementById('editHasilAkhir').value = data.Hasil_akhir || '';
                
                // Isi peserta
                selectedPesertaEdit = data.peserta_details.map(p => ({
                    id: p.user_id,
                    name: p.full_name,
                    nim: p.nim,
                    role: p.role
                }));
                updateSelectedPesertaListEdit();
                
                // Isi lampiran saat ini
                displayCurrentFiles(data.lampiran_files || []);
                
                // Nonaktifkan tombol add untuk peserta yang sudah dipilih
                updateSearchResultsEdit();
                
                // Reset search input
                document.getElementById('editPesertaSearch').value = '';
                
            } else {
                alert(result.error || 'Gagal memuat data notulen');
                closeEditModal();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat memuat data notulen');
            closeEditModal();
        }
    };
    
    function displayCurrentFiles(files) {
        const container = document.getElementById('currentFiles');
        if (!files || files.length === 0) {
            container.innerHTML = '<div class="no-attachment">Tidak ada lampiran</div>';
            return;
        }
        
        container.innerHTML = files.map(file => `
            <div class="file-item">
                <div class="file-info">
                    <i class="fas fa-file"></i>
                    <div class="file-name">${file.original_name}</div>
                </div>
                <a href="uploads/lampiran/${file.file_name}" target="_blank" class="btn btn-small btn-submit">
                    <i class="fas fa-download"></i> Unduh
                </a>
            </div>
        `).join('');
    }
    
    function updateSearchResultsEdit() {
        const resultItems = document.querySelectorAll('#editSearchResults .search-result-item');
        resultItems.forEach(item => {
            const userId = item.dataset.id;
            const addButton = item.querySelector('.result-add');
            const isSelected = selectedPesertaEdit.some(p => p.id == userId);
            
            if (isSelected) {
                addButton.disabled = true;
                addButton.innerHTML = '<i class="fas fa-check"></i>';
                addButton.style.background = '#95a5a6';
            } else {
                addButton.disabled = false;
                addButton.innerHTML = '<i class="fas fa-plus"></i>';
                addButton.style.background = '';
            }
        });
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
    
    // PERBAIKAN: Fungsi handleFormSubmit untuk notulen baru DENGAN FIELD BARU
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
        
        // Validasi peserta
        if (selectedPeserta.length === 0) {
            isValid = false;
            showNotification('Harap pilih minimal 1 peserta!', 'error');
        }
        
        // Validasi tambahan untuk field baru
        const tanggalInput = notulenForm.querySelector('input[name="tanggal"]');
        const hariSelect = notulenForm.querySelector('select[name="hari"]');
        const tempatInput = notulenForm.querySelector('input[name="tempat"]');
        const notulisInput = notulenForm.querySelector('input[name="notulis"]');
        
        if (!tanggalInput.value) {
            isValid = false;
            showNotification('Harap isi tanggal rapat!', 'error');
            tanggalInput.style.borderColor = '#e74c3c';
        }
        
        if (!hariSelect.value) {
            isValid = false;
            showNotification('Harap pilih hari rapat!', 'error');
            hariSelect.style.borderColor = '#e74c3c';
        }
        
        if (!tempatInput.value.trim()) {
            isValid = false;
            showNotification('Harap isi tempat rapat!', 'error');
            tempatInput.style.borderColor = '#e74c3c';
        }
        
        if (!notulisInput.value.trim()) {
            isValid = false;
            showNotification('Harap isi nama notulis!', 'error');
            notulisInput.style.borderColor = '#e74c3c';
        }
        
        if (!isValid) {
            event.preventDefault();
            showNotification('Harap isi semua field yang wajib diisi!', 'error');
        } else {
            // Reset flag saat form berhasil disubmit
            hasUnsavedChanges = false;
        }
    }
    
    // PERBAIKAN BESAR: Fungsi handleEditFormSubmit untuk edit notulen DENGAN FIELD BARU
    function handleEditFormSubmit(event) {
        event.preventDefault(); // Mencegah form submit default
        
        // Validate required fields
        const requiredFields = editNotulenForm.querySelectorAll('[required]');
        let isValid = true;
        
        // Reset error borders
        requiredFields.forEach(field => {
            field.style.borderColor = '';
        });
        
        // Check required fields
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
        
        // Validasi tambahan untuk field baru di form edit
        const editTanggalInput = editNotulenForm.querySelector('input[name="tanggal"]');
        const editHariSelect = editNotulenForm.querySelector('select[name="hari"]');
        const editTempatInput = editNotulenForm.querySelector('input[name="tempat"]');
        const editNotulisInput = editNotulenForm.querySelector('input[name="notulis"]');
        
        if (!editTanggalInput.value) {
            isValid = false;
            editTanggalInput.style.borderColor = '#e74c3c';
        }
        
        if (!editHariSelect.value) {
            isValid = false;
            editHariSelect.style.borderColor = '#e74c3c';
        }
        
        if (!editTempatInput.value.trim()) {
            isValid = false;
            editTempatInput.style.borderColor = '#e74c3c';
        }
        
        if (!editNotulisInput.value.trim()) {
            isValid = false;
            editNotulisInput.style.borderColor = '#e74c3c';
        }
        
        // Check if peserta is selected
        if (selectedPesertaEdit.length === 0) {
            isValid = false;
            showNotification('Pilih minimal 1 peserta!', 'error');
        }
        
        if (!isValid) {
            showNotification('Harap isi semua field yang wajib diisi!', 'error');
            return;
        }
        
        // Tampilkan loading indicator
        const submitBtn = editNotulenForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        submitBtn.disabled = true;
        
        // Submit form menggunakan FormData
        const formData = new FormData(editNotulenForm);
        formData.append('edit_notulen', '1');
        
        // Kirim data menggunakan fetch
        fetch('notulen_rapat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            console.log('Response dari server:', data);
            
            // Cek jika ada pesan sukses
            if (data.includes('Notulen berhasil diperbarui!') || data.includes('success')) {
                showNotification('Notulen berhasil diperbarui!', 'success');
                
                // Tutup modal setelah sukses
                setTimeout(() => {
                    closeEditModal();
                    // Reload halaman untuk melihat perubahan
                    window.location.reload();
                }, 1500);
            } else if (data.includes('error') || data.includes('Error') || data.includes('Gagal')) {
                // Tangani error
                showNotification('Terjadi kesalahan saat menyimpan!', 'error');
                
                // Coba ekstrak pesan error dari response
                const errorMatch = data.match(/<div class="alert alert-danger">.*?<i class="fas fa-exclamation-triangle"><\/i>\s*(.*?)<\/div>/s);
                if (errorMatch && errorMatch[1]) {
                    showNotification(errorMatch[1], 'error');
                }
            } else {
                // Jika tidak ada pesan khusus, anggap berhasil dan reload
                showNotification('Perubahan berhasil disimpan!', 'success');
                setTimeout(() => {
                    closeEditModal();
                    window.location.reload();
                }, 1500);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan saat menyimpan!', 'error');
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
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
    
    // Handle file preview untuk form create
    const fileInput = document.querySelector('input[name="lampiran[]"]');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                hasUnsavedChanges = true;
                console.log(`${files.length} file(s) selected`);
            }
        });
    }
    
    // Handle edit file input
    const editFileInput = editNotulenForm?.querySelector('input[name="lampiran[]"]');
    if (editFileInput) {
        editFileInput.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                console.log(`${files.length} file baru dipilih untuk lampiran`);
            }
        });
    }
    
    // Tambahkan fungsi untuk validasi peserta di form create
    function validatePeserta() {
        if (selectedPeserta.length === 0) {
            showNotification('Harap pilih minimal 1 peserta!', 'error');
            return false;
        }
        return true;
    }
    
    // Tambahkan validasi peserta sebelum submit form create
    if (notulenForm) {
        notulenForm.addEventListener('submit', function(e) {
            if (!validatePeserta()) {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // PERUBAHAN TAMBAHAN: Event listener untuk update hari berdasarkan tanggal
    const tanggalInput = notulenForm?.querySelector('input[name="tanggal"]');
    if (tanggalInput) {
        tanggalInput.addEventListener('change', function() {
            const date = new Date(this.value);
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const dayName = days[date.getDay()];
            
            const hariSelect = notulenForm.querySelector('select[name="hari"]');
            if (hariSelect) {
                // Cari opsi yang sesuai
                for (let i = 0; i < hariSelect.options.length; i++) {
                    if (hariSelect.options[i].value === dayName) {
                        hariSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        });
    }
    
    // Sama untuk form edit
    const editTanggalInput = editNotulenForm?.querySelector('input[name="tanggal"]');
    if (editTanggalInput) {
        editTanggalInput.addEventListener('change', function() {
            const date = new Date(this.value);
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const dayName = days[date.getDay()];
            
            const editHariSelect = editNotulenForm.querySelector('select[name="hari"]');
            if (editHariSelect) {
                // Cari opsi yang sesuai
                for (let i = 0; i < editHariSelect.options.length; i++) {
                    if (editHariSelect.options[i].value === dayName) {
                        editHariSelect.selectedIndex = i;
                        break;
                    }
                }
            }
        });
    }
});
