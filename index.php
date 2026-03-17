<?php require 'koneksi.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistem Inventaris Modern | INV-MANAGER</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="style.css">
</head>

<body>
<div class="app-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header d-flex align-items-center">
            <div class="bg-primary rounded p-2 me-3 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                <i class="fa-solid fa-boxes-stacked text-white fs-5"></i>
            </div>
            <div>
                <h5 class="fw-800 mb-0 text-white tracking-wide" style="letter-spacing: 1px;">INV <span class="text-primary-soft">PRO</span></h5>
                <small class="text-white-50" style="font-size: 10px;">Manajemen Sistem</small>
            </div>
        </div>

        <nav class="nav flex-column mb-auto">
            <div class="nav-label">MAIN MENU</div>
            <a class="nav-link active" onclick="showPage('dashboard', this)"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a class="nav-link" onclick="showPage('transaksi', this)" style="color: #fbbf24;"><i class="fa-solid fa-cash-register"></i> Kasir / Transaksi</a>
            
            <div class="nav-label">MASTER DATA</div>
            <a class="nav-link" onclick="showPage('produk', this)"><i class="fa-solid fa-box-open"></i> Data Produk</a>
            <a class="nav-link" onclick="showPage('kategori', this)"><i class="fa-solid fa-layer-group"></i> Kategori</a>
            <a class="nav-link" onclick="showPage('satuan', this)"><i class="fa-solid fa-weight-scale"></i> Satuan</a>
            <a class="nav-link" onclick="showPage('supplier', this)"><i class="fa-solid fa-truck-field"></i> Supplier</a>
            <a class="nav-link" onclick="showPage('konsumen', this)"><i class="fa-solid fa-users"></i> Konsumen</a>
            
            <div class="nav-label">BUKU RIWAYAT</div>
            <a class="nav-link" onclick="showPage('keluar', this)"><i class="fa-solid fa-arrow-up-right-from-square"></i> Penjualan</a>
            <a class="nav-link" onclick="showPage('masuk', this)"><i class="fa-solid fa-arrow-down-to-line"></i> Restock Masuk</a>
            <a class="nav-link" onclick="showPage('laporan', this)"><i class="fa-solid fa-chart-line"></i> Laporan</a>
            <a class="nav-link" onclick="showPage('pengaturan', this)"><i class="fa-solid fa-gear"></i> Pengaturan</a>
        </nav>
    </aside>

    <main class="main-content">
        <header class="top-nav">
            <div>
                <h4 class="fw-800 mb-1 text-dark" id="pageTitle" style="letter-spacing: -0.5px;">Dashboard</h4>
                <div class="d-flex align-items-center">
                    <div id="loader" class="spinner-grow spinner-grow-sm text-primary d-none me-2" role="status"></div>
                    <span id="realtimeDate" class="fw-600 text-muted small">Memuat tanggal...</span>
                </div>
            </div>
            <div>
                <div class="d-flex align-items-center bg-white border px-3 py-2 rounded-pill shadow-sm cursor-pointer">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=4f46e5&color=fff" alt="Admin" class="rounded-circle me-2" width="30">
                    <span class="fw-bold small text-dark me-2">Administrator</span>
                    <i class="fa-solid fa-chevron-down text-muted" style="font-size: 10px;"></i>
                </div>
            </div>
        </header>

        <div class="content-body">
            <section id="dashboard" class="page-content active">
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fw-bold mb-0">Total Produk</h6>
                                <div class="bg-primary bg-opacity-10 text-primary rounded px-2 py-1"><i class="fa-solid fa-box"></i></div>
                            </div>
                            <h2 id="dashProduk" class="fw-900 text-dark mb-0">0</h2>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fw-bold mb-0">Aset Saat Ini</h6>
                                <div class="bg-info bg-opacity-10 text-info rounded px-2 py-1"><i class="fa-solid fa-wallet"></i></div>
                            </div>
                            <h4 id="dashModal" class="fw-900 text-dark mb-0">Rp 0</h4>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fw-bold mb-0">Omset Bulan ini</h6>
                                <div class="bg-success bg-opacity-10 text-success rounded px-2 py-1"><i class="fa-solid fa-arrow-trend-up"></i></div>
                            </div>
                            <h4 id="dashOmset" class="fw-900 text-success mb-0">Rp 0</h4>
                        </div>
                    </div>
                    <div class="col-xl-3 col-sm-6">
                        <div class="card p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="text-muted fw-bold mb-0">Laba Bulan ini</h6>
                                <div class="bg-warning bg-opacity-10 text-warning rounded px-2 py-1"><i class="fa-solid fa-coins"></i></div>
                            </div>
                            <h4 id="dashProfit" class="fw-900 text-primary mb-0">Rp 0</h4>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0">Aktivitas Terbaru</h6>
                        <button class="btn btn-sm btn-light rounded-pill" onclick="refreshData()"><i class="fa-solid fa-arrows-rotate"></i> Refresh</button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <tbody id="dataRiwayatDashboard">
                                    <tr><td class="text-center py-5">Memuat data...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <div id="pageContainer">
                <?php 
                    include 'views/transaksi.php'; 
                    include 'views/produk.php'; 
                    include 'views/kategori.php'; 
                    include 'views/satuan.php'; 
                    include 'views/supplier.php'; 
                    include 'views/konsumen.php'; 
                    include 'views/barang_masuk.php'; 
                    include 'views/barang_keluar.php'; 
                    include 'views/laporan.php'; 
                    include 'views/pengaturan.php'; 
                ?>
            </div>
        </div>
    </main>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-4" style="z-index: 1055;">
    <div id="liveToast" class="toast align-items-center border-0 shadow-lg rounded-4" role="alert" style="background: white;">
        <div class="d-flex p-3">
            <div class="toast-body d-flex align-items-center text-dark p-0">
                <i class="fa-solid fa-circle-check fa-2xl me-3" id="toastIcon"></i>
                <div>
                    <h6 class="fw-bold mb-1">Notifikasi</h6>
                    <span id="toastMsg" class="fw-500 text-muted" style="font-size: 13px;">Operasi berhasil!</span>
                </div>
            </div>
            <button type="button" class="btn-close ms-auto mt-2" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'views/script.php'; ?>
</body>
</html>