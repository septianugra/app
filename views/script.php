<script>
// =================================================================
// 0. PHP API WRAPPER (DENGAN PENANGKAP ERROR ANTI-MACET)
// =================================================================
const google = {
    script: {
        get run() {
            return {
                successHandler: null,
                failureHandler: null,
                withSuccessHandler: function(cb) { this.successHandler = cb; return this; },
                withFailureHandler: function(cb) { this.failureHandler = cb; return this; },
                
                _send: function(action, payload = null, isGet = false) {
                    let url = 'api.php';
                    let options = { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/json' },
                        cache: 'no-store' 
                    };
                    
                    if (isGet) {
                        url += '?action=' + action;
                        if (payload) url += '&sheet=' + payload;
                        url += '&_t=' + new Date().getTime(); 
                        options.method = 'GET';
                    } else {
                        options.body = JSON.stringify({ action: action, data: payload });
                    }

                    fetch(url, options)
                        .then(async res => {
                            const text = await res.text();
                            if (!text || text.trim() === '') return [];
                            try { return JSON.parse(text); } 
                            catch(e) { console.warn("Bukan JSON:", text); return []; }
                        })
                        .then(data => { if(this.successHandler) this.successHandler(data); })
                        .catch(err => { 
                            console.error("API Fetch Error:", err); 
                            if(this.failureHandler) this.failureHandler(err); 
                            else if(this.successHandler) this.successHandler([]); 
                        });
                },
                getData: function(sheet) { this._send('getData', sheet, true); },
                getDashboard: function() { this._send('getDashboard', null, true); },
                getPengaturan: function() { this._send('getPengaturan', null, true); },
                tambahDataKeSheet: function(sheet, data) { this._send('tambahDataKeSheet', { sheet, data }); },
                updateDataSheet: function(sheet, id, data) { this._send('updateDataSheet', { sheet, id, data }); },
                hapusDataSheet: function(sheet, id) { this._send('hapusDataSheet', { sheet, id }); },
                simpanPengaturan: function(data) { this._send('simpanPengaturan', { data }); },
                simpanBarangMasukMulti: function(data) { this._send('simpanBarangMasukMulti', data); },
                simpanBarangKeluarMulti: function(data) { this._send('simpanBarangKeluarMulti', data); }
            };
        }
    }
};

/* ============================================================
   1. GLOBAL UTILITIES (KOMPRESOR GAMBAR, TOAST, LOADER)
   ============================================================ */

// Fitur Kompresi Gambar agar server tidak crash saat upload nota
function compressImage(file, callback) {
    if(!file.type.startsWith('image/')) {
        // Jika file PDF, langsung proses saja
        const reader = new FileReader();
        reader.onload = e => callback(e.target.result.split(',')[1]);
        reader.readAsDataURL(file);
        return;
    }
    const reader = new FileReader();
    reader.onload = function(event) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const MAX_WIDTH = 800; const MAX_HEIGHT = 800;
            let width = img.width; let height = img.height;

            if (width > height) { if (width > MAX_WIDTH) { height *= MAX_WIDTH / width; width = MAX_WIDTH; } } 
            else { if (height > MAX_HEIGHT) { width *= MAX_HEIGHT / height; height = MAX_HEIGHT; } }
            
            canvas.width = width; canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);
            
            // Kompresi menjadi JPEG kualitas 70% (Sangat ringan untuk server)
            const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
            callback(dataUrl.split(',')[1]);
        };
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
}

function showToast(message, type = "success", duration = 3000) {
    const toastEl = document.getElementById('liveToast'); 
    if (!toastEl) return;
    document.getElementById('toastMsg').innerText = message;
    const icon = document.getElementById('toastIcon');
    icon.className = type === 'success' ? 'fa-solid fa-circle-check fa-2xl me-3 text-success' : (type === 'warning' ? 'fa-solid fa-triangle-exclamation fa-2xl me-3 text-warning' : 'fa-solid fa-circle-xmark fa-2xl me-3 text-danger');
    new bootstrap.Toast(toastEl, { delay: duration }).show();
}

function showProcessing(pesan = "Mohon tunggu sebentar...") {
    Swal.fire({ 
        title: 'Sedang Memproses', 
        html: pesan, 
        allowOutsideClick: false, 
        allowEscapeKey: false, 
        showConfirmButton: false, 
        didOpen: () => { Swal.showLoading(); } 
    });
}

function toggleLoader(show) {
    const loader = document.getElementById('loader');
    if (loader) loader.classList.toggle('d-none', !show);
}

let timerPencarian = {};
function filterTabelGlobal(inputId, tbodyId) {
    clearTimeout(timerPencarian[inputId]);
    timerPencarian[inputId] = setTimeout(() => {
        const input = document.getElementById(inputId).value.toLowerCase();
        const rows = document.querySelectorAll('#' + tbodyId + ' tr');
        let hasResult = false;
        rows.forEach(row => {
            const text = row.textContent.toLowerCase(); 
            if(text.includes('belum ada') || text.includes('memuat data') || row.id === 'notFound-' + tbodyId) return;
            if (text.includes(input)) { row.style.display = ""; hasResult = true; } else { row.style.display = "none"; }
        });
        cekTabelKosong(tbodyId, hasResult, input);
    }, 300);
}

function resetFilter(searchId, tbodyId) {
    document.getElementById(searchId).value = "";
    document.querySelectorAll('#' + tbodyId + ' tr').forEach(row => row.style.display = "");
    cekTabelKosong(tbodyId, true, "");
}

function cekTabelKosong(tbodyId, hasResult, keyword) {
    const tbody = document.getElementById(tbodyId);
    let notFoundRow = document.getElementById('notFound-' + tbodyId);
    if (!hasResult && keyword.trim() !== "") {
        if (!notFoundRow) {
            notFoundRow = document.createElement("tr"); notFoundRow.id = 'notFound-' + tbodyId;
            notFoundRow.innerHTML = '<td colspan="10" class="text-center py-5 text-danger bg-danger bg-opacity-10 rounded-3"><i class="fa-solid fa-search-minus fa-2x mb-2 d-block opacity-50"></i><span class="fw-bold">Pencarian tidak menemukan hasil.</span><br><small class="text-muted">Silakan coba kata kunci lain.</small></td>';
            tbody.appendChild(notFoundRow);
        } else { notFoundRow.style.display = ""; }
    } else if (notFoundRow) { notFoundRow.style.display = "none"; }
}

/* ============================================================
   2. VARIABEL CACHE & NAVIGASI
   ============================================================ */
let cacheBarangMasuk = null; 
let cacheBarangKeluar = null; 
window.daftarProdukLengkap = null; 
window.daftarKonsumenLengkap = null; 
window.dataSupplierMentah = null; 
window.dataKategoriMentah = null; 
window.dataSatuanMentah = null;

function refreshData() {
    showProcessing("Menyinkronkan ulang semua data dari server...");
    cacheBarangMasuk = null; 
    cacheBarangKeluar = null;
    window.daftarProdukLengkap = null; 
    window.dataKategoriMentah = null; 
    window.dataSupplierMentah = null; 
    window.daftarKonsumenLengkap = null; 
    window.dataSatuanMentah = null;
    
    setTimeout(() => {
        Swal.close();
        const activeNav = document.querySelector('.sidebar .nav-link.active');
        if(activeNav) activeNav.click(); 
        showToast("Sinkronisasi Selesai", "success");
    }, 800);
}

function showPage(pageId, element) {
    const idMap = { 
        'dashboard': 'dashboard', 'transaksi': 'transaksi', 'produk': 'produk', 
        'kategori': 'kategori', 'satuan': 'satuan', 'supplier': 'supplier', 
        'konsumen': 'konsumen', 'masuk': 'barang_masuk', 'keluar': 'barang_keluar', 
        'laporan': 'laporan', 'pengaturan': 'pengaturan' 
    };
    const actualId = idMap[pageId] || pageId;
    
    document.querySelectorAll(".page-content").forEach(p => { p.classList.remove("active"); });
    const targetPage = document.getElementById(actualId) || document.getElementById(actualId.charAt(0).toUpperCase() + actualId.slice(1)) || document.getElementById(pageId);
    if (targetPage) targetPage.classList.add("active");
    
    document.querySelectorAll(".sidebar .nav-link").forEach(link => link.classList.remove("active"));
    if (element) { 
        element.classList.add("active"); 
    } else { 
        const fallbackNav = document.querySelector('.sidebar .nav-link[onclick*="' + pageId + '"]'); 
        if (fallbackNav) fallbackNav.classList.add("active"); 
    }

    const titles = { 
        'dashboard': 'Dashboard', 'transaksi': 'Meja Kasir', 'produk': 'Master Produk', 
        'kategori': 'Kategori', 'satuan': 'Master Satuan', 'supplier': 'Master Supplier', 
        'konsumen': 'Data Konsumen', 'masuk': 'Riwayat Restock', 'keluar': 'Riwayat Penjualan', 
        'laporan': 'Laporan Laba', 'pengaturan': 'Pengaturan Sistem' 
    };
    if (document.getElementById("pageTitle")) document.getElementById("pageTitle").innerText = titles[pageId] || pageId.toUpperCase();

    runAutoLoad(pageId);
}

function runAutoLoad(pageId) {
    try {
        if (pageId === 'dashboard') loadDashboard();
        else if (pageId === 'transaksi') initTransaksi();
        else if (pageId === 'produk') loadProduk();
        else if (pageId === 'masuk') loadBarangMasuk();
        else if (pageId === 'keluar') loadBarangKeluar();
        else if (pageId === 'supplier') loadSupplier();
        else if (pageId === 'konsumen') loadKonsumen();
        else if (pageId === 'kategori') loadKategori();
        else if (pageId === 'satuan') loadSatuan();
        else if (pageId === 'laporan') loadLaporan();
        else if (pageId === 'pengaturan') loadPengaturan();
    } catch (e) { console.warn("AutoLoad Error: ", e); }
}

/* ============================================================
   3. DASHBOARD
   ============================================================ */
function loadDashboard(silent = false) {
    if(!silent) toggleLoader(true);
    google.script.run
        .withFailureHandler(e => { toggleLoader(false); console.error(e); })
        .withSuccessHandler(data => {
            const formatAutoShrink = (angka) => { 
                let teks = "Rp " + Number(angka || 0).toLocaleString('id-ID'); 
                return `<span style="letter-spacing: -0.5px; white-space: nowrap;">${teks}</span>`; 
            };
            
            if(document.getElementById('dashProduk')) document.getElementById('dashProduk').innerText = data.produk || 0;
            if(document.getElementById('dashModal')) document.getElementById('dashModal').innerHTML = formatAutoShrink(data.modal);
            if(document.getElementById('dashOmset')) document.getElementById('dashOmset').innerHTML = formatAutoShrink(data.omset);
            if(document.getElementById('dashProfit')) document.getElementById('dashProfit').innerHTML = formatAutoShrink(data.profit);
            
            let html = "";
            if (!data.riwayat || data.riwayat.length === 0) { 
                html = `<tr><td colspan="7" class="text-center py-5 text-muted"><div class="bg-light rounded-circle d-inline-flex justify-content-center align-items-center mb-3" style="width:60px; height:60px;"><i class="fa-solid fa-folder-open fs-3 text-secondary"></i></div><br><span class="fw-bold">Belum ada aktivitas</span><br><small>Transaksi Anda akan muncul di sini.</small></td></tr>`; 
            } else {
                data.riwayat.forEach(r => {
                    const isMasuk = r.jenis === "Masuk"; 
                    const badgeColor = isMasuk ? "success" : "warning"; 
                    const iconJenis = isMasuk ? "fa-arrow-down-to-line" : "fa-arrow-up-from-bracket";
                    html += `<tr><td class="ps-4 py-3 text-muted fw-500 text-nowrap" style="font-size:12px;">${r.tanggal}<br><small style="font-size:10px;">${r.idTrx}</small></td><td class="py-3"><span class="badge bg-${badgeColor} bg-opacity-10 text-${badgeColor} px-2 py-1 rounded-pill" style="font-size:10px;"><i class="fa-solid ${iconJenis} me-1"></i> ${isMasuk ? 'Masuk' : 'Keluar'}</span></td><td class="py-3"><div class="fw-bold text-dark mb-1" style="font-size: 13px;">${r.namaBarang}</div><span class="badge bg-secondary bg-opacity-10 text-secondary border-0" style="font-size:10px;">${r.kategori || '-'}</span></td><td class="py-3 text-muted" style="font-size: 12px;">${r.pihak || '-'}</td><td class="text-center fw-bold text-${badgeColor} fs-6 py-3">${isMasuk ? '+' : '-'}${r.qty} <span class="text-muted fw-normal" style="font-size:10px;">${r.satuan || ''}</span></td><td class="text-end fw-bold text-dark py-3 text-nowrap" style="font-size: 13px;">Rp ${Number(r.total || 0).toLocaleString('id-ID')}</td><td class="pe-4 text-center py-3"><button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm" style="width: 32px; height: 32px;" onclick="showPage('${isMasuk ? 'masuk' : 'keluar'}')" title="Lihat Riwayat Penuh"><i class="fa-solid fa-chevron-right"></i></button></td></tr>`;
                });
            }
            if(document.getElementById('dataRiwayatDashboard')) document.getElementById('dataRiwayatDashboard').innerHTML = html;
            if(!silent) toggleLoader(false);
        }).getDashboard();
}

