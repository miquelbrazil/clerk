<?php

declare(strict_types=1);

/*
 * PHPStan bootstrap.
 *
 * config/paths.php is excluded from analysis (it is a define()-only bootstrap
 * inherited from CakePHP), so its constants are defined here instead to keep
 * them resolvable in the files that use them.
 */

require_once __DIR__ . '/../config/paths.php';
