<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Storage;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Storage\S3Adapter;
use Psr\Http\Message\RequestInterface;

class S3AdapterTest extends TestCase
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
                'version' => 'latest',
                'region' => 'us-east-1',
                'connection' => 'dummy',
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

    public function testCreatePresignedRequestReturnsPsrRequest(): void
    {
        $adapter = new S3Adapter();
        $result = $adapter->createPresignedRequest('users/uuid/file.jpg', 'image/jpeg');
        $this->assertInstanceOf(RequestInterface::class, $result);
        $this->assertSame('https://example.com', (string)$result->getUri());
    }

    public function testPresignedUrlReturnsString(): void
    {
        $adapter = new S3Adapter();
        $result = $adapter->presignedUrl('users/uuid/file.jpg');
        $this->assertSame('https://example.com', $result);
    }

    public function testDeleteObjectReturnsTrueWhenConnectionDummy(): void
    {
        $adapter = new S3Adapter();
        $result = $adapter->deleteObject('users/uuid/file.jpg');
        $this->assertTrue($result);
    }
}
