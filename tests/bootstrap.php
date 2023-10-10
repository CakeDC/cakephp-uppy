<?php
declare(strict_types=1);

/**
 * Copyright 2013 - 2023, Cake Development Corporation, Las Vegas, Nevada (702) 425-5085 https://www.cakedc.com
 * Use and restrictions are governed by Section 8.5 of The Professional Services Agreement.
 * Redistribution is prohibited. All Rights Reserved.
 *
 * @copyright Copyright 2013 - 2023, Cake Development Corporation (https://www.cakedc.com) All Rights Reserved.
 */
use Cake\Core\Plugin;
use Cake\I18n\I18n;
use Cake\I18n\Package;
use CakeDC\Uppy\UppyPlugin;

/**
 * @throws \Exception
 */
$findRoot = function ($root) {
    do {
        $lastRoot = $root;
        $root = dirname($root);
        if (is_dir($root . '/vendor/cakephp/cakephp')) {
            return $root;
        }
    } while ($root !== $lastRoot);

    throw new Exception('Cannot find the root of the application, unable to run tests');
};
$root = $findRoot(__FILE__);
unset($findRoot);

chdir($root);
if (file_exists($root . '/config/bootstrap.php')) {
    require $root . '/config/bootstrap.php';
}

require $root . '/vendor/cakephp/cakephp/tests/bootstrap.php';
require $root . '/vendor/cakephp/cakephp/src/functions.php';

Plugin::getCollection()->add(new UppyPlugin(['path' => dirname(__FILE__, 2) . DS]));
I18n::config('default', function ($name, $locale) {
    $package = new Package('default');
    $messages = [
        'Active' => 'Translated Active',
        'Foo' => 'translated foo',
    ];
    $package->setMessages($messages);

    return $package;
});
