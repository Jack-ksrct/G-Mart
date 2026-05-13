<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$notice = pull_flash('admin_notice');
$adminError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'admin_login') {
        if (login_admin((string) ($_POST['admin_password'] ?? ''))) {
            set_flash('admin_notice', 'Admin panel unlocked.');
            redirect_to('admin.php');
        }

        $adminError = admin_password_is_configured()
            ? 'Incorrect admin password.'
            : 'Set admin_password in configure.php before using the admin panel.';
    }

    if ($action === 'admin_logout') {
        logout_admin();
        set_flash('admin_notice', 'Admin panel locked.');
        redirect_to('admin.php');
    }

    if (admin_is_logged_in() && $action === 'update_order') {
        $updated = update_order_status((int) ($_POST['order_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        set_flash('admin_notice', $updated ? 'Order status updated.' : 'Order status was not changed.');
        redirect_to('admin.php#orders');
    }

    if (admin_is_logged_in() && $action === 'update_product') {
        $updated = update_product_inventory_status(
            (int) ($_POST['product_id'] ?? 0),
            (int) ($_POST['inventory'] ?? 0),
            (string) ($_POST['stock_status'] ?? '')
        );
        set_flash('admin_notice', $updated ? 'Product inventory updated.' : 'Product inventory was not changed.');
        redirect_to('admin.php#products');
    }
}

$metrics = admin_is_logged_in() ? get_admin_metrics() : [];
$orders = admin_is_logged_in() ? get_recent_orders() : [];
$products = admin_is_logged_in() ? get_admin_products() : [];
$orderStatuses = get_admin_order_statuses();

render_page_start('Admin panel', 'admin');
?>
<section class="page-section">
    <?php if ($notice !== null): ?>
        <div class="notice notice-success"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($adminError !== null): ?>
        <div class="notice notice-error"><?= e($adminError) ?></div>
    <?php endif; ?>

    <div class="section-heading compact-heading">
        <div>
            <span class="eyebrow">Admin panel</span>
            <h1>Store dashboard</h1>
        </div>
        <p class="section-copy">
            Manage order status, track stock, and review the storefront numbers from one place.
        </p>
    </div>
</section>

<?php if (!admin_is_logged_in()): ?>
    <section class="page-section auth-layout">
        <div class="form-shell auth-panel">
            <span class="chip">Restricted</span>
            <form class="contact-form" method="post" action="admin.php" novalidate>
                <input type="hidden" name="action" value="admin_login">
                <label class="field">
                    <span>Admin password</span>
                    <input type="password" name="admin_password" placeholder="Enter admin password">
                </label>
                <button class="button" type="submit">Open admin panel</button>
            </form>
        </div>

        <aside class="contact-sidebar">
            <article class="feature-card">
                <span class="eyebrow">Setup</span>
                <h3>Configure access first</h3>
                <p>Change <strong>admin_password</strong> in <strong>configure.php</strong> before uploading this store.</p>
            </article>
        </aside>
    </section>
<?php else: ?>
    <section class="page-section admin-dashboard">
        <div class="admin-toolbar">
            <div class="admin-tabs" role="tablist" aria-label="Admin sections">
                <button class="admin-tab is-active" type="button" data-admin-tab="orders">Orders</button>
                <button class="admin-tab" type="button" data-admin-tab="products">Products</button>
            </div>
            <form class="inline-form" method="post" action="admin.php">
                <input type="hidden" name="action" value="admin_logout">
                <button class="button button-ghost" type="submit">Lock panel</button>
            </form>
        </div>

        <div class="admin-metrics">
            <article class="admin-metric">
                <span>Orders</span>
                <strong><?= e($metrics['orders']) ?></strong>
            </article>
            <article class="admin-metric">
                <span>Revenue</span>
                <strong><?= e(format_currency((int) $metrics['revenue'])) ?></strong>
            </article>
            <article class="admin-metric">
                <span>Products</span>
                <strong><?= e($metrics['products']) ?></strong>
            </article>
            <article class="admin-metric">
                <span>Low stock</span>
                <strong><?= e($metrics['low_stock']) ?></strong>
            </article>
            <article class="admin-metric">
                <span>Customers</span>
                <strong><?= e($metrics['customers']) ?></strong>
            </article>
            <article class="admin-metric">
                <span>Enquiries</span>
                <strong><?= e($metrics['enquiries']) ?></strong>
            </article>
        </div>

        <label class="field admin-search">
            <span>Search admin tables</span>
            <input type="search" id="adminSearch" placeholder="Search order number, customer, product, status...">
        </label>
    </section>

    <section class="page-section admin-panel is-active" id="orders" data-admin-panel="orders">
        <div class="results-toolbar">
            <div>
                <span class="eyebrow">Orders</span>
                <h2>Recent orders</h2>
            </div>
            <span class="results-count"><?= e(count($orders)) ?> shown</span>
        </div>

        <?php if ($orders === []): ?>
            <div class="empty-state">
                <h2>No orders yet</h2>
                <p>Orders placed from checkout will appear here for status updates.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr class="admin-row">
                                <td>
                                    <strong><?= e($order['order_number']) ?></strong>
                                    <small><?= e($order['created_at']) ?></small>
                                </td>
                                <td>
                                    <strong><?= e($order['customer_name']) ?></strong>
                                    <small><?= e($order['email']) ?> - <?= e($order['phone']) ?></small>
                                </td>
                                <td>
                                    <?php foreach (get_order_items_by_order_id((int) $order['id']) as $item): ?>
                                        <span class="admin-list-item"><?= e($item['quantity']) ?> x <?= e($item['product_name']) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td><strong><?= e(format_currency((int) $order['subtotal'])) ?></strong></td>
                                <td><span class="pill"><?= e($order['status']) ?></span></td>
                                <td>
                                    <form class="admin-inline-form" method="post" action="admin.php#orders">
                                        <input type="hidden" name="action" value="update_order">
                                        <input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                                        <select name="status" aria-label="Order status">
                                            <?php foreach ($orderStatuses as $status): ?>
                                                <option value="<?= e($status) ?>" <?= $status === $order['status'] ? 'selected' : '' ?>>
                                                    <?= e($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="button button-small" type="submit">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="page-section admin-panel" id="products" data-admin-panel="products">
        <div class="results-toolbar">
            <div>
                <span class="eyebrow">Products</span>
                <h2>Inventory control</h2>
            </div>
            <span class="results-count"><?= e(count($products)) ?> products</span>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Inventory</th>
                        <th>Status</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr class="admin-row <?= (int) $product['inventory'] <= 5 ? 'is-low-stock' : '' ?>">
                            <td>
                                <div class="admin-product-cell">
                                    <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>">
                                    <div>
                                        <strong><?= e($product['name']) ?></strong>
                                        <small><?= e($product['slug']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($product['category_name']) ?></td>
                            <td><strong><?= e(format_currency((int) $product['price'])) ?></strong></td>
                            <td><?= e($product['inventory']) ?></td>
                            <td><span class="pill"><?= e($product['stock_status']) ?></span></td>
                            <td>
                                <form class="admin-inline-form" method="post" action="admin.php#products">
                                    <input type="hidden" name="action" value="update_product">
                                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                                    <input class="admin-number" type="number" min="0" name="inventory" value="<?= e($product['inventory']) ?>" aria-label="Inventory">
                                    <input type="text" name="stock_status" value="<?= e($product['stock_status']) ?>" aria-label="Stock status">
                                    <button class="button button-small" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(function () {
            $('.admin-tab').on('click', function () {
                var tab = $(this).data('admin-tab');
                $('.admin-tab').removeClass('is-active');
                $(this).addClass('is-active');
                $('.admin-panel').removeClass('is-active');
                $('[data-admin-panel="' + tab + '"]').addClass('is-active');
                window.location.hash = tab;
            });

            if (window.location.hash === '#products') {
                $('[data-admin-tab="products"]').trigger('click');
            }

            $('#adminSearch').on('input', function () {
                var query = $(this).val().toString().toLowerCase();

                $('.admin-row').each(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(query) !== -1);
                });
            });
        });
    </script>
<?php endif; ?>
<?php
render_page_end();
