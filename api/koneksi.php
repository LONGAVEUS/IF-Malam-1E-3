<?php 
$host = "localhost"; 
$user = "root"; 
$pass = ""; 
$db = "db_notulen";

$conn = mysqli_connect($host, $user, $pass, $db); 

if ($conn->connect_error) { 
    die("Koneksi Database Gagal: " . $conn->connect_error); 
} 
?>