/* ============================================================
   4. MEJA KASIR (TRANSAKSI)
   ============================================================ */
let keranjangTrx = []; 

document.addEventListener('click', function(e) {
    // Sembunyikan pop-up Produk
    const hasilBoxProd = document.getElementById('hasilPencarianCustom'); 
    const inputBoxProd = document.getElementById('trxProdukCustom');
    if (hasilBoxProd && inputBoxProd && !inputBoxProd.contains(e.target) && !hasilBoxProd.contains(e.target)) { 
        hasilBoxProd.classList.add('d-none'); 
    }
    
    // Sembunyikan pop-up Konsumen/Supplier
    const hasilBoxPihak = document.getElementById('hasilPencarianPihak'); 
    const inputBoxPihak = document.getElementById('trxPihakKedua');
    if (hasilBoxPihak && inputBoxPihak && !inputBoxPihak.contains(e.target) && !hasilBoxPihak.contains(e.target)) { 
        hasilBoxPihak.classList.add('d-none'); 
    }
});

function initTransaksi() {
    const form = document.getElementById("formTransaksi"); 
    if(form) form.reset();
    
    // Set tanggal otomatis ke hari ini
    if(document.getElementById("trxTanggal")) {
        document.getElementById("trxTanggal").value = new Date().toISOString().split('T')[0];
    }
    
    keranjangTrx = []; 
    toggleLoader(true);
    
    // Mengambil semua data master secara bersamaan (Parallel Loading)
    Promise.all([
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Produk")),
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Konsumen")),
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Supplier"))
    ]).then(([dProduk, dKonsumen, dSupplier]) => {
        window.daftarProdukLengkap = dProduk; 
        window.daftarKonsumenLengkap = dKonsumen; 
        window.dataSupplierMentah = dSupplier;
        
        switchModeTrx(); 
        toggleLoader(false);

        // ============================================================
        // FITUR PENYEMPURNA: AUTO-FOCUS
        // ============================================================
        // Memberi jeda sedikit agar UI siap, lalu arahkan kursor ke kolom cari.
        // Sangat berguna jika Anda menggunakan Barcode Scanner.
        setTimeout(() => {
            const inputCari = document.getElementById('trxProdukCustom');
            if(inputCari) {
                inputCari.focus();
                // Memberi efek border biru sesaat untuk menandakan siap ketik
                inputCari.classList.add('border-primary');
            }
        }, 600);
    });
}


function switchModeTrx() {
    keranjangTrx = []; renderKeranjang();
    const modeObj = document.querySelector('input[name="modeTrx"]:checked'); if(!modeObj) return;
    const isKeluar = modeObj.value === 'keluar'; const areaPihak = document.getElementById("areaPihakKedua");
    
    if(areaPihak) {
        let labelText = isKeluar ? 'CARI KONSUMEN' : 'CARI SUPPLIER';
        let placeholderText = isKeluar ? 'Ketik nama konsumen...' : 'Ketik nama supplier...';
        let borderColor = isKeluar ? 'border-warning' : 'border-success';

        // Desain Custom Dropdown DITAMBAH Tombol Hapus (X)
        areaPihak.innerHTML = `
            <label class="form-label fw-bold text-muted mb-1" style="font-size:11px;">${labelText}</label>
            <div class="position-relative">
                <input class="form-control form-control-sm ${borderColor} fw-500 pe-4" 
                       id="trxPihakKedua" 
                       placeholder="${placeholderText}" 
                       autocomplete="off" 
                       onkeyup="cariPihakCustom()" 
                       onfocus="cariPihakCustom()"
                       required>
                
                <span class="position-absolute top-50 end-0 translate-middle-y pe-2 text-muted" 
                      onclick="resetPilihanPihak()" 
                      title="Hapus Pilihan"
                      style="cursor: pointer; z-index: 10;">
                    <i class="fa-solid fa-circle-xmark"></i>
                </span>

                <div id="hasilPencarianPihak" class="list-group position-absolute w-100 shadow-lg d-none" style="z-index: 1050; max-height: 250px; overflow-y: auto; top: 100%; border-radius: 0.5rem;"></div>
            </div>
        `;
    }
    
    if(document.getElementById("areaUploadNota")) document.getElementById("areaUploadNota").className = isKeluar ? "mb-3 d-none" : "mb-3";
    if(document.getElementById("labelTotal")) document.getElementById("labelTotal").innerText = isKeluar ? "TOTAL PENJUALAN" : "TOTAL BIAYA RESTOCK";
    const btnSubmit = document.getElementById("btnSubmitTrx");
    if(btnSubmit) { btnSubmit.className = isKeluar ? "btn btn-sm btn-primary px-4 py-1 fw-bold shadow-sm text-white" : "btn btn-sm btn-success px-4 py-1 fw-bold shadow-sm text-white"; btnSubmit.innerHTML = isKeluar ? '<i class="fa-solid fa-check me-1"></i> Proses Penjualan' : '<i class="fa-solid fa-boxes-packing me-1"></i> Simpan Restock'; }
}

function cariPihakCustom() {
    const inputEl = document.getElementById('trxPihakKedua');
    const hasilBox = document.getElementById('hasilPencarianPihak');
    if (!inputEl || !hasilBox) return;

    const inputVal = String(inputEl.value).trim().toLowerCase();
    const isKeluar = document.querySelector('input[name="modeTrx"]:checked').value === 'keluar';
    const dataList = isKeluar ? window.daftarKonsumenLengkap : window.dataSupplierMentah;

    if (!dataList) return;

    // Saring data berdasarkan nama atau ID
    const filtered = dataList.filter(item => {
        const nama = String(isKeluar ? item.Nama_Konsumen : item.Nama_Supplier).toLowerCase();
        const id = String(isKeluar ? item.ID_Konsumen : item.ID_Supplier).toLowerCase();
        return nama.includes(inputVal) || id.includes(inputVal);
    });

    if (filtered.length === 0) {
        hasilBox.innerHTML = `<button type="button" class="list-group-item list-group-item-action text-danger fw-bold" style="font-size:12px;" disabled><i class="fa-solid fa-triangle-exclamation me-1"></i> Tidak ditemukan</button>`;
        hasilBox.classList.remove('d-none');
        return;
    }

    let htmlList = '';
    filtered.slice(0, 15).forEach(item => {
        const nama = isKeluar ? item.Nama_Konsumen : item.Nama_Supplier;
        const id = isKeluar ? item.ID_Konsumen : item.ID_Supplier;
        const ikon = isKeluar ? 'fa-user-tag' : 'fa-truck-fast';

        htmlList += `
            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 border-bottom" onclick="pilihPihakOtomatis('${nama}', '${id}')">
                <div>
                    <span class="fw-bold d-block text-dark" style="font-size: 13px;">${nama}</span>
                    <small class="text-muted" style="font-size: 10px;"><i class="fa-solid ${ikon} me-1"></i>${id}</small>
                </div>
            </button>
        `;
    });
    
    hasilBox.innerHTML = htmlList;
    hasilBox.classList.remove('d-none');
}

function pilihPihakOtomatis(nama, id) {
    const inputEl = document.getElementById('trxPihakKedua');
    if (inputEl) {
        // Memasukkan nama dan ID ke kolom dengan format yang dikenali oleh sistem simpan
        inputEl.value = `${nama} [${id}]`; 
        inputEl.classList.remove('border-danger'); // Hilangkan efek error jika ada
    }
    // Sembunyikan kotak pencarian
    document.getElementById('hasilPencarianPihak').classList.add('d-none');
    
    // Opsional: Langsung arahkan kursor ke pencarian produk setelah pilih konsumen
    const inputCariProd = document.getElementById('trxProdukCustom');
    if(inputCariProd) inputCariProd.focus();
}

// FUNGSI BARU: Untuk mengosongkan kolom konsumen/supplier dengan 1 klik
function resetPilihanPihak() {
    const inputEl = document.getElementById('trxPihakKedua');
    if(inputEl) {
        inputEl.value = '';        // Kosongkan isi kolom
        inputEl.focus();           // Arahkan kursor kembali ke kolom ini
        cariPihakCustom();         // Munculkan kembali daftar pilihannya
    }
}

// ==========================================================
// MESIN PENCARI PRODUK (TAMPILAN MELAYANG MODERN)
// ==========================================================
function cariProdukCustom() {
    const inputEl = document.getElementById('trxProdukCustom');
    const hasilBox = document.getElementById('hasilPencarianCustom');
    
    if (!inputEl || !hasilBox || !window.daftarProdukLengkap) return;

    // Kunci posisi box agar melayang cantik di bawah input
    inputEl.parentElement.classList.add('position-relative'); 
    hasilBox.className = "list-group position-absolute w-100 shadow-lg"; 
    hasilBox.style.cssText = "z-index: 1050; max-height: 250px; overflow-y: auto; top: 100%; border-radius: 0.5rem; left: 0;";

    const inputVal = String(inputEl.value).trim().toLowerCase();
    const modeElement = document.querySelector('input[name="modeTrx"]:checked');
    if (!modeElement) return;
    
    const isKeluar = modeElement.value === 'keluar';
    let idSupplierTerpilih = null; 
    let peringatanSupplier = false;

    // Cek apakah Supplier sudah dipilih (Khusus Restock Masuk)
    if (!isKeluar) {
        const inputPihak = document.getElementById("trxPihakKedua");
        if(inputPihak) {
            const matchPihak = inputPihak.value.match(/\[(.*?)\]/);
            if (matchPihak) { idSupplierTerpilih = matchPihak[1]; } else { peringatanSupplier = true; }
        }
    }

    let filterProduk = [];
    if (!peringatanSupplier) {
        filterProduk = window.daftarProdukLengkap.filter(p => {
            const stokCukup = isKeluar ? Number(p.Stok_Saat_Ini || 0) > 0 : true;
            const matchSupplier = isKeluar ? true : (String(p.ID_Supplier).trim() === String(idSupplierTerpilih).trim());
            
            if (inputVal === "") return stokCukup && matchSupplier;
            
            const nama = String(p.Nama_Barang || "").toLowerCase(); 
            const id = String(p.ID_Produk || "").toLowerCase();
            return (nama.includes(inputVal) || id.includes(inputVal)) && stokCukup && matchSupplier;
        });
    }

    // Jika tidak ada hasil / stok kosong
    if (filterProduk.length === 0) {
        let msg = "Produk tidak ditemukan / Stok kosong";
        if (peringatanSupplier) msg = "Pilih Supplier di atas terlebih dahulu!"; 
        else if (!isKeluar) msg = "Tidak ada produk dari Supplier ini.";
        
        hasilBox.innerHTML = `<button type="button" class="list-group-item list-group-item-action text-danger fw-bold py-2" style="font-size:12px;" disabled><i class="fa-solid fa-triangle-exclamation me-2"></i> ${msg}</button>`;
        hasilBox.classList.remove('d-none'); 
        return;
    }

    // Tampilkan daftar produk dengan desain elegan (mirip Konsumen)
    let htmlList = '';
    filterProduk.slice(0, 15).forEach(p => { 
        htmlList += `
        <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 border-bottom" onclick="tambahKeKeranjangOtomatis('${p.ID_Produk}')">
            <div>
                <span class="fw-bold d-block text-primary" style="font-size: 13px;">${p.Nama_Barang}</span>
                <small class="text-muted" style="font-size: 10px;"><i class="fa-solid fa-box me-1"></i>${p.ID_Produk}</small>
            </div>
            <span class="badge bg-secondary bg-opacity-10 text-secondary border" style="font-size: 10px;">Sisa: <strong class="text-dark">${p.Stok_Saat_Ini || 0} ${p.Satuan || ''}</strong></span>
        </button>`; 
    });
    
    hasilBox.innerHTML = htmlList; 
    hasilBox.classList.remove('d-none');
}

