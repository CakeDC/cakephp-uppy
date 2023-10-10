<?php
declare(strict_types=1);

/**
 * Copyright 2013 - 2023, Cake Development Corporation, Las Vegas, Nevada (702) 425-5085 https://www.cakedc.com
 * Use and restrictions are governed by Section 8.5 of The Professional Services Agreement.
 * Redistribution is prohibited. All Rights Reserved.
 *
 * @copyright Copyright 2013 - 2023, Cake Development Corporation (https://www.cakedc.com) All Rights Reserved.
 */
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
