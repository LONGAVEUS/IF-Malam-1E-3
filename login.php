<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'koneksi.php'; 

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nim_input = trim($_POST['nim'] ?? ''); 
    $password_input = trim($_POST['password'] ?? '');

    if (empty($nim_input) || empty($password_input)) {
        $error_message = "NIM dan Password harus diisi.";
    } else {
        $sql = "SELECT user_id, nim, password, role, full_name, photo, is_active, creat_at
        FROM user WHERE nim = ?";

        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $nim_input);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $password_db = $user['password'];

                if ($password_input === $password_db) {


                    
                    // --- PERBAIKAN 3: Memeriksa Status Aktif ---
                    if ($user['is_active'] == 1) {
                        
                        // --- PERBAIKAN 4: Menggunakan 'user_id' & Menyimpan 'full_name' ---
                        $_SESSION['logged_in'] = true;
                        $_SESSION['user_id'] = $user['user_id']; 
                        $_SESSION['nim'] = $user['nim'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['username'] = $user['full_name']; // Menyimpan full_name ke sesi
                        $_SESSION['photo'] = $user['photo'];
                        
                        error_log("Login successful for: " . $user['nim']);

                        if ($user['role'] === 'admin') {
                            header("Location:admin.php");
                            exit();
                        }
                        elseif ($user['role'] === 'notulis') {
                            header("Location:notulis.php");
                            exit();
                        }
                        else {
                            header("Location: tamu.php");
                           exit();
                        }
                        header("Location: admin.php");
                        exit();
                    } else {
                        $error_message = "Akun Anda tidak aktif. Hubungi administrator.";
                    }
                } else {
                    $error_message = "Password anda salah.";
                }
            } else {
                $error_message = "NIM tidak ditemukan.";
            }
            
            $stmt->close();
        } else {
            $error_message = "Error prepare statement: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - NotulenPro</title>
    <link rel="stylesheet" href="login.css" />
</head>
<body>
    <div class="main-container">
        <div class="container" id="login-container">
            <div class="toggle-box login-toggle">
                <h2>NotulenPro</h2>
                <p>Sistem Pencatatan Notulen Digital</p>
            </div>

            <form class="form-box login" action="login.php" method="POST">
                <input type="text" placeholder="NIM" id="login-username" name="nim" required 
                       value="<?php echo isset($_POST['nim']) ? htmlspecialchars($_POST['nim']) : ''; ?>" />
                <input type="password" placeholder="Password" id="login-password" name="password" required />
                <button type="submit">Login</button>
                
                <?php if (!empty($error_message)): ?>
                    <div style="color: red; text-align: center; margin-top: 10px; padding: 10px; border: 1px solid red; background: #ffe6e6;">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close();
}