function tambahKeKeranjangOtomatis(idProduk) {
    const p = window.daftarProdukLengkap.find(x => x.ID_Produk === idProduk); 
    if (!p) return;
    
    const mode = document.querySelector('input[name="modeTrx"]:checked').value;
    const indexAda = keranjangTrx.findIndex(item => item.idProduk === p.ID_Produk);
    let qtySekarang = indexAda !== -1 ? keranjangTrx[indexAda].qty : 0; 
    let qtyBaru = qtySekarang + 1;

    if (mode === 'keluar' && qtyBaru > Number(p.Stok_Saat_Ini)) { 
        showToast(`Stok tidak cukup! Sisa stok ${p.Nama_Barang}: ${p.Stok_Saat_Ini} ${p.Satuan || ''}`, "danger"); 
    } else {
        const harga = mode === 'keluar' ? Number(p.Harga_Jual) : Number(p.Harga_Beli);
        if (indexAda !== -1) { 
            keranjangTrx[indexAda].qty = qtyBaru; 
            keranjangTrx[indexAda].subtotal = qtyBaru * harga; 
        } else { 
            keranjangTrx.push({ idProduk: p.ID_Produk, nama: p.Nama_Barang, harga: harga, qty: 1, subtotal: harga, satuan: p.Satuan || '-' }); 
        }
        renderKeranjang();
    }
    const inputEl = document.getElementById("trxProdukCustom"); 
    if(inputEl) { inputEl.value = ""; inputEl.focus(); }
    document.getElementById('hasilPencarianCustom').classList.add('d-none');
}

function ubahQtyKeranjang(index, newVal) {
    let newQty = Number(newVal); 
    if (newQty <= 0) { hapusDariKeranjang(index); return; }
    
    const mode = document.querySelector('input[name="modeTrx"]:checked').value;
    const item = keranjangTrx[index]; 
    const p = window.daftarProdukLengkap.find(x => x.ID_Produk === item.idProduk);

    if (mode === 'keluar' && newQty > Number(p.Stok_Saat_Ini)) { 
        showToast(`Stok ${item.nama} tersisa ${p.Stok_Saat_Ini} ${item.satuan}!`, "danger"); 
        renderKeranjang(); 
        return; 
    }
    item.qty = newQty; 
    item.subtotal = newQty * item.harga; 
    renderKeranjang();
}

function hapusDariKeranjang(index) { 
    keranjangTrx.splice(index, 1); 
    renderKeranjang(); 
}

function renderKeranjang() {
    const tbody = document.getElementById("tabelKeranjang"); 
    const prevList = document.getElementById("prevListKeranjang"); 
    const btnSubmit = document.getElementById("btnSubmitTrx");
    let htmlTable = ""; 
    let htmlPrev = ""; 
    let grandTotal = 0;
    
    if (keranjangTrx.length === 0) {
        if(tbody) tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-muted" style="font-size:12px;">Keranjang kosong. Tambahkan produk.</td></tr>`;
        if(prevList) prevList.innerHTML = `<div class="text-center text-white-50 mt-4" style="font-size:11px;">Belum ada item</div>`;
        if(document.getElementById("prevTotalHarga")) document.getElementById("prevTotalHarga").innerText = "Rp 0";
        if(btnSubmit) btnSubmit.disabled = true; return;
    }
    
    keranjangTrx.forEach((item, index) => {
        grandTotal += item.subtotal; 
        let unitText = item.satuan !== '-' ? item.satuan : '';
        htmlTable += `<tr><td class="ps-3 align-middle py-1"><span class="fw-bold d-block text-dark" style="font-size:12px;">${item.nama}</span><small class="text-muted" style="font-size:10px;">${item.idProduk}</small></td><td class="text-center text-nowrap align-middle py-1" style="font-size:12px;">Rp ${item.harga.toLocaleString('id-ID')}</td><td class="text-center align-middle py-1" style="width: 100px;"><div class="input-group input-group-sm mx-auto shadow-sm" style="flex-wrap: nowrap;"><input type="number" class="form-control text-center fw-bold text-primary px-1 py-0 border-secondary border-opacity-25" value="${item.qty}" min="1" onchange="ubahQtyKeranjang(${index}, this.value)" style="font-size:12px; height:24px;"><span class="input-group-text bg-light text-muted px-1 py-0 border-secondary border-opacity-25" style="font-size:10px; height:24px;">${unitText}</span></div></td><td class="text-end fw-bold text-nowrap align-middle py-1" style="font-size:12px;">Rp ${item.subtotal.toLocaleString('id-ID')}</td><td class="text-center pe-3 align-middle py-1"><button type="button" class="btn btn-sm btn-light text-danger shadow-sm py-0 px-2" style="height:24px;" onclick="hapusDariKeranjang(${index})"><i class="fa-solid fa-trash" style="font-size:11px;"></i></button></td></tr>`;
        htmlPrev += `<div class="mb-2 border-bottom border-secondary border-opacity-50 pb-1"><div class="d-flex justify-content-between text-light"><span class="fw-bold text-truncate" style="max-width: 60%; font-size:12px;">${item.nama}</span><span class="text-nowrap" style="font-size:12px;">Rp ${item.subtotal.toLocaleString('id-ID')}</span></div><div class="text-white-50" style="font-size:10px;">${item.qty} ${unitText} x Rp ${item.harga.toLocaleString('id-ID')}</div></div>`;
    });
    
    if(tbody) tbody.innerHTML = htmlTable; 
    if(prevList) prevList.innerHTML = htmlPrev;
    if(document.getElementById("prevTotalHarga")) document.getElementById("prevTotalHarga").innerText = "Rp " + grandTotal.toLocaleString('id-ID');
    if(btnSubmit) btnSubmit.disabled = false;
}

function prosesTransaksiTerpusat(e) {
    e.preventDefault();
    if (keranjangTrx.length === 0) { showToast("Keranjang masih kosong!", "warning"); return; }
    
    const isKeluar = document.querySelector('input[name="modeTrx"]:checked').value === 'keluar';
    const inputPihak = document.getElementById("trxPihakKedua").value; 
    const matchPihak = inputPihak.match(/\[(.*?)\]/);
    if (!matchPihak) { showToast("Pilih Nama dari daftar yang tersedia!", "warning"); return; }
    
    const pihakKedua = matchPihak[1];
    const dataKirim = { tanggal: document.getElementById("trxTanggal").value, cart: keranjangTrx };
    
    if(isKeluar) { 
        dataKirim.idKonsumen = pihakKedua; 
    } else { 
        dataKirim.idSupplier = pihakKedua; 
    }

    showProcessing(isKeluar ? "Menyimpan Penjualan & Membuat Dokumen..." : "Menyimpan Transaksi & Mengunggah Nota...");

    const handleRes = (res) => {
        if (res.status === "success") {
            google.script.run.withSuccessHandler(data => { window.daftarProdukLengkap = data; }).getData("Produk");
            if (isKeluar && res.urls) {
                Swal.close(); 
                const btnWA = document.getElementById("btnKirimWA"); 
                const btnCetakInv = document.getElementById("btnCetakInvoice"); 
                const btnCetakSJ = document.getElementById("btnCetakSJ");
                
                if (btnCetakInv) btnCetakInv.href = res.urls.urlInv; 
                if (btnCetakSJ) btnCetakSJ.href = res.urls.urlSJ;
                
                const k = window.daftarKonsumenLengkap.find(x => x.ID_Konsumen === pihakKedua);
                if (btnWA && k && k.Kontak && k.Kontak.toString().trim() !== "-" && k.Kontak.toString().trim() !== "") {
                    let noWA = k.Kontak.toString().replace(/\D/g, ''); 
                    if (noWA.startsWith('0')) { noWA = '62' + noWA.substring(1); }
                    
                    let totalTagihan = keranjangTrx.reduce((sum, item) => sum + item.subtotal, 0); 
                    let listBarang = keranjangTrx.map(item => `- ${item.nama} (${item.qty} ${item.satuan !== '-' ? item.satuan : ''})`).join('\n');
                    let pesanWA = `Halo *${k.Nama_Konsumen}*,\n\nTerima kasih telah berbelanja. Berikut rincian pesanan Anda:\n\n${listBarang}\n\n*Total Tagihan:* Rp ${totalTagihan.toLocaleString('id-ID')}\n\nSilakan klik tautan di bawah ini untuk melihat Invoice Anda:\n🔗 ${res.urls.urlInv}\n\nTerima kasih!`;
                    
                    btnWA.href = `https://wa.me/${noWA}?text=${encodeURIComponent(pesanWA)}`; 
                    btnWA.style.display = "block"; 
                } else if(btnWA) { 
                    btnWA.style.display = "none"; 
                }
                new bootstrap.Modal(document.getElementById('modalSuksesTrx')).show();
            } else { 
                Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, showConfirmButton: false, timer: 1500 }); 
                initTransaksi(); 
            }
        } else { 
            Swal.fire({ icon: 'error', title: 'Gagal', text: res.message }); 
        }
    };

    if (isKeluar) { 
        google.script.run
            .withFailureHandler(err => { Swal.fire('Gagal', 'Terjadi kesalahan sistem/jaringan', 'error'); })
            .withSuccessHandler(handleRes).simpanBarangKeluarMulti(dataKirim); 
    } else {
        const fileInput = document.getElementById("trxFileNota");
        if (fileInput && fileInput.files.length > 0) {
            // Gunakan kompresor gambar sebelum dikirim
            compressImage(fileInput.files[0], (base64String) => {
                dataKirim.fileData = base64String; 
                dataKirim.fileName = fileInput.files[0].name;
                google.script.run
                    .withFailureHandler(err => { Swal.fire('Gagal', 'Gagal memproses gambar/nota. Coba tanpa gambar.', 'error'); })
                    .withSuccessHandler(handleRes).simpanBarangMasukMulti(dataKirim);
            });
        } else { 
            google.script.run
                .withFailureHandler(err => { Swal.fire('Gagal', 'Terjadi kesalahan sistem/jaringan', 'error'); })
                .withSuccessHandler(handleRes).simpanBarangMasukMulti(dataKirim); 
        }
    }
}

function tutupModalSukses() { 
    const mod = bootstrap.Modal.getInstance(document.getElementById('modalSuksesTrx')); 
    if(mod) mod.hide(); 
    initTransaksi(); 
}

/* ============================================================
   5. BUKU RIWAYAT & EDIT TOTAL TRANSAKSI
   ============================================================ */

function editTransaksi(sheetName, idTrx) {
    const modalId = sheetName === 'Barang_Masuk' ? 'modalDetailMasuk' : 'modalDetailKeluar';
    const myModalEl = document.getElementById(modalId);
    const myModal = bootstrap.Modal.getInstance(myModalEl);
    if(myModal) myModal.hide();

    const cache = sheetName === 'Barang_Masuk' ? cacheBarangMasuk : cacheBarangKeluar;
    const item = cache.find(x => (x.ID_Trx_Masuk || x.ID_Transaksi || x.ID_Trx_Keluar) === idTrx);
    if (!item) return;

    // 1. Siapkan Opsi Produk
    let optProduk = '<option value="" disabled>-- Pilih Produk --</option>';
    window.daftarProdukLengkap.forEach(p => {
        optProduk += `<option value="${p.ID_Produk}" ${p.ID_Produk === item.ID_Produk ? 'selected' : ''}>${p.Nama_Barang} (Sisa: ${p.Stok_Saat_Ini})</option>`;
    });

    // 2. Siapkan Opsi Pihak (Akan difilter ulang saat didOpen)
    let optPihak = `<option value="${item.ID_Supplier || item.ID_Konsumen}" selected>Memuat data...</option>`;

    let hargaJualLama = sheetName === 'Barang_Keluar' ? (Number(item.Total_Jual)/Number(item.Qty)) : 0;

    let htmlContent = `
    <div class="text-start px-1" style="font-family: 'Plus Jakarta Sans', sans-serif; min-width: 600px;">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Tanggal Transaksi</label>
                    <input type="date" id="swal-tanggal" class="form-control" value="${item.Tanggal}">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Produk / Barang</label>
                    <select id="swal-produk" class="form-select" onchange="autoPilihPihak(this.value, '${sheetName}')">${optProduk}</select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">${sheetName === 'Barang_Masuk' ? 'Supplier (Terfilter Otomatis)' : 'Konsumen'}</label>
                    <select id="swal-pihak" class="form-select">${optPihak}</select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Jumlah Barang (Qty)</label>
                    <input type="number" id="swal-qty" class="form-control text-center fw-bold fs-4 ${sheetName === 'Barang_Masuk' ? 'text-success' : 'text-danger'}" value="${item.Qty}" min="1">
                </div>
                ${sheetName === 'Barang_Keluar' ? `
                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">Harga Jual Satuan</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="number" id="swal-harga" class="form-control fw-bold" value="${hargaJualLama}">
                    </div>
                </div>
                ` : `
                <div class="mb-3 bg-light p-2 rounded border">
                    <label class="form-label fw-bold small text-dark"><i class="fa-solid fa-file-invoice text-success me-1"></i> Ganti Nota</label>
                    <input type="file" id="swal-file" class="form-control form-control-sm" accept="image/*,.pdf">
                </div>
                `}
            </div>
        </div>
    </div>`;

    // Pastikan modal muncul tanpa bentrok
    setTimeout(() => {
        Swal.fire({
            title: '<i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Edit Transaksi',
            html: htmlContent,
            width: '850px',
            showCancelButton: true,
            confirmButtonText: 'Simpan Perubahan',
            confirmButtonColor: '#4f46e5',
            didOpen: () => {
                // Jalankan filter HANYA SETELAH elemen muncul di layar
                const currentProdId = document.getElementById('swal-produk').value;
                autoPilihPihak(currentProdId, sheetName);
                
                // Kembalikan ke supplier/pembeli asli bawaan transaksi
                const selectPihak = document.getElementById('swal-pihak');
                if(selectPihak) selectPihak.value = (item.ID_Supplier || item.ID_Konsumen);
            },
            preConfirm: () => {
                const tgl = document.getElementById('swal-tanggal').value;
                const prod = document.getElementById('swal-produk').value;
                const pihak = document.getElementById('swal-pihak').value;
                const qty = document.getElementById('swal-qty').value;
                const harga = sheetName === 'Barang_Keluar' ? document.getElementById('swal-harga').value : 0;
                
                if (!tgl || !prod || !pihak || !qty) {
                    Swal.showValidationMessage('Mohon lengkapi semua data!');
                    return false;
                }

                const dataKirim = { tanggal: tgl, produk: prod, pihak: pihak, qty: Number(qty), harga: Number(harga) };

                const updateAct = (d) => {
                    return new Promise((resolve) => {
                        google.script.run.withSuccessHandler(res => {
                            if(res.status === 'success') {
                                resolve();
                                Swal.fire({ icon: 'success', title: 'Berhasil!', timer: 1500, showConfirmButton: false });
                                if(sheetName === 'Barang_Masuk') loadBarangMasuk(true); else loadBarangKeluar(true);
                                google.script.run.withSuccessHandler(p => { window.daftarProdukLengkap = p; }).getData("Produk");
                            } else { Swal.showValidationMessage(res.message); resolve(); }
                        }).updateDataSheet(sheetName, idTrx, d);
                    });
                };

                if (sheetName === 'Barang_Masuk' && document.getElementById('swal-file') && document.getElementById('swal-file').files.length > 0) {
                    const file = document.getElementById('swal-file').files[0];
                    return new Promise((resolve) => {
                        compressImage(file, (base64) => {
                            dataKirim.fileData = base64; dataKirim.fileName = file.name;
                            resolve(updateAct(dataKirim));
                        });
                    });
                }
                return updateAct(dataKirim);
            }
        }).then((res) => { 
            if(res.isDismissed && myModal) myModal.show(); 
        });
    }, 500);
}

