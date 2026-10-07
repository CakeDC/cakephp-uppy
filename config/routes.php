<?php
declare(strict_types=1);

/**
 * Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
use Cake\Core\Configure;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return static function (RouteBuilder $builder): void {
    if (!Configure::read('Uppy.routes', true)) {
        return;
    }

    $builder->plugin(
        'CakeDC/Uppy',
        ['path' => '/uppy'],
        function (RouteBuilder $routes): void {
            $routes->setRouteClass(DashedRoute::class);

            // Only the known endpoints are routed. HTTP methods are enforced by
            // the controller's allowMethod() so reverse routing keeps working.
            $routes->connect('/files/sign', ['controller' => 'Files', 'action' => 'sign']);
            $routes->connect('/files/save', ['controller' => 'Files', 'action' => 'save']);
            $routes->connect('/files/create-multipart-upload', [
                'controller' => 'Files',
                'action' => 'createMultipartUpload',
            ]);
            $routes->connect('/files/sign-part', ['controller' => 'Files', 'action' => 'signPart']);
            $routes->connect('/files/complete-multipart-upload', [
                'controller' => 'Files',
                'action' => 'completeMultipartUpload',
            ]);
            $routes->connect('/files/abort-multipart-upload', [
                'controller' => 'Files',
                'action' => 'abortMultipartUpload',
            ]);

            // File management endpoints (ownership is enforced in the controller).
            // The trailing `*` carries the record id as a passed argument, which
            // keeps URL generation working for `['action' => 'view', $id]`.
            $routes->connect('/files/view/*', ['controller' => 'Files', 'action' => 'view']);
            $routes->connect('/files/delete/*', ['controller' => 'Files', 'action' => 'delete']);
            $routes->connect('/files/index', ['controller' => 'Files', 'action' => 'index']);
            $routes->connect('/files', ['controller' => 'Files', 'action' => 'index']);

            // Demo pages are only exposed when debug is on.
            if (Configure::read('debug')) {
                $routes->connect('/files/add', ['controller' => 'Files', 'action' => 'add']);
                $routes->connect('/files/drag', ['controller' => 'Files', 'action' => 'drag']);
            }
        },
    );
};
