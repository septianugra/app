<section id="pengaturan" class="page-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-800 text-dark mb-0"><i class="fa-solid fa-gear text-secondary me-2"></i> Pengaturan Sistem</h5>
            <p class="text-muted small mb-0">Sesuaikan profil perusahaan dan preferensi aplikasi</p>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-4">
                    <form id="formPengaturan" onsubmit="prosesSimpanPengaturan(event)">
                        <h6 class="fw-bold text-primary mb-3 border-bottom pb-2">Profil Perusahaan</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Nama Aplikasi</label>
                                <input type="text" class="form-control" id="setNamaAplikasi" placeholder="INV-MANAGER">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Nama Perusahaan (Tampil di Invoice)</label>
                                <input type="text" class="form-control" id="setNamaPerusahaan" placeholder="PT. Perusahaan Anda">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">Alamat Perusahaan</label>
                                <textarea class="form-control" id="setAlamatPerusahaan" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Kontak Perusahaan (Telp/WA)</label>
                                <input type="text" class="form-control" id="setKontakPerusahaan">
                            </div>
                        </div>

                        <h6 class="fw-bold text-success mb-3 border-bottom pb-2">Pengaturan Cetak Dokumen</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">Catatan di Bawah Invoice</label>
                                <input type="text" class="form-control" id="setCatatanInvoice" placeholder="Terima kasih atas kepercayaan Anda.">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-bold text-muted">Catatan di Bawah Surat Jalan</label>
                                <input type="text" class="form-control" id="setCatatanSuratJalan" placeholder="Barang diterima dengan baik.">
                            </div>
                        </div>

                        <input type="hidden" id="setLinkFolderDrive" value="local_storage">

                        <div class="text-end pt-3">
                            <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                                <i class="fa-solid fa-save me-2"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>