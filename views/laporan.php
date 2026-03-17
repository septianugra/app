<section id="laporan" class="page-content">
    <div class="row g-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-4"><i class="fa-solid fa-chart-pie text-primary me-2"></i> Laporan Keuangan & Transaksi</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold mb-1">Dari Tanggal</label>
                            <input type="date" id="filterTglAwal" class="form-control form-control-sm border-primary fw-500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold mb-1">Sampai Tanggal</label>
                            <input type="date" id="filterTglAkhir" class="form-control form-control-sm border-primary fw-500">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-bold mb-1">Jenis Transaksi</label>
                            <select id="filterJenis" class="form-select form-select-sm border-primary fw-500">
                                <option value="Semua">Semua Aktivitas</option>
                                <option value="Penjualan">Hanya Penjualan (Uang Masuk)</option>
                                <option value="Restock">Hanya Restock (Uang Keluar)</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button class="btn btn-sm btn-primary w-100 fw-bold shadow-sm" onclick="prosesFilterLaporan()"><i class="fa-solid fa-filter me-1"></i> Terapkan</button>
                            <button class="btn btn-sm btn-outline-dark w-100 fw-bold shadow-sm" onclick="cetakLaporan()"><i class="fa-solid fa-print me-1"></i> Cetak PDF</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4" id="kartuRingkasanLaporan">
                </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">TANGGAL</th>
                                    <th class="py-3">JENIS</th>
                                    <th class="py-3">PRODUK & KATEGORI</th>
                                    <th class="text-center py-3">QTY</th>
                                    <th class="text-end py-3">TOTAL NILAI</th>
                                    <th class="text-end pe-4 py-3">PROFIT (LABA)</th>
                                </tr>
                            </thead>
                            <tbody id="dataTabelLaporan">
                                <tr><td colspan="6" class="text-center py-5 text-muted">Silakan terapkan filter untuk melihat laporan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>