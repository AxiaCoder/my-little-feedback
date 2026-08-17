<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

/*
 * Rebuild the test database once, before the suite runs.
 *
 * The connection already points at `mlf_test` rather than `mlf`: `when@test` in
 * config/packages/doctrine.yaml appends `dbname_suffix: _test`. Nothing ever
 * created that database, which stayed invisible only because the suite booted
 * the kernel and never opened a connection.
 *
 * The schema is built by running the **migrations**, never
 * `doctrine:schema:create`. Spec 01 §2.7: building it from entity metadata would
 * skip the feedback types the migration seeds, so every functional test on
 * POST /api/feedback would fail on a type that does not exist. It also means the
 * migrations are exercised on every run — a check this project would otherwise
 * never have.
 *
 * Dropping rather than reusing costs a second and buys determinism: a run can
 * never inherit a row, a sequence or a half-applied migration from the run
 * before it.
 */
$console = static function (string ...$command): void {
    // `--env=test` is passed explicitly because PHPUnit sets APP_ENV in $_SERVER
    // through phpunit.dist.xml, and a child process inherits the real environment
    // rather than that array. Without it these three commands would cheerfully
    // drop the development database.
    $process = new Process(
        [PHP_BINARY, 'bin/console', ...$command, '--env=test', '--no-interaction'],
        dirname(__DIR__),
    );
    $process->setTimeout(120);
    $process->run();

    if ($process->isSuccessful()) {
        return;
    }

    fwrite(STDERR, sprintf(
        "\nPreparing the test database failed.\n\n  %s\n\n%s%s\n",
        $process->getCommandLine(),
        $process->getOutput(),
        $process->getErrorOutput(),
    ));

    exit(1);
};

$console('doctrine:database:drop', '--force', '--if-exists');
$console('doctrine:database:create');

// `--allow-no-migration` is here because migrations/ is still empty. Drop the
// flag once the first migration lands: an empty migrations directory should then
// be an error, not a suite that passes against an empty schema.
$console('doctrine:migrations:migrate', '--allow-no-migration');
