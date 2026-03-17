<section id="satuan" class="page-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-weight-scale text-primary me-2"></i> Master Satuan</h5>
            <p class="text-muted small mb-0">Kelola satuan ukur produk (Pcs, Box, Kg, dll)</p>
        </div>
        <button class="btn btn-primary shadow-sm px-4" onclick="persiapkanFormSatuan()">
            <i class="fa-solid fa-plus me-2"></i> Tambah Satuan
        </button>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-9">
            <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa-solid fa-search"></i></span>
                <input type="text" id="searchSatuan" class="form-control border-start-0 ps-0" placeholder="Cari ID atau nama satuan..." onkeyup="filterTabelGlobal('searchSatuan', 'dataSatuan')">
            </div>
        </div>
        <div class="col-md-3 text-md-end">
            <button class="btn btn-light text-primary shadow-sm fw-bold border w-100" onclick="resetFilter('searchSatuan', 'dataSatuan')">
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
                            <th width="15%" class="ps-4 text-nowrap">ID Satuan</th>
                            <th class="text-nowrap">Nama Satuan</th>
                            <th width="20%" class="text-center pe-4 text-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="dataSatuan">
                        <tr>
                            <td colspan="3" class="text-center py-5">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                <span class="text-muted fw-500">Memuat data dari server...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalSatuan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-800 text-dark" id="titleModalSatuan">Tambah Satuan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <form id="formSatuan" onsubmit="simpanSatuan(event)">
                    <input type="hidden" name="idSatuan" id="editIdSatuan">
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Nama Satuan</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-weight-hanging text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="namaSatuan" id="inputNamaSatuan" placeholder="Contoh: Pcs, Dus, Pack..." required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4" id="btnSimpanSatuan">
                            <i class="fa-solid fa-save me-2"></i> Simpan Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>