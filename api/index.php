<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$conn = new mysqli("localhost", "root", "", "db_tryout_tp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Koneksi database gagal: " . $conn->connect_error]);
    $conn->close(); 
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// 1. Method GET: Ambil data kuota sesi & seluruh daftar pendaftar
if ($method === 'GET') {
    $sesi = [];
    $resSesi = $conn->query("SELECT * FROM sesi_tryout ORDER BY id ASC");
    while ($row = $resSesi->fetch_assoc()) {
        $sesi[] = $row;
    }

    $pendaftar = [];
    $resPendaftar = $conn->query("SELECT * FROM pendaftar ORDER BY id DESC");
    while ($row = $resPendaftar->fetch_assoc()) {
        $pendaftar[] = $row;
    }

    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "sesi" => $sesi,
        "pendaftar" => $pendaftar
    ]);
}

// 2. Method POST: Tambah pendaftar baru dan kurangi kuota sesi (-1)
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);

    $nama = $conn->real_escape_string($input['nama_siswa'] ?? '');
    $provinsi = $conn->real_escape_string($input['provinsi'] ?? '');
    $sesiArr = $input['sesi_dipilih'] ?? [];
    $mapelArr = $input['mapel_pilihan'] ?? [];

    if (empty($nama) || empty($provinsi) || empty($sesiArr) || empty($mapelArr)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Semua kolom wajib diisi"]);
        exit;
    }

    $sesiStr = $conn->real_escape_string(implode(", ", $sesiArr));
    $mapelStr = $conn->real_escape_string(implode(", ", $mapelArr));

    $sql = "INSERT INTO pendaftar (nama_siswa, provinsi, sesi_dipilih, mapel_pilihan) VALUES ('$nama', '$provinsi', '$sesiStr', '$mapelStr')";

    if ($conn->query($sql)) {
        // untuk mengurangi kuota setiap sesi yang dicentang
        foreach ($sesiArr as $namaSesi) {
            $namaSesiEsc = $conn->real_escape_string($namaSesi);
            $conn->query("UPDATE sesi_tryout SET kuota_tersisa = kuota_tersisa -1 WHERE nama_sesi = '$namaSesiEsc' AND kuota_tersisa > 0");
        }
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Pendaftar berhasil disimpan"]);
    }else{
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan data"]);
    }
}

// 3. METHOD PUT : update data
elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents("php://input"), true);

    $id = intval($input['id'] ?? 0);
    $nama = $conn->real_escape_string($input['nama_siswa'] ?? '');
    $provinsi = $conn->real_escape_string($input['provinsi'] ?? '');
    $sesiStr = $conn->real_escape_string($input['sesi_dipilih'] ?? '');
    $mapelStr = $conn->real_escape_string($input['mapel_pilihan'] ?? '');

    if ($id <= 0 || empty($nama)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID dan Nama tidak valid"]);
        exit;
    }

    $sql = "UPDATE pendaftar SET nama_siswa = '$nama', provinsi = '$provinsi', sesi_dipilih = '$sesiStr', mapel_pilihan = '$mapelStr' WHERE id = $id";

    if ($conn->query($sql)) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Data pendaftar berhasil diperbarui"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal memperbarui data pendaftar"]);
    }
}

// 4. METHOD DELETE : hapus data pendaftar dari halaman Admin
elseif ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID tidak valid"]);
        exit;
    }

    if ($conn->query("DELETE FROM pendaftar WHERE id = $id")) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Data pendaftar berhasil dihapus"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal menghapus data pendaftar"]);
    }
}
?>