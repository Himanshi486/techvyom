<?php
require_once __DIR__ . '/connect.php';

// Fix admin_id column (run once)
$conn->query("
    ALTER TABLE admin_users 
    MODIFY admin_id INT(11) NOT NULL AUTO_INCREMENT,
    ADD PRIMARY KEY (admin_id)
");

// Now insert admin
$username = 'admin';
$password = 'admin123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO admin_users (username, password) VALUES (?, ?)"
);
$stmt->bind_param("ss", $username, $hashedPassword);

if ($stmt->execute()) {
    echo "Admin user created successfully";
} else {
    echo "Error: " . $stmt->error;
}
