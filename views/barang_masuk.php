<section id="barang_masuk" class="page-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-arrow-right-to-bracket text-success me-2"></i> Riwayat Restock (Barang Masuk)</h5>
            <p class="text-muted small mb-0">Lihat detail nota dan kelola riwayat penambahan stok dari supplier.</p>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-9">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-search"></i></span>
                <input type="text" id="searchMasuk" class="form-control border-start-0 ps-0" placeholder="Cari nama produk, supplier, ID transaksi, atau kategori..." onkeyup="filterTabelGlobal('searchMasuk', 'dataMasuk')">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <button class="btn btn-light text-primary shadow-sm fw-bold border w-100" onclick="resetFilter('searchMasuk', 'dataMasuk')">
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
                            <th class="ps-4 text-nowrap">Tanggal</th>
                            <th>Nama Barang</th>
                            <th>Supplier</th>
                            <th class="text-nowrap">Harga Beli</th>
                            <th class="text-center">Qty</th>
                            <th class="text-nowrap">Total Harga</th>
                            <th class="text-center pe-4">Detail</th>
                        </tr>
                    </thead>
                    <tbody id="dataMasuk">
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="spinner-border text-success spinner-border-sm me-2" role="status"></div>
                                <span class="text-muted fw-500">Memuat riwayat transaksi...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalDetailMasuk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"> 
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-800 text-dark"><i class="fa-solid fa-circle-info text-success me-2"></i> Detail Transaksi Masuk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="detailMasukBody"></div>
        </div>
    </div>
</div>