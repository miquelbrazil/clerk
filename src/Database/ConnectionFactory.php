<?php

declare(strict_types=1);

namespace App\Database;

use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use RuntimeException;
use Throwable;

/**
 * Bootstraps cakephp/orm in standalone mode (no framework).
 *
 * CakePHP's ConnectionManager and TableLocator are static registries. Per
 * CLAUDE.md they are the single sanctioned exception to the no-static-state
 * rule, and that exception is confined to this class — application code takes
 * Table instances by constructor injection rather than reaching for the
 * locator itself. See docs/decisions.md D-015.
 */
final class ConnectionFactory
{
    public const string DEFAULT_ALIAS = 'default';

    /**
     * Registers a connection configuration under the given alias.
     *
     * Credentials come from the environment only — never from disk. Locally
     * they are Lando's container credentials; in deployment they are injected
     * by Infisical (Zone B). See docs/decisions.md D-005.
     */
    public static function register(string $alias = self::DEFAULT_ALIAS): void
    {
        if (in_array($alias, ConnectionManager::configured(), true)) {
            return;
        }

        ConnectionManager::setConfig($alias, self::config());
    }

    /**
     * Returns the connection, typed as the concrete Connection.
     *
     * ConnectionManager::get() is declared as returning ConnectionInterface,
     * which does not expose execute()/selectQuery(); the query API lives on
     * the concrete class. Callers get the usable type from here.
     */
    public static function get(string $alias = self::DEFAULT_ALIAS): Connection
    {
        self::register($alias);

        $connection = ConnectionManager::get($alias);

        if (!$connection instanceof Connection) {
            throw new RuntimeException(sprintf(
                'Connection "%s" must be a %s.',
                $alias,
                Connection::class
            ));
        }

        return $connection;
    }

    /**
     * Reports whether the configured database is reachable.
     */
    public static function isReachable(string $alias = self::DEFAULT_ALIAS): bool
    {
        try {
            self::get($alias)->execute('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'className' => \Cake\Database\Connection::class,
            'driver' => Postgres::class,
            'host' => self::env('DB_HOST', 'database'),
            'port' => (int) self::env('DB_PORT', '5432'),
            'username' => self::env('DB_USERNAME', 'postgres'),
            'password' => self::env('DB_PASSWORD', ''),
            'database' => self::env('DB_DATABASE', 'clerk'),
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ];
    }

    /**
     * Reads an environment variable, falling back to a local-development default.
     *
     * Values are never logged or echoed (CLAUDE.md Secrets Rules).
     */
    private static function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        // An unset variable falls back; an explicitly empty one does not —
        // Postgres passwords are legitimately empty in local containers.
        if (!is_string($value)) {
            return $default;
        }

        return $value;
    }
}
