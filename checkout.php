<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$user = current_user();
$form = [
    'customer_name' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'address_line' => '',
    'city' => '',
    'state' => '',
    'postal_code' => '',
    'notes' => '',
];
$errors = [];
$notice = pull_flash('cart_notice');
$checkoutError = null;
$successOrderNumber = trim((string) ($_GET['success'] ?? ''));
$order = $successOrderNumber !== '' ? get_order_with_items($successOrderNumber) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (get_cart_items() === []) {
        set_flash('cart_notice', 'Your cart is empty, so there is nothing to check out yet.');
        redirect_to('products.php');
    }

    [$form, $errors] = validate_checkout_input($_POST);

    if ($errors === []) {
        try {
            $placedOrder = place_order_from_cart($form);
            redirect_to('checkout.php?success=' . rawurlencode((string) $placedOrder['order_number']), 'checkout.php');
        } catch (Throwable $exception) {
            $checkoutError = 'The order could not be placed right now. Please review the cart and try again.';
        }
    }
}

$items = get_cart_items();
$totals = get_cart_totals();
$isSuccess = $order !== null && $successOrderNumber !== '';

render_page_start($isSuccess ? 'Order placed' : 'Checkout', 'cart');
?>
<section class="page-section">
    <?php if ($notice !== null): ?>
        <div class="notice notice-info"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($checkoutError !== null): ?>
        <div class="notice notice-error"><?= e($checkoutError) ?></div>
    <?php endif; ?>

    <div class="section-heading compact-heading">
        <div>
            <span class="eyebrow"><?= $isSuccess ? 'Order placed' : 'Checkout' ?></span>
            <h1><?= $isSuccess ? 'Your order is confirmed' : 'Review items and place your order' ?></h1>
        </div>
        <p class="section-copy">
            <?= $isSuccess
                ? 'You can keep shopping now, and the order summary below still shows exactly what was placed.'
                : 'Check the items, add your delivery details, and place the order when everything looks right.' ?>
        </p>
    </div>
</section>

<?php if ($isSuccess && $order !== null): ?>
    <section class="page-section checkout-success">
        <div class="notice notice-success">
            Order <strong><?= e($order['order_number']) ?></strong> has been placed successfully.
        </div>

        <div class="detail-points">
            <article class="spec-card">
                <span class="eyebrow">Status</span>
                <strong><?= e($order['status']) ?></strong>
                <p><?= e($order['items_count']) ?> item<?= (int) $order['items_count'] === 1 ? '' : 's' ?> confirmed for processing.</p>
            </article>
            <article class="spec-card">
                <span class="eyebrow">Delivery to</span>
                <strong><?= e($order['customer_name']) ?></strong>
                <p><?= e($order['address_line']) ?>, <?= e($order['city']) ?>, <?= e($order['state']) ?> - <?= e($order['postal_code']) ?></p>
            </article>
            <article class="spec-card">
                <span class="eyebrow">Order total</span>
                <strong><?= e(format_currency((int) $order['subtotal'])) ?></strong>
                <p>Confirmation sent to <?= e($order['email']) ?> and support can reach you at <?= e($order['phone']) ?>.</p>
            </article>
        </div>
    </section>

    <section class="page-section cart-layout">
        <div class="cart-items">
            <?php foreach ($order['items'] as $item): ?>
                <article class="cart-item">
                    <img class="cart-image" src="<?= e($item['image_path']) ?>" alt="<?= e($item['product_name']) ?>">
                    <div class="cart-copy">
                        <div class="product-topline">
                            <span class="chip"><?= e($item['category_name']) ?></span>
                            <span class="rating-pill">Qty <?= e($item['quantity']) ?></span>
                        </div>
                        <h3><?= e($item['product_name']) ?></h3>
                        <p>Saved with your order so the summary stays clear even if the live catalog changes later.</p>
                    </div>
                    <div class="cart-side">
                        <div class="price-row">
                            <strong><?= e(format_currency((int) $item['line_total'])) ?></strong>
                        </div>
                        <div class="meta-row">
                            <span>Unit price</span>
                            <span><?= e(format_currency((int) $item['unit_price'])) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h2>Order summary</h2>
            <div class="summary-row">
                <span>Order number</span>
                <strong><?= e($order['order_number']) ?></strong>
            </div>
            <div class="summary-row">
                <span>Items</span>
                <strong><?= e($order['items_count']) ?></strong>
            </div>
            <div class="summary-row summary-total">
                <span>Total paid on delivery</span>
                <strong><?= e(format_currency((int) $order['subtotal'])) ?></strong>
            </div>
            <a class="button" href="products.php">Continue shopping</a>
            <a class="button button-ghost" href="contact.php">Need help with this order?</a>
        </aside>
    </section>
