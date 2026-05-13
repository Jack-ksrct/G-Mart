<?php
declare(strict_types=1);

function render_page_start(string $title, string $activePage, string $searchQuery = ''): void
{
    $pageTitle = $title . ' | ' . APP_NAME;
    $user = current_user();
    $accountLabel = 'Login';

    if ($user !== null) {
        $firstName = explode(' ', trim((string) $user['name']))[0] ?? '';
        $accountLabel = 'Hi, ' . ($firstName !== '' ? $firstName : 'Account');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="image/logo/logo.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="page page-<?= e($activePage) ?>">
    <div class="site-frame">
        <div class="top-strip">
            <span>Support: <a href="<?= e(phone_href(SUPPORT_PHONE)) ?>"><?= e(SUPPORT_PHONE) ?></a></span>
            <span>Prices shown in INR</span>
            <span>Cart and checkout available</span>
        </div>

        <header class="site-header">
            <a class="brand" href="index.php">
                <img class="brand-mark" src="image/logo/logo.png" alt="<?= e(APP_NAME) ?> logo">
                <div class="brand-copy">
                    <span class="brand-badge">Phones, laptops, audio, and more</span>
                    <strong><?= e(APP_NAME) ?></strong>
                </div>
            </a>

            <form class="header-search" action="products.php" method="get">
                <input
                    class="search-input"
                    type="search"
                    name="q"
                    placeholder="Search phones, laptops, speakers..."
                    value="<?= e($searchQuery) ?>"
                >
                <button class="search-button" type="submit">Search</button>
            </form>

            <nav class="site-nav" aria-label="Main navigation">
                <a class="nav-link <?= $activePage === 'home' ? 'is-active' : '' ?>" href="index.php">Home</a>
                <a class="nav-link <?= $activePage === 'products' ? 'is-active' : '' ?>" href="products.php">Products</a>
                <a class="nav-link <?= $activePage === 'contact' ? 'is-active' : '' ?>" href="contact.php">Contact</a>
                <a class="nav-link <?= $activePage === 'login' ? 'is-active' : '' ?>" href="login.php"><?= e($accountLabel) ?></a>
                <a class="nav-link <?= $activePage === 'admin' ? 'is-active' : '' ?>" href="admin.php">Admin</a>
                <a class="nav-link nav-cart <?= $activePage === 'cart' ? 'is-active' : '' ?>" href="cart.php">
                    Cart
                    <span class="cart-count"><?= e(get_cart_count()) ?></span>
                </a>
            </nav>
        </header>

        <main class="page-main">
    <?php
}

function render_page_end(): void
{
    ?>
        </main>

        <footer class="site-footer">
            <div>
                <p class="footer-title"><?= e(APP_NAME) ?></p>
                <p class="footer-copy"><?= e(APP_TAGLINE) ?></p>
                <p class="footer-copy">Rupee pricing, live stock, and a simple cart-to-checkout shopping flow.</p>
            </div>

            <div>
                <p class="footer-title">Support</p>
                <p class="footer-copy">Call <a href="<?= e(phone_href(SUPPORT_PHONE)) ?>"><?= e(SUPPORT_PHONE) ?></a></p>
                <p class="footer-copy">Mail <a href="mailto:<?= e(SUPPORT_EMAIL) ?>"><?= e(SUPPORT_EMAIL) ?></a></p>
            </div>

            <div>
                <p class="footer-title">Quick links</p>
                <p class="footer-copy"><a href="products.php">Browse catalog</a></p>
                <p class="footer-copy"><a href="cart.php">Open cart</a></p>
                <p class="footer-copy"><a href="checkout.php">Go to checkout</a></p>
                <p class="footer-copy"><a href="login.php"><?= current_user() === null ? 'Customer login' : 'My account' ?></a></p>
                <p class="footer-copy"><a href="admin.php">Admin panel</a></p>
                <p class="footer-copy"><a href="contact.php">Request a callback</a></p>
            </div>
        </footer>
	    </div>
	</body>
	</html>
	    <?php
}

function render_product_card(array $product, string $redirectTarget, bool $showCatalogMeta = false): void
{
    $isAvailable = product_has_inventory($product);
    ?>
    <article class="product-card" style="--accent: <?= e($product['accent_color']) ?>;">
        <div class="product-visual">
            <img class="product-image" src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>">
            <?php if (!empty($product['badge'])): ?>
                <span class="badge"><?= e($product['badge']) ?></span>
            <?php endif; ?>
        </div>

        <div class="product-body">
            <div class="product-topline">
                <span class="chip"><?= e($product['category_name']) ?></span>
                <span class="rating-pill"><?= e((string) $product['rating']) ?>/5</span>
            </div>

            <h3><?= e($product['name']) ?></h3>
            <p><?= e($product['description']) ?></p>

            <div class="price-row">
                <strong><?= e(format_currency((int) $product['price'])) ?></strong>
                <?php if (!empty($product['original_price'])): ?>
                    <span class="old-price"><?= e(format_currency((int) $product['original_price'])) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($showCatalogMeta): ?>
                <div class="meta-row">
                    <span><?= e($product['inventory']) ?> units</span>
                    <span><?= e($product['tag'] ?: $product['stock_status']) ?></span>
                </div>
                <div class="meta-row">
                    <span><?= e($product['stock_status']) ?></span>
                    <span><?= e($product['delivery']) ?></span>
                </div>
            <?php else: ?>
                <div class="meta-row">
                    <span><?= e($product['stock_status']) ?></span>
                    <span><?= e($product['delivery']) ?></span>
                </div>
            <?php endif; ?>

            <div class="action-row">
                <a class="button button-ghost" href="product.php?slug=<?= e($product['slug']) ?>">View details</a>
                <?php if ($isAvailable): ?>
                    <form class="inline-form" action="cart.php" method="post">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="redirect" value="<?= e($redirectTarget) ?>">
                        <button class="button button-small" type="submit">Add to cart</button>
                    </form>
                <?php else: ?>
                    <button class="button button-disabled button-small" type="button" disabled>Sold out</button>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}
