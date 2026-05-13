<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$notice = pull_flash('cart_notice');
$metrics = get_catalog_metrics();
$featuredProducts = get_featured_products(4);
$featuredCategories = get_featured_categories(4);
$spotlight = $featuredProducts[0] ?? null;
$dealProducts = array_slice($featuredProducts, 1);
$currentPage = current_request_uri('index.php');

render_page_start('Electronics store', 'home');
?>
<section class="hero-panel">
    <div class="hero-copy">
        <?php if ($notice !== null): ?>
            <div class="notice notice-info"><?= e($notice) ?></div>
        <?php endif; ?>

        <span class="eyebrow">Electronics for everyday use</span>
        <h1>Shop phones, laptops, speakers, cameras, and smart devices without the clutter.</h1>
        <p class="hero-text">
            Start with a category, compare prices in rupees, and move from product page to cart to checkout in one flow.
        </p>

        <div class="hero-actions">
            <a class="button" href="products.php">Explore products</a>
            <a class="button button-ghost" href="cart.php">Open cart</a>
        </div>

        <div class="hero-points">
            <article class="hero-metric">
                <strong><?= e((string) $metrics['product_count']) ?>+</strong>
                <small>Products live</small>
            </article>
            <article class="hero-metric">
                <strong><?= e((string) $metrics['category_count']) ?></strong>
                <small>Category lanes</small>
            </article>
            <article class="hero-metric">
                <strong><?= e($metrics['average_rating']) ?>/5</strong>
                <small>Average rating</small>
            </article>
            <article class="hero-metric">
                <strong><?= e((string) $metrics['inventory']) ?></strong>
                <small>Units ready</small>
            </article>
        </div>
    </div>

    <?php if ($spotlight !== null): ?>
        <aside class="spotlight-card">
            <div class="spotlight-header">
                <span class="chip chip-accent">Popular right now</span>
                <a class="text-link" href="product.php?slug=<?= e($spotlight['slug']) ?>">See details</a>
            </div>
            <img class="spotlight-image" src="<?= e($spotlight['image_path']) ?>" alt="<?= e($spotlight['name']) ?>">
            <div class="spotlight-body">
                <p class="spotlight-kicker"><?= e($spotlight['category_name']) ?></p>
                <h2><?= e($spotlight['name']) ?></h2>
                <p><?= e($spotlight['description']) ?></p>
                <div class="price-row">
                    <strong><?= e(format_currency((int) $spotlight['price'])) ?></strong>
                    <?php if (!empty($spotlight['original_price'])): ?>
                        <span class="old-price"><?= e(format_currency((int) $spotlight['original_price'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="meta-stack">
                    <span><?= e((string) $spotlight['rating']) ?>/5 rated</span>
                    <span><?= e($spotlight['stock_status']) ?></span>
                    <span><?= e($spotlight['delivery']) ?></span>
                </div>
                <div class="action-row">
                    <a class="button button-ghost" href="product.php?slug=<?= e($spotlight['slug']) ?>">View details</a>
                    <form class="inline-form" action="cart.php" method="post">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="slug" value="<?= e($spotlight['slug']) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="redirect" value="<?= e($currentPage) ?>">
                        <button class="button button-small" type="submit">Add to cart</button>
                    </form>
                </div>
            </div>
        </aside>
    <?php endif; ?>
</section>

<section class="page-section service-strip">
    <article class="service-item">
        <strong>Rupee pricing</strong>
        <span>Clear current price and original price on every product.</span>
    </article>
    <article class="service-item">
        <strong>Live stock status</strong>
        <span>Inventory updates when items are ordered through checkout.</span>
    </article>
    <article class="service-item">
        <strong>Quick support</strong>
        <span>Call or message the store team if you want help choosing.</span>
    </article>
</section>

<section class="page-section">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Shop by category</span>
            <h2>Popular sections to start with</h2>
        </div>
        <p class="section-copy">Open a category, scan the prices, and jump straight into the products that match what you want to buy.</p>
    </div>

    <div class="category-grid">
        <?php foreach ($featuredCategories as $category): ?>
            <a class="category-card" href="products.php?category=<?= e($category['slug']) ?>" style="--accent: <?= e($category['accent_color']) ?>;">
                <img src="<?= e($category['hero_image']) ?>" alt="<?= e($category['name']) ?>">
                <div class="category-body">
                    <div class="category-top">
                        <span class="chip"><?= e((string) $category['product_count']) ?> products</span>
                        <span class="text-link">Browse</span>
                    </div>
                    <h3><?= e($category['name']) ?></h3>
                    <p><?= e($category['description']) ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="home-columns">
    <div class="page-section">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Featured deals</span>
                <h2>Products worth checking first</h2>
            </div>
            <a class="text-link" href="products.php">View full catalog</a>
        </div>

        <div class="product-grid compact-product-grid">
            <?php foreach ($featuredProducts as $product): ?>
                <?php render_product_card($product, $currentPage); ?>
            <?php endforeach; ?>
        </div>
    </div>

    <aside class="page-section side-panel">
        <div class="section-heading compact-heading">
            <div>
                <span class="eyebrow">Quick picks</span>
                <h2>Good starting points</h2>
            </div>
        </div>

        <div class="deal-list">
            <?php foreach ($dealProducts as $product): ?>
                <article class="deal-item">
                    <img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>">
                    <div class="deal-copy">
                        <span class="chip"><?= e($product['category_name']) ?></span>
                        <h3><?= e($product['name']) ?></h3>
                        <p><?= e(format_currency((int) $product['price'])) ?></p>
                        <a class="text-link" href="product.php?slug=<?= e($product['slug']) ?>">Open product</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="support-note">
            <h3>Need help before you buy?</h3>
            <p>Reach the store team for product comparisons, stock checks, or bulk purchase questions.</p>
            <a class="button button-secondary" href="contact.php">Contact the store</a>
        </div>
    </aside>
</section>
<?php
render_page_end();
