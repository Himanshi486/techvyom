<?php
$credDir = __DIR__ . '/credentials';
$credFile = $credDir . '/alumni-service.json';

if (!file_exists($credFile)) {
    $googleCreds = getenv('GOOGLE_CREDENTIALS');
    if ($googleCreds) {
        if (!is_dir($credDir)) {
            mkdir($credDir, 0755, true);
        }
        file_put_contents($credFile, $googleCreds);
    }
}
$host = getenv("DB_HOST");
$user = getenv("DB_USER");
$pass = getenv("DB_PASS");
$db   = getenv("DB_NAME");
$port = getenv("DB_PORT");

$port = (int)$port;

$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
