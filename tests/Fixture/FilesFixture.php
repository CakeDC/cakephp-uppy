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
namespace CakeDC\Uppy\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * FilesFixture
 */
class FilesFixture extends TestFixture
{
    /**
     * @var string
     */
    public $table = 'uppy_files';

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->fields = [
            'id' => ['type' => 'uuid', 'null' => false],
            'user_id' => ['type' => 'uuid', 'null' => true],
            'model' => ['type' => 'string', 'length' => 128, 'null' => true],
            'foreign_key' => ['type' => 'integer', 'null' => false],
            'filename' => ['type' => 'string', 'length' => 255, 'null' => true],
            'filesize' => ['type' => 'integer', 'null' => true],
            'mime_type' => ['type' => 'string', 'length' => 128, 'null' => true],
            'extension' => ['type' => 'string', 'length' => 32, 'null' => true],
            'hash' => ['type' => 'string', 'length' => 64, 'null' => true],
            'path' => ['type' => 'string', 'length' => 255, 'null' => true],
            'adapter' => ['type' => 'string', 'length' => 32, 'null' => true],
            'metadata' => ['type' => 'text', 'null' => true],
            'created' => ['type' => 'datetime', 'null' => true],
            'modified' => ['type' => 'datetime', 'null' => true],
        ];
        $this->records = [
            [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'foreign_key' => 1,
                'filename' => 'test.pdf',
                'filesize' => 1024,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'path' => 'test/test.pdf',
                'adapter' => 'S3',
                'created' => '2026-01-01 00:00:00',
                'modified' => '2026-01-01 00:00:00',
            ],
        ];
        parent::init();
    }
}
