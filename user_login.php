<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = find_user(trim($_POST['username'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && $user['role'] === 'user' && password_verify($_POST['password'] ?? '', $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = 'user';
    header('Location: UserAccount.php');
    exit;
}

header('Location: UserLogin.php');
exit;