// FUNGSI BARU: Otomatis pilih Supplier saat Produk di klik (Hanya untuk Barang Masuk)
// FUNGSI PINTAR: Filter Supplier berdasarkan Kategori Produk yang dipilih
function autoPilihPihak(idProduk, sheetName) {
    if(sheetName !== 'Barang_Masuk') return; // Hanya berlaku untuk restock barang masuk

    const produk = window.daftarProdukLengkap.find(p => p.ID_Produk === idProduk);
    const selectPihak = document.getElementById('swal-pihak');
    
    if(produk && selectPihak) {
        // 1. Ambil kategori produk (misal: ATK)
        const kategoriProduk = produk.Kategori;
        
        // 2. Filter data supplier yang kategorinya sama
        let optFiltered = '<option value="" disabled>-- Pilih Supplier Relevan --</option>';
        const supplierRelevan = window.dataSupplierMentah.filter(s => s.Kategori === kategoriProduk);
        
        if(supplierRelevan.length > 0) {
            supplierRelevan.forEach(s => {
                const isSelected = (s.ID_Supplier === produk.ID_Supplier) ? 'selected' : '';
                optFiltered += `<option value="${s.ID_Supplier}" ${isSelected}>${s.Nama_Supplier}</option>`;
            });
        } else {
            optFiltered += '<option value="" disabled>Tidak ada supplier untuk kategori ini</option>';
        }
        
        // 3. Update isi dropdown Supplier
        selectPihak.innerHTML = optFiltered;
        
        // 4. Otomatis arahkan ke supplier utama produk tersebut
        if(produk.ID_Supplier) selectPihak.value = produk.ID_Supplier;
    }
}

function konfirmasiHapusTrx(sheetName, idTrans) {
    let textMsg = sheetName === 'Barang_Masuk' ? "Stok produk akan otomatis DIKURANGI KEMBALI jika restock dibatalkan." : "Penjualan dibatalkan! Stok barang otomatis DIKEMBALIKAN KE GUDANG.";
    Swal.fire({ title: 'Hapus Transaksi?', text: textMsg, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#6c757d', confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal' }).then((result) => {
        if (result.isConfirmed) {
            showProcessing("Membatalkan transaksi dan mereset stok...");
            google.script.run.withSuccessHandler(res => {
                if (res.status === "success") {
                    Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message, showConfirmButton: false, timer: 1500 });
                    if (sheetName === 'Barang_Masuk') loadBarangMasuk(true); else loadBarangKeluar(true);
                    google.script.run.withSuccessHandler(p => { window.daftarProdukLengkap = p; }).getData("Produk");
                    if(typeof loadDashboard === "function") loadDashboard(true); 
                } else { Swal.fire({icon: 'error', title: 'Gagal Hapus', text: res.message}); }
            }).hapusDataSheet(sheetName, idTrans);
        }
    });
}

function loadBarangMasuk(forceRefresh = false, silent = false) {
    if (!silent && document.getElementById("dataMasuk")) document.getElementById("dataMasuk").innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Memuat data...</td></tr>';
    Promise.all([ 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Barang_Masuk")), 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Produk")), 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Supplier")) 
    ]).then(([dMasuk, dProduk, dSupplier]) => { 
        cacheBarangMasuk = dMasuk; window.daftarProdukLengkap = dProduk; window.dataSupplierMentah = dSupplier; 
        renderTabelMasuk(dMasuk); 
    });
}

function renderTabelMasuk(dMasuk) {
    let html = "";
    if (!dMasuk || dMasuk.length === 0) { html = '<tr><td colspan="7" class="text-center py-5 text-muted">Belum ada transaksi masuk.</td></tr>'; } 
    else {
        const mapP = {}; (window.daftarProdukLengkap || []).forEach(x => mapP[x.ID_Produk] = x); 
        const mapS = {}; (window.dataSupplierMentah || []).forEach(x => mapS[x.ID_Supplier] = x);
        dMasuk.slice().reverse().forEach(item => {
            const idTrx = item.ID_Trx_Masuk || item.ID_Transaksi; 
            const p = mapP[item.ID_Produk]; 
            const s = mapS[item.ID_Supplier]; 
            const hBeli = p ? Number(p.Harga_Beli || 0) : 0; 
            const unit = p && p.Satuan ? p.Satuan : '';
            html += `<tr><td class="ps-4 text-muted small text-nowrap">${item.Tanggal}</td><td><div class="fw-bold text-dark">${p ? p.Nama_Barang : item.ID_Produk}</div><span class="badge bg-secondary bg-opacity-10 text-secondary mt-1 border-0">${item.Kategori}</span></td><td><small>${s ? s.Nama_Supplier : item.ID_Supplier}</small></td><td class="text-nowrap">Rp ${hBeli.toLocaleString('id-ID')}</td><td class="text-center"><span class="badge bg-success fs-6">+ ${item.Qty} <span class="fw-normal" style="font-size:10px">${unit}</span></span></td><td class="fw-bold text-nowrap">Rp ${(hBeli * item.Qty).toLocaleString('id-ID')}</td><td class="text-center pe-4"><button class="btn btn-sm btn-primary shadow-sm px-3" onclick="showDetailMasuk('${idTrx}')"><i class="fa fa-eye me-1"></i> Detail</button></td></tr>`;
        });
    }
    if(document.getElementById("dataMasuk")) document.getElementById("dataMasuk").innerHTML = html;
}

function showDetailMasuk(idTrx) {
    if (!cacheBarangMasuk) return; 
    const item = cacheBarangMasuk.find(x => (x.ID_Trx_Masuk || x.ID_Transaksi) === idTrx); 
    if (!item) return;
    
    const p = window.daftarProdukLengkap.find(x => x.ID_Produk == item.ID_Produk); 
    const s = window.dataSupplierMentah.find(x => x.ID_Supplier == item.ID_Supplier);
    const hBeli = p ? Number(p.Harga_Beli || 0) : 0; 
    const fileUrl = item.Bukti_Nota || item.Bukti_nota || "";
    const btnNota = fileUrl ? `<a href="${fileUrl}" target="_blank" class="btn btn-outline-success fw-bold w-100 mb-2"><i class="fa-solid fa-file-invoice me-2"></i>Lihat Lampiran Nota</a>` : `<div class="text-center text-muted small py-2 bg-light rounded-3 mb-2 border border-warning border-opacity-50"><i class="fa-solid fa-image-slash me-2"></i>Tidak ada lampiran nota</div>`;
    
    let html = `
    <div class="row">
        <div class="col-md-6 border-end pe-md-4">
            <div class="mb-3 border-bottom pb-2"><small class="text-muted text-uppercase fw-bold d-block">ID Transaksi</small><span class="fs-5 fw-bold text-success">${idTrx}</span></div>
            <div class="row mb-3"><div class="col-6"><small class="text-muted text-uppercase fw-bold d-block">Tanggal</small><span>${item.Tanggal}</span></div><div class="col-6"><small class="text-muted text-uppercase fw-bold d-block">Kategori</small><span class="badge bg-secondary bg-opacity-10 text-secondary border-0">${item.Kategori}</span></div></div>
            <div class="mb-3"><small class="text-muted text-uppercase fw-bold d-block">Produk</small><span class="fw-bold text-dark fs-6">${p ? p.Nama_Barang : item.ID_Produk}</span></div>
            <div class="mb-4"><small class="text-muted text-uppercase fw-bold d-block">Supplier</small><span class="d-block">${s ? s.Nama_Supplier : item.ID_Supplier}</span></div>
        </div>
        <div class="col-md-6 ps-md-4 d-flex flex-column">
            <div class="row mb-4 bg-light p-3 rounded-3 border"><div class="col-6 text-center border-end border-white"><small class="text-muted text-uppercase fw-bold d-block">Qty Masuk</small><span class="fw-bold fs-4 text-success">+${item.Qty}</span></div><div class="col-6 text-center"><small class="text-muted text-uppercase fw-bold d-block">Total Biaya</small><span class="fw-bold text-dark fs-4">Rp ${(hBeli * item.Qty).toLocaleString('id-ID')}</span></div></div>
            <div class="mb-4"><small class="text-muted text-uppercase fw-bold d-block mb-2">Tindakan Khusus</small>${btnNota}<button type="button" class="btn btn-outline-primary fw-bold w-100" onclick="editTransaksi('Barang_Masuk', '${idTrx}')"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Full Transaksi</button></div>
            <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-3"><button type="button" class="btn btn-light border text-danger fw-bold px-3" onclick="hapusDariModalDetailMasuk('${idTrx}')"><i class="fa fa-trash me-1"></i> Batalkan Transaksi</button><button type="button" class="btn btn-secondary px-4 shadow-sm" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>`;
    document.getElementById("detailMasukBody").innerHTML = html; new bootstrap.Modal(document.getElementById('modalDetailMasuk')).show();
}
function hapusDariModalDetailMasuk(idTrx) { const modDetail = bootstrap.Modal.getInstance(document.getElementById('modalDetailMasuk')); if(modDetail) modDetail.hide(); setTimeout(() => { konfirmasiHapusTrx('Barang_Masuk', idTrx); }, 150); }

function loadBarangKeluar(forceRefresh = false, silent = false) {
    if (!silent && document.getElementById("dataKeluar")) document.getElementById("dataKeluar").innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-warning me-2"></div>Memuat data...</td></tr>';
    Promise.all([ 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Barang_Keluar")), 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Produk")), 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Konsumen")) 
    ]).then(([dKeluar, dProduk, dKonsumen]) => { 
        cacheBarangKeluar = dKeluar; window.daftarProdukLengkap = dProduk; window.daftarKonsumenLengkap = dKonsumen; renderTabelKeluar(dKeluar); 
    });
}

