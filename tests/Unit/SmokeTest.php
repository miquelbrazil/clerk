<?php

declare(strict_types=1);

/*
 * Proves the suite runs and the library autoloads — the Phase 1 smoke test.
 */

use App\Web\AppFactory;
use Slim\App;

it('runs the test suite', function (): void {
    expect(true)->toBeTrue();
});

it('requires PHP 8.4 or newer', function (): void {
    expect(PHP_VERSION_ID)->toBeGreaterThanOrEqual(80400);
});

it('autoloads the application namespace', function (): void {
    expect(class_exists(AppFactory::class))->toBeTrue();
});

it('builds the Slim application', function (): void {
    expect(AppFactory::create())->toBeInstanceOf(App::class);
});
