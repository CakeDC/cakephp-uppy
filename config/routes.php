<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $builder): void {
    $builder->plugin(
        'CakeDC/Uppy',
        ['path' => '/uppy'],
        function (RouteBuilder $routes): void {
            $routes->setRouteClass(DashedRoute::class);
        }
    );
};
