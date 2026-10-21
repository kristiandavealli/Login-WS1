<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($username) < 3 || !$address || !$contactNumber || strlen($password) < 6 || $password !== $confirmPassword) {
        flash('Complete all fields. Passwords must match and have at least 6 characters.');
        redirect('register.php');
    }

    try {
        $statement = db()->prepare('INSERT INTO users (username, password, role, address, contact_number) VALUES (?, ?, "user", ?, ?)');
        $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), $address, $contactNumber]);
        flash('Registration successful. You can now log in.');
        redirect('UserLogin.php');
    } catch (PDOException $exception) {
        flash('That username is already in use.');
        redirect('register.php');
    }
}

require __DIR__ . '/Register.html';
