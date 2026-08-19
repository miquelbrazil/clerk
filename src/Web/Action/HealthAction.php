<?php

declare(strict_types=1);

namespace App\Web\Action;

use App\Database\ConnectionFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Liveness endpoint: reports whether the app booted and can reach Postgres.
 *
 * Returns 503 when the database is unreachable so a deployment health check
 * fails loudly rather than serving a half-working app.
 */
final class HealthAction
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $database = $this->databaseStatus();
        $healthy = $database === 'ok';

        $payload = [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => [
                'php' => PHP_VERSION,
                'database' => $database,
            ],
        ];

        $response->getBody()->write((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($healthy ? 200 : 503);
    }

    /**
     * Never leaks connection details or credentials into the response
     * (CLAUDE.md Secrets Rules); failures report a fixed string only.
     */
    private function databaseStatus(): string
    {
        return ConnectionFactory::isReachable() ? 'ok' : 'unreachable';
    }
}
