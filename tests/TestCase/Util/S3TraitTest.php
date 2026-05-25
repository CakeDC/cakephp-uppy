<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Util;

use Aws\Result;
use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Util\S3Trait;
use Exception;
use Psr\Http\Message\RequestInterface;

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

    public function testPresignedUrlReadsConstantsKey(): void
    {
        // Only constants key set — contants key absent — must not throw
        Configure::write('Uppy.S3.constants', [
            'lifeTimeGetObject' => '+20 minutes',
            'lifeTimePutObject' => '+5 minutes',
        ]);
        Configure::delete('Uppy.S3.contants');

        $subject = new class {
            use S3Trait;

            public function callPresignedUrl(string $path, string $name): string
            {
                return $this->presignedUrl($path, $name);
            }
        };

        // dummy connection returns 'https://example.com'
        $url = $subject->callPresignedUrl('uuid-test.png', 'test.png');
        $this->assertSame('https://example.com', $url);
    }

    public function testCreatePresignedRequestReadsConstantsKey(): void
    {
        Configure::write('Uppy.S3.constants', [
            'lifeTimeGetObject' => '+20 minutes',
            'lifeTimePutObject' => '+5 minutes',
        ]);
        Configure::delete('Uppy.S3.contants');

        $subject = new class {
            use S3Trait;

            public function callCreatePresignedRequest(string $path, string $ct): RequestInterface
            {
                return $this->createPresignedRequest($path, $ct);
            }
        };

        $req = $subject->callCreatePresignedRequest('uuid-test.png', 'image/png');
        $this->assertSame('https://example.com', (string)$req->getUri());
    }

    public function testFolderExistsThrowsWhenContentsEmpty(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willReturn(new Result([
            'Contents' => [],
        ]));

        // Override makeS3Client() so the real folderExists() uses the mock — no AWS calls made
        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function makeS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Folder doesn't exist");
        $subject->folderExists('some/path/');
    }

    public function testFolderExistsReturnsTrueWhenContentsNonEmpty(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willReturn(new Result([
            'Contents' => [['Key' => 'some/path/file.txt']],
        ]));

        // Override makeS3Client() so the real folderExists() uses the mock — no AWS calls made
        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function makeS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->assertTrue($subject->folderExists('some/path/'));
    }
}