function renderTabelKeluar(dataKeluar) {
    let html = "";
    if (!dataKeluar || dataKeluar.length === 0) { html = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fa-solid fa-cart-arrow-down fa-2x mb-3 opacity-50 d-block"></i>Belum ada transaksi keluar.</td></tr>'; } 
    else {
        const mapP = {}; (window.daftarProdukLengkap || []).forEach(x => mapP[x.ID_Produk] = x); 
        const mapK = {}; (window.daftarKonsumenLengkap || []).forEach(x => mapK[x.ID_Konsumen] = x);
        dataKeluar.slice().reverse().forEach(item => {
            const idTrx = item.ID_Trx_Keluar || item.ID_Transaksi || "N/A"; 
            const p = mapP[item.ID_Produk]; 
            const k = mapK[item.ID_Konsumen];
            const tJual = Number(item.Total_Jual || 0); 
            const hJual = p ? Number(p.Harga_Jual || 0) : 0; 
            const unit = p && p.Satuan ? p.Satuan : '';
            html += `<tr><td class="ps-4 text-muted small text-nowrap">${item.Tanggal}</td><td><div class="fw-bold text-dark">${p ? p.Nama_Barang : item.ID_Produk}</div><span class="badge bg-secondary bg-opacity-10 text-secondary border-0 mt-1">${item.Kategori}</span></td><td><small class="fw-500">${k ? k.Nama_Konsumen : item.ID_Konsumen}</small></td><td class="text-nowrap text-primary fw-500">Rp ${hJual.toLocaleString('id-ID')}</td><td class="text-center"><span class="badge bg-danger-subtle text-danger px-3 fs-6">- ${item.Qty} <span class="fw-normal" style="font-size:10px">${unit}</span></span></td><td class="text-end fw-bold text-dark pe-4 text-nowrap">Rp ${tJual.toLocaleString('id-ID')}</td><td class="text-center pe-4"><button class="btn btn-sm btn-primary shadow-sm px-3" onclick="showDetailKeluar('${idTrx}')"><i class="fa fa-eye me-1"></i> Detail</button></td></tr>`;
        });
    }
    if (document.getElementById("dataKeluar")) document.getElementById("dataKeluar").innerHTML = html;
}

function showDetailKeluar(idTrx) {
    if (!cacheBarangKeluar) return; 
    const item = cacheBarangKeluar.find(x => (x.ID_Trx_Keluar || x.ID_Transaksi) === idTrx); 
    if (!item) return;
    
    const p = window.daftarProdukLengkap.find(x => x.ID_Produk == item.ID_Produk); 
    const k = window.daftarKonsumenLengkap.find(x => x.ID_Konsumen == item.ID_Konsumen);
    const namaProduk = p ? p.Nama_Barang : item.ID_Produk; 
    const namaKonsumen = k ? k.Nama_Konsumen : item.ID_Konsumen;
    const kontakKonsumen = k ? k.Kontak : ""; 
    const urlInv = item.Link_Invoice || item.Link_invoice || ""; 
    const urlSJ = item.Link_Surat_Jalan || item.Link_surat_jalan || "";
    const totalJual = Number(item.Total_Jual || 0); 
    const hBeli = p ? Number(p.Harga_Beli || 0) : 0; 
    const hJual = p ? Number(p.Harga_Jual || 0) : 0;

    const btnInv = urlInv ? `<a href="${urlInv}" target="_blank" class="btn btn-outline-primary fw-bold w-100 mb-2"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Cetak Invoice</a>` : '';
    const btnSJ = urlSJ ? `<a href="${urlSJ}" target="_blank" class="btn btn-outline-success fw-bold w-100 mb-2"><i class="fa-solid fa-truck-fast me-2"></i>Cetak Surat Jalan</a>` : '';
    const errorMsg = (!urlInv && !urlSJ) ? `<div class="text-center text-muted small py-2 bg-light rounded-3 mb-2">Dokumen belum digenerate</div>` : '';

    let btnWA = '';
    if (kontakKonsumen && urlInv) {
        let noWA = kontakKonsumen.toString().replace(/\D/g, ''); if (noWA.startsWith('0')) noWA = '62' + noWA.substring(1);
        const pesanWA = `Halo *${namaKonsumen}*,\n\nTerima kasih telah mempercayakan transaksi Anda kepada kami. Berikut adalah rincian pesanan Anda:\n\n*No. Invoice:* ${idTrx}\n*Tanggal:* ${item.Tanggal}\n*Produk:* ${namaProduk} (${item.Qty} ${p?p.Satuan:''})\n*Total Tagihan:* Rp ${totalJual.toLocaleString('id-ID')}\n\nSilakan klik tautan di bawah ini untuk melihat/mengunduh dokumen *Invoice* Anda:\n${urlInv}\n\nTerima kasih!`;
        const linkWA = `https://wa.me/${noWA}?text=${encodeURIComponent(pesanWA)}`;
        btnWA = `<a href="${linkWA}" target="_blank" class="btn btn-success fw-bold w-100 mb-3 shadow-sm" style="background-color: #25D366; border-color: #25D366;"><i class="fa-brands fa-whatsapp me-2 fs-5 align-middle"></i>Kirim Invoice via WhatsApp</a>`;
    } else if (!kontakKonsumen && urlInv) { btnWA = `<div class="text-center text-muted small py-2 bg-light rounded-3 mb-3 border border-warning border-opacity-50"><i class="fa-brands fa-whatsapp text-secondary me-2"></i>Nomor WA Kosong</div>`; }

    let html = `
    <div class="row">
        <div class="col-md-6 border-end pe-md-4">
            <div class="mb-3 border-bottom pb-2"><small class="text-muted text-uppercase fw-bold d-block">ID Transaksi</small><span class="fs-5 fw-bold text-primary">${idTrx}</span></div>
            <div class="row mb-3"><div class="col-6"><small class="text-muted text-uppercase fw-bold d-block">Tanggal Keluar</small><span>${item.Tanggal}</span></div><div class="col-6"><small class="text-muted text-uppercase fw-bold d-block">Kategori</small><span class="badge bg-secondary bg-opacity-10 text-secondary border-0">${item.Kategori}</span></div></div>
            <div class="mb-3"><small class="text-muted text-uppercase fw-bold d-block">Produk</small><span class="fw-bold text-dark fs-6">${namaProduk}</span></div>
            <div class="mb-4"><small class="text-muted text-uppercase fw-bold d-block">Konsumen</small><span class="d-block">${namaKonsumen}</span></div>
            <div class="mb-3"><button type="button" class="btn btn-outline-warning fw-bold w-100 text-dark" onclick="editTransaksi('Barang_Keluar', '${idTrx}')"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Full Transaksi</button></div>
        </div>
        <div class="col-md-6 ps-md-4 d-flex flex-column">
            <div class="row mb-3 bg-light p-3 rounded-3 border"><div class="col-4 text-center border-end border-white"><small class="text-muted text-uppercase fw-bold d-block">Harga Beli</small><span class="fw-bold text-muted">Rp ${hBeli.toLocaleString('id-ID')}</span></div><div class="col-4 text-center border-end border-white"><small class="text-muted text-uppercase fw-bold d-block">Harga Jual</small><span class="fw-bold text-primary">Rp ${hJual.toLocaleString('id-ID')}</span></div><div class="col-4 text-center"><small class="text-muted text-uppercase fw-bold d-block">Qty Keluar</small><span class="fw-bold text-danger fs-5">-${item.Qty}</span></div></div>
            <div class="text-end mb-4 px-2"><small class="text-muted text-uppercase fw-bold d-block">Total Penjualan</small><span class="fw-bold text-dark fs-3">Rp ${totalJual.toLocaleString('id-ID')}</span></div>
            <div class="mb-4"><small class="text-muted text-uppercase fw-bold d-block mb-2">Tindakan Dokumen</small>${btnWA}${btnInv}${btnSJ}${errorMsg}</div>
            <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-3"><button type="button" class="btn btn-light border text-danger fw-bold px-3" onclick="hapusDariModalDetailKeluar('${idTrx}')"><i class="fa fa-trash me-1"></i> Batalkan Transaksi</button><button type="button" class="btn btn-secondary px-4 shadow-sm" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>`;
    document.getElementById("detailKeluarBody").innerHTML = html; new bootstrap.Modal(document.getElementById('modalDetailKeluar')).show();
}
function hapusDariModalDetailKeluar(idTrx) { const modDetail = bootstrap.Modal.getInstance(document.getElementById('modalDetailKeluar')); if(modDetail) modDetail.hide(); setTimeout(() => { konfirmasiHapusTrx('Barang_Keluar', idTrx); }, 150); }

/* ============================================================
   6. MASTER DATA CRUD (PRODUK, DLL)
   ============================================================ */
function konfirmasiHapusDataMaster(sheetName, id) {
    Swal.fire({ title: "Hapus Data " + sheetName + "?", text: "Data akan dihapus permanen!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!' }).then((result) => {
        if (result.isConfirmed) {
            showProcessing("Menghapus data...");
            google.script.run.withSuccessHandler(res => {
                if (res.status === "success") {
                    Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message, showConfirmButton: false, timer: 1500 });
                    if (sheetName === 'Produk') loadProduk(true); else if (sheetName === 'Kategori') loadKategori(true); else if (sheetName === 'Satuan') loadSatuan(true); else if (sheetName === 'Supplier') loadSupplier(true); else if (sheetName === 'Konsumen') loadKonsumen(true);
                    if(typeof loadDashboard === "function") loadDashboard(true);
                } else { Swal.fire({icon: 'error', title: 'Gagal', text: res.message}); }
            }).hapusDataSheet(sheetName, id);
        }
    });
}

