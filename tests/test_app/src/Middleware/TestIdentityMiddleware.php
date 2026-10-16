<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Identity\TestIdentity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test-only middleware: promotes the `X-Test-Identity` header into the PSR-7
 * `identity` request attribute, emulating cakephp/authentication.
 */
class TestIdentityMiddleware implements MiddlewareInterface
{
    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler Next handler.
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $userId = $request->getHeaderLine('X-Test-Identity');
        if ($userId !== '') {
            $request = $request->withAttribute('identity', new TestIdentity($userId));
        }

        return $handler->handle($request);
    }
}
