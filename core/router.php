<?php

declare(strict_types=1);

/*
 * Router script for PHP's built-in web server. Development only — never used
 * behind a real web server.
 *
 * Without it, `php -S -t public` answers 404 to any URI whose last segment
 * contains a dot: it assumes a static file, finds none, and never reaches the
 * front controller. `/api/doc.json` is the first casualty, and the symptom is
 * misleading — the route exists and `debug:router` lists it.
 *
 * Returning false hands the request back to the built-in server so it serves
 * genuine static files itself (Swagger UI's assets under public/bundles).
 */

$path = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);

if (\is_string($path) && '' !== $path && is_file(__DIR__.'/public'.$path)) {
    return false;
}

// Symfony derives the base URL from these. Left pointing at router.php, every
// generated URL would be prefixed with /router.php.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';

require __DIR__.'/public/index.php';
