<?php
session_start();

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['status'] = 'error';
    $_SESSION['message'] = 'Silakan login terlebih dahulu';
    header("Location: login.php");
    exit;
}

// Koneksi ke database
$host = "localhost";
$username = "root";
$password = "";
$dbname = "antikans_billiar";

// Coba koneksi ke MySQL tanpa memilih database dulu
$conn = new mysqli($host, $username, $password);

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi ke server MySQL gagal: " . $conn->connect_error);
}

// Cek database, buat jika tidak ada
$check_db = $conn->query("SHOW DATABASES LIKE '$dbname'");
if ($check_db->num_rows == 0) {
    $create_db = $conn->query("CREATE DATABASE $dbname");
    if (!$create_db) {
        die("Gagal membuat database: " . $conn->error);
    }
}
$conn->select_db($dbname);

// Cek tabel items, buat jika tidak ada
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

// Direktori upload
$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Fungsi Create
if (isset($_POST['tambah'])) {
    $debug_messages = [];
    $nama_produk = $conn->real_escape_string($_POST['nama_produk']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $harga = $conn->real_escape_string($_POST['harga']);

    $img = $_FILES['img']['name'];
    $img_tmp = $_FILES['img']['tmp_name'];
    $img_path = $upload_dir . basename($img);

    $debug_messages[] = "Nama File: " . $img;
    $debug_messages[] = "Temporary File: " . $img_tmp;
    $debug_messages[] = "Target Path: " . $img_path;

    $status = 'error';
    $message = '';

    if (move_uploaded_file($img_tmp, $img_path)) {
        $debug_messages[] = "Upload berhasil";
        $sql = "INSERT INTO items (nama_produk, deskripsi, harga, img) VALUES ('$nama_produk', '$deskripsi', '$harga', '$img')";
        if ($conn->query($sql) === TRUE) {
            $debug_messages[] = "Data berhasil dimasukkan ke database";
            $status = 'success';
            $message = 'Produk berhasil ditambahkan';
        } else {
            $debug_messages[] = "Gagal menambah data: " . $conn->error;
            $message = 'Gagal menambah produk ke database';
        }
    } else {
        $debug_messages[] = "Gagal mengupload gambar. Error: " . $_FILES['img']['error'];
        $message = 'Gagal mengupload gambar. Error code: ' . $_FILES['img']['error'];
    }

    error_log("Create Product Debug: " . implode(" | ", $debug_messages));

    $_SESSION['debug_messages'] = $debug_messages;
    $_SESSION['status'] = $status;
    $_SESSION['message'] = $message;

    header("Location: index.php");
    exit;
}

// Fungsi Update
if (isset($_POST['update'])) {
    $id = $conn->real_escape_string($_POST['id']);
    $nama_produk = $conn->real_escape_string($_POST['nama_produk']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $harga = $conn->real_escape_string($_POST['harga']);

    $sql_old = "SELECT img FROM items WHERE id='$id'";
    $old_result = $conn->query($sql_old);
    $old_data = $old_result->fetch_assoc();
    $old_img = $old_data['img'];

    if (!empty($_FILES['img']['name'])) {
        $img = $_FILES['img']['name'];
        $img_tmp = $_FILES['img']['tmp_name'];
        $img_path = $upload_dir . basename($img);

        if (move_uploaded_file($img_tmp, $img_path)) {
            if (file_exists($upload_dir . $old_img)) {
                unlink($upload_dir . $old_img);
            }
        } else {
            die("Gagal mengupload gambar.");
        }
    } else {
        $img = $old_img;
    }

    $sql = "UPDATE items SET nama_produk='$nama_produk', deskripsi='$deskripsi', harga='$harga', img='$img' WHERE id='$id'";
    if (!$conn->query($sql)) {
        die("Gagal mengupdate data: " . $conn->error);
    }
    header("Location: index.php");
    exit;
}

// Fungsi Delete
if (isset($_GET['delete'])) {
    $id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        error_log("Invalid ID provided for deletion: " . (isset($_GET['delete']) ? $_GET['delete'] : 'null'));
        header("Location: index.php?status=error&message=ID produk tidak valid");
        exit;
    }

    error_log("Attempting to delete product with ID: $id");
    $conn->begin_transaction();

    try {
        $check_table = $conn->query("SHOW TABLES LIKE 'items'");
        if ($check_table->num_rows == 0) {
            throw new Exception("Tabel items tidak ditemukan di database");
        }

        $sql_img = "SELECT img FROM items WHERE id = ?";
        $stmt_img = $conn->prepare($sql_img);
        if (!$stmt_img) {
            throw new Exception("Gagal menyiapkan query untuk gambar: " . $conn->error);
        }
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $img_result = $stmt_img->get_result();

        if ($img_result->num_rows === 0) {
            $id_list = $conn->query("SELECT id FROM items");
            $ids = [];
            while ($row = $id_list->fetch_assoc()) {
                $ids[] = $row['id'];
            }
            error_log("Product with ID $id not found. Available IDs: " . implode(", ", $ids));
            throw new Exception("Produk dengan ID $id tidak ditemukan");
        }

        $img_data = $img_result->fetch_assoc();
        $img_file = $img_data['img'];

        if (!empty($img_file) && file_exists($upload_dir . $img_file)) {
            if (!unlink($upload_dir . $img_file)) {
                error_log("Failed to delete image file: $img_file");
                throw new Exception("Gagal menghapus file gambar: $img_file");
            }
        } else {
            error_log("Image file not found or empty for ID $id: $img_file");
        }

        $sql = "DELETE FROM items WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Gagal menyiapkan query untuk penghapusan: " . $conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception("Tidak ada data yang dihapus untuk ID: $id");
        }

        $conn->commit();
        error_log("Successfully deleted product with ID: $id");

        $stmt_img->close();
        $stmt->close();

        header("Location: index.php?status=deleted");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Delete error: " . $e->getMessage());
        header("Location: index.php?status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
}

// Fungsi Read
$sql = "SELECT * FROM items";
$result = $conn->query($sql);
if (!$result) {
    die("Gagal mengambil data: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Data Produk</title>
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css" />
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css" />

    <style>
        .profile-pic-large {
            width: 350px;
            height: 350px;
            object-fit: cover;
            border-radius: 50%;
            display: block;
            margin: 0 auto;
        }

        .user-panel .image {
            width: auto !important;
            height: auto !important;
        }

        @media (max-width: 768px) {
            .profile-pic-large {
                width: 250px;
                height: 250px;
            }
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <!-- Brand Logo -->
            <a href="index.php" class="brand-link">
                <i class="fas fa-store text-white ml-3 mr-2"></i>
                <span class="brand-text font-weight-light">Manajemen Produk</span>
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image">
                        <img src="profill admin.jpg" class="img-circle elevation-2" alt="User Image" />
                    </div>
                    <div class="info">
                        <a href="#" class="d-block">Admin</a>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" role="menu">
                        <li class="nav-item">
                            <a href="index.php" class="nav-link active">
                                <i class="nav-icon fas fa-box"></i>
                                <p>Produk</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="logout.php" class="nav-link">
                                <i class="nav-icon fas fa-sign-out-alt text-danger"></i>
                                <p>Logout</p>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </aside>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <section class="content pt-4">
                <div class="container">
                    <?php if (isset($_SESSION['status'])) : ?>
                        <div class="alert alert-<?php echo $_SESSION['status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($_SESSION['message']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <?php unset($_SESSION['status'], $_SESSION['message']); ?>
                    <?php endif; ?>

                    <h1 class="mb-4">Data Produk</h1>

                    <!-- Button to trigger modal tambah produk -->
                    <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#tambahModal">
                        <i class="fas fa-plus"></i> Tambah Produk
                    </button>

                    <table class="table table-bordered table-striped table-hover">
                        <thead class="thead-dark text-center">
                            <tr>
                                <th>#</th>
                                <th>Nama Produk</th>
                                <th>Deskripsi</th>
                                <th>Harga</th>
                                <th>Gambar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0) : ?>
                                <?php $no = 1; ?>
                                <?php while ($row = $result->fetch_assoc()) : ?>
                                    <tr>
                                        <td class="text-center"><?php echo $no++; ?></td>
                                        <td><?php echo htmlspecialchars($row['nama_produk']); ?></td>
                                        <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                                        <td class="text-right">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($row['img']) && file_exists($upload_dir . $row['img'])) : ?>
                                                <img src="<?php echo $upload_dir . $row['img']; ?>" alt="gambar" width="70" height="70" style="object-fit: cover; border-radius: 5px;">
                                            <?php else : ?>
                                                <span class="text-muted">Tidak ada gambar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center">
                                                <button class="btn btn-warning btn-sm me-3" data-toggle="modal" data-target="#editModal"
                                                    onclick='editData(<?php echo $row["id"]; ?>, "<?php echo addslashes(htmlspecialchars($row["nama_produk"])); ?>", "<?php echo addslashes(htmlspecialchars($row["deskripsi"])); ?>", "<?php echo $row["harga"]; ?>", "<?php echo addslashes(htmlspecialchars($row["img"])); ?>")'
                                                    title="Edit Produk">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="index.php?delete=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus produk ini?')" title="Hapus Produk">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="6" class="text-center">Tidak ada data produk.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Modal Tambah Produk -->
        <div class="modal fade" id="tambahModal" tabindex="-1" role="dialog" aria-labelledby="tambahModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="index.php" method="POST" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tambahModalLabel">Tambah Produk</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nama_produk">Nama Produk</label>
                            <input type="text" name="nama_produk" id="nama_produk" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi</label>
                            <input type="text" name="deskripsi" id="deskripsi" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga (Rp)</label>
                            <input type="number" name="harga" id="harga" class="form-control" required min="0" />
                        </div>
                        <div class="form-group">
                            <label for="img">Gambar Produk</label>
                            <input type="file" name="img" id="img" class="form-control-file" required accept="image/*" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="tambah" class="btn btn-primary">Tambah</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Edit Produk -->
        <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form action="index.php" method="POST" enctype="multipart/form-data" class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Produk</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="edit_id" />
                        <div class="form-group">
                            <label for="edit_nama_produk">Nama Produk</label>
                            <input type="text" name="nama_produk" id="edit_nama_produk" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="edit_deskripsi">Deskripsi</label>
                            <input type="text" name="deskripsi" id="edit_deskripsi" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="edit_harga">Harga (Rp)</label>
                            <input type="number" name="harga" id="edit_harga" class="form-control" required min="0" />
                        </div>
                        <div class="form-group">
                            <label for="edit_img">Gambar Produk (biarkan kosong jika tidak ingin mengganti)</label>
                            <input type="file" name="img" id="edit_img" class="form-control-file" accept="image/*" />
                            <small class="form-text text-muted" id="current_image_text"></small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="update" class="btn btn-success">Update</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function editData(id, nama_produk, deskripsi, harga, img) {
                $('#edit_id').val(id);
                $('#edit_nama_produk').val(nama_produk);
                $('#edit_deskripsi').val(deskripsi);
                $('#edit_harga').val(harga);
                if (img) {
                    $('#current_image_text').text("Gambar saat ini: " + img);
                } else {
                    $('#current_image_text').text("Tidak ada gambar saat ini");
                }
            }
        </script>
</body>

</html>