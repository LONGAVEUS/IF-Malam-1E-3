// notulen-rapat.js - VERSI BAHARU DENGAN FUNGSI EDIT YANG DIPERBAIKI
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
    
    // State untuk edit modal
    window.selectedPesertaEdit = [];
    
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
        
        // Close modal when clicking outside overlay
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
        
        // Escape key to close modals
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
        
        // Peserta search for create modal
        pesertaSearch?.addEventListener('input', handlePesertaSearch);
        pesertaSearch?.addEventListener('focus', showSearchResults);
        
        // Peserta selection for create modal
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
        });
        
        // Setup edit modal setelah DOM siap
        setTimeout(setupEditModal, 100);
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
    
    // ================== FUNGSI EDIT MODAL BAHARU ==================
    function setupEditModal() {
        const editForm = document.getElementById('editNotulenForm');
        const editPesertaSearch = document.getElementById('editPesertaSearch');
        const editSearchResults = document.getElementById('editSearchResults');
        const editSelectedPesertaList = document.getElementById('editSelectedPesertaList');
        const cancelEditFormBtn = document.getElementById('cancelEditForm');
        
        if (!editForm || !editPesertaSearch) return;
        
        // Event listeners untuk edit modal
        cancelEditFormBtn?.addEventListener('click', closeEditModal);
        
        // Peserta search untuk edit modal
        editPesertaSearch.addEventListener('input', handleEditPesertaSearch);
        editPesertaSearch.addEventListener('focus', showEditSearchResults);
        
        // Peserta selection untuk edit modal
        editSearchResults?.addEventListener('click', handleEditPesertaSelection);
        editSelectedPesertaList?.addEventListener('click', handleEditPesertaRemoval);
        
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
        
        // Tutup dropdown search edit ketika klik di luar
        document.addEventListener('click', function(event) {
            const editSearchContainer = document.querySelectorAll('.peserta-search-container')[1];
            if (editSearchContainer && !editSearchContainer.contains(event.target) && 
                event.target !== editPesertaSearch) {
                hideEditSearchResults();
            }
        });
    }
    
    // Fungsi openEditModal yang baru dan lebih baik
    window.openEditModal = async function(notulenId) {
    try {
        console.log('Membuka modal edit untuk notulen ID:', notulenId);
        
        // Buka modal
        const editModal = document.getElementById('editNotulenModal');
        editModal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Tampilkan loading
        const modalBody = document.querySelector('#editNotulenModal .modal-body');
        const originalContent = modalBody.innerHTML;
        modalBody.innerHTML = `
            <div class="loading">
                <i class="fas fa-spinner fa-spin"></i> Memuat data notulen...
            </div>
        `;
        
        const response = await fetch(`edit_notulen.php?id=${notulenId}`);
        const result = await response.json();
        
        console.log('Response dari server:', result);
        
        if (!result.success) {
            throw new Error(result.error || 'Gagal memuat data notulen');
        }
        
        // Restore content
        modalBody.innerHTML = originalContent;
        
        const data = result.data;
        
        // Tunggu DOM ready
        await new Promise(resolve => setTimeout(resolve, 50));
        
        // Set nilai ke form - DENGAN PENGECEKAN
        document.getElementById('editNotulenId').value = data.Id || '';
        document.getElementById('editJudul').value = data.judul || '';
        document.getElementById('editHari').value = data.hari || '';
        document.getElementById('editTanggal').value = data.tanggal || '';
        
        // KRUSIAL: Set jam dengan pengecekan elemen dan data
        const jamMulaiInput = document.getElementById('editJamMulai');
        const jamSelesaiInput = document.getElementById('editJamSelesai');
        
        if (jamMulaiInput && data.jam_mulai) {
            jamMulaiInput.value = data.jam_mulai;
            console.log('✓ Jam Mulai diset:', data.jam_mulai);
        } else {
            console.error('✗ Gagal set Jam Mulai. Input:', jamMulaiInput, 'Data:', data.jam_mulai);
        }
        
        if (jamSelesaiInput && data.jam_selesai) {
            jamSelesaiInput.value = data.jam_selesai;
            console.log('✓ Jam Selesai diset:', data.jam_selesai);
        } else {
            console.error('✗ Gagal set Jam Selesai. Input:', jamSelesaiInput, 'Data:', data.jam_selesai);
        }
        
        document.getElementById('editTempat').value = data.Tempat || '';
        document.getElementById('editNotulis').value = data.notulis || '';
        
        // KRUSIAL: Set jurusan dengan pengecekan
        const jurusanInput = document.getElementById('editJurusan');
        if (jurusanInput && data.jurusan) {
            jurusanInput.value = data.jurusan;
            console.log('✓ Jurusan diset:', data.jurusan);
        } else {
            console.error('✗ Gagal set Jurusan. Input:', jurusanInput, 'Data:', data.jurusan);
        }
        
        document.getElementById('editPenanggungJawab').value = data.penanggung_jawab || '';
        document.getElementById('editPembahasan').value = data.Pembahasan || '';
        document.getElementById('editHasilAkhir').value = data.Hasil_akhir || '';
        
        // Set peserta
        if (data.peserta_details && Array.isArray(data.peserta_details)) {
            window.selectedPesertaEdit = data.peserta_details.map(p => ({
                id: String(p.id || p.user_id),
                name: p.name || p.full_name,
                nim: p.nim,
                role: p.role
            }));
        } else {
            window.selectedPesertaEdit = [];
        }
        
        updateEditSelectedPesertaList();
        
        setTimeout(() => {
            updateEditSearchResults();
        }, 100);
        
        // Tampilkan lampiran
        if (data.lampiran_files && data.lampiran_files.length > 0) {
            displayCurrentFilesEdit(data.lampiran_files);
        } else {
            document.getElementById('currentFiles').innerHTML = 
                '<div class="no-attachment">Tidak ada lampiran</div>';
        }
        
        // Reset search
        document.getElementById('editPesertaSearch').value = '';
        
        // Verifikasi final
        setTimeout(() => {
            console.log('=== VERIFIKASI FORM ===');
            console.log('Jurusan:', document.getElementById('editJurusan').value);
            console.log('Jam Mulai:', document.getElementById('editJamMulai').value);
            console.log('Jam Selesai:', document.getElementById('editJamSelesai').value);
        }, 200);
        
    } catch (error) {
        console.error('Error:', error);
        alert('Terjadi kesalahan: ' + error.message);
        closeEditModal();
    }
};
    
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
                               user.role.toLowerCase().includes(searchTerm);
                
                item.style.display = matches ? 'flex' : 'none';
            }
        });
    }
    
    function showEditSearchResults() {
        const editSearchResults = document.getElementById('editSearchResults');
        if (editSearchResults) {
            editSearchResults.style.display = 'block';
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
        
        // Cek apakah peserta sudah dipilih
        if (!window.selectedPesertaEdit.some(p => p.id === userId)) {
            window.selectedPesertaEdit.push({
                id: userId,
                name: userName,
                nim: userNim,
                role: userRole
            });
            
            updateEditSelectedPesertaList();
        }
        
        addButton.disabled = true;
        addButton.innerHTML = '<i class="fas fa-check"></i>';
        addButton.style.background = '#95a5a6';
    }
    
    function handleEditPesertaRemoval(event) {
        const removeButton = event.target.closest('.peserta-remove');
        if (!removeButton) return;
        
        const pesertaItem = removeButton.closest('.peserta-item');
        const userId = pesertaItem.dataset.id;
        
        window.selectedPesertaEdit = window.selectedPesertaEdit.filter(p => p.id !== userId);
        updateEditSelectedPesertaList();
        
        const resultItem = document.querySelector(`#editSearchResults .search-result-item[data-id="${userId}"]`);
        if (resultItem) {
            const addButton = resultItem.querySelector('.result-add');
            addButton.disabled = false;
            addButton.innerHTML = '<i class="fas fa-plus"></i>';
            addButton.style.background = '';
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
        const resultItems = document.querySelectorAll('#editSearchResults .search-result-item');
        resultItems.forEach(item => {
            const userId = item.dataset.id;
            const addButton = item.querySelector('.result-add');
            const isSelected = window.selectedPesertaEdit.some(p => p.id == userId);
            
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
    
    function displayCurrentFilesEdit(files) {
        const container = document.getElementById('currentFiles');
        if (!files || files.length === 0) {
            container.innerHTML = '<div class="no-attachment">Tidak ada lampiran</div>';
            return;
        }
        
        container.innerHTML = files.map(file => `
            <div class="file-item">
                <div class="file-info">
                    <i class="fas fa-file"></i>
                    <div class="file-name">${file.original_name || file.file_name}</div>
                </div>
                <a href="uploads/lampiran/${file.file_name}" target="_blank" class="btn btn-small btn-submit">
                    <i class="fas fa-download"></i> Unduh
                </a>
            </div>
        `).join('');
    }
    
    function resetEditForm() {
        window.selectedPesertaEdit = [];
        
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
            showNotification('Harap pilih minimal 1 peserta!', 'error');
            document.querySelector('.peserta-search-container').classList.add('form-error');
        }
        
        if (!isValid) {
            event.preventDefault();
            showNotification('Harap isi semua field yang wajib diisi!', 'error');
        } else {
            hasUnsavedChanges = false;
        }
    }
    
    function handleEditFormSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        
        // Validasi semua field required
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            field.classList.remove('form-error');
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('form-error');
            }
        });
        
        if (!window.selectedPesertaEdit || window.selectedPesertaEdit.length === 0) {
            isValid = false;
            showNotification('Pilih minimal 1 peserta!', 'error');
            document.querySelectorAll('.peserta-search-container')[1]?.classList.add('form-error');
        }
        
        if (!isValid) {
            showNotification('Harap isi semua field yang wajib diisi!', 'error');
            return;
        }
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        submitBtn.disabled = true;
        
        const formData = new FormData(form);
        
        fetch('notulen_rapat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            console.log('Response dari server:', data);
            
            if (data.includes('Notulen berhasil diperbarui!') || data.includes('success') || 
                data.includes('berhasil')) {
                showNotification('Notulen berhasil diperbarui!', 'success');
                
                setTimeout(() => {
                    closeEditModal();
                    window.location.reload();
                }, 1500);
            } else {
                // Coba parsing error message
                const errorMatch = data.match(/class="alert alert-danger">.*?<i class="fas fa-exclamation-triangle"><\/i>\s*(.*?)<\/div>/s);
                if (errorMatch && errorMatch[1]) {
                    showNotification(errorMatch[1], 'error');
                } else {
                    showNotification('Terjadi kesalahan saat menyimpan!', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Terjadi kesalahan koneksi!', 'error');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
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
    
    function showNotification(message, type = 'info') {
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 5000);
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
    
    // Validasi peserta sebelum submit
    function validatePeserta() {
        if (selectedPeserta.length === 0) {
            showNotification('Harap pilih minimal 1 peserta!', 'error');
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
    
    // File input change handlers
    const fileInput = document.querySelector('#notulenModal input[name="lampiran[]"]');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const files = this.files;
            if (files.length > 0) {
                hasUnsavedChanges = true;
            }
        });
    }
    
    const editFileInput = document.querySelector('#editNotulenModal input[name="lampiran[]"]');
    if (editFileInput) {
        editFileInput.addEventListener('change', function() {
            console.log(`${this.files.length} file baru dipilih untuk lampiran`);
        });
    }
});