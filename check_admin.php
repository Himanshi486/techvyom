<?php
require_once __DIR__ . '/connect.php';

$result = $conn->query("SELECT * FROM admin_users");

if (!$result) {
    die("Query failed: " . $conn->error);
}

if ($result->num_rows === 0) {
    echo "Admin table exists, but NO records found";
} else {
    echo "Admin records found: " . $result->num_rows;
}
