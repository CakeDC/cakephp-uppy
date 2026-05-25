<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Util;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Util\S3Trait;

class S3TraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Uppy.S3', [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version'     => 'latest',
                'region'      => 'us-east-1',
                'connection'  => 'dummy',
                'credentials' => ['key' => 'fake', 'secret' => 'fake'],
            ],
            'bucket' => 'test-bucket',
        ]);
    }

    protected function tearDown(): void
    {
        Configure::delete('Uppy.S3');
        parent::tearDown();
    }
}
