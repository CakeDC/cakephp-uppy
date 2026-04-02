<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Storage;

use CakeDC\Uppy\Storage\AdapterFactory;
use CakeDC\Uppy\Storage\GcsAdapter;
use CakeDC\Uppy\Storage\R2Adapter;
use CakeDC\Uppy\Storage\S3Adapter;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;

class AdapterFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Configure::delete('Uppy.driver');
        parent::tearDown();
    }

    public function testCreateReturnsS3AdapterForS3Driver(): void
    {
        Configure::write('Uppy.driver', 's3');
        $this->assertInstanceOf(S3Adapter::class, AdapterFactory::create());
    }

    public function testCreateReturnsGcsAdapterForGcsDriver(): void
    {
        Configure::write('Uppy.driver', 'gcs');
        $this->assertInstanceOf(GcsAdapter::class, AdapterFactory::create());
    }

    public function testCreateReturnsR2AdapterForR2Driver(): void
    {
        Configure::write('Uppy.driver', 'r2');
        $this->assertInstanceOf(R2Adapter::class, AdapterFactory::create());
    }

    public function testCreateDefaultsToS3WhenDriverNotSet(): void
    {
        Configure::delete('Uppy.driver');
        $this->assertInstanceOf(S3Adapter::class, AdapterFactory::create());
    }

    public function testCreateThrowsForUnknownDriver(): void
    {
        Configure::write('Uppy.driver', 'azure');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Uppy driver: azure');
        AdapterFactory::create();
    }
}
