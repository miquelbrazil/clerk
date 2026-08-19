<?php

declare(strict_types=1);

namespace App\Web\Action;

use League\Plates\Engine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Placeholder landing page. Phase 8 turns this into the real dashboard.
 */
final class HomeAction
{
    public function __construct(private readonly Engine $templates)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->templates->render('home', [
            'title' => 'Clerk',
            'tagline' => 'Personal financial data engineering.',
        ]));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
