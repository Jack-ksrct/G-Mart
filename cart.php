<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $redirect = safe_redirect_target((string) ($_POST['redirect'] ?? 'cart.php'), 'cart.php');
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    switch ($action) {
        case 'add':
            if ($slug !== '' && add_to_cart($slug, $quantity)) {
                $product = get_product_by_slug($slug);
                $name = $product !== null ? (string) $product['name'] : 'Item';
                set_flash('cart_notice', $name . ' added to cart.');
            } else {
                set_flash('cart_notice', 'That product could not be added to the cart.');
            }
            redirect_to($redirect, 'cart.php');

        case 'set_quantity':
            if ($slug !== '') {
                $updated = update_cart_quantity($slug, (int) ($_POST['quantity'] ?? 1));
                set_flash(
                    'cart_notice',
                    $updated
                        ? 'Cart quantity updated.'
                        : 'That item is no longer available in the requested quantity, so your cart was adjusted.'
                );
            }
            redirect_to('cart.php');

        case 'remove':
            if ($slug !== '') {
                remove_from_cart($slug);
                set_flash('cart_notice', 'Item removed from cart.');
            }
            redirect_to('cart.php');

        case 'clear':
            clear_cart();
            set_flash('cart_notice', 'Cart cleared.');
            redirect_to('cart.php');

        default:
            set_flash('cart_notice', 'No cart action was applied.');
            redirect_to('cart.php');
    }
}

$notice = pull_flash('cart_notice');
$items = get_cart_items();
$totals = get_cart_totals();

render_page_start('Your cart', 'cart');
?>
<section class="page-section">
    <?php if ($notice !== null): ?>
        <div class="notice notice-info"><?= e($notice) ?></div>
    <?php endif; ?>

    <div class="section-heading compact-heading">
        <div>
            <span class="eyebrow">Cart</span>
            <h1>Your shopping cart</h1>
        </div>
        <p class="section-copy">
            <?= e($totals['items_count']) ?> item<?= $totals['items_count'] === 1 ? '' : 's' ?>
            currently in your cart.
        </p>
    </div>
</section>

<section class="page-section cart-layout">
    <div class="cart-items">
        <?php if ($items === []): ?>
            <div class="empty-state">
                <h2>Your cart is empty</h2>
                <p>Add a few products from the catalog to start an order.</p>
                <a class="button" href="products.php">Shop products</a>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <article class="cart-item">
                    <img class="cart-image" src="<?= e($item['image_path']) ?>" alt="<?= e($item['name']) ?>">
                    <div class="cart-copy">
                        <div class="product-topline">
                            <span class="chip"><?= e($item['category_name']) ?></span>
                            <span class="rating-pill"><?= e((string) $item['rating']) ?>/5</span>
                        </div>
                        <h3><a class="text-link" href="product.php?slug=<?= e($item['slug']) ?>"><?= e($item['name']) ?></a></h3>
                        <p><?= e($item['description']) ?></p>
                        <div class="meta-row">
                            <span><?= e($item['stock_status']) ?></span>
                            <span><?= e($item['delivery']) ?></span>
                        </div>
                    </div>
                    <div class="cart-side">
                        <div class="price-row">
                            <strong><?= e(format_currency((int) $item['line_total'])) ?></strong>
                            <?php if ($item['line_original_total'] > $item['line_total']): ?>
                                <span class="old-price"><?= e(format_currency((int) $item['line_original_total'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <form class="cart-quantity-form" action="cart.php" method="post">
                            <input type="hidden" name="action" value="set_quantity">
                            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                            <label class="field">
                                <span>Qty</span>
                                <input
                                    class="quantity-input"
                                    type="number"
                                    name="quantity"
                                    min="1"
                                    max="<?= e($item['inventory']) ?>"
                                    value="<?= e($item['quantity']) ?>"
                                >
                            </label>
                            <button class="button button-small" type="submit">Update</button>
                        </form>

                        <form class="inline-form" action="cart.php" method="post">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="slug" value="<?= e($item['slug']) ?>">
                            <button class="button button-ghost" type="submit">Remove</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
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

        <a class="button" href="checkout.php">Proceed to checkout</a>
        <a class="button button-ghost" href="products.php">Continue shopping</a>
        <a class="button button-ghost" href="contact.php">Need buying help?</a>

        <?php if ($items !== []): ?>
            <form class="inline-form" action="cart.php" method="post">
                <input type="hidden" name="action" value="clear">
                <button class="button button-ghost" type="submit">Clear cart</button>
            </form>
        <?php endif; ?>
    </aside>
</section>
<?php
render_page_end();
