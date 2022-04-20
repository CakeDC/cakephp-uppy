<?php

use Cake\Routing\Route\DashedRoute;

$routes->plugin(
    'CakeDC/Uppy',
    ['path' => '/uppy'],
    function ($routes) {
        $routes->setRouteClass(DashedRoute::class);
    }
);
