<?php
header('Content-Type: application/json');
require 'koneksi.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

function normalizeRow($row) {
    if (!$row) return null;
    $res = [];
    foreach ($row as $key => $val) {
        $parts = explode('_', strtolower($key));
        foreach ($parts as &$p) {
            if ($p === 'id') $p = 'ID';
            else if ($p === 'trx') $p = 'Trx';
            else if ($p === 'qty') $p = 'Qty';
            else $p = ucfirst($p); 
        }
        $newKey = implode('_', $parts);
        $res[$newKey] = $val;               
        $res[$key] = $val;                  
        $res[strtolower($key)] = $val;      
    }
    return $res;
}

function generateId($conn, $table, $column, $prefix) {
    $sql = "SELECT $column FROM $table ORDER BY $column DESC LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = normalizeRow($result->fetch_assoc());
        $lastId = $row[$column] ?? $row[strtolower($column)] ?? "";
        if ($lastId) {
            $num = (int) preg_replace('/[^0-9]/', '', $lastId);
            return $prefix . "-" . str_pad($num + 1, 3, "0", STR_PAD_LEFT);
        }
    }
    return $prefix . "-001";
}

switch ($action) {
    case 'getData':
        $sheet = strtolower($_GET['sheet'] ?? '');
        $data = [];
        $tables = ['produk'=>'produk', 'kategori'=>'kategori', 'satuan'=>'satuan', 'supplier'=>'supplier', 'konsumen'=>'konsumen', 'barang_masuk'=>'barang_masuk', 'barang_keluar'=>'barang_keluar'];
        if (array_key_exists($sheet, $tables)) {
            $tableName = $tables[$sheet];
            $result = $conn->query("SELECT * FROM $tableName");
            if ($result) { while($row = $result->fetch_assoc()) { $data[] = normalizeRow($row); } }
        }
        echo json_encode($data);
        break;

    case 'getDashboard':
        $bulanIni = date('Y-m'); // Mengambil tahun-bulan saat ini

        // Total Produk & Aset tetap dihitung keseluruhan
        $produk = $conn->query("SELECT COUNT(*) as total FROM produk")->fetch_assoc()['total'] ?? 0;
        $modalQ = $conn->query("SELECT SUM(Stok_Saat_Ini * Harga_Beli) as total FROM produk");
        $modal = $modalQ ? ($modalQ->fetch_assoc()['total'] ?? 0) : 0;

        // TOTAL OMSET & PROFIT DIFILTER HANYA BULAN INI (Agar sinkron dengan Laporan)
        $omsetQ = $conn->query("SELECT SUM(Total_Jual) as total FROM barang_keluar WHERE Tanggal LIKE '$bulanIni%'");
        $omset = $omsetQ ? ($omsetQ->fetch_assoc()['total'] ?? 0) : 0;

        $profitQ = $conn->query("SELECT SUM(Profit) as total FROM barang_keluar WHERE Tanggal LIKE '$bulanIni%'");
        $profit = $profitQ ? ($profitQ->fetch_assoc()['total'] ?? 0) : 0;

        // Riwayat tetap mengambil 5 data terbaru tanpa filter bulan
        $sqlRiwayat = "SELECT Tanggal as tanggal, 'Masuk' as jenis, ID_Trx_Masuk as idTrx, ID_Produk as idProduk, Qty as qty, ID_Supplier as pihak FROM barang_masuk UNION ALL SELECT Tanggal as tanggal, 'Keluar' as jenis, ID_Trx_Keluar as idTrx, ID_Produk as idProduk, Qty as qty, ID_Konsumen as pihak FROM barang_keluar ORDER BY tanggal DESC LIMIT 5"; $resRiwayat = $conn->query($sqlRiwayat);
        $riwayat = [];
        if ($resRiwayat) {
            while($r = $resRiwayat->fetch_assoc()) {
                $idP = $r['idProduk'] ?? $r['ID_Produk'] ?? $r['id_produk'] ?? '';
                $pQuery = $conn->query("SELECT * FROM produk WHERE ID_Produk='$idP' OR id_produk='$idP'");
                $p = $pQuery ? normalizeRow($pQuery->fetch_assoc()) : null;
                $r['namaBarang'] = $p['Nama_Barang'] ?? $idP; $r['kategori'] = $p['Kategori'] ?? '-'; $r['satuan'] = $p['Satuan'] ?? '';
                if ($r['jenis'] == 'Masuk') {
                    $r['total'] = ((int)($r['qty'] ?? 0)) * ((float)($p['Harga_Beli'] ?? 0));
                    $supQ = $conn->query("SELECT * FROM supplier WHERE ID_Supplier='{$r['pihak']}' OR id_supplier='{$r['pihak']}'");
                    $sup = $supQ ? normalizeRow($supQ->fetch_assoc()) : null; $r['pihak'] = $sup['Nama_Supplier'] ?? $r['pihak'];
                } else {
                    $tj = $conn->query("SELECT Total_Jual, total_jual FROM barang_keluar WHERE ID_Trx_Keluar='{$r['idTrx']}' OR id_trx_keluar='{$r['idTrx']}'")->fetch_assoc();
                    $r['total'] = $tj['Total_Jual'] ?? $tj['total_jual'] ?? 0;
                    $konQ = $conn->query("SELECT * FROM konsumen WHERE ID_Konsumen='{$r['pihak']}' OR id_konsumen='{$r['pihak']}'");
                    $kon = $konQ ? normalizeRow($konQ->fetch_assoc()) : null; $r['pihak'] = $kon['Nama_Konsumen'] ?? $r['pihak'];
                }
                $riwayat[] = $r;
            }
        }
        echo json_encode(["produk" => $produk, "modal" => $modal, "omset" => $omset, "profit" => $profit, "riwayat" => $riwayat]);
        break;

    case 'getPengaturan':
        $res = $conn->query("SELECT * FROM pengaturan"); $data = [];
        if ($res && $res->num_rows > 0) { while($row = $res->fetch_assoc()) { $data[$row['pengaturan_key'] ?? $row['PENGATURAN_KEY']] = $row['pengaturan_value'] ?? $row['PENGATURAN_VALUE']; } }
        echo json_encode($data); break;

    case 'simpanPengaturan':
        $d = $input['data'] ?? []; $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO pengaturan (pengaturan_key, pengaturan_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE pengaturan_value = ?");
            foreach($d as $key => $val) { $k = $key; $v = $val; $stmt->bind_param("sss", $k, $v, $v); $stmt->execute(); }
            $conn->commit(); echo json_encode(["status" => "success", "message" => "Pengaturan berhasil disimpan!"]);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    case 'tambahDataKeSheet':
        $sheet = $input['data']['sheet'] ?? $input['sheet'] ?? ''; $d = $input['data']['data'] ?? $input['data'] ?? [];
        try {
            if ($sheet === 'Produk') {
                $id = generateId($conn, 'produk', 'ID_Produk', 'PRD');
                $nama = $d['namaBarang']; $kat = $d['kategori']; $sup = $d['idSupplier']; $hb = (float)$d['hargaBeli']; $hj = (float)$d['hargaJual']; $sat = $d['satuan'];
                $stmt = $conn->prepare("INSERT INTO produk (ID_Produk, Nama_Barang, Kategori, ID_Supplier, Harga_Beli, Harga_Jual, Satuan, Stok_Awal, Stok_Saat_Ini) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0)");
                $stmt->bind_param("ssssdds", $id, $nama, $kat, $sup, $hb, $hj, $sat); $stmt->execute();
            } 
            else if ($sheet === 'Kategori') {
                $id = generateId($conn, 'kategori', 'ID_Kategori', 'CAT'); $nama = $d['namaKategori'];
                $stmt = $conn->prepare("INSERT INTO kategori (ID_Kategori, Nama_Kategori) VALUES (?, ?)"); $stmt->bind_param("ss", $id, $nama); $stmt->execute();
            }
            else if ($sheet === 'Satuan') {
                $id = generateId($conn, 'satuan', 'ID_Satuan', 'UNT'); $nama = $d['namaSatuan'];
                $stmt = $conn->prepare("INSERT INTO satuan (ID_Satuan, Nama_Satuan) VALUES (?, ?)"); $stmt->bind_param("ss", $id, $nama); $stmt->execute();
            }
            else if ($sheet === 'Supplier') {
                $id = generateId($conn, 'supplier', 'ID_Supplier', 'SUP'); $nama = $d['namaSupplier']; $kat = $d['kategori']; $kon = $d['kontak']; $alm = $d['alamat'];
                $stmt = $conn->prepare("INSERT INTO supplier (ID_Supplier, Nama_Supplier, Kategori, Kontak, Alamat) VALUES (?, ?, ?, ?, ?)"); $stmt->bind_param("sssss", $id, $nama, $kat, $kon, $alm); $stmt->execute();
            }
            else if ($sheet === 'Konsumen') {
                $id = generateId($conn, 'konsumen', 'ID_Konsumen', 'CUS'); $nama = $d['namaKonsumen']; $kon = $d['kontak']; $alm = $d['alamat'];
                $stmt = $conn->prepare("INSERT INTO konsumen (ID_Konsumen, Nama_Konsumen, Kontak, Alamat) VALUES (?, ?, ?, ?)"); $stmt->bind_param("ssss", $id, $nama, $kon, $alm); $stmt->execute();
            }
            echo json_encode(["status" => "success", "message" => "Data berhasil ditambahkan!"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    case 'updateDataSheet':
        $sheet = $input['data']['sheet'] ?? $input['sheet'] ?? '';
        $id = $input['data']['id'] ?? $input['id'] ?? '';
        $d = $input['data']['data'] ?? $input['data'] ?? [];

        try {
            if ($sheet === 'Produk') {
                $nama = $d['namaBarang']; $kat = $d['kategori']; $sup = $d['idSupplier']; $hb = (float)$d['hargaBeli']; $hj = (float)$d['hargaJual']; $sat = $d['satuan'];
                $stmt = $conn->prepare("UPDATE produk SET Nama_Barang=?, Kategori=?, ID_Supplier=?, Harga_Beli=?, Harga_Jual=?, Satuan=? WHERE ID_Produk=? OR id_produk=?");
                $stmt->bind_param("sssddsss", $nama, $kat, $sup, $hb, $hj, $sat, $id, $id); $stmt->execute();
            } 
            else if ($sheet === 'Kategori') {
                $nama = $d['namaKategori']; $stmt = $conn->prepare("UPDATE kategori SET Nama_Kategori=? WHERE ID_Kategori=? OR id_kategori=?");
                $stmt->bind_param("sss", $nama, $id, $id); $stmt->execute();
            }
            else if ($sheet === 'Satuan') {
                $nama = $d['namaSatuan']; $stmt = $conn->prepare("UPDATE satuan SET Nama_Satuan=? WHERE ID_Satuan=? OR id_satuan=?");
                $stmt->bind_param("sss", $nama, $id, $id); $stmt->execute();
            }
            else if ($sheet === 'Supplier') {
                $nama = $d['namaSupplier']; $kat = $d['kategori']; $kon = $d['kontak']; $alm = $d['alamat'];
                $stmt = $conn->prepare("UPDATE supplier SET Nama_Supplier=?, Kategori=?, Kontak=?, Alamat=? WHERE ID_Supplier=? OR id_supplier=?");
                $stmt->bind_param("ssssss", $nama, $kat, $kon, $alm, $id, $id); $stmt->execute();
            }
            else if ($sheet === 'Konsumen') {
                $nama = $d['namaKonsumen']; $kon = $d['kontak']; $alm = $d['alamat'];
                $stmt = $conn->prepare("UPDATE konsumen SET Nama_Konsumen=?, Kontak=?, Alamat=? WHERE ID_Konsumen=? OR id_konsumen=?");
                $stmt->bind_param("sssss", $nama, $kon, $alm, $id, $id); $stmt->execute();
            }
            
            // --- EDIT BARANG MASUK (DENGAN PENGHAPUS FILE LAMA) ---
            else if ($sheet === 'Barang_Masuk') {
                $qtyBaru = (int)$d['qty'];
                $tanggalBaru = $d['tanggal'];
                $idProdukBaru = $d['produk'];
                $idSupplierBaru = $d['pihak'];
                
                $fileUrlSql = "";
                $fileUrlParam = "";

                // 1. Logika Jika Ada Upload Nota Baru
                if (!empty($d['fileData'])) {
                    // --- MULAI PROSES HAPUS FILE LAMA ---
                    $oldQuery = $conn->query("SELECT Bukti_Nota FROM barang_masuk WHERE ID_Trx_Masuk='$id' OR id_trx_masuk='$id'")->fetch_assoc();
                    if ($oldQuery && !empty($oldQuery['Bukti_Nota'])) {
                        // Ubah URL Web menjadi Path Folder Lokal (Contoh: http://localhost/inv/uploads/file.jpg -> uploads/file.jpg)
                        $oldPath = str_replace("http://" . $_SERVER['HTTP_HOST'] . "/inventaris/", "", $oldQuery['Bukti_Nota']);
                        if (file_exists($oldPath)) {
                            unlink($oldPath); // Perintah hapus file fisik
                        }
                    }
                    // --- SELESAI HAPUS FILE LAMA ---

                    $decoded = base64_decode($d['fileData']);
                    $fileName = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $d['fileName']);
                    $dir = __DIR__ . '/uploads/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    file_put_contents($dir . $fileName, $decoded);
                    
                    $fileUrlParam = "http://" . $_SERVER['HTTP_HOST'] . "/inventaris/uploads/" . $fileName; 
                    $fileUrlSql = ", Bukti_Nota=?";
                }
                
                $lamaData = $conn->query("SELECT Qty, ID_Produk FROM barang_masuk WHERE ID_Trx_Masuk='$id' OR id_trx_masuk='$id'")->fetch_assoc();
                if($lamaData) {
                    $lama = normalizeRow($lamaData);
                    $qtyLama = (int)$lama['Qty'];
                    $idProdukLama = $lama['ID_Produk'];
                    
                    // Ambil kategori terbaru dari produk terpilih
                    $katQ = $conn->query("SELECT Kategori FROM produk WHERE ID_Produk='$idProdukBaru' OR id_produk='$idProdukBaru'")->fetch_assoc();
                    $kategoriBaru = $katQ['Kategori'] ?? $katQ['kategori'] ?? '-';

                    // 2. Update Database
                    if ($fileUrlParam !== "") {
                        $stmt = $conn->prepare("UPDATE barang_masuk SET Tanggal=?, ID_Produk=?, Kategori=?, ID_Supplier=?, Qty=? $fileUrlSql WHERE ID_Trx_Masuk=? OR id_trx_masuk=?");
                        $stmt->bind_param("ssssisss", $tanggalBaru, $idProdukBaru, $kategoriBaru, $idSupplierBaru, $qtyBaru, $fileUrlParam, $id, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE barang_masuk SET Tanggal=?, ID_Produk=?, Kategori=?, ID_Supplier=?, Qty=? WHERE ID_Trx_Masuk=? OR id_trx_masuk=?");
                        $stmt->bind_param("ssssiss", $tanggalBaru, $idProdukBaru, $kategoriBaru, $idSupplierBaru, $qtyBaru, $id, $id);
                    }
                    $stmt->execute();

                    // 3. Update Stok Gudang (Sangat Penting)
                    if ($idProdukBaru === $idProdukLama) {
                        $selisih = $qtyBaru - $qtyLama; 
                        if ($selisih != 0) $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini + $selisih WHERE ID_Produk='$idProdukLama' OR id_produk='$idProdukLama'");
                    } else {
                        // Jika ganti produk: Stok produk lama dikurangi, stok produk baru ditambah
                        $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini - $qtyLama WHERE ID_Produk='$idProdukLama' OR id_produk='$idProdukLama'");
                        $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini + $qtyBaru WHERE ID_Produk='$idProdukBaru' OR id_produk='$idProdukBaru'");
                    }
                }
            }
            
            // --- EDIT FULL TRANSAKSI KELUAR ---
            else if ($sheet === 'Barang_Keluar') {
                $qtyBaru = (int)$d['qty']; $tanggalBaru = $d['tanggal']; $idProdukBaru = $d['produk']; 
                $idKonsumenBaru = $d['pihak']; $hargaJualSatuan = (float)$d['harga'];

                $lamaData = $conn->query("SELECT * FROM barang_keluar WHERE ID_Trx_Keluar='$id' OR id_trx_keluar='$id'")->fetch_assoc();
                if($lamaData) {
                    $lama = normalizeRow($lamaData);
                    $qtyLama = (int)$lama['Qty']; $idProdukLama = $lama['ID_Produk'];
                    
                    $pData = $conn->query("SELECT Harga_Beli, Kategori FROM produk WHERE ID_Produk='$idProdukBaru' OR id_produk='$idProdukBaru'")->fetch_assoc();
                    $p = normalizeRow($pData);
                    
                    $hBeli = (float)($p['Harga_Beli'] ?? 0);
                    $kategoriBaru = $p['Kategori'] ?? '-';
                    $totalJualBaru = $hargaJualSatuan * $qtyBaru;
                    $profitBaru = ($hargaJualSatuan - $hBeli) * $qtyBaru;

                    $stmt = $conn->prepare("UPDATE barang_keluar SET Tanggal=?, ID_Produk=?, Kategori=?, ID_Konsumen=?, Qty=?, Total_Jual=?, Profit=? WHERE ID_Trx_Keluar=? OR id_trx_keluar=?");
                    $stmt->bind_param("ssssiddss", $tanggalBaru, $idProdukBaru, $kategoriBaru, $idKonsumenBaru, $qtyBaru, $totalJualBaru, $profitBaru, $id, $id);
                    $stmt->execute();

                    if ($idProdukBaru === $idProdukLama) {
                        $selisih = $qtyBaru - $qtyLama; 
                        if ($selisih != 0) $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini - $selisih WHERE ID_Produk='$idProdukLama' OR id_produk='$idProdukLama'");
                    } else {
                        $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini + $qtyLama WHERE ID_Produk='$idProdukLama' OR id_produk='$idProdukLama'");
                        $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini - $qtyBaru WHERE ID_Produk='$idProdukBaru' OR id_produk='$idProdukBaru'");
                    }
                }
            }
            
            echo json_encode(["status" => "success", "message" => "Perubahan berhasil disimpan!"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    // =========================================================
    // 5. HAPUS DATA (DELETE & PEMBERSIH FILE & RESET STOK)
    // =========================================================
    case 'hapusDataSheet':
        $sheet = strtolower($input['data']['sheet'] ?? $input['sheet'] ?? '');
        $id = $input['data']['id'] ?? $input['id'] ?? '';
        
        $pkMap = [
            'produk' => 'ID_Produk', 'kategori' => 'ID_Kategori', 'satuan' => 'ID_Satuan',
            'supplier' => 'ID_Supplier', 'konsumen' => 'ID_Konsumen',
            'barang_masuk' => 'ID_Trx_Masuk', 'barang_keluar' => 'ID_Trx_Keluar'
        ];

        if (!array_key_exists($sheet, $pkMap)) {
            echo json_encode(["status" => "error", "message" => "Tabel tidak ditemukan"]);
            exit;
        }
        $pk = $pkMap[$sheet];

        try {
            // --- LOGIKA KHUSUS SEBELUM DATA DIHAPUS ---
            
            if($sheet == 'barang_masuk') {
                $trxData = $conn->query("SELECT * FROM barang_masuk WHERE $pk='$id' OR LOWER($pk)='$id'")->fetch_assoc();
                if($trxData) {
                    $trx = normalizeRow($trxData);
                    
                    // 1. Kembalikan/Kurangi Stok Produk
                    $qty = (int)$trx['Qty'];
                    $idP = $trx['ID_Produk'];
                    $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini - $qty WHERE ID_Produk='$idP' OR id_produk='$idP'");
                    
                    // 2. HAPUS FILE NOTA FISIK DARI FOLDER UPLOADS
                    if (!empty($trx['Bukti_Nota'])) {
                        $oldPath = str_replace("http://" . $_SERVER['HTTP_HOST'] . "/inventaris/", "", $trx['Bukti_Nota']);
                        if (file_exists($oldPath)) {
                            unlink($oldPath); // File dihapus permanen dari hosting/server
                        }
                    }
                }
            } 
            else if ($sheet == 'barang_keluar') {
                $trxData = $conn->query("SELECT * FROM barang_keluar WHERE $pk='$id' OR LOWER($pk)='$id'")->fetch_assoc();
                if($trxData) {
                    $trx = normalizeRow($trxData);
                    // Kembalikan Stok Produk (karena batal jual, stok nambah lagi)
                    $qty = (int)$trx['Qty'];
                    $idP = $trx['ID_Produk'];
                    $conn->query("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini + $qty WHERE ID_Produk='$idP' OR id_produk='$idP'");
                }
            }

            // --- EKSEKUSI HAPUS DATA DARI DATABASE ---
            $stmt = $conn->prepare("DELETE FROM $sheet WHERE $pk=? OR LOWER($pk)=?");
            $stmt->bind_param("ss", $id, $id);
            $stmt->execute();
            
            echo json_encode(["status" => "success", "message" => "Data dan file terkait berhasil dihapus permanen!"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'simpanBarangMasukMulti':
        $d = $input['data'] ?? []; $conn->begin_transaction();
        try {
            $fileUrl = "";
            if (!empty($d['fileData'])) {
                $decoded = base64_decode($d['fileData']); $fileName = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $d['fileName']);
                $dir = __DIR__ . '/uploads/'; if (!is_dir($dir)) mkdir($dir, 0777, true); file_put_contents($dir . $fileName, $decoded);
                $fileUrl = "http://" . $_SERVER['HTTP_HOST'] . "/inventaris/uploads/" . $fileName; 
            }
            $idTrx = generateId($conn, 'barang_masuk', 'ID_Trx_Masuk', 'TRM'); $tanggal = $d['tanggal']; $idSup = $d['idSupplier'];
            $stmtInsert = $conn->prepare("INSERT INTO barang_masuk (ID_Trx_Masuk, Tanggal, ID_Produk, Kategori, ID_Supplier, Qty, Bukti_Nota) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtUpdateStok = $conn->prepare("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini + ? WHERE ID_Produk=? OR id_produk=?");
            foreach($d['cart'] as $item) {
                $idProduk = $item['idProduk']; $qty = (int)$item['qty'];
                $kategoriData = $conn->query("SELECT Kategori, kategori FROM produk WHERE ID_Produk='$idProduk' OR id_produk='$idProduk'")->fetch_assoc();
                $kategori = $kategoriData['Kategori'] ?? $kategoriData['kategori'] ?? '-';
                $stmtInsert->bind_param("sssssis", $idTrx, $tanggal, $idProduk, $kategori, $idSup, $qty, $fileUrl); $stmtInsert->execute();
                $stmtUpdateStok->bind_param("iss", $qty, $idProduk, $idProduk); $stmtUpdateStok->execute();
            }
            $conn->commit(); echo json_encode(["status" => "success", "message" => "Restock berhasil disimpan!"]);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    case 'simpanBarangKeluarMulti':
        $d = $input['data'] ?? []; $conn->begin_transaction();
        try {
            $idTrx = generateId($conn, 'barang_keluar', 'ID_Trx_Keluar', 'TRK'); $tanggal = $d['tanggal']; $idKon = $d['idKonsumen'];
            $stmtInsert = $conn->prepare("INSERT INTO barang_keluar (ID_Trx_Keluar, Tanggal, ID_Produk, Kategori, ID_Konsumen, Qty, Total_Jual, Profit, Link_Invoice, Link_Surat_Jalan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtUpdateStok = $conn->prepare("UPDATE produk SET Stok_Saat_Ini = Stok_Saat_Ini - ? WHERE ID_Produk=? OR id_produk=?");
            $urlInv = "http://" . $_SERVER['HTTP_HOST'] . "/inventaris/invoices/INV_$idTrx.html"; $urlSJ = "http://" . $_SERVER['HTTP_HOST'] . "/inventaris/invoices/SJ_$idTrx.html";
            
            foreach($d['cart'] as $item) {
                $idProduk = $item['idProduk']; $qty = (int)$item['qty']; $subtotal = (float)$item['subtotal'];
                $pData = $conn->query("SELECT * FROM produk WHERE ID_Produk='$idProduk' OR id_produk='$idProduk'")->fetch_assoc();
                $p = normalizeRow($pData);
                if($p['Stok_Saat_Ini'] < $qty) throw new Exception("Stok produk {$item['nama']} tidak mencukupi!");
                $hBeli = (float)($p['Harga_Beli'] ?? 0); $profit = ($item['harga'] - $hBeli) * $qty; $kategori = $p['Kategori'] ?? '-';
                
                $stmtInsert->bind_param("sssssiddds", $idTrx, $tanggal, $idProduk, $kategori, $idKon, $qty, $subtotal, $profit, $urlInv, $urlSJ); $stmtInsert->execute();
                $stmtUpdateStok->bind_param("iss", $qty, $idProduk, $idProduk); $stmtUpdateStok->execute();
            }
            $dirInv = __DIR__ . '/invoices/'; if (!is_dir($dirInv)) mkdir($dirInv, 0777, true);
            file_put_contents($dirInv . "INV_$idTrx.html", "<h2 style='font-family:sans-serif;'>Invoice: $idTrx</h2><p style='font-family:sans-serif; color:gray;'>Tekan Ctrl+P untuk cetak dokumen ini.</p><script>window.print()</script>");
            file_put_contents($dirInv . "SJ_$idTrx.html", "<h2 style='font-family:sans-serif;'>Surat Jalan: $idTrx</h2><p style='font-family:sans-serif; color:gray;'>Tekan Ctrl+P untuk cetak dokumen ini.</p><script>window.print()</script>");

            $conn->commit(); echo json_encode(["status" => "success", "message" => "Penjualan berhasil!", "urls" => ["urlInv" => $urlInv, "urlSJ" => $urlSJ]]);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    default: echo json_encode(["status" => "error", "message" => "Action tidak valid"]); break;
}
?>