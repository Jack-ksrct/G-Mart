<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$notice = pull_flash('cart_notice');
$product = $slug !== '' ? get_product_by_slug($slug) : null;
$relatedProducts = $product !== null ? get_related_products($product['category_slug'], $product['slug'], 4) : [];
$currentPage = current_request_uri('product.php');

render_page_start($product !== null ? $product['name'] : 'Product not found', 'products');
?>
<section class="page-section">
    <?php if ($notice !== null): ?>
        <div class="notice notice-info"><?= e($notice) ?></div>
    <?php endif; ?>

    <?php if ($product === null): ?>
        <div class="empty-state">
            <h2>Product not found</h2>
            <p>The link may be outdated, or the item is no longer available.</p>
            <a class="button" href="products.php">Browse all products</a>
        </div>
    <?php else: ?>
        <div class="detail-layout">
            <div class="detail-media">
                <img class="detail-image" src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>">
            </div>

            <div class="detail-summary">
                <a class="text-link" href="products.php?category=<?= e($product['category_slug']) ?>">Back to <?= e($product['category_name']) ?></a>
                <div class="product-topline">
                    <span class="chip"><?= e($product['category_name']) ?></span>
                    <span class="rating-pill"><?= e((string) $product['rating']) ?>/5</span>
                </div>

                <h1 class="detail-title"><?= e($product['name']) ?></h1>
                <p class="section-copy"><?= e($product['description']) ?></p>

                <div class="price-row">
                    <strong><?= e(format_currency((int) $product['price'])) ?></strong>
                    <?php if (!empty($product['original_price'])): ?>
                        <span class="old-price"><?= e(format_currency((int) $product['original_price'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!product_has_inventory($product)): ?>
                    <div class="notice notice-info">
                        This item is currently sold out. Contact the store for a restock update or similar options.
                    </div>
                <?php endif; ?>

                <div class="detail-points">
                    <div class="spec-card">
                        <span class="eyebrow">Stock</span>
                        <strong><?= e($product['stock_status']) ?></strong>
                        <p><?= e($product['inventory']) ?> units ready to order</p>
                    </div>
                    <div class="spec-card">
                        <span class="eyebrow">Delivery</span>
                        <strong><?= e($product['delivery']) ?></strong>
                        <p>Contact the store if you want help before placing the order.</p>
                    </div>
                    <div class="spec-card">
                        <span class="eyebrow">Highlights</span>
                        <strong><?= e($product['tag'] ?: 'Popular pick') ?></strong>
                        <p><?= e($product['badge'] ?: 'Customer favorite') ?></p>
                    </div>
                </div>

                <div class="detail-actions">
                    <?php if (product_has_inventory($product)): ?>
                        <form class="purchase-form" action="cart.php" method="post">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                            <input type="hidden" name="redirect" value="<?= e($currentPage) ?>">
                            <label class="field quantity-field">
                                <span>Quantity</span>
                                <input
                                    class="quantity-input"
                                    type="number"
                                    name="quantity"
                                    min="1"
                                    max="<?= e($product['inventory']) ?>"
                                    value="1"
                                >
                            </label>
                            <button class="button" type="submit">Add to cart</button>
                        </form>

                        <form class="purchase-form" action="cart.php" method="post">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                            <input type="hidden" name="quantity" value="1">
                            <input type="hidden" name="redirect" value="checkout.php">
                            <button class="button button-secondary" type="submit">Buy now</button>
                        </form>
                    <?php else: ?>
                        <a class="button button-secondary" href="contact.php">Ask about restock</a>
                        <a class="button button-ghost" href="products.php?category=<?= e($product['category_slug']) ?>">Browse similar products</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($product !== null && $relatedProducts !== []): ?>
    <section class="page-section">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Related picks</span>
                <h2>More from <?= e($product['category_name']) ?></h2>
            </div>
            <a class="text-link" href="products.php?category=<?= e($product['category_slug']) ?>">See full collection</a>
        </div>

        <div class="product-grid">
            <?php foreach ($relatedProducts as $related): ?>
                <?php render_product_card($related, $currentPage); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php
render_page_end();
