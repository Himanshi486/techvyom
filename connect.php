<?php

$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$pass = getenv("DB_PASS");
$name = getenv("DB_NAME");
$port = getenv("DB_PORT");

// Convert port to integer or set default 3306 if empty
$port = $port !== false ? (int)$port : 3306;

// Debug (TEMPORARY) – check values
// echo "HOST=$host USER=$user DB=$name PORT=$port";
// exit;

$conn = mysqli_connect($host, $user, $pass, $name, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
