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
 * Verifies the plugin routes: the API is registered regardless of `debug`,
 * the demo pages are debug-only, and only the known actions are routed.
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
     * Parse a request against the plugin routes.
     *
     * @param string $url Request URL.
     * @param string $method HTTP method.
     * @return array<string, mixed>
     */
    protected function parse(string $url, string $method = 'GET'): array
    {
        return $this->collection()->parseRequest(
            new ServerRequest([
                'url' => $url,
                'environment' => ['REQUEST_METHOD' => $method],
            ]),
        );
    }

    /**
     * @return void
     */
    public function testSignRouteIsRegisteredWithDebugOff(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', true);

        $params = $this->parse('/uppy/files/sign', 'POST');

        $this->assertSame('CakeDC/Uppy', $params['plugin']);
        $this->assertSame('Files', $params['controller']);
        $this->assertSame('sign', $params['action']);
    }

    /**
     * @return void
     */
    public function testMultipartRouteIsRegisteredWithDebugOff(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', true);

        $params = $this->parse('/uppy/files/create-multipart-upload', 'POST');

        $this->assertSame('createMultipartUpload', $params['action']);
    }

    /**
     * @return void
     */
    public function testViewRoutePassesId(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', true);

        $params = $this->parse('/uppy/files/view/abc-123', 'GET');

        $this->assertSame('view', $params['action']);
        $this->assertSame(['abc-123'], $params['pass']);
    }

    /**
     * @return void
     */
    public function testDemoRoutesAreNotRegisteredWhenDebugOff(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', true);

        $this->expectException(MissingRouteException::class);
        $this->parse('/uppy/files/add', 'GET');
    }

    /**
     * @return void
     */
    public function testDemoRoutesAreRegisteredWhenDebugOn(): void
    {
        Configure::write('debug', true);
        Configure::write('Uppy.routes', true);

        $params = $this->parse('/uppy/files/add', 'GET');

        $this->assertSame('add', $params['action']);
    }

    /**
     * @return void
     */
    public function testUnknownActionIsNotRouted(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', true);

        $this->expectException(MissingRouteException::class);
        $this->parse('/uppy/files/arbitrary-action', 'GET');
    }

    /**
     * @return void
     */
    public function testRoutesNotRegisteredWhenFlagIsOff(): void
    {
        Configure::write('debug', false);
        Configure::write('Uppy.routes', false);

        $this->expectException(MissingRouteException::class);
        $this->parse('/uppy/files/sign', 'POST');
    }
}