<?php elseif ($items === []): ?>
    <section class="page-section">
        <div class="empty-state">
            <h2>Your cart is empty</h2>
            <p>Add a few products first, then come back here to place the order.</p>
            <a class="button" href="products.php">Browse products</a>
        </div>
    </section>
<?php else: ?>
    <section class="page-section cart-layout">
        <div class="form-shell">
            <div class="checkout-copy">
                <span class="chip">Delivery details</span>
                <p class="section-copy">
                    Fill in the delivery details below, then place the order. The cart summary stays on the right
                    so you can double-check what you are buying before you confirm it.
                </p>
            </div>

            <form class="contact-form" method="post" action="checkout.php" novalidate>
                <div class="field-grid">
                    <label class="field">
                        <span>Customer name</span>
                        <input type="text" name="customer_name" value="<?= e($form['customer_name']) ?>" placeholder="Your full name">
                        <?php if (isset($errors['customer_name'])): ?>
                            <small class="form-error"><?= e($errors['customer_name']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?= e($form['email']) ?>" placeholder="you@example.com">
                        <?php if (isset($errors['email'])): ?>
                            <small class="form-error"><?= e($errors['email']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>Phone</span>
                        <input type="tel" name="phone" value="<?= e($form['phone']) ?>" placeholder="10-digit mobile number">
                        <?php if (isset($errors['phone'])): ?>
                            <small class="form-error"><?= e($errors['phone']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>PIN code</span>
                        <input type="text" name="postal_code" value="<?= e($form['postal_code']) ?>" placeholder="6-digit PIN code">
                        <?php if (isset($errors['postal_code'])): ?>
                            <small class="form-error"><?= e($errors['postal_code']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field field-wide">
                        <span>Address</span>
                        <textarea name="address_line" rows="4" placeholder="House number, street, area, landmark"><?= e($form['address_line']) ?></textarea>
                        <?php if (isset($errors['address_line'])): ?>
                            <small class="form-error"><?= e($errors['address_line']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>City</span>
                        <input type="text" name="city" value="<?= e($form['city']) ?>" placeholder="Your city">
                        <?php if (isset($errors['city'])): ?>
                            <small class="form-error"><?= e($errors['city']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span>State</span>
                        <input type="text" name="state" value="<?= e($form['state']) ?>" placeholder="Your state">
                        <?php if (isset($errors['state'])): ?>
                            <small class="form-error"><?= e($errors['state']) ?></small>
                        <?php endif; ?>
                    </label>

                    <label class="field field-wide">
                        <span>Delivery notes</span>
                        <textarea name="notes" rows="4" placeholder="Gate code, preferred call time, or any other instructions"><?= e($form['notes']) ?></textarea>
                    </label>
                </div>

                <button class="button" type="submit">Place order</button>
            </form>
        </div>

        <aside class="cart-summary">
            <h2>Order summary</h2>
            <div class="summary-row">
                <span>Items</span>
                <strong><?= e($totals['items_count']) ?></strong>
            </div>
            <div class="summary-row">
                <span>Subtotal</span>
                <strong><?= e(format_currency((int) $totals['subtotal'])) ?></strong>
            </div>
            <div class="summary-row">
                <span>You save</span>
                <strong><?= e(format_currency((int) $totals['savings'])) ?></strong>
            </div>
            <div class="summary-row summary-total">
                <span>Total</span>
                <strong><?= e(format_currency((int) $totals['subtotal'])) ?></strong>
            </div>

            <div class="order-mini-list">
                <?php foreach ($items as $item): ?>
                    <article class="order-mini-item">
                        <img src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>">
                        <div>
                            <strong><?= e($item['name']) ?></strong>
                            <p><?= e($item['quantity']) ?> x <?= e(format_currency((int) $item['price'])) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </aside>
    </section>
<?php endif; ?>
<?php
render_page_end();
