<?php
declare(strict_types=1);

require __DIR__ . '/auth.php';
require_role('admin');

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_staff') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $address = trim($_POST['address'] ?? '');
        $contactNumber = trim($_POST['contact_number'] ?? '');

        if (strlen($username) < 3 || strlen($password) < 6 || !$address || !$contactNumber) {
            flash('Complete all staff fields. The password must have at least 6 characters.');
        } else {
            try {
                $statement = $pdo->prepare('INSERT INTO users (username, password, role, address, contact_number) VALUES (?, ?, "staff", ?, ?)');
                $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), $address, $contactNumber]);
                flash('Staff account created.');
            } catch (PDOException $exception) {
                flash('Unable to create staff. Username may already exist.');
            }
        }
    }

    if ($action === 'toggle_user') {
        $pdo->prepare('UPDATE users SET is_active = CASE is_active WHEN 1 THEN 0 ELSE 1 END WHERE id = ? AND role != "admin"')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Account status updated.');
    }

    if ($action === 'delete_staff') {
        $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "staff"')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Staff account deleted.');
    }

    redirect('AdminDashboard.php');
}

$stats = [
    'Users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'Staff' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'staff'")->fetchColumn(),
    'Products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
];

$accounts = $pdo->query(
    "SELECT id, username, role, address, contact_number, is_active, created_at
     FROM users
     WHERE role != 'admin'
     ORDER BY role, username"
)->fetchAll();

$sales = (float) $pdo->query(
    "SELECT COALESCE(SUM(total), 0)
     FROM orders
     WHERE payment_status = 'paid'"
)->fetchColumn();

$pendingOrders = $pdo->query(
    "SELECT COUNT(*) FROM orders WHERE status = 'pending'"
)->fetchColumn();

$lowStockItems = $pdo->query(
    'SELECT COUNT(*) FROM products WHERE stock <= 5'
)->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | Marketly</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>
<body>
    <header class="top">
        <strong>Marketly Admin</strong>
        <nav class="nav">
            <a href="#accounts">Accounts</a>
            <a href="#monitoring">Monitoring</a>
            <a href="logout.php">Log out</a>
        </nav>
    </header>

    <main class="wrap">
        <?php if ($message = take_flash()): ?>
            <p class="flash"><?= e($message) ?></p>
        <?php endif; ?>

        <h1>Admin Dashboard</h1>
        <p class="muted">Manage access and monitor the system.</p>

        <section class="grid">
            <?php foreach ($stats as $label => $value): ?>
                <article class="card">
                    <span class="muted"><?= e($label) ?></span>
                    <h2><?= $value ?></h2>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="two">
            <section class="panel">
                <h2>Create Staff Account</h2>
                <form method="post">
                    <input type="hidden" name="action" value="add_staff">
                    <input name="username" placeholder="Username" required>
                    <input name="password" type="password" minlength="6" placeholder="Temporary password" required>
                    <input name="address" placeholder="Address" required>
                    <input name="contact_number" placeholder="Contact number" required>
                    <button>Create staff</button>
                </form>
            </section>

            <section class="panel" id="monitoring">
                <h2>System Monitoring</h2>
                <p><strong>Sales:</strong> &#8369;<?= number_format($sales, 2) ?></p>
                <p><strong>Pending orders:</strong> <?= e((string) $pendingOrders) ?></p>
                <p><strong>Low stock items:</strong> <?= e((string) $lowStockItems) ?></p>
                <p class="muted">Only Admin can create staff accounts or change account access.</p>
            </section>
        </div>

        <section class="panel" id="accounts">
            <h2>Staff &amp; User Accounts</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                            <tr>
                                <td>
                                    <?= e($account['username']) ?><br>
                                    <small class="muted"><?= e($account['address']) ?></small>
                                </td>
                                <td><?= e($account['role']) ?></td>
                                <td><?= e($account['contact_number']) ?></td>
                                <td>
                                    <span class="status">
                                        <?= $account['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle_user">
                                            <input type="hidden" name="id" value="<?= e((string) $account['id']) ?>">
                                            <button class="small">
                                                <?= $account['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <?php if ($account['role'] === 'staff'): ?>
                                            <form method="post">
                                                <input type="hidden" name="action" value="delete_staff">
                                                <input type="hidden" name="id" value="<?= e((string) $account['id']) ?>">
                                                <button class="small danger" onclick="return confirm('Delete this staff account?')">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
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
