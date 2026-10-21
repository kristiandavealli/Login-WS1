<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_role('staff');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image = trim($_POST['image'] ?? '');
        $price = max(0, (float) ($_POST['price'] ?? 0));
        $stock = max(0, (int) ($_POST['stock'] ?? 0));

        if (!$name) {
            flash('Item name is required.');
        } elseif ($id) {
            $pdo->prepare('UPDATE products SET name = ?, description = ?, image = ?, price = ?, stock = ? WHERE id = ?')->execute([$name, $description, $image, $price, $stock, $id]);
            flash('Item updated.');
        } else {
            $pdo->prepare('INSERT INTO products (name, description, image, price, stock) VALUES (?, ?, ?, ?, ?)')->execute([$name, $description, $image, $price, $stock]);
            flash('Item added.');
        }
    }

    if ($action === 'delete_product') {
        $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Item removed from the shop.');
    }

    if ($action === 'order_status') {
        $allowedStatuses = ['pending', 'processing', 'ready', 'completed', 'cancelled'];
        $status = $_POST['status'] ?? 'pending';
        if (in_array($status, $allowedStatuses, true)) {
            $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, (int) ($_POST['id'] ?? 0)]);
            flash('Order status updated.');
        }
    }

    redirect('Market-dashboard.php');
}

$products = $pdo->query('SELECT * FROM products ORDER BY is_active DESC, id DESC')->fetchAll();
$orders = $pdo->query('SELECT o.*, u.username, u.address, u.contact_number FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC')->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Dashboard | Marketly</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>
<body>
    <header class="top">
        <strong>Marketly Staff</strong>
        <nav class="nav">
            <a href="#inventory">Inventory</a>
            <a href="#orders">Orders</a>
            <a href="logout.php">Log out</a>
        </nav>
    </header>

    <main class="wrap">
        <?php if ($message = take_flash()): ?>
            <p class="flash"><?= e($message) ?></p>
        <?php endif; ?>

        <h1>Staff Dashboard</h1>
        <section class="grid">
            <article class="card">
                <span class="muted">Products</span>
                <h2><?= count($products) ?></h2>
            </article>
            <article class="card">
                <span class="muted">Pending Orders</span>
                <h2><?= e((string) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn()) ?></h2>
            </article>
            <article class="card">
                <span class="muted">Low Stock</span>
                <h2><?= e((string) $pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 5 AND is_active = 1')->fetchColumn()) ?></h2>
            </article>
        </section>

        <div class="two" id="inventory">
            <section class="panel">
                <h2>Add Item</h2>
                <form method="post">
                    <input type="hidden" name="action" value="save_product">
                    <input name="name" placeholder="Item name" required>
                    <textarea name="description" placeholder="Description"></textarea>
                    <label for="item-image">Image filename (optional)</label>
                    <input id="item-image" name="image" placeholder="Example: product.jpg">
                    <input name="price" type="number" min="0" step="0.01" placeholder="Price" required>
                    <input name="stock" type="number" min="0" placeholder="Stock quantity" required>
                    <button>Add Item</button>
                </form>
                <p class="muted">Leave the image filename blank if the item has no image.</p>
            </section>

            <section class="panel">
                <h2>Inventory</h2>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php if ($product['image'] && is_file(__DIR__ . '/' . $product['image'])): ?>
                                            <img class="item-thumb" src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($product['name']) ?></strong><br>
                                        <small class="muted"><?= e($product['description']) ?></small>
                                        <?= !$product['is_active'] ? '<br><span class="status">Removed</span>' : '' ?>
                                    </td>
                                    <td>&#8369;<?= number_format((float) $product['price'], 2) ?></td>
                                    <td><?= e((string) $product['stock']) ?></td>
                                    <td>
                                        <form method="post" class="actions">
                                            <input type="hidden" name="action" value="save_product">
                                            <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                            <input name="name" value="<?= e($product['name']) ?>" required>
                                            <input name="description" value="<?= e($product['description']) ?>">
                                            <input name="image" value="<?= e($product['image']) ?>" placeholder="Image filename (optional)">
                                            <input name="price" type="number" step="0.01" value="<?= e((string) $product['price']) ?>" required>
                                            <input name="stock" type="number" min="0" value="<?= e((string) $product['stock']) ?>" required>
                                            <button class="small">Update</button>
                                        </form>
                                        <?php if ($product['is_active']): ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                                <button class="small danger">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="panel" id="orders">
            <h2>Customer Orders</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer information</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= e((string) $order['id']) ?><br><small class="muted"><?= e($order['created_at']) ?></small></td>
                                <td><?= e($order['username']) ?><br><small><?= e($order['address']) ?> ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· <?= e($order['contact_number']) ?></small></td>
                                <td>&#8369;<?= number_format((float) $order['total'], 2) ?></td>
                                <td><?= e($order['payment_status']) ?></td>
                                <td><span class="status"><?= e($order['status']) ?></span></td>
                                <td>
                                    <form method="post">
                                        <input type="hidden" name="action" value="order_status">
                                        <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
                                        <select name="status">
                                            <?php foreach (['pending', 'processing', 'ready', 'completed', 'cancelled'] as $status): ?>
                                                <option <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="small">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.flash, .form-message').forEach((notification) => {
                window.setTimeout(() => {
                    notification.style.transition = 'opacity 0.35s ease';
                    notification.style.opacity = '0';
                    window.setTimeout(() => notification.remove(), 350);
                }, 3000);
            });
        });
    </script>
</body>
</html>
