<?php
$host = getenv("MYSQLHOST");        // or DB_HOST if you created custom vars
$user = getenv("MYSQLUSER");        // or DB_USER
$pass = getenv("MYSQLPASSWORD");    // or DB_PASS
$db   = getenv("MYSQLDATABASE");    // or DB_NAME
$port = getenv("MYSQLPORT");        // or DB_PORT

$port = (int)$port;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

?>

