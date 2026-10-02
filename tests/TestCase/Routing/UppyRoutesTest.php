<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Routing;

use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\RouteBuilder;
use Cake\Routing\RouteCollection;
use Cake\TestSuite\TestCase;

/**
 * Verifies the plugin routes are registered regardless of `debug`
 * (issue: production endpoints must be reachable with debug off).
 */
class UppyRoutesTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::delete('debug');
        Configure::delete('Uppy.routes');
        parent::tearDown();
    }

    /**
     * Build a RouteCollection loading the plugin's config/routes.php.
     *
     * @return \Cake\Routing\RouteCollection
     */
    protected function collection(): RouteCollection
    {
        $collection = new RouteCollection();
        $builder = new RouteBuilder($collection, '/');
        $loader = require dirname(__DIR__, 3) . '/config/routes.php';
        $loader($builder);

        return $collection;
    }

    /**
     * @return void
     */
    public function testSignRouteIsRegisteredWithDebugOff(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', true);

        $params = $this->collection()->parseRequest(
            new ServerRequest(['url' => '/uppy/files/sign']),
        );

        $this->assertSame('CakeDC/Uppy', $params['plugin']);
        $this->assertSame('Files', $params['controller']);
        $this->assertSame('sign', $params['action']);
    }

    /**
     * @return void
     */
    public function testRoutesNotRegisteredWhenFlagIsOff(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', false);

        $this->expectException(MissingRouteException::class);
        $this->collection()->parseRequest(
            new ServerRequest(['url' => '/uppy/files/sign']),
        );
    }
}
