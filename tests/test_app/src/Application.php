<?php
declare(strict_types=1);

namespace App;

use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\RouteBuilder;

/**
 * Minimal Application stub for plugin integration tests.
 */
class Application extends BaseApplication
{
    /**
     * Override bootstrap to avoid requiring a real config/bootstrap.php.
     * Plugins are loaded explicitly here.
     */
    public function bootstrap(): void
    {
        // Do NOT call parent::bootstrap() — it requires a real config/bootstrap.php.

        // Prepend our test-app templates directory so test-only error templates
        // (e.g. Error/error400.php) are found without shipping them in the plugin.
        // __DIR__ = tests/test_app/src → dirname one level = tests/test_app
        $testAppTemplates = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR;
        Configure::write('App.paths.templates', array_merge(
            [$testAppTemplates],
            (array)Configure::read('App.paths.templates', [])
        ));

        $this->addPlugin('CakeDC/Uppy');
    }

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            ->add(new BodyParserMiddleware())
            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }

    /**
     * Override routes to use the plugin's own routes file rather than a
     * non-existent app routes file.
     */
    public function routes(RouteBuilder $routes): void
    {
        // Load the plugin routes (CakeDC/Uppy config/routes.php)
        $this->pluginRoutes($routes);
    }
}
