<?php
declare(strict_types=1);

/**
 * Copyright 2024, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2024, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Storage;

use Cake\Core\Configure;
use InvalidArgumentException;

class AdapterFactory
{
    public static function create(): StorageAdapterInterface
    {
        $driver = Configure::read('Uppy.driver', 's3');

        return match ($driver) {
            's3'    => new S3Adapter(),
            'gcs'   => new GcsAdapter(),
            'r2'    => new R2Adapter(),
            default => throw new InvalidArgumentException("Unknown Uppy driver: {$driver}"),
        };
    }
}
