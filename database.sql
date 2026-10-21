CREATE DATABASE IF NOT EXISTS marketly
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE marketly;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'user') NOT NULL,
    address VARCHAR(255) NOT NULL DEFAULT '',
    contact_number VARCHAR(50) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) NOT NULL DEFAULT '',
    price DECIMAL(10, 2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    CONSTRAINT fk_carts_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_cart_product (cart_id, product_id),
    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'paid',
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copy existing accounts from the old shopping_db into the Marketly database.
-- Existing usernames in marketly are kept as-is.
INSERT IGNORE INTO marketly.users
    (username, password, role, address, contact_number, created_at)
SELECT username, password, role, address, contact_number, created_at
FROM shopping_db.users;

-- Demo accounts. Password values are PHP password_hash() hashes.
INSERT INTO users (username, password, role, is_active) VALUES
    ('kriz', '$2y$10$ziOPRxaSlvGO4Nih9b/WO.tx6NC4tCbJrxipjKRdTQqdRRtBCV6GS', 'admin', 1),
    ('jade', '$2y$10$dliN/lfpmafk2/mO3gI88OOAKMl4zdwMPjXk7eDXlyCrvK3ctP4F.', 'staff', 1),
    ('nico', '$2y$10$fBrkuOrvZAeBHwG3tb6ZwuyoObaKg7H4K/RZQfoxdqFypkpoDsubC', 'user', 1)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    role = VALUES(role),
    is_active = VALUES(is_active);

-- Disable old seeded logins if this script is imported into an existing database.
UPDATE users SET is_active = 0 WHERE username IN ('admin', 'staff', 'demo_user');

-- Starter products, inserted only when the product name does not already exist.
INSERT INTO products (name, description, image, price, stock)
SELECT 'Shoes', '', '', 1299.00, 10
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Shoes');

INSERT INTO products (name, description, image, price, stock)
SELECT 'Bag', '', '', 899.00, 10
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Bag');
