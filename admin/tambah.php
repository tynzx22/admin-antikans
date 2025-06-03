<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    header("Location: login.php");
    exit;
}

// Koneksi ke database (sama seperti index.php)
$host = "localhost";
$username = "root";
$password = "";
$dbname = "antikans_billiar";

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Koneksi ke server MySQL gagal: " . $conn->connect_error);
}

$check_db = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($check_db->num_rows == 0) {
    $create_db = $conn->query("CREATE DATABASE $dbname");
    if (!$create_db) {
        die("Gagal membuat database: " . $conn->error);
    }
}
$conn->select_db($dbname);

$check_table = $conn->query("SHOW TABLES LIKE 'items'");
if ($check_table->num_rows == 0) {
    $create_table = $conn->query("
        CREATE TABLE items (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_produk VARCHAR(255) NOT NULL,
            deskripsi VARCHAR(255) NOT NULL,
            harga DECIMAL(10,2) NOT NULL,
            img TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    if (!$create_table) {
        die("Gagal membuat tabel: " . $conn->error);
    }
}

$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (isset($_POST['tambah'])) {
    $nama_produk = $conn->real_escape_string($_POST['nama_produk']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $harga = $conn->real_escape_string($_POST['harga']);

    $img = $_FILES['img']['name'];
    $img_tmp = $_FILES['img']['tmp_name'];
    $img_path = $upload_dir . basename($img);

    if (move_uploaded_file($img_tmp, $img_path)) {
        $sql = "INSERT INTO items (nama_produk, deskripsi, harga, img) VALUES ('$nama_produk', '$deskripsi', '$harga', '$img')";
        if ($conn->query($sql) === TRUE) {
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = 'Produk berhasil ditambahkan';
            header("Location: index.php");
            exit;
        } else {
            $error_msg = "Gagal menambah produk ke database: " . $conn->error;
        }
    } else {
        $error_msg = "Gagal mengupload gambar. Error code: " . $_FILES['img']['error'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Tambah Produk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" />
</head>

<body class="container mt-5">
    <h1>Tambah Produk</h1>

    <?php if (isset($error_msg)) : ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form action="tambah.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>Nama Produk</label>
            <input type="text" name="nama_produk" class="form-control" required />
        </div>
        <div class="form-group">
            <label>Deskripsi</label>
            <input type="text" name="deskripsi" class="form-control" required />
        </div>
        <div class="form-group">
            <label>Harga</label>
            <input type="number" step="0.01" name="harga" class="form-control" required />
        </div>
        <div class="form-group">
            <label>Gambar Produk</label>
            <input type="file" name="img" class="form-control-file" required />
        </div>
        <button type="submit" name="tambah" class="btn btn-primary">Tambah Produk</button>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </form>
</body>

</html>
