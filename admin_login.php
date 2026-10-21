<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = find_user(trim($_POST['username'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $user['role'] === 'admin' && password_verify($_POST['password'] ?? '', $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = 'admin';
    header('Location: AdminDashboard.php');
    exit;
}

header('Location: LoginForm.php');
exit;
