<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Util;

use Aws\Exception\AwsException;
use Aws\Result;
use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Util\S3Trait;
use Exception;
use InvalidArgumentException;

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
        Configure::write('Uppy.AcceptedContentTypes', ['image/jpeg', 'image/png', 'application/pdf']);
    }

    protected function tearDown(): void
    {
        Configure::delete('Uppy.S3');
        Configure::delete('Uppy.AcceptedContentTypes');
        Configure::delete('Uppy.MaxFileSize');
        parent::tearDown();
    }

    // ── setPublicPermissions ──────────────────────────────────────────────────

    public function testSetPublicPermissionsCallsPutObjectAclWithCorrectParams(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['putObjectAcl'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('putObjectAcl')
            ->with([
                'Bucket' => 'test-bucket',
                'Key' => 'path/to/file.jpg',
                'ACL' => 'public-read',
            ])
            ->willReturn(new Result([]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $subject->setPublicPermissions('path/to/file.jpg');
        $this->assertTrue(true); // no exception thrown
    }

    // ── listFilesWithUrls ─────────────────────────────────────────────────────

    public function testListFilesWithUrlsReturnsObjectsWithPresignedUrls(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willReturn(new Result([
            'Contents' => [
                ['Key' => 'folder/file.jpg', 'Size' => 1024],
                ['Key' => 'folder/other.png', 'Size' => 2048],
            ],
        ]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        // dummy connection → presignedUrl() returns 'https://example.com' without real AWS call
        $result = $subject->listFilesWithUrls();
        $this->assertCount(2, $result);
        $this->assertSame('folder/file.jpg', $result[0]['Key']);
        $this->assertArrayHasKey('presigned_url', $result[0]);
        $this->assertSame('https://example.com', $result[0]['presigned_url']);
        $this->assertArrayHasKey('presigned_url', $result[1]);
    }

    public function testListFilesWithUrlsReturnsEmptyArrayWhenNoContents(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willReturn(new Result(['Contents' => null]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->assertSame([], $subject->listFilesWithUrls());
    }

    public function testListFilesWithUrlsPassesPrefixAndMaxKeys(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('listObjectsV2')
            ->with([
                'Bucket' => 'test-bucket',
                'MaxKeys' => 50,
                'Prefix' => 'uploads/',
            ])
            ->willReturn(new Result(['Contents' => null]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $subject->listFilesWithUrls('uploads/', 50);
        $this->assertTrue(true);
    }

    public function testListFilesWithUrlsOmitsPrefixWhenEmpty(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('listObjectsV2')
            ->with([
                'Bucket' => 'test-bucket',
                'MaxKeys' => 1000,
            ])
            ->willReturn(new Result(['Contents' => null]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $subject->listFilesWithUrls();
        $this->assertTrue(true);
    }

    public function testListFilesWithUrlsThrowsOnAwsException(): void
    {
        $mockException = $this->getMockBuilder(AwsException::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willThrowException($mockException);

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error listing files with URLs from S3:');
        $subject->listFilesWithUrls();
    }

    // ── listFiles ─────────────────────────────────────────────────────────────

    public function testListFilesReturnsContents(): void
    {
        $files = [
            ['Key' => 'folder/a.jpg', 'Size' => 512],
            ['Key' => 'folder/b.png', 'Size' => 1024],
        ];

        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willReturn(new Result(['Contents' => $files]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->assertSame($files, $subject->listFiles());
    }

    public function testListFilesReturnsEmptyArrayWhenNoContents(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willReturn(new Result(['Contents' => null]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->assertSame([], $subject->listFiles());
    }

    public function testListFilesPassesPrefixAndMaxKeys(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('listObjectsV2')
            ->with([
                'Bucket' => 'test-bucket',
                'MaxKeys' => 100,
                'Prefix' => 'archive/',
            ])
            ->willReturn(new Result(['Contents' => null]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $subject->listFiles('archive/', 100);
        $this->assertTrue(true);
    }

    public function testListFilesOmitsPrefixWhenEmpty(): void
    {
        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->expects($this->once())
            ->method('listObjectsV2')
            ->with([
                'Bucket' => 'test-bucket',
                'MaxKeys' => 1000,
            ])
            ->willReturn(new Result(['Contents' => null]));

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $subject->listFiles();
        $this->assertTrue(true);
    }

    public function testListFilesThrowsOnAwsException(): void
    {
        $mockException = $this->getMockBuilder(AwsException::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockClient = $this->getMockBuilder(S3Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['listObjectsV2'])
            ->getMock();
        $mockClient->method('listObjectsV2')->willThrowException($mockException);

        $subject = new class ($mockClient) {
            use S3Trait;

            public function __construct(private readonly S3Client $injectedClient)
            {
            }

            protected function getS3Client(): S3Client
            {
                return $this->injectedClient;
            }
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error listing files from S3:');
        $subject->listFiles();
    }

    // ── buildStorageKey ───────────────────────────────────────────────────────

    public function testBuildStorageKeyGeneratesUuidPrefixedKey(): void
    {
        $subject = new class {
            use S3Trait;

            public function testBuildStorageKey(string $filename, ?string $prefix = null): string
            {
                return $this->buildStorageKey($filename, $prefix);
            }
        };

        $result = $subject->testBuildStorageKey('video.mp4');
        $this->assertStringContainsString('-video-mp4', $result);
        // Should have UUID format at the start
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}-/', $result);
    }

    public function testBuildStorageKeyWithPrefixIncludesPrefix(): void
    {
        $subject = new class {
            use S3Trait;

            public function testBuildStorageKey(string $filename, ?string $prefix = null): string
            {
                return $this->buildStorageKey($filename, $prefix);
            }
        };

        $result = $subject->testBuildStorageKey('video.mp4', 'uploads/videos');
        $this->assertStringContainsString('uploads/videos/', $result);
        $this->assertStringContainsString('-video-mp4', $result);
    }

    public function testBuildStorageKeyTrimsSlashesFromPrefix(): void
    {
        $subject = new class {
            use S3Trait;

            public function testBuildStorageKey(string $filename, ?string $prefix = null): string
            {
                return $this->buildStorageKey($filename, $prefix);
            }
        };

        $result = $subject->testBuildStorageKey('file.pdf', '/documents/');
        $this->assertStringStartsWith('documents/', $result);
        $this->assertStringNotContainsString('//', $result);
    }

    // ── assertAcceptedContentType ────────────────────────────────────────────

    public function testAssertAcceptedContentTypeDoesNotThrowForValidType(): void
    {
        $subject = new class {
            use S3Trait;

            public function testAssertAcceptedContentType(string $contentType): void
            {
                $this->assertAcceptedContentType($contentType);
            }
        };

        // Should not throw
        $subject->testAssertAcceptedContentType('image/jpeg');
        $this->assertTrue(true);
    }

    public function testAssertAcceptedContentTypeThrowsForInvalidType(): void
    {
        $subject = new class {
            use S3Trait;

            public function testAssertAcceptedContentType(string $contentType): void
            {
                $this->assertAcceptedContentType($contentType);
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contentType video/mp4 is not valid');
        $subject->testAssertAcceptedContentType('video/mp4');
    }

    // ── assertMaxFileSize ─────────────────────────────────────────────────────

    public function testAssertMaxFileSizeDoesNotThrowWhenNoLimitSet(): void
    {
        Configure::write('Uppy.MaxFileSize', null);

        $subject = new class {
            use S3Trait;

            public function testAssertMaxFileSize(int $filesize): void
            {
                $this->assertMaxFileSize($filesize);
            }
        };

        // Should not throw for any size when no limit
        $subject->testAssertMaxFileSize(999999999);
        $this->assertTrue(true);
    }

    public function testAssertMaxFileSizeDoesNotThrowWhenBelowLimit(): void
    {
        Configure::write('Uppy.MaxFileSize', 10000000); // 10 MB

        $subject = new class {
            use S3Trait;

            public function testAssertMaxFileSize(int $filesize): void
            {
                $this->assertMaxFileSize($filesize);
            }
        };

        // Should not throw for 5 MB
        $subject->testAssertMaxFileSize(5000000);
        $this->assertTrue(true);
    }

    public function testAssertMaxFileSizeThrowsWhenExceedsLimit(): void
    {
        Configure::write('Uppy.MaxFileSize', 10000000); // 10 MB

        $subject = new class {
            use S3Trait;

            public function testAssertMaxFileSize(int $filesize): void
            {
                $this->assertMaxFileSize($filesize);
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File size 20000000 exceeds maximum allowed size 10000000');
        $subject->testAssertMaxFileSize(20000000); // 20 MB
    }
}
