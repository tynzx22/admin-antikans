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

// Cek apakah tabel users ada, jika tidak, buat tabel
$check_table = $conn->query("SHOW TABLES LIKE 'users'");
if ($check_table->num_rows == 0) {
    $create_table = $conn->query("
        CREATE TABLE users (
            id INT(11) NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    if (!$create_table) {
        die("Gagal membuat tabel users: " . $conn->error);
    }
}

// Proses login
if (isset($_POST['login'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    $sql = "SELECT id, username, password FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['status'] = 'success';
            $_SESSION['message'] = 'Login berhasil';
            header("Location: index.php");
            exit;
        } else {
            $_SESSION['status'] = 'error';
            $_SESSION['message'] = 'Password salah';
        }
    } else {
        $_SESSION['status'] = 'error';
        $_SESSION['message'] = 'Username tidak ditemukan';
    }
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Antikans Billiar</title>
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
        .login-box {
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
    <div class="login-box">
        <div class="card">
            <div class="card-body">
                <h3 class="text-center mb-4">Login</h3>
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
                    <button type="submit" name="login" class="btn btn-primary btn-block">Login</button>
                </form>
                <p class="mt-3 text-center">Belum punya akun? <a href="register.php">Daftar di sini</a></p>
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