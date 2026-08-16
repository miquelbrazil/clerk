<?php

declare(strict_types=1);

/*
 * Pest configuration.
 *
 * Integration tests touch the real Postgres staging database — there is no
 * SQLite fallback by design (docs/decisions.md D-010). They are grouped so a
 * suite run without a database can exclude them:
 *
 *     vendor/bin/pest --exclude-group=database
 */

use App\Database\ConnectionFactory;

require_once dirname(__DIR__) . '/config/bootstrap.php';

/**
 * Reports whether the configured staging database is reachable.
 */
function databaseIsReachable(): bool
{
    return ConnectionFactory::isReachable();
}
