<?php

declare(strict_types=1);

/*
 * Shared bootstrap for every entry point (CLI, web, tests).
 *
 * Loads .env if one exists — a fresh clone has none, and secrets are injected
 * at runtime rather than read from disk (docs/decisions.md D-005 / D-013).
 */

use App\Database\ConnectionFactory;

require_once dirname(__DIR__) . '/config/paths.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT);
$dotenv->safeLoad();

ConnectionFactory::register();
