<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$notice = pull_flash('cart_notice');
$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? 'featured'));
$currentPage = current_request_uri('products.php');

$products = get_products([
    'search' => $search,
    'category' => $category,
    'sort' => $sort,
]);

$categories = get_categories();
$sortOptions = get_sort_options();

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'featured';
}

$activeCategoryName = 'All products';

foreach ($categories as $categoryRow) {
    if ($categoryRow['slug'] === $category) {
        $activeCategoryName = $categoryRow['name'];
        break;
    }
}

render_page_start('Product catalog', 'products', $search);
?>
<?php
$allQuery = http_build_query([
    'q' => $search !== '' ? $search : null,
    'sort' => $sort !== 'featured' ? $sort : null,
]);
?>
<section class="catalog-layout">
    <aside class="page-section catalog-sidebar">
        <?php if ($notice !== null): ?>
            <div class="notice notice-info"><?= e($notice) ?></div>
        <?php endif; ?>

        <div class="section-heading compact-heading">
            <div>
                <span class="eyebrow">Filters</span>
                <h1>Find products faster</h1>
            </div>
        </div>

        <form class="catalog-form" method="get" action="products.php">
            <label class="field">
                <span>Search</span>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name, type, or category">
            </label>

            <label class="field">
                <span>Category</span>
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $categoryRow): ?>
                        <option value="<?= e($categoryRow['slug']) ?>" <?= $categoryRow['slug'] === $category ? 'selected' : '' ?>>
                            <?= e($categoryRow['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <span>Sort by</span>
                <select name="sort">
                    <?php foreach ($sortOptions as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $value === $sort ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="form-actions">
                <button class="button filter-submit" type="submit">Show results</button>
                <a class="button button-ghost button-small" href="products.php">Reset</a>
            </div>
        </form>

        <div class="sidebar-group">
            <h2>Browse categories</h2>
            <div class="sidebar-links">
                <a class="sidebar-link <?= $category === '' ? 'is-active' : '' ?>" href="products.php<?= $allQuery !== '' ? '?' . e($allQuery) : '' ?>">
                    All products
                </a>
                <?php foreach ($categories as $categoryRow): ?>
                    <?php
                    $query = http_build_query([
                        'category' => $categoryRow['slug'],
                        'q' => $search !== '' ? $search : null,
                        'sort' => $sort !== 'featured' ? $sort : null,
                    ]);
                    ?>
                    <a class="sidebar-link <?= $categoryRow['slug'] === $category ? 'is-active' : '' ?>" href="products.php?<?= e($query) ?>">
                        <span><?= e($categoryRow['name']) ?></span>
                        <small><?= e((string) $categoryRow['product_count']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sidebar-note">
            <h2>Need buying help?</h2>
            <p>Use the contact page if you want product suggestions, stock confirmation, or help with a bigger order.</p>
            <a class="text-link" href="contact.php">Contact the store</a>
        </div>
    </aside>

    <div class="catalog-results">
        <section class="page-section results-panel">
            <div class="results-toolbar">
                <div>
                    <span class="eyebrow">Catalog</span>
                    <h2><?= e($activeCategoryName) ?></h2>
                    <p class="section-copy">
                        <?= e((string) count($products)) ?> products
                        <?php if ($search !== ''): ?>
                            matching "<?= e($search) ?>"
                        <?php endif; ?>.
                        Prices are shown in Indian rupees (₹).
                    </p>
                </div>
                <div class="results-meta">
                    <span>Sort: <?= e($sortOptions[$sort]) ?></span>
                    <span>Stock updates after each order</span>
                </div>
            </div>
        </section>

        <section class="page-section">
            <?php if ($products === []): ?>
                <div class="empty-state">
                    <h2>No products matched those filters</h2>
                    <p>Try a broader search or clear the category filter to see the full catalog again.</p>
                    <a class="button" href="products.php">Reset catalog</a>
                </div>
            <?php else: ?>
                <div class="product-grid compact-product-grid">
                    <?php foreach ($products as $product): ?>
                        <?php render_product_card($product, $currentPage, true); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php
render_page_end();
