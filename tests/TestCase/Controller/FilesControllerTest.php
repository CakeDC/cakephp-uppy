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
namespace CakeDC\Uppy\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Controller\FilesController;

/**
 * CakeDC\Uppy\Controller\FilesController Test Case
 *
 * @uses \CakeDC\Uppy\Controller\FilesController
 */
class FilesControllerTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configureDummyUpload();
    }

    /**
     * @return void
     */
    protected function configureDummyUpload(): void
    {
        Configure::write('Uppy.S3.config.connection', 'dummy');
        Configure::write('Uppy.AcceptedContentTypes', [
            'application/pdf',
            'image/png',
        ]);
        Configure::write('Uppy.MaxFileSize', 1073741824);
    }

    /**
     * @param string $action Controller action.
     * @param array<string, mixed> $payload Request body.
     * @return \Cake\Http\Response
     */
    protected function invokeAction(string $action, array $payload): Response
    {
        $request = (new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
        ]))->withParsedBody($payload);

        $response = new Response();
        $controller = new FilesController($request, $response);
        $controller->setRequest($request);
        $controller->setResponse($response);
        $controller->initialize();

        return $controller->{$action}();
    }

    /**
     * @param \Cake\Http\Response $response
     * @return array<string, mixed>
     */
    protected function decodeResponse(Response $response): array
    {
        $decoded = json_decode((string)$response->getBody(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return void
     */
    public function testSignRejectsMissingFilename(): void
    {
        $response = $this->invokeAction('sign', [
            'contentType' => 'application/pdf',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertTrue($body['error']);
        $this->assertSame(400, $body['code']);
    }

    /**
     * @return void
     */
    public function testSignRejectsInvalidContentType(): void
    {
        $response = $this->invokeAction('sign', [
            'filename' => 'test.exe',
            'contentType' => 'application/x-msdownload',
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertTrue($body['error']);
    }

    /**
     * @return void
     */
    public function testSignRejectsOversizeWhenMaxFileSizeSet(): void
    {
        $response = $this->invokeAction('sign', [
            'filename' => 'large.pdf',
            'contentType' => 'application/pdf',
            'filesize' => 2000000000,
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertTrue($body['error']);
    }

    /**
     * @return void
     */
    public function testSignSuccessWithDummyS3(): void
    {
        $response = $this->invokeAction('sign', [
            'filename' => 'doc.pdf',
            'contentType' => 'application/pdf',
            'prefix' => 'uploads',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertFalse($body['error']);
        $this->assertSame(200, $body['code']);
        $this->assertSame('PUT', $body['method']);
        $this->assertStringContainsString('https://example.com/dummy/', $body['url']);
        $this->assertStringContainsString('uploads/', $body['key']);
    }

    /**
     * @return void
     */
    public function testCreateMultipartUploadSuccess(): void
    {
        $response = $this->invokeAction('createMultipartUpload', [
            'filename' => 'large.pdf',
            'contentType' => 'application/pdf',
            'prefix' => 'alerrt/ResourceFiles',
            'filesize' => 524288000,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertFalse($body['error']);
        $this->assertSame('dummy-upload-id', $body['uploadId']);
        $this->assertStringContainsString('alerrt/ResourceFiles/', $body['key']);
    }

    /**
     * @return void
     */
    public function testCreateMultipartUploadRejectsOversize(): void
    {
        $response = $this->invokeAction('createMultipartUpload', [
            'filename' => 'huge.pdf',
            'contentType' => 'application/pdf',
            'filesize' => 2000000000,
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertTrue($body['error']);
    }

    /**
     * @return void
     */
    public function testSignPartRequiresPartNumber(): void
    {
        $response = $this->invokeAction('signPart', [
            'uploadId' => 'dummy-upload-id',
            'key' => 'alerrt/ResourceFiles/test.pdf',
            'partNumber' => 0,
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertTrue($body['error']);
    }

    /**
     * @return void
     */
    public function testSignPartSuccess(): void
    {
        $response = $this->invokeAction('signPart', [
            'uploadId' => 'dummy-upload-id',
            'key' => 'alerrt/ResourceFiles/test.pdf',
            'partNumber' => 1,
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertFalse($body['error']);
        $this->assertStringContainsString('partNumber=1', $body['url']);
    }

    /**
     * @return void
     */
    public function testCompleteMultipartUploadValidatesParts(): void
    {
        $response = $this->invokeAction('completeMultipartUpload', [
            'uploadId' => 'dummy-upload-id',
            'key' => 'alerrt/ResourceFiles/test.pdf',
            'parts' => [
                ['PartNumber' => 1],
            ],
        ]);

        $this->assertSame(400, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertTrue($body['error']);
    }

    /**
     * @return void
     */
    public function testCompleteMultipartUploadSuccess(): void
    {
        $response = $this->invokeAction('completeMultipartUpload', [
            'uploadId' => 'dummy-upload-id',
            'key' => 'alerrt/ResourceFiles/test.pdf',
            'parts' => [
                ['PartNumber' => 1, 'ETag' => '"abc"'],
            ],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertFalse($body['error']);
        $this->assertStringContainsString('alerrt/ResourceFiles/test.pdf', $body['location']);
        $this->assertSame('alerrt/ResourceFiles/test.pdf', $body['key']);
    }

    /**
     * @return void
     */
    public function testAbortMultipartUploadSuccess(): void
    {
        $response = $this->invokeAction('abortMultipartUpload', [
            'uploadId' => 'dummy-upload-id',
            'key' => 'alerrt/ResourceFiles/test.pdf',
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decodeResponse($response);
        $this->assertFalse($body['error']);
    }
}
