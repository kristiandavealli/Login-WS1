<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_role('user');

$pdo = db();
$user = current_user();

function cart_id(PDO $pdo, int $userId): int
{
    $pdo->prepare('INSERT OR IGNORE INTO carts (user_id) VALUES (?)')->execute([$userId]);
    $statement = $pdo->prepare('SELECT id FROM carts WHERE user_id = ?');
    $statement->execute([$userId]);
    return (int) $statement->fetchColumn();
}

$cartId = cart_id($pdo, (int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cart') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $requestedQuantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $statement = $pdo->prepare('SELECT stock, is_active FROM products WHERE id = ?');
        $statement->execute([$productId]);
        $product = $statement->fetch();

        if (!$product || !$product['is_active'] || (int) $product['stock'] < 1) {
            flash('This item is unavailable.');
        } else {
            $statement = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?');
            $statement->execute([$cartId, $productId]);
            $cartItem = $statement->fetch();
            $quantity = min((int) $product['stock'], $requestedQuantity + (int) ($cartItem['quantity'] ?? 0));

            if ($cartItem) {
                $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')->execute([$quantity, $cartItem['id']]);
            } else {
                $pdo->prepare('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (?, ?, ?)')->execute([$cartId, $productId, $quantity]);
            }
            flash('Item added to cart.');
        }
    }

    if ($action === 'cart_qty') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        if ($quantity < 1) {
            $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND cart_id = ?')->execute([$itemId, $cartId]);
        } else {
            $statement = $pdo->prepare('SELECT p.stock FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.id = ? AND ci.cart_id = ?');
            $statement->execute([$itemId, $cartId]);
            $stock = (int) $statement->fetchColumn();
            $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?')->execute([min($quantity, $stock), $itemId, $cartId]);
        }
        flash('Cart updated.');
    }

    if ($action === 'checkout') {
        $statement = $pdo->prepare('SELECT ci.*, p.name, p.price, p.stock FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
        $statement->execute([$cartId]);
        $items = $statement->fetchAll();

        if (!$items) {
            flash('Your cart is empty.');
            redirect('UserAccount.php');
        }

        $pdo->beginTransaction();
        try {
            $total = 0.0;
            foreach ($items as $item) {
                if ((int) $item['quantity'] > (int) $item['stock']) {
                    throw new RuntimeException($item['name'] . ' no longer has enough stock.');
                }
                $total += (float) $item['price'] * (int) $item['quantity'];
            }

            $pdo->prepare('INSERT INTO orders (user_id, total, payment_status, status) VALUES (?, ?, "paid", "pending")')->execute([$user['id'], $total]);
            $orderId = (int) $pdo->lastInsertId();
            $itemStatement = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)');
            $stockStatement = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');

            foreach ($items as $item) {
                $itemStatement->execute([$orderId, $item['product_id'], $item['name'], $item['price'], $item['quantity']]);
                $stockStatement->execute([$item['quantity'], $item['product_id']]);
            }

            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
            $pdo->commit();
            flash('Payment received. Order #' . $orderId . ' has been placed.');
        } catch (Throwable $exception) {
            $pdo->rollBack();
            flash($exception->getMessage());
        }
    }

    redirect('UserAccount.php');
}

$products = $pdo->query('SELECT * FROM products WHERE is_active = 1 ORDER BY id DESC')->fetchAll();
$statement = $pdo->prepare('SELECT ci.id, ci.quantity, p.name, p.price, p.stock FROM cart_items ci JOIN products p ON p.id = ci.product_id WHERE ci.cart_id = ?');
$statement->execute([$cartId]);
$cart = $statement->fetchAll();
$statement = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$statement->execute([$user['id']]);
$orders = $statement->fetchAll();
$cartTotal = array_sum(array_map(fn (array $item): float => (float) $item['price'] * (int) $item['quantity'], $cart));
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My Shop | Marketly</title><link rel="stylesheet" href="app.css"></head><body>
<header class="top"><strong>Marketly</strong><nav class="nav"><a href="#shop">Shop</a><a href="#cart">Cart (<?= count($cart) ?>)</a><a href="#orders">My Orders</a><a href="logout.php">Log out</a></nav></header>
<main class="wrap">
<?php if ($message = take_flash()): ?><p class="flash"><?= e($message) ?></p><?php endif; ?>
<h1>Welcome, <?= e($user['username']) ?></h1><p class="muted">Browse items, manage your cart, and track only your own orders.</p>
<section class="panel" id="shop"><h2>Available Items</h2><div class="grid"><?php foreach ($products as $product): ?><article class="card product"><?php if ($product['image']): ?><img class="product-image" src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?><div><h3><?= e($product['name']) ?></h3><p class="muted"><?= e($product['description']) ?></p><p class="price">₱<?= number_format((float) $product['price'], 2) ?></p><small><?= $product['stock'] ?> in stock</small></div><form method="post"><input type="hidden" name="action" value="add_cart"><input type="hidden" name="product_id" value="<?= $product['id'] ?>"><input name="quantity" type="number" min="1" max="<?= $product['stock'] ?>" value="1"><button>Add to cart</button></form></article><?php endforeach; if (!$products): ?><p class="muted">No items available yet.</p><?php endif; ?></div></section>
<div class="two"><section class="panel" id="cart"><h2>My Cart</h2><?php if ($cart): ?><div class="table-scroll"><table><thead><tr><th>Item</th><th>Price</th><th>Quantity</th><th>Subtotal</th></tr></thead><tbody><?php foreach ($cart as $item): ?><tr><td><?= e($item['name']) ?></td><td>₱<?= number_format((float) $item['price'], 2) ?></td><td><form method="post"><input type="hidden" name="action" value="cart_qty"><input type="hidden" name="item_id" value="<?= $item['id'] ?>"><input name="quantity" type="number" min="0" max="<?= $item['stock'] ?>" value="<?= $item['quantity'] ?>"><button class="small">Update</button></form></td><td>₱<?= number_format((float) $item['price'] * (int) $item['quantity'], 2) ?></td></tr><?php endforeach; ?></tbody></table></div><p><strong>Total: ₱<?= number_format($cartTotal, 2) ?></strong></p><form method="post"><input type="hidden" name="action" value="checkout"><button>Place Order &amp; Pay</button></form><?php else: ?><p class="muted">Your cart is empty.</p><?php endif; ?></section>
<section class="panel" id="orders"><h2>My Order History</h2><div class="table-scroll"><table><thead><tr><th>Order</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead><tbody><?php foreach ($orders as $order): ?><tr><td>#<?= $order['id'] ?></td><td>₱<?= number_format((float) $order['total'], 2) ?></td><td><?= e($order['payment_status']) ?></td><td><span class="status"><?= e($order['status']) ?></span></td><td><?= e($order['created_at']) ?></td></tr><?php endforeach; if (!$orders): ?><tr><td colspan="5" class="muted">No orders yet.</td></tr><?php endif; ?></tbody></table></div></section></div>
</main></body></html>
