<section id="konsumen" class="page-content">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-users text-primary me-2"></i> Database Konsumen</h5>
            <p class="text-muted small mb-0">Kelola informasi pelanggan dan riwayat kontak</p>
        </div>
        <button class="btn btn-primary shadow-sm px-4" onclick="persiapkanFormKonsumen()">
            <i class="fa-solid fa-user-plus me-2"></i> Tambah Konsumen
        </button>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-9">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-search"></i></span>
                <input type="text" id="searchKonsumen" class="form-control border-start-0 ps-0" placeholder="Cari nama pelanggan, ID, nomor kontak, atau alamat..." onkeyup="filterTabelGlobal('searchKonsumen', 'dataKonsumen')">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <button class="btn btn-light text-primary shadow-sm fw-bold border w-100" onclick="resetFilter('searchKonsumen', 'dataKonsumen')">
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
                            <th class="ps-4 text-nowrap">ID / Nama Pelanggan</th>
                            <th class="text-nowrap">Kontak (WhatsApp)</th>
                            <th>Alamat Pengiriman</th>
                            <th class="text-center pe-4 text-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="dataKonsumen">
                        <tr>
                            <td colspan="4" class="text-center py-5">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                <span class="text-muted fw-500">Memuat data pelanggan...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalKonsumen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-800 text-dark" id="titleModalKonsumen">Tambah Konsumen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <form id="formKonsumen" onsubmit="simpanKonsumen(event)">
                    <input type="hidden" name="idKonsumen" id="editIdKonsumen">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Nama Lengkap</label>
                        <input type="text" class="form-control" name="namaKonsumen" id="inputNamaKonsumen" placeholder="Masukkan nama pelanggan" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Nomor Telepon / WA</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-brands fa-whatsapp text-success"></i></span>
                            <input type="text" class="form-control border-start-0" name="kontak" id="inputKontakKonsumen" placeholder="08xxxx" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Alamat Lengkap</label>
                        <textarea class="form-control" name="alamat" id="inputAlamatKonsumen" rows="3" placeholder="Alamat rumah atau kantor..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnSimpanKonsumen">
                            <i class="fa-solid fa-save me-2"></i> Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>