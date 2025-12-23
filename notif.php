<?php
session_start();

// Contoh pesan (ganti dengan proses kamu sendiri)
$message = "";
$error = "";

// Jika tombol simpan ditekan
if (isset($_POST['save'])) {
    $message = "Data berhasil disimpan!";
}

if (isset($_POST['fail'])) {
    $error = "Terjadi kesalahan!";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Notif Slide Down</title>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .top-notif {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: top 0.5s ease;
            z-index: 9999;
        }

        .notif-success {
            background: #4CAF50;
        }

        .notif-error {
            background: #E53935;
        }

        .notif.show {
            top: 20px;
            opacity: 1;
}
    </style>
</head>
<body>

<!-- NOTIFIKASI -->
<div id="notifSuccess" class="top-notif notif-success">
  <?php echo $message; ?>
</div>

<div id="notifError" class="top-notif notif-error">
  <?php echo $error; ?>
</div>


<!-- FORM CONTOH -->
<form method="POST">
    <button name="save">Simpan Data</button>
    <button name="fail">Buat Error</button>
</form>


<script>
window.onload = function() {
    const success = "<?php echo $message; ?>";
    const error   = "<?php echo $error; ?>";

    if (success) {
        let box = document.getElementById("notifSuccess");
        box.style.top = "20px"; // turun
        setTimeout(() => box.style.top = "-100px", 2500); // naik lagi
    }

    if (error) {
        let box = document.getElementById("notifError");
        box.style.top = "20px"; 
        setTimeout(() => box.style.top = "-100px", 2500);
    }
};
</script>
</body> 
</html>  
