<?php
require_once __DIR__ . '/connect.php';

$sqlFile = __DIR__ . '/techvyom.sql';

if (!file_exists($sqlFile)) {
    die("SQL file not found");
}

$sql = file_get_contents($sqlFile);

// Split queries safely
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $query) {
    if ($query !== '') {
        if (!$conn->query($query)) {
            echo "Error: " . $conn->error . "<br>";
            exit;
        }
    }
}

echo "Database imported successfully";
