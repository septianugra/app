<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_inventaris";

// Membuat koneksi
$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Koneksi Database Gagal: " . $conn->connect_error]));
}

// Set zona waktu ke Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');
?>