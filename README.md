# G-Mart PHP Storefront

This storefront uses PHP with a MySQL database, ready for InfinityFree hosting.

## Requirements

- PHP 8.1 or newer
- MySQL database
- `pdo_mysql` enabled

## Run locally

Update `configure.php` with your local or hosting database details first.

```bash
cd /home/techjack-p/Programming/e-commerce
php -S localhost:8000
```

Then open `http://localhost:8000`.

If you want to start it from another folder, use:

```bash
php -S localhost:8000 -t /home/techjack-p/Programming/e-commerce
```

## Database

- Put your InfinityFree MySQL host, database name, username, and password in `configure.php`.
- The required MySQL tables are created automatically on first request.
- Schema file: `database/schema.sql`
- Catalog seed data syncs into the database automatically when the catalog definition changes.
- Live inventory is preserved after orders instead of being reset on each request.
- Orders are saved into `orders` and `order_items` tables during checkout.

## Upload to InfinityFree

Upload these project files and folders:

- `app/`
- `database/`
- `image/`
- `cart.php`
- `checkout.php`
- `admin.php`
- `configure.php`
- `contact.php`
- `index.php`
- `login.php`
- `product.php`
- `products.php`
- `style.css`

Before uploading, edit `configure.php` with the exact database details shown in InfinityFree's MySQL Databases panel.

## Pages

- `index.php` - home page and featured catalog view
- `products.php` - searchable and filterable product catalog
- `product.php` - product detail page with buy-now and add-to-cart actions
- `cart.php` - session-backed shopping cart
- `login.php` - customer login, registration, and logout
- `checkout.php` - delivery form and order placement flow
- `contact.php` - validated contact form that stores enquiries
- `admin.php` - password-protected admin panel for orders and inventory

## Admin Panel

Set `admin_password` in `configure.php`, then open `admin.php`.
The admin panel uses jQuery for dashboard tabs and live table search.

## Marketplace Flow

1. Open `products.php` and add items to the cart.
2. Review or update quantities in `cart.php`.
3. Complete the delivery form in `checkout.php`.
4. The app saves the order in MySQL and reduces product inventory automatically.
