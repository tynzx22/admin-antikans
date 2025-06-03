<?php
session_start();

// Koneksi ke database
$host = "localhost";
$username = "root";
$password = "";
$dbname = "antikans_billiar";

$conn = new mysqli($host, $username, $password, $dbname);

// Periksa koneksi
if ($conn->connect_error) {
    die("Koneksi ke server MySQL gagal: " . $conn->connect_error);
}

// Proses registrasi
if (isset($_POST['register'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validasi password
    if ($password !== $confirm_password) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Password dan konfirmasi password tidak cocok';
        header("Location: register.php");
        exit;
    }

    // Cek apakah username sudah ada
    $sql_check = "SELECT id FROM users WHERE username = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Username sudah digunakan';
        header("Location: register.php");
        exit;
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Simpan user ke database
    $sql = "INSERT INTO users (username, password) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $username, $hashed_password);

    if ($stmt->execute()) {
        $_SESSION['status'] = 'success';
        $_SESSION['message'] = 'Registrasi berhasil. Silakan login.';
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Gagal mendaftar: ' . $conn->error;
        header("Location: register.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Antikans Billiar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@5.15.4/css/all.min.css">
    <style>
        body {
            background-image: url('bg login.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .register-box {
            width: 100%;
            max-width: 400px;
            padding: 15px;
        }
        .card {
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            background: rgba(255, 255, 255, 0.9); /* Semi-transparent white for readability */
            border-radius: 10px;
        }
        .card-body {
            padding: 2rem;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="register-box">
        <div class="card">
            <div class="card-body">
                <h3 class="text-center mb-4">Register</h3>
                <?php
                if (isset($_SESSION['status']) && isset($_SESSION['message'])) {
                    $alert_class = $_SESSION['status'] === 'success' ? 'alert-success' : 'alert-danger';
                    echo "<div class='alert $alert_class alert-dismissible'>
                            <button type='button' class='close' data-dismiss='alert' aria-hidden='true'>×</button>
                            <h5><i class='icon fas fa-info'></i> Info</h5>
                            {$_SESSION['message']}
                          </div>";
                    unset($_SESSION['status']);
                    unset($_SESSION['message']);
                }
                ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="register" class="btn btn-primary btn-block">Register</button>
                </form>
                <p class="mt-3 text-center">Sudah punya akun? <a href="login.php">Login di sini</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$conn->close();
?>