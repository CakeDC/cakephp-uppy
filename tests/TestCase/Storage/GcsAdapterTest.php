<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Storage;

use CakeDC\Uppy\Storage\GcsAdapter;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use DateTime;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StorageObject;
use Psr\Http\Message\RequestInterface;

class GcsAdapterTest extends TestCase
{
    private StorageClient $mockClient;
    private Bucket $mockBucket;
    private StorageObject $mockObject;

    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Uppy.GCS', [
            'projectId' => 'test-project',
            'keyFilePath' => '/tmp/fake-key.json',
            'bucket' => 'test-bucket',
            'lifeTimePutObject' => '+5 minutes',
            'lifeTimeGetObject' => '+20 minutes',
        ]);

        $this->mockObject = $this->createMock(StorageObject::class);
        $this->mockBucket = $this->createMock(Bucket::class);
        $this->mockBucket->method('object')->willReturn($this->mockObject);
        $this->mockClient = $this->createMock(StorageClient::class);
        $this->mockClient->method('bucket')->willReturn($this->mockBucket);
    }

    protected function tearDown(): void
    {
        Configure::delete('Uppy.GCS');
        parent::tearDown();
    }

    public function testCreatePresignedRequestReturnsPsrRequestWithPutMethod(): void
    {
        $this->mockObject
            ->method('signedUrl')
            ->with($this->isInstanceOf(DateTime::class), $this->arrayHasKey('method'))
            ->willReturn('https://storage.googleapis.com/signed-put-url');

        $adapter = new GcsAdapter($this->mockClient);
        $result = $adapter->createPresignedRequest('users/uuid/file.jpg', 'image/jpeg');

        $this->assertInstanceOf(RequestInterface::class, $result);
        $this->assertSame('PUT', $result->getMethod());
        $this->assertStringContainsString('signed-put-url', (string)$result->getUri());
        $this->assertSame('image/jpeg', $result->getHeaderLine('Content-Type'));
    }

    public function testPresignedUrlReturnsSignedGetUrl(): void
    {
        $this->mockObject
            ->method('signedUrl')
            ->with($this->isInstanceOf(DateTime::class), $this->arrayHasKey('method'))
            ->willReturn('https://storage.googleapis.com/signed-get-url');

        $adapter = new GcsAdapter($this->mockClient);
        $result = $adapter->presignedUrl('users/uuid/file.jpg', 3600);

        $this->assertSame('https://storage.googleapis.com/signed-get-url', $result);
    }

    public function testDeleteObjectReturnsTrueOnSuccess(): void
    {
        $this->mockObject->expects($this->once())->method('delete');

        $adapter = new GcsAdapter($this->mockClient);
        $result = $adapter->deleteObject('users/uuid/file.jpg');

        $this->assertTrue($result);
    }

    public function testDeleteObjectReturnsFalseOnException(): void
    {
        $this->mockObject
            ->method('delete')
            ->willThrowException(new GoogleException('GCS error'));

        $adapter = new GcsAdapter($this->mockClient);
        $result = $adapter->deleteObject('users/uuid/file.jpg');

        $this->assertFalse($result);
    }

    public function testDeleteObjectReturnsTrueWhenObjectNotFound(): void
    {
        $this->mockObject
            ->method('delete')
            ->willThrowException(new NotFoundException('Not found'));

        $adapter = new GcsAdapter($this->mockClient);
        $result = $adapter->deleteObject('users/uuid/file.jpg');

        $this->assertTrue($result);
    }
}
