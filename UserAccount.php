<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_role('user');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Marketly</title>
    <link rel="stylesheet" href="UserDashboard.css">
</head>
<body class="user-page">
    <header class="user-header">
        <a class="brand" href="UserAccount.php">Marketly</a>
        <nav class="landing-nav" aria-label="Account navigation">
            <span>Welcome, <?= htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') ?></span>
            <a class="nav-login" href="logout.php">Log out</a>
        </nav>
    </header>
    <main class="landing-main">
        <section class="hero">
            <p class="eyebrow">My account</p>
            <h1>Welcome to Marketly.</h1>
            <p>Your account is ready. Products and order features can be added here next.</p>
        </section>
    </main>
</body>
</html>
