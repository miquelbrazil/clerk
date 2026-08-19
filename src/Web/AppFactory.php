<?php

declare(strict_types=1);

namespace App\Web;

use App\Web\Action\HealthAction;
use App\Web\Action\HomeAction;
use DI\Container;
use League\Plates\Engine;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

/**
 * Builds the Slim application.
 *
 * Routes and actions stay thin: they prepare view data and hand it to a
 * template. Business logic lives in src/ as a library (CLAUDE.md Conventions).
 */
final class AppFactory
{
    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    public static function create(): App
    {
        $container = new Container();

        $container->set(Engine::class, static function (): Engine {
            $engine = new Engine(ROOT . DS . 'templates');
            $engine->addFolder('layout', ROOT . DS . 'templates' . DS . 'layout');

            return $engine;
        });

        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        $app->addRoutingMiddleware();
        $app->addErrorMiddleware(self::debugEnabled(), true, true);

        $app->get('/', HomeAction::class);
        $app->get('/health', HealthAction::class);

        return $app;
    }

    private static function debugEnabled(): bool
    {
        $value = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG');

        return in_array($value, ['1', 'true', true], true);
    }
}
