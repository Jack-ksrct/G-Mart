<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!class_exists('PDO')) {
        throw new RuntimeException('PDO is not enabled in this PHP installation.');
    }

    if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException(
            'MySQL support is missing. Enable the PDO MySQL extension for PHP before running this storefront.'
        );
    }

    $config = require __DIR__ . '/../configure.php';
    $requiredKeys = ['host', 'database', 'username', 'password', 'charset'];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config) || trim((string) $config[$key]) === '') {
            throw new RuntimeException('Missing database configuration value: ' . $key . '.');
        }
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        (string) $config['host'],
        (string) $config['database'],
        (string) $config['charset']
    );

    if (!empty($config['port'])) {
        $dsn .= ';port=' . (int) $config['port'];
    }

    try {
        $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
    } catch (PDOException $exception) {
        throw new RuntimeException(
            'Unable to connect to the MySQL database. Check the values in configure.php.',
            0,
            $exception
        );
    }

    initialize_database($pdo);

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $schema = load_schema_sql();

    if ($schema === '') {
        throw new RuntimeException('Unable to load the database schema.');
    }

    foreach (split_schema_statements($schema) as $statement) {
        $pdo->exec($statement);
    }

    sync_catalog($pdo);

    $initialized = true;
}

function load_schema_sql(): string
{
    $schemaPath = __DIR__ . '/../database/schema.sql';
    $schema = file_get_contents($schemaPath);

    return $schema === false ? '' : trim($schema);
}

function split_schema_statements(string $schema): array
{
    return array_values(array_filter(array_map('trim', explode(';', $schema))));
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? $user : null;
}

function user_is_logged_in(): bool
{
    return current_user() !== null;
}

function set_current_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => (string) $user['name'],
        'email' => (string) $user['email'],
        'phone' => (string) ($user['phone'] ?? ''),
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
}

function validate_login_input(array $input): array
{
    $data = [
        'email' => strtolower(trim((string) ($input['email'] ?? ''))),
        'password' => (string) ($input['password'] ?? ''),
    ];

    $errors = [];

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($data['password'] === '') {
        $errors['password'] = 'Enter your password.';
    }

    return [$data, $errors];
}

function validate_registration_input(array $input): array
{
    $data = [
        'name' => trim((string) ($input['name'] ?? '')),
        'email' => strtolower(trim((string) ($input['email'] ?? ''))),
        'phone' => preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '',
        'password' => (string) ($input['password'] ?? ''),
    ];

    $errors = [];

    if ($data['name'] === '' || strlen($data['name']) < 2) {
        $errors['name'] = 'Enter your full name.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($data['phone'] !== '' && !preg_match('/^\d{10}$/', $data['phone'])) {
        $errors['phone'] = 'Enter a 10-digit phone number.';
    }

    if (strlen($data['password']) < 6) {
        $errors['password'] = 'Use at least 6 characters.';
    }

    return [$data, $errors];
}

