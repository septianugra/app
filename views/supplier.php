<section id="supplier" class="page-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-truck-moving text-primary me-2"></i> Master Supplier</h5>
            <p class="text-muted small mb-0">Kelola daftar distributor dan mitra penyedia stok</p>
        </div>
        <button class="btn btn-primary shadow-sm px-4" onclick="persiapkanFormSupplier()">
            <i class="fa-solid fa-plus me-2"></i> Tambah Supplier
        </button>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-9">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-search"></i></span>
                <input type="text" id="searchSupplier" class="form-control border-start-0 ps-0" placeholder="Cari nama supplier, ID, kategori, atau kontak..." onkeyup="filterTabelGlobal('searchSupplier', 'dataSupplier')">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <button class="btn btn-light text-primary shadow-sm fw-bold border w-100" onclick="resetFilter('searchSupplier', 'dataSupplier')">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Reset Pencarian
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-nowrap">ID / Nama Supplier</th>
                            <th>Kategori Bisnis</th>
                            <th class="text-nowrap">Kontak</th>
                            <th>Alamat</th>
                            <th class="text-center pe-4 text-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="dataSupplier">
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                <span class="text-muted fw-500">Menghubungkan ke database...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalSupplier" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-800 text-dark" id="titleModalSupplier">Tambah Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <form id="formSupplier" onsubmit="simpanSupplier(event)">
                    <input type="hidden" name="idSupplier" id="editIdSupplier">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Nama Perusahaan/Supplier</label>
                        <input type="text" class="form-control" name="namaSupplier" id="inputNamaSupplier" placeholder="PT. Jaya Abadi" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Kategori Produk</label>
                        <select class="form-select" name="kategori" id="inputKategoriSupplier" required>
                            <option value="" disabled selected>Pilih Kategori...</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Kontak / No. WhatsApp</label>
                        <input type="text" class="form-control" name="kontak" id="inputKontakSupplier" placeholder="0812xxxx" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Alamat Lengkap</label>
                        <textarea class="form-control" name="alamat" id="inputAlamatSupplier" rows="3" placeholder="Jl. Raya No. 123..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnSimpanSupplier">
                            <i class="fa-solid fa-save me-2"></i> Simpan Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>