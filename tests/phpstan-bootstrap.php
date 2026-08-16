<?php

declare(strict_types=1);

/*
 * PHPStan bootstrap.
 *
 * Defines the path constants from config/paths.php so analysis can resolve
 * them, WITHOUT loading spatie/ray: Ray's helpers.php registers a shutdown
 * function that instantiates Ray (and a UUID factory) inside PHPStan's
 * parallel workers, which aborts the run. Ray is a local debugging aid and
 * has no place in the analysis path.
 */

require_once __DIR__ . '/../config/paths.php';