function find_user_by_email(string $email): ?array
{
    $statement = db()->prepare(
        'SELECT id, name, email, password_hash, phone
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
    $statement->execute();
    $user = $statement->fetch();

    return $user === false ? null : $user;
}

function register_user(array $data): array
{
    if (find_user_by_email($data['email']) !== null) {
        throw new RuntimeException('An account already exists with this email address.');
    }

    $statement = db()->prepare(
        'INSERT INTO users (name, email, password_hash, phone)
         VALUES (:name, :email, :password_hash, :phone)'
    );
    $statement->execute([
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ':phone' => $data['phone'],
    ]);

    $user = [
        'id' => (int) db()->lastInsertId(),
        'name' => $data['name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
    ];

    set_current_user($user);

    return $user;
}

function login_user(array $data): bool
{
    $user = find_user_by_email($data['email']);

    if ($user === null || !password_verify($data['password'], (string) $user['password_hash'])) {
        return false;
    }

    set_current_user($user);

    return true;
}

function sync_catalog(PDO $pdo): void
{
    $seedHash = hash('sha256', serialize([
        'categories' => catalog_categories(),
        'products' => catalog_products(),
    ]));

    $statement = $pdo->prepare('SELECT value FROM app_meta WHERE meta_key = :meta_key LIMIT 1');
    $statement->bindValue(':meta_key', 'catalog_seed_hash', PDO::PARAM_STR);
    $statement->execute();
    $savedHash = $statement->fetchColumn();

    if ($savedHash === $seedHash) {
        return;
    }

    seed_database($pdo);

    $metaUpsert = $pdo->prepare(
        'INSERT INTO app_meta (meta_key, value)
         VALUES (:meta_key, :value)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $metaUpsert->execute([
        ':meta_key' => 'catalog_seed_hash',
        ':value' => $seedHash,
    ]);
}

function seed_database(PDO $pdo): void
{
    $categoryUpsert = $pdo->prepare(
        'INSERT INTO categories (slug, name, description, accent_color, hero_image, featured)
         VALUES (:slug, :name, :description, :accent_color, :hero_image, :featured)
         ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            description = VALUES(description),
            accent_color = VALUES(accent_color),
            hero_image = VALUES(hero_image),
            featured = VALUES(featured)'
    );

    $productUpsert = $pdo->prepare(
        'INSERT INTO products (
            category_id,
            name,
            slug,
            description,
            price,
            original_price,
            rating,
            inventory,
            stock_status,
            delivery,
            tag,
            badge,
            image_path,
            featured
        ) VALUES (
            :category_id,
            :name,
            :slug,
            :description,
            :price,
            :original_price,
            :rating,
            :inventory,
            :stock_status,
            :delivery,
            :tag,
            :badge,
            :image_path,
            :featured
        )
        ON DUPLICATE KEY UPDATE
            category_id = VALUES(category_id),
            name = VALUES(name),
            description = VALUES(description),
            price = VALUES(price),
            original_price = VALUES(original_price),
            rating = VALUES(rating),
            delivery = VALUES(delivery),
            tag = VALUES(tag),
            badge = VALUES(badge),
            image_path = VALUES(image_path),
            featured = VALUES(featured)'
    );

    $pdo->beginTransaction();

    try {
        foreach (catalog_categories() as $category) {
            $categoryUpsert->execute($category);
        }

        $categoryRows = $pdo->query('SELECT id, slug FROM categories')->fetchAll();
        $categoryIds = [];

        foreach ($categoryRows as $row) {
            $categoryIds[$row['slug']] = (int) $row['id'];
        }

        foreach (catalog_products() as $product) {
            $product['category_id'] = $categoryIds[$product['category_slug']];
            unset($product['category_slug']);
            $productUpsert->execute($product);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function catalog_categories(): array
{
    return [
        [
            'slug' => 'smart-watch',
            'name' => 'Smart Watches',
            'description' => 'Health tracking, quick calling, and bright displays for everyday wear.',
            'accent_color' => '#ef7c45',
            'hero_image' => 'image/Smart Watch/Noice Smart Watch Pro.jpg',
            'featured' => 1,
        ],
        [
            'slug' => 'headphones',
            'name' => 'Headphones',
            'description' => 'Portable, gaming, and focus-ready audio gear with reliable battery life.',
            'accent_color' => '#1e8b84',
            'hero_image' => 'image/Headphones/Headphones.jpg',
            'featured' => 1,
        ],
        [
            'slug' => 'smart-phone',
            'name' => 'Mobile Phones',
            'description' => 'Flagship phones and value devices chosen for battery, speed, and cameras.',
            'accent_color' => '#1f4d8f',
            'hero_image' => 'image/Smart Phone/OnePlus Phone.jpg',
            'featured' => 1,
        ],
        [
            'slug' => 'laptops',
            'name' => 'Laptops',
            'description' => 'Performance notebooks for study, work, travel, and heavy multitasking.',
            'accent_color' => '#6d5bd0',
            'hero_image' => 'image/Laptop/Gaming Laptop.jpg',
            'featured' => 1,
        ],
        [
            'slug' => 'cameras',
            'name' => 'Cameras',
            'description' => 'Mirrorless, DSLR, and action-ready cameras for creators and travelers.',
            'accent_color' => '#c44f7f',
            'hero_image' => 'image/Camera/Mirrorless Camera.jpg',
            'featured' => 1,
        ],
        [
            'slug' => 'bluetooth-speakers',
            'name' => 'Bluetooth Speakers',
            'description' => 'Portable speakers for home, travel, parties, and outdoor use.',
            'accent_color' => '#148662',
            'hero_image' => 'image/Bluetooth Speaker/JBL Speaker.jpg',
            'featured' => 0,
        ],
        [
            'slug' => 'tablets',
            'name' => 'Tablets',
            'description' => 'Large-screen devices for streaming, classes, sketching, and casual work.',
            'accent_color' => '#b86c1b',
            'hero_image' => 'image/Tablet/Ipad.jpg',
            'featured' => 0,
        ],
        [
            'slug' => 'dashcams',
            'name' => 'Dashcams',
            'description' => 'Road-safety cameras with night vision, parking mode, and wide coverage.',
            'accent_color' => '#8b3f2f',
            'hero_image' => 'image/Dashcam/Qubo.jpg',
            'featured' => 0,
        ],
    ];
}

function catalog_products(): array
{
    return [
        [
            'category_slug' => 'smart-watch',
            'name' => 'Noise Smart Watch Pro',
            'slug' => 'noise-smart-watch-pro',
            'description' => 'AMOLED display, workout tracking, and a polished strap design for daily wear.',
            'price' => 1999,
            'original_price' => 2499,
            'rating' => 4.6,
            'inventory' => 24,
            'stock_status' => 'Ready to ship',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Fitness',
            'badge' => 'Best seller',
            'image_path' => 'image/Smart Watch/Noice Smart Watch Pro.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'smart-watch',
            'name' => 'Fitness Watch',
            'slug' => 'fitness-watch',
            'description' => 'A light training partner with sleep tracking, heart-rate alerts, and long battery life.',
            'price' => 2499,
            'original_price' => 2999,
            'rating' => 4.5,
            'inventory' => 18,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Active',
            'badge' => 'Coach pick',
            'image_path' => 'image/Smart Watch/Fitness Watch.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'smart-watch',
            'name' => 'Hybrid Smart Watch',
            'slug' => 'hybrid-smart-watch',
            'description' => 'Classic analog styling outside with smart reminders and movement insights inside.',
            'price' => 1499,
            'original_price' => 1999,
            'rating' => 4.3,
            'inventory' => 20,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Lifestyle',
            'badge' => 'Everyday value',
            'image_path' => 'image/Smart Watch/Hybrid Smart Watch.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'smart-watch',
            'name' => 'AMOLED Runner Watch',
            'slug' => 'amoled-runner-watch',
            'description' => 'Bright display, guided run modes, and extra battery backup for active days.',
            'price' => 3299,
            'original_price' => 3899,
            'rating' => 4.7,
            'inventory' => 14,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'AMOLED',
            'badge' => 'New launch',
            'image_path' => 'image/Smart Watch/Noice Smart Watch Pro.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'smart-watch',
            'name' => 'Call Sync Smart Watch',
            'slug' => 'call-sync-smart-watch',
            'description' => 'Bluetooth calling, message alerts, and clean daily health tracking.',
            'price' => 2799,
            'original_price' => 3299,
            'rating' => 4.4,
            'inventory' => 16,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Calls',
            'badge' => 'Value pick',
            'image_path' => 'image/Smart Watch/Fitness Watch.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'headphones',
            'name' => 'Gaming Headphones',
            'slug' => 'gaming-headphones',
            'description' => 'Boom mic, oversized drivers, and a balanced fit for long competitive sessions.',
            'price' => 1990,
            'original_price' => 2590,
            'rating' => 4.5,
            'inventory' => 12,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Gaming',
            'badge' => 'Top rated',
            'image_path' => 'image/Headphones/Headphones.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'headphones',
            'name' => 'Wireless Headphones',
            'slug' => 'wireless-headphones',
            'description' => 'Foldable wireless set with smooth bass and travel-friendly comfort.',
            'price' => 2990,
            'original_price' => 3490,
            'rating' => 4.4,
            'inventory' => 20,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Wireless',
            'badge' => 'Travel ready',
            'image_path' => 'image/Headphones/Wireless Headphone.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'headphones',
            'name' => 'Earpods',
            'slug' => 'earpods',
            'description' => 'Pocket-sized buds for calls, playlists, and quick meetings on the go.',
            'price' => 1999,
            'original_price' => 2299,
            'rating' => 4.2,
            'inventory' => 27,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Portable',
            'badge' => 'Quick grab',
            'image_path' => 'image/Headphones/Earpods.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'headphones',
            'name' => 'ANC Neckband',
            'slug' => 'anc-neckband',
            'description' => 'Commuter-friendly wireless audio with noise reduction and steady battery life.',
            'price' => 2499,
            'original_price' => 2999,
            'rating' => 4.3,
            'inventory' => 25,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'ANC',
            'badge' => 'Commute pick',
            'image_path' => 'image/Headphones/Wireless Headphone.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'headphones',
            'name' => 'Studio Monitor Headphones',
            'slug' => 'studio-monitor-headphones',
            'description' => 'Balanced tuning for editing, practice sessions, and clearer monitoring.',
            'price' => 4599,
            'original_price' => 5299,
            'rating' => 4.6,
            'inventory' => 10,
            'stock_status' => 'Limited stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Studio',
            'badge' => 'Clear audio',
            'image_path' => 'image/Headphones/Headphones.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'smart-phone',
            'name' => 'OnePlus Phone',
            'slug' => 'oneplus-phone',
            'description' => 'Fast charging, smooth display, and reliable day-to-day flagship performance.',
            'price' => 49999,
            'original_price' => 54999,
            'rating' => 4.7,
            'inventory' => 10,
            'stock_status' => 'Fast moving',
            'delivery' => 'Priority delivery available',
            'tag' => 'Flagship',
            'badge' => 'Speed favorite',
            'image_path' => 'image/Smart Phone/OnePlus Phone.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'smart-phone',
            'name' => 'Samsung Galaxy A16',
            'slug' => 'samsung-galaxy-a16',
            'description' => 'Balanced mid-range phone with a vivid screen and versatile camera.',
            'price' => 35999,
            'original_price' => 39999,
            'rating' => 4.4,
            'inventory' => 14,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Value',
            'badge' => 'Budget smart',
            'image_path' => 'image/Smart Phone/Samsung A16.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'smart-phone',
            'name' => 'iPhone 15',
            'slug' => 'iphone-15',
            'description' => 'Premium camera system, polished ecosystem, and long software support.',
            'price' => 69999,
            'original_price' => 74999,
            'rating' => 4.8,
            'inventory' => 8,
            'stock_status' => 'Limited stock',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Premium',
            'badge' => 'Camera king',
            'image_path' => 'image/Smart Phone/Iphone 15.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'smart-phone',
            'name' => 'Redmi Note 13 5G',
            'slug' => 'redmi-note-13-5g',
            'description' => 'Reliable 5G phone with a sharp screen and strong everyday battery life.',
            'price' => 21999,
            'original_price' => 23999,
            'rating' => 4.3,
            'inventory' => 18,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => '5G',
            'badge' => 'Hot value',
            'image_path' => 'image/Smart Phone/Samsung A16.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'smart-phone',
            'name' => 'Nothing Phone 2a',
            'slug' => 'nothing-phone-2a',
            'description' => 'Distinctive design, strong display quality, and smooth daily performance.',
            'price' => 27999,
            'original_price' => 30999,
            'rating' => 4.6,
            'inventory' => 9,
            'stock_status' => 'Fast moving',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Design',
            'badge' => 'New style',
            'image_path' => 'image/Smart Phone/OnePlus Phone.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'laptops',
            'name' => 'Gaming Laptop',
            'slug' => 'gaming-laptop',
            'description' => 'High-refresh display, discrete graphics, and thermal headroom for serious play.',
            'price' => 199999,
            'original_price' => 214999,
            'rating' => 4.8,
            'inventory' => 6,
            'stock_status' => 'Low stock',
            'delivery' => 'Ships with insured packing',
            'tag' => 'Gaming',
            'badge' => 'Power build',
            'image_path' => 'image/Laptop/Gaming Laptop.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'laptops',
            'name' => 'Business Laptop',
            'slug' => 'business-laptop',
            'description' => 'Slim design, dependable performance, and battery life tuned for workdays.',
            'price' => 79999,
            'original_price' => 86999,
            'rating' => 4.5,
            'inventory' => 9,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Work',
            'badge' => 'Office pick',
            'image_path' => 'image/Laptop/Business Laptop.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'laptops',
            'name' => 'Student Laptop',
            'slug' => 'student-laptop',
            'description' => 'Portable notebook for notes, browsing, classes, and lightweight work.',
            'price' => 49999,
            'original_price' => 53999,
            'rating' => 4.4,
            'inventory' => 17,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Study',
            'badge' => 'Campus choice',
            'image_path' => 'image/Laptop/Student Laptop.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'laptops',
            'name' => 'Creator Laptop 14',
            'slug' => 'creator-laptop-14',
            'description' => 'A high-color display and strong processor pairing for design work and edits.',
            'price' => 104999,
            'original_price' => 112999,
            'rating' => 4.6,
            'inventory' => 7,
            'stock_status' => 'In stock',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Creator',
            'badge' => 'Render ready',
            'image_path' => 'image/Laptop/Business Laptop.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'laptops',
            'name' => 'Thin and Light Laptop',
            'slug' => 'thin-and-light-laptop',
            'description' => 'Portable workhorse for classes, office tasks, travel, and long unplugged sessions.',
            'price' => 64999,
            'original_price' => 70999,
            'rating' => 4.5,
            'inventory' => 13,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Portable',
            'badge' => 'Daily carry',
            'image_path' => 'image/Laptop/Student Laptop.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'cameras',
            'name' => 'DSLR Camera',
            'slug' => 'dslr-camera',
            'description' => 'Manual control, crisp image quality, and dependable lenses for creators.',
            'price' => 29999,
            'original_price' => 33999,
            'rating' => 4.6,
            'inventory' => 11,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Creator',
            'badge' => 'Studio starter',
            'image_path' => 'image/Camera/Camera.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'cameras',
            'name' => 'Mirrorless Camera',
            'slug' => 'mirrorless-camera',
            'description' => 'Compact body with strong autofocus and creator-friendly video controls.',
            'price' => 45999,
            'original_price' => 49999,
            'rating' => 4.7,
            'inventory' => 7,
            'stock_status' => 'Fast moving',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Pro',
            'badge' => 'Creator favorite',
            'image_path' => 'image/Camera/Mirrorless Camera.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'cameras',
            'name' => 'Action Camera',
            'slug' => 'action-camera',
            'description' => 'Adventure-ready camera with stabilization and rugged framing support.',
            'price' => 15999,
            'original_price' => 18999,
            'rating' => 4.4,
            'inventory' => 13,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Outdoor',
            'badge' => 'Weekend gear',
            'image_path' => 'image/Camera/Action Camera.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'cameras',
            'name' => 'Vlog Camera Kit',
            'slug' => 'vlog-camera-kit',
            'description' => 'Compact creator setup with fast autofocus and easy handheld framing.',
            'price' => 24999,
            'original_price' => 27999,
            'rating' => 4.5,
            'inventory' => 8,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Vlog',
            'badge' => 'Creator kit',
            'image_path' => 'image/Camera/Mirrorless Camera.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'cameras',
            'name' => 'Travel Zoom Camera',
            'slug' => 'travel-zoom-camera',
            'description' => 'An easy carry camera with zoom flexibility for trips and family events.',
            'price' => 36999,
            'original_price' => 39999,
            'rating' => 4.4,
            'inventory' => 6,
            'stock_status' => 'Limited stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Travel',
            'badge' => 'Holiday pick',
            'image_path' => 'image/Camera/Camera.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'bluetooth-speakers',
            'name' => 'Boat Speaker',
            'slug' => 'boat-speaker',
            'description' => 'Portable party speaker with bold bass and long battery backup.',
            'price' => 3499,
            'original_price' => 4199,
            'rating' => 4.3,
            'inventory' => 22,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Party',
            'badge' => 'Outdoor sound',
            'image_path' => 'image/Bluetooth Speaker/Boat Speaker.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'bluetooth-speakers',
            'name' => 'JBL Speaker',
            'slug' => 'jbl-speaker',
            'description' => 'Portable tuning with clean vocals and reliable Bluetooth range.',
            'price' => 8999,
            'original_price' => 9999,
            'rating' => 4.6,
            'inventory' => 16,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Portable',
            'badge' => 'Room filler',
            'image_path' => 'image/Bluetooth Speaker/JBL Speaker.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'bluetooth-speakers',
            'name' => 'Sony Speaker',
            'slug' => 'sony-speaker',
            'description' => 'Premium portable speaker with strong clarity and an upscale finish.',
            'price' => 12999,
            'original_price' => 14499,
            'rating' => 4.5,
            'inventory' => 9,
            'stock_status' => 'In stock',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Premium',
            'badge' => 'Audio upgrade',
            'image_path' => 'image/Bluetooth Speaker/Sony Speaker.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'bluetooth-speakers',
            'name' => 'Mini Party Speaker',
            'slug' => 'mini-party-speaker',
            'description' => 'A compact room-filler with bass boost and easy phone pairing.',
            'price' => 5499,
            'original_price' => 6299,
            'rating' => 4.4,
            'inventory' => 17,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Party',
            'badge' => 'Bass boost',
            'image_path' => 'image/Bluetooth Speaker/Boat Speaker.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'bluetooth-speakers',
            'name' => 'Waterproof Speaker',
            'slug' => 'waterproof-speaker',
            'description' => 'Outdoor-ready portable speaker built for poolside playlists and travel.',
            'price' => 6999,
            'original_price' => 7799,
            'rating' => 4.5,
            'inventory' => 15,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Outdoor',
            'badge' => 'Splash safe',
            'image_path' => 'image/Bluetooth Speaker/JBL Speaker.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'tablets',
            'name' => 'OnePlus Tablet',
            'slug' => 'oneplus-tablet',
            'description' => 'Slim tablet for media, study, browsing, and light planning on the move.',
            'price' => 14999,
            'original_price' => 16999,
            'rating' => 4.4,
            'inventory' => 19,
            'stock_status' => 'In stock',
            'delivery' => 'Ships tomorrow',
            'tag' => 'Study',
            'badge' => 'Smart value',
            'image_path' => 'image/Tablet/Tablet.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'tablets',
            'name' => 'iPad',
            'slug' => 'ipad',
            'description' => 'Fluid performance for notes, entertainment, sketching, and focused everyday work.',
            'price' => 49999,
            'original_price' => 53999,
            'rating' => 4.8,
            'inventory' => 8,
            'stock_status' => 'Fast moving',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Premium',
            'badge' => 'Creator favorite',
            'image_path' => 'image/Tablet/Ipad.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'tablets',
            'name' => 'Samsung Tab',
            'slug' => 'samsung-tab',
            'description' => 'Large-screen tablet with strong media playback and useful multitasking.',
            'price' => 24999,
            'original_price' => 27999,
            'rating' => 4.5,
            'inventory' => 14,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Entertainment',
            'badge' => 'Streaming pick',
            'image_path' => 'image/Tablet/Samsung Tab.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'tablets',
            'name' => 'Android Study Pad',
            'slug' => 'android-study-pad',
            'description' => 'Student-focused tablet for reading, video lessons, notes, and light tasks.',
            'price' => 18999,
            'original_price' => 20999,
            'rating' => 4.3,
            'inventory' => 18,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Study',
            'badge' => 'Classroom pick',
            'image_path' => 'image/Tablet/Tablet.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'tablets',
            'name' => 'Premium Drawing Tablet',
            'slug' => 'premium-drawing-tablet',
            'description' => 'Smooth stylus-ready tablet for sketching, note taking, and creative work.',
            'price' => 58999,
            'original_price' => 62999,
            'rating' => 4.7,
            'inventory' => 5,
            'stock_status' => 'Low stock',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Design',
            'badge' => 'Pen ready',
            'image_path' => 'image/Tablet/Ipad.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'dashcams',
            'name' => 'Boat Dashcam',
            'slug' => 'boat-dashcam',
            'description' => 'Road safety camera with loop recording and a simple in-car setup.',
            'price' => 7999,
            'original_price' => 8999,
            'rating' => 4.3,
            'inventory' => 21,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Safety',
            'badge' => 'Daily driver',
            'image_path' => 'image/Dashcam/dashcam.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'dashcams',
            'name' => 'Qubo Dashcam',
            'slug' => 'qubo-dashcam',
            'description' => 'Compact dashcam with wide-angle coverage and easy mobile syncing.',
            'price' => 9999,
            'original_price' => 10999,
            'rating' => 4.5,
            'inventory' => 13,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Connected',
            'badge' => 'Road favorite',
            'image_path' => 'image/Dashcam/Qubo.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'dashcams',
            'name' => 'Night Vision Dashcam',
            'slug' => 'night-vision-dashcam',
            'description' => 'Extra night coverage with crisp low-light capture and parking protection.',
            'price' => 12999,
            'original_price' => 14499,
            'rating' => 4.6,
            'inventory' => 11,
            'stock_status' => 'Limited stock',
            'delivery' => 'Premium delivery in 48 hours',
            'tag' => 'Night drive',
            'badge' => 'Night safety',
            'image_path' => 'image/Dashcam/Night Vision Dashcam.jpg',
            'featured' => 0,
        ],
        [
            'category_slug' => 'dashcams',
            'name' => 'Dual Lens Dashcam',
            'slug' => 'dual-lens-dashcam',
            'description' => 'Front and cabin coverage for rideshare drivers and long road trips.',
            'price' => 14999,
            'original_price' => 16499,
            'rating' => 4.6,
            'inventory' => 10,
            'stock_status' => 'In stock',
            'delivery' => 'Delivery in 2 days',
            'tag' => 'Dual view',
            'badge' => 'Cabin + road',
            'image_path' => 'image/Dashcam/Night Vision Dashcam.jpg',
            'featured' => 1,
        ],
        [
            'category_slug' => 'dashcams',
            'name' => 'Parking Monitor Dashcam',
            'slug' => 'parking-monitor-dashcam',
            'description' => 'Compact safety cam with parking watch, motion detection, and clean footage.',
            'price' => 10999,
            'original_price' => 12499,
            'rating' => 4.4,
            'inventory' => 12,
            'stock_status' => 'In stock',
            'delivery' => 'Dispatches in 24 hours',
            'tag' => 'Parking',
            'badge' => '24/7 guard',
            'image_path' => 'image/Dashcam/Qubo.jpg',
            'featured' => 0,
        ],
    ];
}

function product_select_sql(): string
{
    return <<<SQL
        SELECT
            p.*,
            c.slug AS category_slug,
            c.name AS category_name,
            c.accent_color,
            c.description AS category_description
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
    SQL;
}

function get_catalog_metrics(): array
{
    $pdo = db();

    $productCount = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $categoryCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $averageRating = (float) $pdo->query('SELECT AVG(rating) FROM products')->fetchColumn();
    $inventory = (int) $pdo->query('SELECT SUM(inventory) FROM products')->fetchColumn();
    $enquiries = (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    $orders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

    return [
        'product_count' => $productCount,
        'category_count' => $categoryCount,
        'average_rating' => number_format($averageRating, 1),
        'inventory' => $inventory,
        'enquiries' => $enquiries,
        'orders' => $orders,
    ];
}

function get_admin_password(): string
{
    $config = require __DIR__ . '/../configure.php';

    return trim((string) ($config['admin_password'] ?? ''));
}

function admin_password_is_configured(): bool
{
    $password = get_admin_password();

    return $password !== '' && $password !== 'change_this_admin_password';
}

function admin_is_logged_in(): bool
{
    return !empty($_SESSION['admin_authenticated']);
}

function login_admin(string $password): bool
{
    if (!admin_password_is_configured()) {
        return false;
    }

    if (!hash_equals(get_admin_password(), $password)) {
        return false;
    }

    $_SESSION['admin_authenticated'] = true;

    return true;
}

function logout_admin(): void
{
    unset($_SESSION['admin_authenticated']);
}

function get_admin_metrics(): array
{
    $pdo = db();

    return [
        'orders' => (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'revenue' => (int) $pdo->query('SELECT COALESCE(SUM(subtotal), 0) FROM orders')->fetchColumn(),
        'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'low_stock' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE inventory <= 5')->fetchColumn(),
        'customers' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'enquiries' => (int) $pdo->query('SELECT COUNT(*) FROM contacts')->fetchColumn(),
    ];
}

function get_recent_orders(int $limit = 12): array
{
    $statement = db()->prepare(
        'SELECT *
         FROM orders
         ORDER BY created_at DESC, id DESC
         LIMIT :limit'
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function get_order_items_by_order_id(int $orderId): array
{
    $statement = db()->prepare(
        'SELECT *
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY id ASC'
    );
    $statement->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function get_admin_products(): array
{
    $sql = product_select_sql() . ' ORDER BY p.inventory ASC, p.name ASC';

    return db()->query($sql)->fetchAll();
}

function get_admin_order_statuses(): array
{
    return ['Placed', 'Processing', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];
}

function update_order_status(int $orderId, string $status): bool
{
    if (!in_array($status, get_admin_order_statuses(), true)) {
        return false;
    }

    $statement = db()->prepare(
        'UPDATE orders
         SET status = :status
         WHERE id = :id'
    );
    $statement->bindValue(':status', $status, PDO::PARAM_STR);
    $statement->bindValue(':id', $orderId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->rowCount() > 0;
}

function update_product_inventory_status(int $productId, int $inventory, string $status): bool
{
    $inventory = max(0, $inventory);
    $status = trim($status);

    if ($status === '') {
        $status = resolve_stock_status($inventory);
    }

    $statement = db()->prepare(
        'UPDATE products
         SET inventory = :inventory, stock_status = :stock_status
         WHERE id = :id'
    );
    $statement->bindValue(':inventory', $inventory, PDO::PARAM_INT);
    $statement->bindValue(':stock_status', $status, PDO::PARAM_STR);
    $statement->bindValue(':id', $productId, PDO::PARAM_INT);
    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_categories(): array
{
    $sql = <<<SQL
        SELECT
            c.id,
            c.slug,
            c.name,
            c.description,
            c.accent_color,
            c.hero_image,
            c.featured,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id
        ORDER BY c.featured DESC, c.name ASC
    SQL;

    return db()->query($sql)->fetchAll();
}

function get_featured_categories(int $limit = 4): array
{
    $sql = <<<SQL
        SELECT
            c.id,
            c.slug,
            c.name,
            c.description,
            c.accent_color,
            c.hero_image,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON p.category_id = c.id
        GROUP BY c.id
        ORDER BY c.featured DESC, product_count DESC, c.name ASC
        LIMIT :limit
    SQL;

    $statement = db()->prepare($sql);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function get_featured_products(int $limit = 6): array
{
    $sql = product_select_sql() . ' ORDER BY p.featured DESC, p.rating DESC, p.price DESC LIMIT :limit';
    $statement = db()->prepare($sql);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function get_products(array $filters = []): array
{
    $allowedSorts = get_sort_options();
    $sort = $filters['sort'] ?? 'featured';

    if (!array_key_exists($sort, $allowedSorts)) {
        $sort = 'featured';
    }

    $sql = product_select_sql();
    $conditions = [];
    $params = [];

    if (!empty($filters['search'])) {
        $conditions[] = '(p.name LIKE :search OR p.description LIKE :search OR c.name LIKE :search OR p.tag LIKE :search)';
        $params[':search'] = '%' . trim((string) $filters['search']) . '%';
    }

    if (!empty($filters['category'])) {
        $conditions[] = 'c.slug = :category';
        $params[':category'] = trim((string) $filters['category']);
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $orderBy = match ($sort) {
        'price_low' => 'p.price ASC, p.rating DESC',
        'price_high' => 'p.price DESC, p.rating DESC',
        'top_rated' => 'p.rating DESC, p.featured DESC, p.price DESC',
        'name' => 'p.name ASC',
        default => 'p.featured DESC, p.rating DESC, p.price DESC',
    };

    $sql .= ' ORDER BY ' . $orderBy;

    $statement = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value, PDO::PARAM_STR);
    }

    $statement->execute();

    return $statement->fetchAll();
}

function get_product_by_slug(string $slug): ?array
{
    $sql = product_select_sql() . ' WHERE p.slug = :slug LIMIT 1';
    $statement = db()->prepare($sql);
    $statement->bindValue(':slug', $slug, PDO::PARAM_STR);
    $statement->execute();
    $product = $statement->fetch();

    return $product === false ? null : $product;
}

function get_related_products(string $categorySlug, string $excludeSlug, int $limit = 4): array
{
    $sql = product_select_sql() . ' WHERE c.slug = :category AND p.slug != :slug ORDER BY p.featured DESC, p.rating DESC LIMIT :limit';
    $statement = db()->prepare($sql);
    $statement->bindValue(':category', $categorySlug, PDO::PARAM_STR);
    $statement->bindValue(':slug', $excludeSlug, PDO::PARAM_STR);
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function get_products_by_slugs(array $slugs): array
{
    $slugs = array_values(array_filter(array_map('strval', $slugs)));

    if ($slugs === []) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($slugs), '?'));
    $sql = product_select_sql() . ' WHERE p.slug IN (' . $placeholders . ')';
    $statement = db()->prepare($sql);

    foreach ($slugs as $index => $slug) {
        $statement->bindValue($index + 1, $slug, PDO::PARAM_STR);
    }

    $statement->execute();
    $rows = $statement->fetchAll();
    $bySlug = [];

    foreach ($rows as $row) {
        $bySlug[$row['slug']] = $row;
    }

    $ordered = [];

    foreach ($slugs as $slug) {
        if (isset($bySlug[$slug])) {
            $ordered[] = $bySlug[$slug];
        }
    }

    return $ordered;
}

function get_sort_options(): array
{
    return [
        'featured' => 'Featured first',
        'top_rated' => 'Top rated',
        'price_low' => 'Price: low to high',
        'price_high' => 'Price: high to low',
        'name' => 'Name: A to Z',
    ];
}

function product_has_inventory(array $product): bool
{
    return (int) ($product['inventory'] ?? 0) > 0;
}

function get_cart(): array
{
    $cart = $_SESSION['cart'] ?? [];
    $normalized = [];

    if (!is_array($cart)) {
        return [];
    }

    foreach ($cart as $slug => $quantity) {
        $slug = trim((string) $slug);
        $quantity = (int) $quantity;

        if ($slug !== '' && $quantity > 0) {
            $normalized[$slug] = $quantity;
        }
    }

    return $normalized;
}

function save_cart(array $cart): void
{
    if ($cart === []) {
        unset($_SESSION['cart']);
        return;
    }

    $_SESSION['cart'] = $cart;
}

function get_cart_count(): int
{
    return array_sum(get_cart());
}

function add_to_cart(string $slug, int $quantity = 1): bool
{
    $product = get_product_by_slug($slug);

    if ($product === null) {
        return false;
    }

    $quantity = max(1, $quantity);
    $inventory = max(0, (int) $product['inventory']);

    if ($inventory === 0) {
        return false;
    }

    $cart = get_cart();
    $currentQuantity = (int) ($cart[$slug] ?? 0);
    $cart[$slug] = min($inventory, $currentQuantity + $quantity);
    save_cart($cart);

    return true;
}

function update_cart_quantity(string $slug, int $quantity): bool
{
    $cart = get_cart();

    if (!isset($cart[$slug])) {
        return false;
    }

    if ($quantity <= 0) {
        unset($cart[$slug]);
        save_cart($cart);
        return true;
    }

    $product = get_product_by_slug($slug);

    if ($product === null) {
        unset($cart[$slug]);
        save_cart($cart);
        return false;
    }

    $inventory = max(0, (int) $product['inventory']);

    if ($inventory === 0) {
        unset($cart[$slug]);
        save_cart($cart);
        return false;
    }

    $cart[$slug] = min($inventory, $quantity);
    save_cart($cart);

    return true;
}

function remove_from_cart(string $slug): void
{
    $cart = get_cart();
    unset($cart[$slug]);
    save_cart($cart);
}

function clear_cart(): void
{
    unset($_SESSION['cart']);
}

function get_cart_items(): array
{
    $cart = get_cart();

    if ($cart === []) {
        return [];
    }

    $products = get_products_by_slugs(array_keys($cart));
    $items = [];
    $normalizedCart = [];

    foreach ($products as $product) {
        $slug = (string) $product['slug'];
        $inventory = max(0, (int) $product['inventory']);
        $quantity = min((int) $cart[$slug], $inventory);

        if ($quantity < 1) {
            continue;
        }

        $lineTotal = (int) $product['price'] * $quantity;
        $lineOriginalTotal = !empty($product['original_price'])
            ? (int) $product['original_price'] * $quantity
            : $lineTotal;

        $normalizedCart[$slug] = $quantity;
        $items[] = array_merge($product, [
            'quantity' => $quantity,
            'line_total' => $lineTotal,
            'line_original_total' => $lineOriginalTotal,
        ]);
    }

    save_cart($normalizedCart);

    return $items;
}

function get_cart_totals(): array
{
    $items = get_cart_items();
    $subtotal = 0;
    $originalTotal = 0;
    $itemCount = 0;

    foreach ($items as $item) {
        $subtotal += (int) $item['line_total'];
        $originalTotal += (int) $item['line_original_total'];
        $itemCount += (int) $item['quantity'];
    }

    return [
        'items_count' => $itemCount,
        'subtotal' => $subtotal,
        'original_total' => $originalTotal,
        'savings' => max(0, $originalTotal - $subtotal),
    ];
}

function resolve_stock_status(int $inventory, string $currentStatus = 'In stock'): string
{
    if ($inventory <= 0) {
        return 'Sold out';
    }

    if ($inventory <= 5) {
        return 'Low stock';
    }

    if ($inventory <= 10) {
        return 'Limited stock';
    }

    $normalized = strtolower(trim($currentStatus));

    if (in_array($normalized, ['fast moving', 'ready to ship', 'in stock'], true)) {
        return $currentStatus;
    }

    return 'In stock';
}

function generate_order_number(PDO $pdo): string
{
    do {
        $orderNumber = 'GM-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $statement = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_number = :order_number');
        $statement->bindValue(':order_number', $orderNumber, PDO::PARAM_STR);
        $statement->execute();
    } while ((int) $statement->fetchColumn() > 0);

    return $orderNumber;
}

function validate_checkout_input(array $input): array
{
    $data = [
        'customer_name' => trim((string) ($input['customer_name'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'phone' => preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '',
        'address_line' => trim((string) ($input['address_line'] ?? '')),
        'city' => trim((string) ($input['city'] ?? '')),
        'state' => trim((string) ($input['state'] ?? '')),
        'postal_code' => preg_replace('/\D+/', '', (string) ($input['postal_code'] ?? '')) ?? '',
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];

    $errors = [];

    if ($data['customer_name'] === '' || strlen($data['customer_name']) < 2) {
        $errors['customer_name'] = 'Please enter the customer name.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (!preg_match('/^\d{10}$/', $data['phone'])) {
        $errors['phone'] = 'Please enter a 10-digit phone number.';
    }

    if ($data['address_line'] === '' || strlen($data['address_line']) < 8) {
        $errors['address_line'] = 'Please add a delivery address.';
    }

    if ($data['city'] === '') {
        $errors['city'] = 'Please enter the city.';
    }

    if ($data['state'] === '') {
        $errors['state'] = 'Please enter the state.';
    }

    if (!preg_match('/^\d{6}$/', $data['postal_code'])) {
        $errors['postal_code'] = 'Please enter a 6-digit PIN code.';
    }

    return [$data, $errors];
}

function place_order_from_cart(array $customer): array
{
    $items = get_cart_items();

    if ($items === []) {
        throw new RuntimeException('Cannot place an order with an empty cart.');
    }

    $totals = get_cart_totals();
    $pdo = db();
    $orderNumber = generate_order_number($pdo);

    $orderInsert = $pdo->prepare(
        'INSERT INTO orders (
            order_number,
            customer_name,
            email,
            phone,
            address_line,
            city,
            state,
            postal_code,
            notes,
            items_count,
            subtotal,
            status
        ) VALUES (
            :order_number,
            :customer_name,
            :email,
            :phone,
            :address_line,
            :city,
            :state,
            :postal_code,
            :notes,
            :items_count,
            :subtotal,
            :status
        )'
    );

    $itemInsert = $pdo->prepare(
        'INSERT INTO order_items (
            order_id,
            product_slug,
            product_name,
            category_name,
            image_path,
            unit_price,
            quantity,
            line_total
        ) VALUES (
            :order_id,
            :product_slug,
            :product_name,
            :category_name,
            :image_path,
            :unit_price,
            :quantity,
            :line_total
        )'
    );

    $stockUpdate = $pdo->prepare(
        'UPDATE products
         SET inventory = :inventory, stock_status = :stock_status
         WHERE slug = :slug'
    );

    $pdo->beginTransaction();

    try {
        $orderInsert->execute([
            ':order_number' => $orderNumber,
            ':customer_name' => $customer['customer_name'],
            ':email' => $customer['email'],
            ':phone' => $customer['phone'],
            ':address_line' => $customer['address_line'],
            ':city' => $customer['city'],
            ':state' => $customer['state'],
            ':postal_code' => $customer['postal_code'],
            ':notes' => $customer['notes'],
            ':items_count' => $totals['items_count'],
            ':subtotal' => $totals['subtotal'],
            ':status' => 'Placed',
        ]);

        $orderId = (int) $pdo->lastInsertId();

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $currentInventory = (int) $item['inventory'];
            $remainingInventory = max(0, $currentInventory - $quantity);

            $itemInsert->execute([
                ':order_id' => $orderId,
                ':product_slug' => $item['slug'],
                ':product_name' => $item['name'],
                ':category_name' => $item['category_name'],
                ':image_path' => $item['image_path'],
                ':unit_price' => $item['price'],
                ':quantity' => $quantity,
                ':line_total' => $item['line_total'],
            ]);

            $stockUpdate->execute([
                ':inventory' => $remainingInventory,
                ':stock_status' => resolve_stock_status($remainingInventory, (string) $item['stock_status']),
                ':slug' => $item['slug'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    clear_cart();

    $order = get_order_with_items($orderNumber);

    if ($order === null) {
        throw new RuntimeException('The order was placed, but the confirmation could not be loaded.');
    }

    return $order;
}

function get_order_with_items(string $orderNumber): ?array
{
    $orderStatement = db()->prepare(
        'SELECT *
         FROM orders
         WHERE order_number = :order_number
         LIMIT 1'
    );
    $orderStatement->bindValue(':order_number', $orderNumber, PDO::PARAM_STR);
    $orderStatement->execute();
    $order = $orderStatement->fetch();

    if ($order === false) {
        return null;
    }

    $itemStatement = db()->prepare(
        'SELECT *
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY id ASC'
    );
    $itemStatement->bindValue(':order_id', (int) $order['id'], PDO::PARAM_INT);
    $itemStatement->execute();

    $order['items'] = $itemStatement->fetchAll();

    return $order;
}

function validate_contact_input(array $input): array
{
    $data = [
        'name' => trim((string) ($input['name'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'phone' => preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '',
        'city' => trim((string) ($input['city'] ?? '')),
        'budget' => trim((string) ($input['budget'] ?? '')),
        'message' => trim((string) ($input['message'] ?? '')),
    ];

    $errors = [];

    if ($data['name'] === '' || strlen($data['name']) < 2) {
        $errors['name'] = 'Please enter your name.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if (!preg_match('/^\d{10}$/', $data['phone'])) {
        $errors['phone'] = 'Please enter a 10-digit phone number.';
    }

    if ($data['message'] === '' || strlen($data['message']) < 10) {
        $errors['message'] = 'Tell us a little more about what you need.';
    }

    return [$data, $errors];
}

function save_contact_request(array $data): void
{
    $statement = db()->prepare(
        'INSERT INTO contacts (name, email, phone, city, budget, message)
         VALUES (:name, :email, :phone, :city, :budget, :message)'
    );

    $statement->execute([
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':phone' => $data['phone'],
        ':city' => $data['city'],
        ':budget' => $data['budget'],
        ':message' => $data['message'],
    ]);
}
