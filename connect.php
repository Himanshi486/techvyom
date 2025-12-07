<?php
$conn = mysqli_connect(
    getenv("DB_HOST"),
    getenv("DB_USER"),
    getenv("DB_PASS"),
    getenv("DB_NAME"),
    getenv("DB_PORT")
);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
