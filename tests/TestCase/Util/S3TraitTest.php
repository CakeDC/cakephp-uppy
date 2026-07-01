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
namespace CakeDC\Uppy\Test\TestCase\Util;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Util\S3Trait;

class S3TraitTest extends TestCase
{
    /**
     * @var object
     */
    private $subject;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class {
            use S3Trait {
                buildStorageKey as public;
                assertAcceptedContentType as public;
                assertMaxFileSize as public;
                createMultipartUpload as public;
                createPresignedUploadPart as public;
                completeMultipartUpload as public;
                abortMultipartUpload as public;
                createPresignedRequest as public;
            }
        };

        Configure::write('Uppy.S3.config.connection', 'dummy');
        Configure::write('Uppy.AcceptedContentTypes', [
            'application/pdf',
            'image/png',
        ]);
        Configure::write('Uppy.MaxFileSize', 1073741824);
    }

    /**
     * @return void
     */
    public function testBuildStorageKeyWithoutPrefix(): void
    {
        $key = $this->subject->buildStorageKey('My Document.pdf', null);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]+-My-Document-pdf$/', $key);
    }

    /**
     * @return void
     */
    public function testBuildStorageKeyWithPrefix(): void
    {
        $key = $this->subject->buildStorageKey('video.mp4', 'alerrt/ResourceFiles');
        $this->assertStringStartsWith('alerrt/ResourceFiles/', $key);
        $this->assertStringEndsWith('-video-mp4', $key);
    }

    /**
     * @return void
     */
    public function testAssertAcceptedContentTypeRejectsUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subject->assertAcceptedContentType('application/x-msdownload');
    }

    /**
     * @return void
     */
    public function testAssertMaxFileSizeRejectsOversize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->subject->assertMaxFileSize(2000000000);
    }

    /**
     * @return void
     */
    public function testCreateMultipartUploadDummyMode(): void
    {
        $result = $this->subject->createMultipartUpload('alerrt/test.pdf', 'application/pdf');
        $this->assertSame('dummy-upload-id', $result['uploadId']);
        $this->assertSame('alerrt/test.pdf', $result['key']);
    }

    /**
     * @return void
     */
    public function testCreatePresignedUploadPartDummyMode(): void
    {
        $request = $this->subject->createPresignedUploadPart('alerrt/test.pdf', 'upload-1', 2);
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('partNumber=2', (string)$request->getUri());
    }

    /**
     * @return void
     */
    public function testCompleteMultipartUploadDummyMode(): void
    {
        $result = $this->subject->completeMultipartUpload('alerrt/test.pdf', 'upload-1', [
            ['PartNumber' => 1, 'ETag' => '"abc"'],
        ]);
        $this->assertSame('https://example.com/alerrt/test.pdf', $result['location']);
    }

    /**
     * @return void
     */
    public function testCreatePresignedRequestDummyMode(): void
    {
        $request = $this->subject->createPresignedRequest('alerrt/test.pdf', 'application/pdf');
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('https://example.com/dummy/', (string)$request->getUri());
    }

    /**
     * @return void
     */
    public function testGetS3ClientRespectsExplicitPathStyleEndpoint(): void
    {
        Configure::write('Uppy.S3.config', [
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'minio',
                'secret' => 'minio123',
            ],
        ]);
        Configure::write('Uppy.S3.bucket', 'alerrt-local');

        $subject = new class {
            use S3Trait {
                _getS3Client as public getS3Client;
            }
        };

        $client = $subject->getS3Client();
        $this->assertInstanceOf(\Aws\S3\S3Client::class, $client);
    }
}
