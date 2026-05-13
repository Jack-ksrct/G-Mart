<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$form = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'city' => '',
    'budget' => '',
    'message' => '',
];
$errors = [];
$notice = pull_flash('contact_notice');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$form, $errors] = validate_contact_input($_POST);

    if ($errors === []) {
        save_contact_request($form);
        set_flash('contact_notice', 'Your enquiry has been sent. The team can follow up using the details you shared.');
        redirect_to('contact.php');
    }
}

$metrics = get_catalog_metrics();

render_page_start('Contact sales', 'contact');
?>
<section class="page-section contact-hero">
    <div class="section-heading compact-heading">
        <div>
            <span class="eyebrow">Contact</span>
            <h1>Talk to the store team</h1>
        </div>
        <p class="section-copy">Ask about stock, pricing, product suggestions, or bulk buying help and the team can get back to you with a clear answer.</p>
    </div>

    <div class="support-grid">
        <article class="support-card">
            <span class="chip">Direct call</span>
            <h3><a href="<?= e(phone_href(SUPPORT_PHONE)) ?>"><?= e(SUPPORT_PHONE) ?></a></h3>
            <p>Ideal for quick stock checks and same-day buying help.</p>
        </article>
        <article class="support-card">
            <span class="chip">Email desk</span>
            <h3><a href="mailto:<?= e(SUPPORT_EMAIL) ?>"><?= e(SUPPORT_EMAIL) ?></a></h3>
            <p>Use this for detailed quotes, business orders, or product comparisons.</p>
        </article>
        <article class="support-card">
            <span class="chip">Live store stats</span>
            <h3><?= e((string) $metrics['enquiries']) ?> saved enquiries</h3>
            <p>Customers are already using the form for quotes, stock checks, and order support.</p>
        </article>
    </div>
</section>

<section class="page-section contact-layout">
    <div class="form-shell">
        <?php if ($notice !== null): ?>
            <div class="notice notice-success">
                <?= e($notice) ?>
            </div>
        <?php endif; ?>

        <form class="contact-form" method="post" action="contact.php" novalidate>
            <div class="field-grid">
                <label class="field">
                    <span>Name</span>
                    <input type="text" name="name" value="<?= e($form['name']) ?>" placeholder="Your full name">
                    <?php if (isset($errors['name'])): ?>
                        <small class="form-error"><?= e($errors['name']) ?></small>
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
                    <span>City</span>
                    <input type="text" name="city" value="<?= e($form['city']) ?>" placeholder="Your city">
                </label>

                <label class="field field-wide">
                    <span>Budget range</span>
                    <select name="budget">
                        <option value="">Select a budget</option>
                        <option value="under-5000" <?= $form['budget'] === 'under-5000' ? 'selected' : '' ?>>Under ₹5,000</option>
                        <option value="5000-25000" <?= $form['budget'] === '5000-25000' ? 'selected' : '' ?>>₹5,000 to ₹25,000</option>
                        <option value="25000-75000" <?= $form['budget'] === '25000-75000' ? 'selected' : '' ?>>₹25,000 to ₹75,000</option>
                        <option value="75000-plus" <?= $form['budget'] === '75000-plus' ? 'selected' : '' ?>>₹75,000 and above</option>
                    </select>
                </label>

                <label class="field field-wide">
                    <span>What do you need?</span>
                    <textarea name="message" rows="6" placeholder="Tell us the product type, use case, or feature list you have in mind"><?= e($form['message']) ?></textarea>
                    <?php if (isset($errors['message'])): ?>
                        <small class="form-error"><?= e($errors['message']) ?></small>
                    <?php endif; ?>
                </label>
            </div>

            <button class="button" type="submit">Save enquiry</button>
        </form>
    </div>

    <aside class="contact-sidebar">
        <article class="feature-card">
            <span class="eyebrow">Quick help</span>
            <h3>Get product guidance faster</h3>
            <p>Share your budget and what you need, and the team can narrow down good options instead of starting from scratch.</p>
        </article>
        <article class="feature-card">
            <span class="eyebrow">For bigger purchases</span>
            <h3>Useful for bulk or business orders</h3>
            <p>If you are comparing several products or buying for a team, this is the easiest place to ask for help.</p>
        </article>
        <article class="feature-card">
            <span class="eyebrow">Need product ideas first?</span>
            <h3>Browse the catalog first</h3>
            <p><a class="text-link" href="products.php">Open the products page</a> if you want to compare items before reaching out.</p>
        </article>
    </aside>
</section>
<?php
render_page_end();
