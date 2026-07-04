<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Storage;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Storage\R2Adapter;
use Psr\Http\Message\RequestInterface;

class R2AdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Uppy.R2', [
            'bucket' => 'test-bucket',
            'publicDomain' => 'files.example.com',
            'constants' => [
                'lifeTimePutObject' => '+5 minutes',
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimeUploadPart' => '+20 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => 'https://test.r2.cloudflarestorage.com',
                'credentials' => ['key' => 'fake', 'secret' => 'fake'],
                'connection' => 'dummy',
                'use_path_style_endpoint' => true,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Configure::delete('Uppy.R2');
        parent::tearDown();
    }

    public function testCreatePresignedRequestReturnsPsrRequest(): void
    {
        $adapter = new R2Adapter();
        $result = $adapter->createPresignedRequest('users/uuid/file.jpg', 'image/jpeg');
        $this->assertInstanceOf(RequestInterface::class, $result);
        $this->assertSame('https://example.com', (string)$result->getUri());
    }

    public function testPresignedUrlReturnsString(): void
    {
        $adapter = new R2Adapter();
        $result = $adapter->presignedUrl('users/uuid/file.jpg');
        $this->assertSame('https://example.com', $result);
    }

    public function testDeleteObjectReturnsTrueWhenConnectionDummy(): void
    {
        $adapter = new R2Adapter();
        $result = $adapter->deleteObject('users/uuid/file.jpg');
        $this->assertTrue($result);
    }

    public function testCreateMultipartUploadReturnsUploadIdAndKey(): void
    {
        $adapter = new R2Adapter();
        $result = $adapter->createMultipartUpload('users/uuid/large-video.mp4', 'video/mp4');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('uploadId', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertSame('dummy-upload-id', $result['uploadId']);
        $this->assertSame('users/uuid/large-video.mp4', $result['key']);
    }

    public function testCreatePresignedUploadPartReturnsPsrRequest(): void
    {
        $adapter = new R2Adapter();
        $result = $adapter->createPresignedUploadPart('users/uuid/file.mp4', 'upload-id-123', 1);
        $this->assertInstanceOf(RequestInterface::class, $result);
        $this->assertSame('https://example.com/part', (string)$result->getUri());
    }

    public function testCompleteMultipartUploadReturnsLocation(): void
    {
        $adapter = new R2Adapter();
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
        $adapter = new R2Adapter();
        // Should not throw any exception
        $adapter->abortMultipartUpload('users/uuid/file.mp4', 'upload-id-123');
        $this->assertTrue(true); // Assert test ran successfully
    }
}
