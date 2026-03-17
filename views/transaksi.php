<section id="transaksi" class="page-content">
    <div class="row g-3">
        
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0 fs-5"><i class="fa-solid fa-cash-register text-primary me-2"></i> Meja Kasir</h6>
                    <div class="btn-group shadow-sm" role="group">
                        <input type="radio" class="btn-check" name="modeTrx" id="modeKeluar" value="keluar" autocomplete="off" checked onchange="switchModeTrx()">
                        <label class="btn btn-outline-warning fw-bold px-4 py-1 small" for="modeKeluar">Penjualan</label>

                        <input type="radio" class="btn-check" name="modeTrx" id="modeMasuk" value="masuk" autocomplete="off" onchange="switchModeTrx()">
                        <label class="btn btn-outline-success fw-bold px-4 py-1 small" for="modeMasuk">Restock</label>
                    </div>
                </div>
                
                <div class="card-body px-4 pb-4 pt-2">
                    <form id="formTransaksi" onsubmit="prosesTransaksiTerpusat(event)">
                        
                        <div class="row mb-3 g-3">
                            <div class="col-md-5">
                                <label class="form-label text-muted fw-bold mb-1" style="font-size:11px;">TANGGAL TRANSAKSI</label>
                                <input type="date" class="form-control form-control-sm bg-light border-0 fw-500 text-muted shadow-none" id="trxTanggal" required>
                            </div>
                            <div class="col-md-7" id="areaPihakKedua">
                                </div>
                        </div>

                        <div class="mb-3 p-3 bg-light rounded-3 border position-relative">
                            <label class="form-label fw-bold text-muted mb-2" style="font-size:11px;">CARI PRODUK (KLIK UNTUK MASUK KERANJANG)</label>
                            
                            <input type="text" 
                                   class="form-control form-control-sm border-primary fw-500 py-2" 
                                   id="trxProdukCustom" 
                                   placeholder="Ketik nama atau ID produk..." 
                                   onkeyup="cariProdukCustom()" 
                                   onclick="cariProdukCustom()" 
                                   autocomplete="off">
                                   
                            <div id="hasilPencarianCustom" class="list-group position-absolute w-100 shadow-lg d-none mt-1" style="z-index: 1050; top: 100%; left:0; max-height: 250px; overflow-y: auto; border-radius: 0.5rem;"></div>
                        </div>

                        <div class="table-responsive mb-3 border rounded-3">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light text-muted" style="font-size:11px;">
                                    <tr>
                                        <th class="ps-3 py-2 text-nowrap">PRODUK</th>
                                        <th class="text-center py-2 text-nowrap">HARGA</th>
                                        <th class="text-center py-2 text-nowrap" style="width: 100px;">QTY</th>
                                        <th class="text-end py-2 text-nowrap">SUBTOTAL</th>
                                        <th class="text-center py-2 pe-3" style="width: 40px;"><i class="fa-solid fa-trash"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="tabelKeranjang">
                                    <tr><td colspan="5" class="text-center py-5 text-muted" style="font-size:12px;">Keranjang kosong. Tambahkan produk.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mb-3 d-none p-3 bg-light rounded-3 border" id="areaUploadNota">
                            <label class="form-label text-dark fw-bold mb-1" style="font-size:11px;"><i class="fa-solid fa-file-invoice text-success me-1"></i> UPLOAD BUKTI NOTA (OPSIONAL)</label>
                            <input type="file" class="form-control form-control-sm" id="trxFileNota" accept="image/*,application/pdf">
                        </div>

                        <div class="text-end d-flex justify-content-end gap-2 border-top pt-3 mt-4">
                            <button type="button" class="btn btn-sm btn-light px-4 fw-bold shadow-sm" onclick="initTransaksi()">Reset Kasir</button>
                            <button type="submit" id="btnSubmitTrx" class="btn btn-sm btn-primary px-4 py-2 fw-bold shadow-sm text-white" disabled>
                                <i class="fa-solid fa-check me-1"></i> Proses Transaksi
                            </button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-dark text-white" style="background: linear-gradient(145deg, #1e293b, #0f172a);">
                <div class="card-body p-4 d-flex flex-column">
                    <h6 class="text-uppercase text-white fw-bold mb-3 border-bottom border-secondary pb-3" style="font-size:13px; letter-spacing: 1px;">
                        <i class="fa-solid fa-receipt me-2 text-primary-soft"></i> Ringkasan
                    </h6>
                    
                    <div id="prevListKeranjang" class="flex-grow-1 overflow-auto mb-3" style="max-height: 350px;">
                        <div class="text-center text-white-50 mt-5 pt-4" style="font-size:12px;">
                            <i class="fa-solid fa-basket-shopping fs-1 mb-3 opacity-25"></i><br>
                            Belum ada item
                        </div>
                    </div>

                    <div class="mt-auto pt-3 text-center bg-black bg-opacity-25 rounded-3 p-3 border border-secondary shadow-sm">
                        <small class="text-uppercase text-white-50 fw-bold d-block mb-1" id="labelTotal" style="font-size: 11px; letter-spacing: 1px;">TOTAL PENJUALAN</small>
                        <h3 class="fw-900 text-warning mb-0" id="prevTotalHarga">Rp 0</h3>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</section> 

<div class="modal fade" id="modalSuksesTrx" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg rounded-4 text-center">
            <div class="modal-body p-4">
                <div class="mb-3">
                    <i class="fa-solid fa-circle-check text-success" style="font-size: 4rem;"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">Transaksi Selesai!</h5>
                <p class="text-muted mb-4" style="font-size:12px;">Silakan cetak dokumen melalui tombol di bawah.</p>
                
                <div class="d-grid gap-2 mb-4">
                    <a href="#" id="btnKirimWA" target="_blank" class="btn btn-success btn-sm fw-bold shadow-sm py-2" style="background-color: #25D366; border-color: #25D366; display: none;">
                        <i class="fa-brands fa-whatsapp me-1"></i> Kirim Invoice WA
                    </a>
                    <a href="#" id="btnCetakInvoice" target="_blank" class="btn btn-outline-primary btn-sm fw-bold shadow-sm py-2">
                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Cetak Invoice
                    </a>
                    <a href="#" id="btnCetakSJ" target="_blank" class="btn btn-outline-success btn-sm fw-bold shadow-sm py-2">
                        <i class="fa-solid fa-truck-fast me-1"></i> Cetak Surat Jalan
                    </a>
                </div>
                
                <button type="button" class="btn btn-light btn-sm fw-bold w-100 rounded-3 py-2 text-muted" onclick="tutupModalSukses()">
                    Selesai & Tutup
                </button>
            </div>
        </div>
    </div>
</div>