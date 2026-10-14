<?php
declare(strict_types=1);

session_start();

const USER_DATA_FILE = __DIR__ . '/data/users.json';

function load_users(): array
{
    if (!is_file(USER_DATA_FILE)) {
        $directory = dirname(USER_DATA_FILE);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $users = [
            [
                'username' => 'admin',
                'password' => password_hash('Admin123!', PASSWORD_DEFAULT),
                'role' => 'admin',
            ],
            [
                'username' => 'staff',
                'password' => password_hash('Staff123!', PASSWORD_DEFAULT),
                'role' => 'staff',
            ],
        ];
        save_users($users);
        return $users;
    }

    $contents = file_get_contents(USER_DATA_FILE);
    $users = json_decode($contents ?: '[]', true);
    return is_array($users) ? $users : [];
}

function save_users(array $users): void
{
    $json = json_encode($users, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    file_put_contents(USER_DATA_FILE, $json, LOCK_EX);
}

function find_user(string $username): ?array
{
    foreach (load_users() as $user) {
        if (strcasecmp($user['username'], $username) === 0) {
            return $user;
        }
    }
    return null;
}

function require_role(string $role): void
{
    if (($_SESSION['role'] ?? null) !== $role) {
        header('Location: ' . ($role === 'user' ? 'UserLogin.php' : 'LoginForm.php'));
        exit;
    }
}
