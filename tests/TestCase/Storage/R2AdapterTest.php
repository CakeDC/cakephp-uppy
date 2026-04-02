<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Storage;

use Aws\S3\S3Client;
use CakeDC\Uppy\Storage\R2Adapter;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Psr\Http\Message\RequestInterface;

class R2AdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Uppy.R2', [
            'accountId' => 'test-account-id',
            'credentials' => ['key' => 'fake', 'secret' => 'fake'],
            'bucket' => 'test-bucket',
            'publicDomain' => 'files.example.com',
            'lifeTimePutObject' => '+5 minutes',
            'lifeTimeGetObject' => '+20 minutes',
            'connection' => 'dummy',
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
}
