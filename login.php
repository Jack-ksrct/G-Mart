<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$redirect = safe_redirect_target((string) ($_GET['redirect'] ?? 'index.php'), 'index.php');
$loginForm = [
    'email' => '',
    'password' => '',
];
$registerForm = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'password' => '',
];
$loginErrors = [];
$registerErrors = [];
$notice = pull_flash('auth_notice');
$authError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? 'login'));

    if ($action === 'logout') {
        logout_user();
        set_flash('auth_notice', 'You have been logged out.');
        redirect_to('login.php');
    }

    if ($action === 'register') {
        $mode = 'register';
        [$registerForm, $registerErrors] = validate_registration_input($_POST);

        if ($registerErrors === []) {
            try {
                register_user($registerForm);
                set_flash('auth_notice', 'Account created. You are now logged in.');
                redirect_to($redirect, 'index.php');
            } catch (Throwable $exception) {
                $authError = $exception->getMessage();
            }
        }
    } else {
        $mode = 'login';
        [$loginForm, $loginErrors] = validate_login_input($_POST);

        if ($loginErrors === [] && login_user($loginForm)) {
            set_flash('auth_notice', 'Welcome back. You are logged in.');
            redirect_to($redirect, 'index.php');
        }

        if ($loginErrors === []) {
            $authError = 'The email or password is incorrect.';
        }
    }
}

$user = current_user();

render_page_start('Customer login', 'login');
?>
<section class="page-section">
    <?php if ($notice !== null): ?>
        <div class="notice notice-success"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($authError !== null): ?>
        <div class="notice notice-error"><?= e($authError) ?></div>
    <?php endif; ?>

    <div class="section-heading compact-heading">
        <div>
            <span class="eyebrow">Customer account</span>
            <h1><?= $user === null ? 'Login to your account' : 'Your account is active' ?></h1>
        </div>
        <p class="section-copy">
            <?= $user === null
                ? 'Sign in before checkout, or create a new account with your email and mobile number.'
                : 'You are signed in and can continue shopping with this account.' ?>
        </p>
    </div>
</section>

<?php if ($user !== null): ?>
    <section class="page-section auth-layout">
        <div class="form-shell account-card">
            <span class="chip">Logged in</span>
            <h2><?= e($user['name']) ?></h2>
            <p><?= e($user['email']) ?></p>
            <?php if ($user['phone'] !== ''): ?>
                <p><?= e($user['phone']) ?></p>
            <?php endif; ?>
            <div class="form-actions">
                <a class="button" href="products.php">Continue shopping</a>
                <form class="inline-form" method="post" action="login.php">
                    <input type="hidden" name="action" value="logout">
                    <button class="button button-ghost" type="submit">Logout</button>
                </form>
            </div>
        </div>

        <aside class="contact-sidebar">
            <article class="feature-card">
                <span class="eyebrow">Cart</span>
                <h3><?= e((string) get_cart_count()) ?> item<?= get_cart_count() === 1 ? '' : 's' ?> saved</h3>
                <p><a class="text-link" href="cart.php">Open your cart</a> to review items and proceed to checkout.</p>
            </article>
            <article class="feature-card">
                <span class="eyebrow">Support</span>
                <h3>Need help?</h3>
                <p><a class="text-link" href="contact.php">Contact the store</a> for order help, stock checks, or product suggestions.</p>
            </article>
        </aside>
    </section>
<?php else: ?>
    <section class="page-section auth-layout">
        <div class="form-shell auth-panel">
            <div class="auth-tabs">
                <a class="auth-tab <?= $mode === 'login' ? 'is-active' : '' ?>" href="login.php">Login</a>
                <a class="auth-tab <?= $mode === 'register' ? 'is-active' : '' ?>" href="login.php?mode=register">Create account</a>
            </div>

            <?php if ($mode === 'register'): ?>
                <form class="contact-form" method="post" action="login.php?mode=register&redirect=<?= e(rawurlencode($redirect)) ?>" novalidate>
                    <input type="hidden" name="action" value="register">
                    <div class="field-grid">
                        <label class="field">
                            <span>Name</span>
                            <input type="text" name="name" value="<?= e($registerForm['name']) ?>" placeholder="Your full name">
                            <?php if (isset($registerErrors['name'])): ?>
                                <small class="form-error"><?= e($registerErrors['name']) ?></small>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e($registerForm['email']) ?>" placeholder="you@example.com">
                            <?php if (isset($registerErrors['email'])): ?>
                                <small class="form-error"><?= e($registerErrors['email']) ?></small>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Phone</span>
                            <input type="tel" name="phone" value="<?= e($registerForm['phone']) ?>" placeholder="10-digit mobile number">
                            <?php if (isset($registerErrors['phone'])): ?>
                                <small class="form-error"><?= e($registerErrors['phone']) ?></small>
                            <?php endif; ?>
                        </label>

                        <label class="field">
                            <span>Password</span>
                            <input type="password" name="password" placeholder="At least 6 characters">
                            <?php if (isset($registerErrors['password'])): ?>
                                <small class="form-error"><?= e($registerErrors['password']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                    <button class="button" type="submit">Create account</button>
                </form>
            <?php else: ?>
                <form class="contact-form" method="post" action="login.php?redirect=<?= e(rawurlencode($redirect)) ?>" novalidate>
                    <input type="hidden" name="action" value="login">
                    <div class="field-grid">
                        <label class="field field-wide">
                            <span>Email</span>
                            <input type="email" name="email" value="<?= e($loginForm['email']) ?>" placeholder="you@example.com">
                            <?php if (isset($loginErrors['email'])): ?>
                                <small class="form-error"><?= e($loginErrors['email']) ?></small>
                            <?php endif; ?>
                        </label>

                        <label class="field field-wide">
                            <span>Password</span>
                            <input type="password" name="password" placeholder="Your password">
                            <?php if (isset($loginErrors['password'])): ?>
                                <small class="form-error"><?= e($loginErrors['password']) ?></small>
                            <?php endif; ?>
                        </label>
                    </div>
                    <button class="button" type="submit">Login</button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="contact-sidebar">
            <article class="feature-card">
                <span class="eyebrow">Checkout-ready</span>
                <h3>Keep your cart and account in one session</h3>
                <p>Login keeps the shopping flow familiar while your cart stays available until checkout.</p>
            </article>
            <article class="feature-card">
                <span class="eyebrow">New customer</span>
                <h3>Create an account in seconds</h3>
                <p>Use your email, phone, and password to start a customer profile for the store.</p>
            </article>
        </aside>
    </section>
<?php endif; ?>
<?php
render_page_end();
