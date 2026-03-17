<section id="produk" class="page-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-box-open text-primary me-2"></i> Master Produk</h5>
            <p class="text-muted small mb-0">Manajemen master barang, stok, dan harga</p>
        </div>
        <button class="btn btn-primary shadow-sm px-4" onclick="persiapkanFormProduk()">
            <i class="fa-solid fa-plus me-2"></i> Tambah Produk
        </button>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-9">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-search"></i></span>
                <input type="text" id="searchProduk" class="form-control border-start-0 ps-0" placeholder="Cari nama produk, kategori, atau nama supplier..." onkeyup="filterTabelGlobal('searchProduk', 'dataProduk')">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <button class="btn btn-light text-primary shadow-sm fw-bold border w-100" onclick="resetFilter('searchProduk', 'dataProduk')">
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
                            <th class="ps-4 text-center text-nowrap" width="5%">No</th>
                            <th class="text-nowrap">Produk & Kategori</th>
                            <th>Supplier</th>
                            <th class="text-nowrap">Harga Beli</th>
                            <th class="text-nowrap">Harga Jual</th>
                            <th class="text-center text-nowrap">Stok</th>
                            <th class="text-center pe-4 text-nowrap">Detail</th>
                        </tr>
                    </thead>
                    <tbody id="dataProduk">
                        <tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div><span class="text-muted fw-500">Memuat data produk...</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-800 text-dark" id="modalTitleText">Form Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formProduk" onsubmit="simpanProduk(event)">
                    <input type="hidden" name="idProduk" id="editIdProduk">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Nama Barang</label>
                        <input type="text" class="form-control" name="namaBarang" id="inputNamaBarang" placeholder="Masukkan nama barang lengkap" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Kategori</label>
                            <select class="form-select" name="kategori" id="dropdownKategoriProduk" onchange="filterSupplierByKategori(this.value)" required></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Supplier</label>
                            <select class="form-select" name="idSupplier" id="dropdownSupplierProduk" required disabled></select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase">Harga Beli</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">Rp</span>
                                <input type="number" class="form-control border-start-0 ps-0" name="hargaBeli" id="inputHargaBeli" placeholder="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-muted small text-uppercase text-primary">Harga Jual</label>
                            <div class="input-group">
                                <span class="input-group-text bg-primary bg-opacity-10 border-primary border-opacity-25 border-end-0 text-primary">Rp</span>
                                <input type="number" class="form-control border-primary border-opacity-25 border-start-0 ps-0" name="hargaJual" id="inputHargaJual" placeholder="0" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Satuan</label>
                        <select class="form-select" name="satuan" id="dropdownSatuanProduk" required></select>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnSimpanProduk"><i class="fa-solid fa-save me-2"></i> Simpan Produk</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetailProduk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-800 text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i> Detail Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detailProdukBody"></div>
        </div>
    </div>
</div>