function loadProduk(forceRefresh = false, silent = false) {
    if (!silent && document.getElementById('dataProduk')) document.getElementById('dataProduk').innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Memuat data...</td></tr>';
    Promise.all([ 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Produk")), 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Supplier")) 
    ]).then(([dProd, dSup]) => { 
        window.daftarProdukLengkap = dProd; window.dataSupplierMentah = dSup; 
        renderTabelProduk(dProd); 
    });
}
function renderTabelProduk(data) {
    let html = ""; 
    if (!data || data.length === 0) { html = '<tr><td colspan="7" class="text-center py-5 text-muted">Belum ada data produk.</td></tr>'; } 
    else {
        const mapS = {}; (window.dataSupplierMentah || []).forEach(x => mapS[x.ID_Supplier] = x);
        data.slice().reverse().forEach((p, index) => {
            const s = mapS[p.ID_Supplier]; const namaSupplier = s ? s.Nama_Supplier : p.ID_Supplier;
            html += `<tr><td class="ps-4 text-center text-muted fw-500">${index + 1}</td><td><div class="fw-bold text-dark">${p.Nama_Barang}</div><span class="badge bg-secondary bg-opacity-10 text-secondary border-0 mt-1">${p.Kategori}</span><div class="small text-muted mt-1" style="font-size: 11px;">${p.ID_Produk}</div></td><td><small class="fw-500">${namaSupplier}</small></td><td class="text-nowrap text-muted">Rp ${Number(p.Harga_Beli).toLocaleString('id-ID')}</td><td class="text-nowrap text-primary fw-500">Rp ${Number(p.Harga_Jual).toLocaleString('id-ID')}</td><td class="text-center fw-bold fs-6 ${p.Stok_Saat_Ini <= 5 ? 'text-danger' : 'text-dark'}">${p.Stok_Saat_Ini || 0} <span class="fw-normal text-muted" style="font-size:10px">${p.Satuan || ''}</span></td><td class="text-center pe-4"><button class="btn btn-sm btn-primary shadow-sm px-3" onclick="showDetailProduk('${p.ID_Produk}')"><i class="fa fa-eye me-1"></i> Detail</button></td></tr>`;
        });
    }
    if(document.getElementById('dataProduk')) document.getElementById('dataProduk').innerHTML = html;
}
function showDetailProduk(idProduk) {
    if (!window.daftarProdukLengkap) return; 
    const p = window.daftarProdukLengkap.find(x => x.ID_Produk === idProduk); 
    if (!p) return;
    const s = (window.dataSupplierMentah || []).find(x => x.ID_Supplier === p.ID_Supplier); 
    const namaSupplier = s ? s.Nama_Supplier : p.ID_Supplier; 
    const profit = Number(p.Harga_Jual) - Number(p.Harga_Beli);
    let html = `<div class="mb-3 border-bottom pb-2"><small class="text-muted text-uppercase fw-bold d-block">ID Produk</small><span class="fs-5 fw-bold text-primary">${p.ID_Produk}</span></div><div class="mb-3"><small class="text-muted text-uppercase fw-bold d-block">Nama Barang & Kategori</small><div class="fw-bold text-dark fs-6">${p.Nama_Barang}</div><span class="badge bg-secondary bg-opacity-10 text-secondary border-0 mt-1">${p.Kategori}</span></div><div class="mb-4"><small class="text-muted text-uppercase fw-bold d-block">Supplier / Pemasok</small><span>${namaSupplier}</span></div><div class="row mb-4 bg-light p-3 rounded-3 border g-2"><div class="col-6 border-end border-white"><small class="text-muted text-uppercase fw-bold d-block">Harga Beli</small><span class="fw-bold text-muted d-block">Rp ${Number(p.Harga_Beli).toLocaleString('id-ID')}</span><small class="text-muted text-uppercase fw-bold d-block mt-2">Harga Jual</small><span class="fw-bold text-primary d-block">Rp ${Number(p.Harga_Jual).toLocaleString('id-ID')}</span></div><div class="col-6 text-center d-flex flex-column justify-content-center align-items-center"><small class="text-muted text-uppercase fw-bold d-block">Stok Gudang</small><span class="fw-bold fs-2 ${p.Stok_Saat_Ini <= 5 ? 'text-danger' : 'text-success'}">${p.Stok_Saat_Ini} <span class="fs-6 fw-normal">${p.Satuan || ''}</span></span><small class="text-muted">Potensi Laba: Rp ${profit.toLocaleString('id-ID')}/${p.Satuan || 'pcs'}</small></div></div><div class="d-flex justify-content-between align-items-center pt-2"><div class="btn-group shadow-sm"><button type="button" class="btn btn-light border text-primary fw-bold px-3" onclick="editDariModalDetailProduk('${p.ID_Produk}')"><i class="fa fa-edit me-1"></i> Edit</button><button type="button" class="btn btn-light border text-danger fw-bold px-3" onclick="hapusDariModalDetailProduk('${p.ID_Produk}')"><i class="fa fa-trash me-1"></i> Hapus</button></div><button type="button" class="btn btn-secondary px-4 shadow-sm" data-bs-dismiss="modal">Tutup</button></div>`;
    document.getElementById("detailProdukBody").innerHTML = html; 
    new bootstrap.Modal(document.getElementById('modalDetailProduk')).show();
}
function editDariModalDetailProduk(idProduk) {
    const modDetail = bootstrap.Modal.getInstance(document.getElementById('modalDetailProduk')); 
    if(modDetail) modDetail.hide();
    
    setTimeout(() => {
        const p = window.daftarProdukLengkap.find(x => x.ID_Produk === idProduk); 
        if(!p) return;
        Promise.all([ 
            new Promise(r => google.script.run.withSuccessHandler(r).getData("Kategori")), 
            new Promise(r => google.script.run.withSuccessHandler(r).getData("Satuan")) 
        ]).then(([dKat, dSat]) => {
            window.dataKategoriMentah = dKat; window.dataSatuanMentah = dSat; 
            document.getElementById("formProduk").reset(); 
            document.getElementById("editIdProduk").value = p.ID_Produk; 
            document.getElementById("inputNamaBarang").value = p.Nama_Barang; 
            document.getElementById("inputHargaBeli").value = p.Harga_Beli; 
            document.getElementById("inputHargaJual").value = p.Harga_Jual;
            
            let htmlKat = '<option value="" disabled>Pilih Kategori...</option>'; 
            dKat.forEach(k => { htmlKat += `<option value="${k.Nama_Kategori}">${k.Nama_Kategori}</option>`; }); 
            const dk = document.getElementById("dropdownKategoriProduk"); 
            if(dk) { dk.innerHTML = htmlKat; dk.value = p.Kategori; }
            
            let htmlSat = '<option value="" disabled>Pilih Satuan...</option>'; 
            dSat.forEach(s => { htmlSat += `<option value="${s.Nama_Satuan}">${s.Nama_Satuan}</option>`; }); 
            const dSatEl = document.getElementById("dropdownSatuanProduk"); 
            if(dSatEl) { dSatEl.innerHTML = htmlSat; dSatEl.value = p.Satuan || ''; }
            
            filterSupplierByKategori(p.Kategori, p.ID_Supplier); 
            new bootstrap.Modal(document.getElementById('modalProduk')).show();
        });
    }, 150);
}
function hapusDariModalDetailProduk(idProduk) { 
    const modDetail = bootstrap.Modal.getInstance(document.getElementById('modalDetailProduk')); 
    if(modDetail) modDetail.hide(); 
    setTimeout(() => { konfirmasiHapusDataMaster('Produk', idProduk); }, 150); 
}
function filterSupplierByKategori(kategoriDipilih, selectedSupplierId = "") {
    const ds = document.getElementById("dropdownSupplierProduk"); 
    if (!ds) return; ds.disabled = false; 
    const filtered = (window.dataSupplierMentah || []).filter(s => s.Kategori === kategoriDipilih);
    let html = `<option value="" disabled ${!selectedSupplierId ? 'selected' : ''}>-- Pilih Supplier --</option>`;
    if (filtered.length > 0) { 
        filtered.forEach(s => { const isSel = (s.ID_Supplier === selectedSupplierId) ? 'selected' : ''; html += `<option value="${s.ID_Supplier}" ${isSel}>${s.Nama_Supplier}</option>`; }); 
    } else { 
        html = '<option value="" disabled>Tidak ada supplier</option>'; 
    }
    ds.innerHTML = html;
}
function persiapkanFormProduk() { 
    if(document.getElementById("formProduk")) document.getElementById("formProduk").reset(); 
    document.getElementById("editIdProduk").value = ""; 
    document.getElementById("dropdownSupplierProduk").disabled = true; 
    document.getElementById("dropdownSupplierProduk").innerHTML = '<option value="" disabled selected>Pilih Kategori dulu...</option>';
    
    Promise.all([ 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Kategori")), 
        new Promise(r => google.script.run.withSuccessHandler(r).getData("Satuan")) 
    ]).then(([dKat, dSat]) => {
        window.dataKategoriMentah = dKat; window.dataSatuanMentah = dSat;
        let htmlK = '<option value="" disabled selected>Pilih Kategori...</option>'; 
        dKat.forEach(k => { htmlK += `<option value="${k.Nama_Kategori}">${k.Nama_Kategori}</option>`; }); 
        if(document.getElementById("dropdownKategoriProduk")) document.getElementById("dropdownKategoriProduk").innerHTML = htmlK; 
        
        let htmlS = '<option value="" disabled selected>Pilih Satuan...</option>'; 
        dSat.forEach(s => { htmlS += `<option value="${s.Nama_Satuan}">${s.Nama_Satuan}</option>`; }); 
        if(document.getElementById("dropdownSatuanProduk")) document.getElementById("dropdownSatuanProduk").innerHTML = htmlS; 
        
        new bootstrap.Modal(document.getElementById('modalProduk')).show(); 
    });
}
function simpanProduk(e) {
    e.preventDefault(); 
    showProcessing("Menyimpan Produk..."); 
    const satEl = document.getElementById("dropdownSatuanProduk");
    const d = { 
        namaBarang: document.getElementById("inputNamaBarang").value, 
        kategori: document.getElementById("dropdownKategoriProduk").value, 
        idSupplier: document.getElementById("dropdownSupplierProduk").value, 
        hargaBeli: document.getElementById("inputHargaBeli").value, 
        hargaJual: document.getElementById("inputHargaJual").value, 
        satuan: satEl ? satEl.value : '' 
    }; 
    const id = document.getElementById("editIdProduk").value;
    const handler = res => { 
        if(res.status==="success") { 
            loadProduk(true, true); 
            const mod = bootstrap.Modal.getInstance(document.getElementById('modalProduk')); 
            if (mod) mod.hide(); 
            Swal.fire({icon: 'success', title: 'Berhasil!', text: res.message, showConfirmButton: false, timer: 1500}); 
            if(typeof loadDashboard === "function") loadDashboard(true); 
        } else { 
            Swal.fire({icon: 'error', title: 'Gagal', text: res.message}); 
        } 
    };
    if(id) { google.script.run.withSuccessHandler(handler).updateDataSheet("Produk", id, d); } 
    else { google.script.run.withSuccessHandler(handler).tambahDataKeSheet("Produk", d); }
}

