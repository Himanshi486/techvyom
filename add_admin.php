<?php
require_once __DIR__ . '/connect.php';

/*
CHANGE THESE VALUES
*/
$username = 'admin';
$password = 'admin123'; // change if you want

// Hash password (recommended)
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$sql = "INSERT INTO admin_users (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $hashedPassword);

if ($stmt->execute()) {
    echo "Admin user created successfully<br>";
    echo "Username: $username<br>";
    echo "Password: $password";
} else {
    echo "Error: " . $stmt->error;
}
