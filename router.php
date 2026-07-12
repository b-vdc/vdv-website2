<?php
/**
 * Local preview router — emulates the production .htaccess for PHP's built-in
 * server (`php -S`), which ignores .htaccess. Not used in production (Apache
 * handles routing there). Keep the mapping/redirects in sync with .htaccess.
 */

$map = [
    'training'       => 'Training.php',
    'advice'         => 'Advice.php',
    'about'          => 'About.php',
    'contact'        => 'Contact.php',
    'privacy-policy' => 'Privacy-Policy.php',
    'cookie-policy'  => 'Cookie-Policy.php',
    'disclaimer'     => 'Disclaimer.php',
];

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$path = trim($path, '/');

// Canonicalise legacy URLs (capitalised and/or .php) -> clean lowercase, 301.
$slug = strtolower(preg_replace('/\.php$/i', '', $path));
$hadPhp = (bool) preg_match('/\.php$/i', $path);
$hadCaps = ($path !== strtolower($path));

if ($slug === 'index' || (($hadPhp || $hadCaps) && $path === 'index.php')) {
    header('Location: /', true, 301);
    exit;
}
if (($hadPhp || $hadCaps) && isset($map[$slug])) {
    header("Location: /$slug", true, 301);
    exit;
}

// Serve clean lowercase URLs from the capitalised .php files.
if ($path === '') {
    require __DIR__ . '/index.php';
    return true;
}
if (isset($map[$path])) {
    require __DIR__ . '/' . $map[$path];
    return true;
}

// Let the built-in server serve any real file as-is (css, js, images,
// contact-submit.php, the .html stubs, vendor, ...).
if (is_file(__DIR__ . '/' . $path)) {
    return false;
}

http_response_code(404);
echo 'Not found';
return true;