function loadSatuan(forceRefresh = false, silent = false) {
  if (!silent && document.getElementById("dataSatuan")) document.getElementById("dataSatuan").innerHTML = `<tr><td colspan="3" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Memuat data...</td></tr>`;
  google.script.run.withSuccessHandler(data => { window.dataSatuanMentah = data; renderTabelSatuan(data); }).getData("Satuan");
}
function renderTabelSatuan(data) { 
    let html = ""; 
    if (!data || data.length === 0) { html = `<tr><td colspan="3" class="text-center py-5 text-muted">Belum ada data satuan.</td></tr>`; } 
    else { data.forEach(r => { html += `<tr><td class="ps-4 text-muted fw-500">${r.ID_Satuan || '-'}</td><td class="fw-bold text-dark">${r.Nama_Satuan}</td><td class="text-center pe-4"><button class="btn btn-sm btn-light text-primary me-1" onclick="editSatuan('${r.ID_Satuan}', '${r.Nama_Satuan}')"><i class="fa-solid fa-pen-to-square"></i></button><button class="btn btn-sm btn-light text-danger" onclick="konfirmasiHapusDataMaster('Satuan', '${r.ID_Satuan}')"><i class="fa-solid fa-trash-can"></i></button></td></tr>`; }); } 
    if(document.getElementById("dataSatuan")) document.getElementById("dataSatuan").innerHTML = html; 
}
function persiapkanFormSatuan() { if(document.getElementById("formSatuan")) document.getElementById("formSatuan").reset(); document.getElementById("editIdSatuan").value = ""; bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSatuan')).show(); }
function editSatuan(id, nama) { document.getElementById("editIdSatuan").value = id; document.getElementById("inputNamaSatuan").value = nama; bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSatuan')).show(); }
function simpanSatuan(e) { 
    e.preventDefault(); showProcessing("Menyimpan Satuan..."); 
    const d = { namaSatuan: document.getElementById("inputNamaSatuan").value }; 
    const id = document.getElementById("editIdSatuan").value; 
    const handler = res => { if(res.status==="success") { loadSatuan(true, true); bootstrap.Modal.getInstance(document.getElementById('modalSatuan')).hide(); Swal.fire({icon: 'success', title: 'Berhasil!', timer: 1500}); } else { Swal.fire('Gagal', res.message, 'error'); } }; 
    if(id) google.script.run.withSuccessHandler(handler).updateDataSheet("Satuan", id, d); else google.script.run.withSuccessHandler(handler).tambahDataKeSheet("Satuan", d); 
}

function loadKategori(forceRefresh = false, silent = false) {
  if (!silent && document.getElementById("dataKategori")) document.getElementById("dataKategori").innerHTML = '<tr><td colspan="3" class="text-center py-5"><div class="spinner-border spinner-border-sm"></div></td></tr>';
  google.script.run.withSuccessHandler(data => { window.dataKategoriMentah = data; renderTabelKategori(data); }).getData("Kategori");
}
function renderTabelKategori(data) { 
    let html = ""; 
    if (!data || data.length === 0) { html = '<tr><td colspan="3" class="text-center py-5">Kosong</td></tr>'; } 
    else { data.forEach(r => { html += `<tr><td class="ps-4">${r.ID_Kategori}</td><td class="fw-bold">${r.Nama_Kategori}</td><td class="text-center"><button class="btn btn-sm btn-light text-primary me-1" onclick="editKategori('${r.ID_Kategori}', '${r.Nama_Kategori}')"><i class="fa fa-pen-to-square"></i></button><button class="btn btn-sm btn-light text-danger" onclick="konfirmasiHapusDataMaster('Kategori', '${r.ID_Kategori}')"><i class="fa fa-trash"></i></button></td></tr>`; }); } 
    if(document.getElementById("dataKategori")) document.getElementById("dataKategori").innerHTML = html; 
}
function persiapkanFormKategori() { if(document.getElementById("formKategori")) document.getElementById("formKategori").reset(); document.getElementById("editIdKategori").value = ""; bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKategori')).show(); }
function editKategori(id, nama) { document.getElementById("editIdKategori").value = id; document.getElementById("inputNamaKategori").value = nama; bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKategori')).show(); }
function simpanKategori(e) { 
    e.preventDefault(); showProcessing("Menyimpan..."); 
    const d = { namaKategori: document.getElementById("inputNamaKategori").value }; 
    const id = document.getElementById("editIdKategori").value; 
    const handler = res => { if(res.status==="success") { loadKategori(true, true); bootstrap.Modal.getInstance(document.getElementById('modalKategori')).hide(); Swal.fire('Berhasil', '', 'success'); } else { Swal.fire('Gagal', res.message, 'error'); } }; 
    if(id) google.script.run.withSuccessHandler(handler).updateDataSheet("Kategori", id, d); else google.script.run.withSuccessHandler(handler).tambahDataKeSheet("Kategori", d); 
}

function loadSupplier(forceRefresh = false, silent = false) {
  if (!silent && document.getElementById("dataSupplier")) document.getElementById("dataSupplier").innerHTML = '<tr><td colspan="5" class="text-center py-5"><div class="spinner-border spinner-border-sm"></div></td></tr>';
  Promise.all([ 
      new Promise(r => google.script.run.withSuccessHandler(r).getData("Supplier")), 
      new Promise(r => google.script.run.withSuccessHandler(r).getData("Kategori")) 
  ]).then(([dSup, dKat]) => { window.dataSupplierMentah = dSup; window.dataKategoriMentah = dKat; renderTabelSupplier(dSup); });
}
function renderTabelSupplier(data) { 
    let html = ""; 
    if (!data || data.length === 0) { html = '<tr><td colspan="5" class="text-center py-5">Kosong</td></tr>'; } 
    else { data.forEach(r => { html += `<tr><td class="ps-4"><div class="fw-bold">${r.Nama_Supplier}</div><small>${r.ID_Supplier}</small></td><td><span class="badge bg-secondary bg-opacity-10 text-secondary">${r.Kategori}</span></td><td>${r.Kontak}</td><td class="small">${r.Alamat}</td><td class="text-center"><button class="btn btn-sm btn-light text-primary" onclick="editSupplier('${r.ID_Supplier}', '${r.Nama_Supplier}', '${r.Kategori}', '${r.Kontak}', '${r.Alamat}')"><i class="fa fa-edit"></i></button><button class="btn btn-sm btn-light text-danger" onclick="konfirmasiHapusDataMaster('Supplier', '${r.ID_Supplier}')"><i class="fa fa-trash"></i></button></td></tr>`; }); } 
    if(document.getElementById("dataSupplier")) document.getElementById("dataSupplier").innerHTML = html; 
}
function loadKatToSupp(sel = null) { 
    let html = '<option value="" disabled selected>Pilih Kategori...</option>'; 
    (window.dataKategoriMentah || []).forEach(k => { html += `<option value="${k.Nama_Kategori}">${k.Nama_Kategori}</option>`; }); 
    const el = document.getElementById("inputKategoriSupplier"); 
    if(el) { el.innerHTML = html; if(sel) el.value = sel; } 
}
function persiapkanFormSupplier() { if(document.getElementById("formSupplier")) document.getElementById("formSupplier").reset(); document.getElementById("editIdSupplier").value = ""; loadKatToSupp(); bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSupplier')).show(); }
function editSupplier(id, nama, kat, kon, alm) { document.getElementById("editIdSupplier").value = id; document.getElementById("inputNamaSupplier").value = nama; document.getElementById("inputKontakSupplier").value = kon; document.getElementById("inputAlamatSupplier").value = alm; loadKatToSupp(kat); bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSupplier')).show(); }
function simpanSupplier(e) { 
    e.preventDefault(); showProcessing("Menyimpan..."); 
    const d = { namaSupplier: document.getElementById("inputNamaSupplier").value, kategori: document.getElementById("inputKategoriSupplier").value, kontak: document.getElementById("inputKontakSupplier").value, alamat: document.getElementById("inputAlamatSupplier").value }; 
    const id = document.getElementById("editIdSupplier").value; 
    const handler = res => { if(res.status==="success"){ loadSupplier(true, true); bootstrap.Modal.getInstance(document.getElementById('modalSupplier')).hide(); Swal.fire('Berhasil', '', 'success'); } else { Swal.fire('Gagal', '', 'error'); } }; 
    if(id) google.script.run.withSuccessHandler(handler).updateDataSheet("Supplier", id, d); else google.script.run.withSuccessHandler(handler).tambahDataKeSheet("Supplier", d); 
}

function loadKonsumen(forceRefresh = false, silent = false) {
  if (!silent && document.getElementById("dataKonsumen")) document.getElementById("dataKonsumen").innerHTML = '<tr><td colspan="4" class="text-center py-5"><div class="spinner-border spinner-border-sm"></div></td></tr>';
  google.script.run.withSuccessHandler(data => { window.daftarKonsumenLengkap = data; renderTabelKonsumen(data); }).getData("Konsumen");
}
function renderTabelKonsumen(data) { 
    let html = ""; 
    if (!data || data.length === 0) { html = '<tr><td colspan="4" class="text-center py-5">Kosong</td></tr>'; } 
    else { data.forEach(r => { html += `<tr><td class="ps-4"><div class="fw-bold">${r.Nama_Konsumen}</div><small>${r.ID_Konsumen}</small></td><td>${r.Kontak}</td><td class="small">${r.Alamat}</td><td class="text-center"><button class="btn btn-sm btn-light text-primary" onclick="editKonsumen('${r.ID_Konsumen}', '${r.Nama_Konsumen}', '${r.Kontak}', '${r.Alamat}')"><i class="fa fa-edit"></i></button><button class="btn btn-sm btn-light text-danger" onclick="konfirmasiHapusDataMaster('Konsumen', '${r.ID_Konsumen}')"><i class="fa fa-trash"></i></button></td></tr>`; }); } 
    if(document.getElementById("dataKonsumen")) document.getElementById("dataKonsumen").innerHTML = html; 
}
function persiapkanFormKonsumen() { if(document.getElementById("formKonsumen")) document.getElementById("formKonsumen").reset(); document.getElementById("editIdKonsumen").value = ""; bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKonsumen')).show(); }
function editKonsumen(id, nama, kon, alm) { document.getElementById("editIdKonsumen").value = id; document.getElementById("inputNamaKonsumen").value = nama; document.getElementById("inputKontakKonsumen").value = kon; document.getElementById("inputAlamatKonsumen").value = alm; bootstrap.Modal.getOrCreateInstance(document.getElementById('modalKonsumen')).show(); }
function simpanKonsumen(e) { 
    e.preventDefault(); showProcessing("Menyimpan..."); 
    const d = { namaKonsumen: document.getElementById("inputNamaKonsumen").value, kontak: document.getElementById("inputKontakKonsumen").value, alamat: document.getElementById("inputAlamatKonsumen").value }; 
    const id = document.getElementById("editIdKonsumen").value; 
    const handler = res => { if(res.status==="success") { loadKonsumen(true, true); bootstrap.Modal.getInstance(document.getElementById('modalKonsumen')).hide(); Swal.fire('Berhasil', '', 'success'); } else { Swal.fire('Gagal', '', 'error'); } }; 
    if(id) google.script.run.withSuccessHandler(handler).updateDataSheet("Konsumen", id, d); else google.script.run.withSuccessHandler(handler).tambahDataKeSheet("Konsumen", d); 
}

/* ============================================================
   7. PENGATURAN
   ============================================================ */
function loadPengaturan() {
    google.script.run.withSuccessHandler(data => {
        if(document.getElementById("setNamaAplikasi")) document.getElementById("setNamaAplikasi").value = data.namaAplikasi || "";
        if(document.getElementById("setNamaPerusahaan")) document.getElementById("setNamaPerusahaan").value = data.namaPerusahaan || "";
        if(document.getElementById("setAlamatPerusahaan")) document.getElementById("setAlamatPerusahaan").value = data.alamatPerusahaan || "";
        if(document.getElementById("setKontakPerusahaan")) document.getElementById("setKontakPerusahaan").value = data.kontakPerusahaan || "";
        if(document.getElementById("setCatatanInvoice")) document.getElementById("setCatatanInvoice").value = data.catatanInvoice || "";
        if(document.getElementById("setCatatanSuratJalan")) document.getElementById("setCatatanSuratJalan").value = data.catatanSuratJalan || "";
    }).getPengaturan();
}

function prosesSimpanPengaturan(e) {
    e.preventDefault(); showProcessing("Menyimpan Pengaturan...");
    const data = { 
        namaAplikasi: document.getElementById("setNamaAplikasi").value, 
        namaPerusahaan: document.getElementById("setNamaPerusahaan").value, 
        alamatPerusahaan: document.getElementById("setAlamatPerusahaan").value, 
        kontakPerusahaan: document.getElementById("setKontakPerusahaan").value, 
        catatanInvoice: document.getElementById("setCatatanInvoice").value, 
        catatanSuratJalan: document.getElementById("setCatatanSuratJalan").value 
    };
    google.script.run.withSuccessHandler(res => {
        if (res.status === "success") { 
            Swal.fire({icon: 'success', title: 'Berhasil!', timer: 1500}); 
            if (document.querySelector(".sidebar-header h5")) { document.querySelector(".sidebar-header h5").innerHTML = data.namaAplikasi + ' <span class="badge bg-primary fs-6">PHP</span>'; } 
        } else { Swal.fire('Gagal', res.message, 'error'); }
    }).simpanPengaturan(data);
}

/* ============================================================
   8. TANGGAL, STOK & STARTUP
   ============================================================ */
function updateDateIndo() { 
    const hari = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"], bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"]; 
    const now = new Date(); 
    if(document.getElementById("realtimeDate")) document.getElementById("realtimeDate").innerText = `${hari[now.getDay()]}, ${now.getDate()} ${bulan[now.getMonth()]} ${now.getFullYear()}`; 
}

function cekStokMenipis() {
    if (!window.daftarProdukLengkap) return;
    const alertArea = document.getElementById("alertStokBanner"); if(alertArea) alertArea.remove();
    const produkTipis = window.daftarProdukLengkap.filter(p => Number(p.Stok_Saat_Ini || 0) <= 5);
    if(produkTipis.length > 0 && document.getElementById("dashboard")) {
        document.getElementById("dashboard").insertAdjacentHTML('afterbegin', `<div id="alertStokBanner" class="alert alert-danger shadow-sm py-2"><strong>Perhatian!</strong> Ada ${produkTipis.length} produk yang stoknya menipis (<= 5).</div>`);
    }
}
/* ============================================================
   9. FITUR LAPORAN KEUANGAN (MODE DIAGNOSTIK & SUPER AMAN)
   ============================================================ */
let dataLaporanFilter = []; 

async function loadLaporan() {
    const tbody = document.getElementById("dataTabelLaporan");
    // Kita buat tempat khusus untuk menampilkan log proses loading
    if(tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-dark"><div class="spinner-border spinner-border-sm text-primary me-2"></div><span id="logLoading" class="fw-bold">Memulai sistem laporan...</span></td></tr>';
    
    const logEl = document.getElementById("logLoading");
    const updateLog = (msg) => { 
        if(logEl) logEl.innerText = msg; 
        console.log("[Laporan] " + msg); // Catat juga di Console browser
    };

    try {
        const timeCache = new Date().getTime();
        const opt = { cache: 'no-store' }; 

        // 1. Tarik Data Barang Masuk
        updateLog("1/3: Menarik data Barang Masuk...");
        let resMasuk = [];
        try {
            const req1 = await fetch(`api.php?action=getData&sheet=Barang_Masuk&_t=${timeCache}`, opt);
            resMasuk = await req1.json();
            updateLog(`-> Barang Masuk Sukses (${resMasuk.length} data)`);
        } catch(e) { console.error("Error Masuk:", e); }

        // 2. Tarik Data Barang Keluar
        updateLog("2/3: Menarik data Barang Keluar...");
        let resKeluar = [];
        try {
            const req2 = await fetch(`api.php?action=getData&sheet=Barang_Keluar&_t=${timeCache}`, opt);
            resKeluar = await req2.json();
            updateLog(`-> Barang Keluar Sukses (${resKeluar.length} data)`);
        } catch(e) { console.error("Error Keluar:", e); }

        // 3. Tarik Data Produk
        updateLog("3/3: Menarik data Master Produk...");
        let resProduk = [];
        try {
            const req3 = await fetch(`api.php?action=getData&sheet=Produk&_t=${timeCache}`, opt);
            resProduk = await req3.json();
            updateLog(`-> Produk Sukses (${resProduk.length} data)`);
        } catch(e) { console.error("Error Produk:", e); }

        updateLog("Menyusun tabel dan kalkulasi...");

        // Simpan ke Variabel Global
        cacheBarangMasuk = Array.isArray(resMasuk) ? resMasuk : [];
        cacheBarangKeluar = Array.isArray(resKeluar) ? resKeluar : [];
        window.daftarProdukLengkap = Array.isArray(resProduk) ? resProduk : [];

        // Atur Tanggal Default (Bulan Ini)
        const inputTglAwal = document.getElementById('filterTglAwal');
        const inputTglAkhir = document.getElementById('filterTglAkhir');

        if(inputTglAwal && !inputTglAwal.value) {
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');

            inputTglAwal.value = `${yyyy}-${mm}-01`;
            if(inputTglAkhir) inputTglAkhir.value = `${yyyy}-${mm}-${dd}`;
        }

        // Beri jeda 0.5 detik agar UI sempat menampilkan pesan terakhir, lalu proses tabel
        setTimeout(() => {
            try {
                prosesFilterLaporan();
            } catch (filterErr) {
                console.error("Error Kalkulasi:", filterErr);
                if(tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger"><i class="fa-solid fa-bug mb-2 fs-3"></i><br>Error saat kalkulasi angka. Cek Console (F12).</td></tr>';
            }
        }, 500);

    } catch (err) {
        console.error("Error Total Laporan:", err);
        if(tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger"><i class="fa-solid fa-triangle-exclamation mb-2 fs-3"></i><br>Koneksi ke server XAMPP terputus.</td></tr>';
    }
}

function prosesFilterLaporan() {
    const tglAwal = document.getElementById('filterTglAwal').value;
    const tglAkhir = document.getElementById('filterTglAkhir').value;
    const jenis = document.getElementById('filterJenis').value;

    let gabunganData = [];
    let totalOmset = 0; let totalModalMasuk = 0; let totalProfit = 0;

    const mapProduk = {};
    (window.daftarProdukLengkap || []).forEach(p => mapProduk[p.ID_Produk] = p);

    // 1. Ambil Penjualan
    if (jenis === 'Semua' || jenis === 'Penjualan') {
        (cacheBarangKeluar || []).forEach(k => {
            if (k.Tanggal >= tglAwal && k.Tanggal <= tglAkhir) {
                const prod = mapProduk[k.ID_Produk];
                const profitSatuan = Number(k.Profit || 0);
                const omsetSatuan = Number(k.Total_Jual || 0);
                
                gabunganData.push({
                    tanggal: k.Tanggal, idTrx: k.ID_Trx_Keluar || '-', jenis: 'Penjualan',
                    idProduk: k.ID_Produk, namaProduk: prod ? prod.Nama_Barang : k.ID_Produk,
                    kategori: k.Kategori || '-', satuan: prod ? prod.Satuan : '',
                    qty: Number(k.Qty || 0), total: omsetSatuan, profit: profitSatuan
                });
                totalOmset += omsetSatuan;
                totalProfit += profitSatuan;
            }
        });
    }

    // 2. Ambil Restock
    if (jenis === 'Semua' || jenis === 'Restock') {
        (cacheBarangMasuk || []).forEach(m => {
            if (m.Tanggal >= tglAwal && m.Tanggal <= tglAkhir) {
                const prod = mapProduk[m.ID_Produk];
                const hargaBeli = prod ? Number(prod.Harga_Beli) : 0;
                const totalModal = hargaBeli * Number(m.Qty || 0);

                gabunganData.push({
                    tanggal: m.Tanggal, idTrx: m.ID_Trx_Masuk || '-', jenis: 'Restock',
                    idProduk: m.ID_Produk, namaProduk: prod ? prod.Nama_Barang : m.ID_Produk,
                    kategori: m.Kategori || '-', satuan: prod ? prod.Satuan : '',
                    qty: Number(m.Qty || 0), total: totalModal, profit: 0
                });
                totalModalMasuk += totalModal;
            }
        });
    }

    // Urutkan (Terbaru di atas)
    gabunganData.sort((a, b) => new Date(b.tanggal) - new Date(a.tanggal));
    dataLaporanFilter = gabunganData; 

    // 3. Render Kartu
    let htmlRingkasan = '';
    if (jenis === 'Semua' || jenis === 'Penjualan') {
        htmlRingkasan += `
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-primary text-white h-100" style="background: linear-gradient(135deg, #4f46e5, #3b82f6);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-white-50 fw-bold" style="letter-spacing: 1px;">TOTAL OMSET PENJUALAN</small>
                        <i class="fa-solid fa-wallet fs-4 opacity-50"></i>
                    </div>
                    <h3 class="fw-bold mb-0">Rp ${totalOmset.toLocaleString('id-ID')}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-success text-white h-100" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-white-50 fw-bold" style="letter-spacing: 1px;">KEUNTUNGAN (PROFIT)</small>
                        <i class="fa-solid fa-chart-line fs-4 opacity-50"></i>
                    </div>
                    <h3 class="fw-bold mb-0">Rp ${totalProfit.toLocaleString('id-ID')}</h3>
                </div>
            </div>
        </div>`;
    }
    if (jenis === 'Semua' || jenis === 'Restock') {
        htmlRingkasan += `
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 bg-danger text-white h-100" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-white-50 fw-bold" style="letter-spacing: 1px;">TOTAL BIAYA RESTOCK</small>
                        <i class="fa-solid fa-boxes-packing fs-4 opacity-50"></i>
                    </div>
                    <h3 class="fw-bold mb-0">Rp ${totalModalMasuk.toLocaleString('id-ID')}</h3>
                </div>
            </div>
        </div>`;
    }
    document.getElementById("kartuRingkasanLaporan").innerHTML = htmlRingkasan;

    // 4. Render Tabel
    let htmlTabel = '';
    if (gabunganData.length === 0) {
        htmlTabel = `<tr><td colspan="6" class="text-center py-5 text-danger bg-danger bg-opacity-10"><i class="fa-solid fa-folder-open fa-2x mb-2 d-block opacity-50"></i>Tidak ada transaksi pada tanggal tersebut.</td></tr>`;
    } else {
        gabunganData.forEach(d => {
            const isJual = d.jenis === 'Penjualan';
            const badgeJenis = isJual ? `<span class="badge bg-warning text-dark"><i class="fa-solid fa-arrow-up-right-dots me-1"></i> Penjualan</span>` : `<span class="badge bg-info text-dark"><i class="fa-solid fa-arrow-down-to-bracket me-1"></i> Restock</span>`;
            const teksProfit = isJual ? `<span class="text-success fw-bold">+ Rp ${d.profit.toLocaleString('id-ID')}</span>` : `<span class="text-muted">-</span>`;
            
            htmlTabel += `
            <tr>
                <td class="ps-4 py-3"><div class="fw-bold text-dark">${d.tanggal}</div><small class="text-muted" style="font-size:10px;">${d.idTrx}</small></td>
                <td>${badgeJenis}</td>
                <td><div class="fw-bold text-dark">${d.namaProduk}</div><span class="badge bg-secondary bg-opacity-10 text-secondary border-0 mt-1">${d.kategori}</span></td>
                <td class="text-center fw-bold ${isJual ? 'text-danger' : 'text-success'}">${isJual ? '-' : '+'}${d.qty} <small class="text-muted fw-normal">${d.satuan}</small></td>
                <td class="text-end fw-bold text-dark text-nowrap">Rp ${d.total.toLocaleString('id-ID')}</td>
                <td class="text-end pe-4 text-nowrap">${teksProfit}</td>
            </tr>`;
        });
    }
    document.getElementById("dataTabelLaporan").innerHTML = htmlTabel;
}

// ==========================================================
// FUNGSI CETAK PDF LAPORAN (DESAIN PREMIUM A4)
// ==========================================================
function cetakLaporan() {
    if (!dataLaporanFilter || dataLaporanFilter.length === 0) {
        Swal.fire('Data Kosong', 'Tidak ada data laporan untuk dicetak pada rentang tanggal ini.', 'warning');
        return;
    }

    const tglAwal = document.getElementById('filterTglAwal').value;
    const tglAkhir = document.getElementById('filterTglAkhir').value;
    const jenis = document.getElementById('filterJenis').value;

    // Ambil nama perusahaan dari pengaturan (Jika ada)
    const namaPerusahaan = document.getElementById("setNamaPerusahaan") ? document.getElementById("setNamaPerusahaan").value : "NAMA PERUSAHAAN";
    const alamatPerusahaan = document.getElementById("setAlamatPerusahaan") ? document.getElementById("setAlamatPerusahaan").value : "Alamat Perusahaan";

    // Susun Template HTML Kertas A4
    let htmlContent = `
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Laporan Keuangan - ${tglAwal} s/d ${tglAkhir}</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap');
            body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; color: #1e293b; margin: 0; padding: 40px; font-size: 12px; }
            .header { text-align: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 25px; }
            .header h1 { margin: 0 0 5px 0; font-size: 24px; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; }
            .header h3 { margin: 0 0 10px 0; font-size: 16px; color: #475569; }
            .header p { margin: 0; color: #64748b; font-size: 12px; }
            
            .info-box { display: flex; justify-content: space-between; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; }
            .info-box div { font-size: 13px; }
            .info-box strong { color: #0f172a; }
            
            table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            th, td { padding: 12px 10px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
            th { background-color: #f1f5f9; color: #475569; font-weight: 800; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
            tbody tr:nth-child(even) { background-color: #f8fafc; }
            
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .badge { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; border: 1px solid #ccc; }
            
            .summary-container { display: flex; justify-content: flex-end; gap: 20px; page-break-inside: avoid; }
            .summary-card { background: #f8fafc; padding: 15px 25px; border-radius: 8px; border: 1px solid #e2e8f0; min-width: 200px; text-align: right; }
            .summary-card h4 { margin: 0 0 5px 0; font-size: 11px; color: #64748b; text-transform: uppercase; }
            .summary-card .amount { margin: 0; font-size: 18px; font-weight: 800; color: #0f172a; }
            
            .profit { color: #10b981 !important; }
            .loss { color: #ef4444 !important; }
            
            /* Konfigurasi Khusus Saat Kertas Diprint */
            @media print {
                body { padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .summary-card, .info-box, th { border: 1px solid #cbd5e1; background-color: #f1f5f9 !important; }
                .badge { border: 1px solid #64748b; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>${namaPerusahaan}</h1>
            <p>${alamatPerusahaan}</p>
            <h3 style="margin-top: 15px;">LAPORAN TRANSAKSI KEUANGAN</h3>
        </div>
        
        <div class="info-box">
            <div><strong>Periode:</strong> ${tglAwal} s/d ${tglAkhir}</div>
            <div><strong>Jenis Laporan:</strong> ${jenis}</div>
            <div><strong>Dicetak Pada:</strong> ${new Date().toLocaleString('id-ID')}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Referensi</th>
                    <th>Jenis</th>
                    <th>Produk & Kategori</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Total Nilai (Rp)</th>
                    <th class="text-right">Profit (Rp)</th>
                </tr>
            </thead>
            <tbody>`;

    let tOmset = 0; let tModal = 0; let tProfit = 0;

    dataLaporanFilter.forEach(d => {
        const isJual = d.jenis === 'Penjualan';
        if (isJual) { tOmset += d.total; tProfit += d.profit; } else { tModal += d.total; }
        
        const signQty = isJual ? '-' : '+';
        const textProfit = isJual ? `+ ${d.profit.toLocaleString('id-ID')}` : '-';
        const colorProfit = isJual && d.profit > 0 ? 'profit' : '';

        htmlContent += `
            <tr>
                <td>${d.tanggal}</td>
                <td style="font-family: monospace; color: #475569;">${d.idTrx}</td>
                <td><span class="badge">${d.jenis}</span></td>
                <td><strong>${d.namaProduk}</strong><br><span style="font-size:10px; color:#64748b;">${d.kategori}</span></td>
                <td class="text-center"><strong>${signQty}${d.qty}</strong> <span style="font-size:10px; color:#64748b;">${d.satuan}</span></td>
                <td class="text-right">${d.total.toLocaleString('id-ID')}</td>
                <td class="text-right ${colorProfit}"><strong>${textProfit}</strong></td>
            </tr>`;
    });

    htmlContent += `
            </tbody>
        </table>

        <div class="summary-container">`;
    
    // Tampilkan Ringkasan Sesuai Filter
    if (jenis === 'Semua' || jenis === 'Restock') {
        htmlContent += `
            <div class="summary-card">
                <h4>Total Biaya Restock</h4>
                <p class="amount loss">Rp ${tModal.toLocaleString('id-ID')}</p>
            </div>`;
    }
    
    if (jenis === 'Semua' || jenis === 'Penjualan') {
        htmlContent += `
            <div class="summary-card">
                <h4>Total Omset Penjualan</h4>
                <p class="amount">Rp ${tOmset.toLocaleString('id-ID')}</p>
            </div>
            <div class="summary-card">
                <h4>Total Profit Bersih</h4>
                <p class="amount profit">Rp ${tProfit.toLocaleString('id-ID')}</p>
            </div>`;
    }

    htmlContent += `
        </div>
        
        <div style="margin-top: 50px; text-align: right; color: #64748b; font-size: 11px;">
            <p>Dokumen ini di-generate secara otomatis oleh Sistem Inventaris.</p>
        </div>

        <script>
            window.onload = function() {
                setTimeout(() => {
                    window.print();
                }, 500);
            };
        <\/script>
    </body>
    </html>`;

    // Eksekusi Pembuatan Jendela Baru untuk Print
    let printWindow = window.open('', '_blank');
    printWindow.document.open();
    printWindow.document.write(htmlContent);
    printWindow.document.close();
}
// ==========================================================
// STARTUP APLIKASI (SISTEM ANTREAN)
// ==========================================================
window.onload = async function() {
    updateDateIndo(); 
    try {
        // Fokus muat Dashboard dulu
        showPage('dashboard', document.querySelector('.sidebar .nav-link.active'));

        // Jeda 1 detik baru cek stok di background agar tidak macet
        setTimeout(async () => {
            try {
                const timeCache = new Date().getTime();
                const reqProduk = await fetch(`api.php?action=getData&sheet=Produk&_t=${timeCache}`, { cache: 'no-store' });
                const dataProduk = await reqProduk.json();
                window.daftarProdukLengkap = Array.isArray(dataProduk) ? dataProduk : [];
                cekStokMenipis();
            } catch (errProd) { console.warn("Gagal cek stok:", errProd); }
        }, 1000); 
    } catch (e) { console.error("Startup Error:", e); }
};
</script>