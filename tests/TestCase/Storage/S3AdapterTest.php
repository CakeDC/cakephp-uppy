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
                'lifeTimeUploadPart' => '+20 minutes',
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
        $this->assertSame('https://example.com/dummy/users/uuid/file.jpg', (string)$result->getUri());
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

    public function testCreateMultipartUploadReturnsUploadIdAndKey(): void
    {
        $adapter = new S3Adapter();
        $result = $adapter->createMultipartUpload('users/uuid/large-video.mp4', 'video/mp4');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('uploadId', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertSame('dummy-upload-id', $result['uploadId']);
        $this->assertSame('users/uuid/large-video.mp4', $result['key']);
    }

    public function testCreatePresignedUploadPartReturnsPsrRequest(): void
    {
        $adapter = new S3Adapter();
        $result = $adapter->createPresignedUploadPart('users/uuid/file.mp4', 'upload-id-123', 1);
        $this->assertInstanceOf(RequestInterface::class, $result);
        $this->assertSame('https://example.com/part', (string)$result->getUri());
    }

    public function testCompleteMultipartUploadReturnsLocation(): void
    {
        $adapter = new S3Adapter();
        $parts = [
            ['PartNumber' => 1, 'ETag' => '"abc123"'],
            ['PartNumber' => 2, 'ETag' => '"def456"'],
        ];
        $result = $adapter->completeMultipartUpload('users/uuid/file.mp4', 'upload-id-123', $parts);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('location', $result);
        $this->assertStringContainsString('users/uuid/file.mp4', $result['location']);
    }

    public function testAbortMultipartUploadDoesNotThrow(): void
    {
        $adapter = new S3Adapter();
        // Should not throw any exception
        $adapter->abortMultipartUpload('users/uuid/file.mp4', 'upload-id-123');
        $this->assertTrue(true); // Assert test ran successfully
    }
}
