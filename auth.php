<?php
declare(strict_types=1);

session_start();

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $directory = __DIR__ . '/data';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $directory . '/marketly.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'staff', 'user')),
        address TEXT DEFAULT '',
        contact_number TEXT DEFAULT '',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT DEFAULT '',
        image TEXT DEFAULT '',
        price REAL NOT NULL CHECK(price >= 0),
        stock INTEGER NOT NULL DEFAULT 0 CHECK(stock >= 0),
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $productColumns = $pdo->query('PRAGMA table_info(products)')->fetchAll();
    $hasImageColumn = in_array('image', array_column($productColumns, 'name'), true);
    if (!$hasImageColumn) {
        $pdo->exec("ALTER TABLE products ADD COLUMN image TEXT DEFAULT ''");
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS carts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS cart_items (id INTEGER PRIMARY KEY AUTOINCREMENT, cart_id INTEGER NOT NULL, product_id INTEGER NOT NULL, quantity INTEGER NOT NULL CHECK(quantity > 0), UNIQUE(cart_id, product_id), FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE, FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, total REAL NOT NULL, payment_status TEXT NOT NULL DEFAULT 'paid', status TEXT NOT NULL DEFAULT 'pending', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(user_id) REFERENCES users(id))");
    $pdo->exec('CREATE TABLE IF NOT EXISTS order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER NOT NULL, product_name TEXT NOT NULL, price REAL NOT NULL, quantity INTEGER NOT NULL, FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE)');

    $seedUser = $pdo->prepare('INSERT OR IGNORE INTO users (username, password, role) VALUES (?, ?, ?)');
    $seedUser->execute(['admin', password_hash('Admin123!', PASSWORD_DEFAULT), 'admin']);
    $seedUser->execute(['staff', password_hash('Staff123!', PASSWORD_DEFAULT), 'staff']);

    $sampleProduct = $pdo->prepare('SELECT id FROM products WHERE image = ? LIMIT 1');
    $seedProduct = $pdo->prepare('INSERT INTO products (name, description, image, price, stock) VALUES (?, ?, ?, ?, ?)');
    foreach ([
        ['Shoes', 'Comfortable everyday shoes.', 'shoes.webp', 1299, 10],
        ['Bag', 'Practical bag for daily essentials.', 'bag.jpeg', 899, 10],
    ] as $product) {
        $sampleProduct->execute([$product[2]]);
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

function require_role(string $role): void
{
    $user = current_user();
    if (!$user || $user['role'] !== $role) {
        header('Location: ' . ($role === 'user' ? 'UserLogin.php' : 'LoginForm.php'));
        exit;
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
