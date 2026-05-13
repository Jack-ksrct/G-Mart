<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

const APP_NAME = 'G-Mart';
const APP_TAGLINE = 'Phones, laptops, audio, and accessories at everyday prices.';
const SUPPORT_PHONE = '+91 7397572882';
const SUPPORT_EMAIL = 'sales@gmart.in';

require_once __DIR__ . '/store.php';
require_once __DIR__ . '/layout.php';

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_currency(int $amount): string
{
    return '₹' . number_format($amount);
}

function phone_href(string $phone): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}

function safe_redirect_target(string $target, string $fallback = 'index.php'): string
{
    $target = trim($target);

    if (
        $target === '' ||
        str_contains($target, '://') ||
        str_starts_with($target, '//') ||
        str_starts_with(strtolower($target), 'javascript:')
    ) {
        return $fallback;
    }

    return preg_match('/^[A-Za-z0-9_?=&%.#\\/-]+$/', $target) === 1 ? $target : $fallback;
}

function current_request_uri(string $fallback = 'index.php'): string
{
    return safe_redirect_target((string) ($_SERVER['REQUEST_URI'] ?? $fallback), $fallback);
}

function redirect_to(string $target, string $fallback = 'index.php'): never
{
    header('Location: ' . safe_redirect_target($target, $fallback));
    exit;
}

function set_flash(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function pull_flash(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $message = (string) $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    if (empty($_SESSION['_flash'])) {
        unset($_SESSION['_flash']);
    }

    return $message;
}
