<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = current_user();
if ($user) {
    redirect(dashboard_for_role($user['role']));
}

require __DIR__ . '/LoginForm.html';
