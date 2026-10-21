<?php
declare(strict_types=1);

$sessionDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'marketly_sessions';
if (!is_dir($sessionDirectory)) {
    mkdir($sessionDirectory, 0775, true);
}

session_save_path($sessionDirectory);
session_start();

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $database = getenv('DB_NAME') ?: 'marketly';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'staff', 'user') NOT NULL,
            address VARCHAR(255) NOT NULL DEFAULT '',
            contact_number VARCHAR(50) NOT NULL DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            image VARCHAR(255) NOT NULL DEFAULT '',
            price DECIMAL(10, 2) NOT NULL,
            stock INT UNSIGNED NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS carts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL UNIQUE,
            CONSTRAINT fk_carts_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cart_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            cart_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_cart_product (cart_id, product_id),
            CONSTRAINT fk_cart_items_cart
                FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
            CONSTRAINT fk_cart_items_product
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            payment_status VARCHAR(30) NOT NULL DEFAULT 'paid',
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_orders_user
                FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            price DECIMAL(10, 2) NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            CONSTRAINT fk_order_items_order
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_items_product
                FOREIGN KEY (product_id) REFERENCES products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $seedUser = $pdo->prepare(
        'INSERT INTO users (username, password, role) VALUES (?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role), is_active = 1'
    );
    $seedUser->execute(['kriz', password_hash('kriz123', PASSWORD_DEFAULT), 'admin']);
    $seedUser->execute(['jade', password_hash('jade12345', PASSWORD_DEFAULT), 'staff']);
    $seedUser->execute(['nico', password_hash('nico1122', PASSWORD_DEFAULT), 'user']);

    $pdo->exec("UPDATE users SET is_active = 0 WHERE username IN ('admin', 'staff', 'demo_user')");

    $sampleProduct = $pdo->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
    $seedProduct = $pdo->prepare(
        'INSERT INTO products (name, description, image, price, stock) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ([
        ['Shoes', '', '', 1299, 10],
        ['Bag', '', '', 899, 10],
    ] as $product) {
        $sampleProduct->execute([$product[0]]);
        if (!$sampleProduct->fetch()) {
            $seedProduct->execute($product);
        }
    }

    return $pdo;
}
function find_user(string $username): ?array
{
    $statement = db()->prepare('SELECT * FROM users WHERE lower(username) = lower(?) LIMIT 1');
    $statement->execute([$username]);
    return $statement->fetch() ?: null;
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $statement = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $statement->execute([(int) $_SESSION['user_id']]);
    return $statement->fetch() ?: null;
}

function dashboard_for_role(string $role): string
{
    return match ($role) {
        'admin' => 'AdminDashboard.php',
        'staff' => 'Market-dashboard.php',
        'user' => 'UserAccount.php',
        default => 'LoginForm.php',
    };
}
function require_role(string $role): void
{
    $user = current_user();

    if (!$user) {
        redirect('LoginForm.php');
    }

    if ($user['role'] !== $role) {
        redirect(dashboard_for_role($user['role']));
    }
}
function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function take_flash(): string
{
    $message = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $message;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
