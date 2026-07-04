<?php
declare(strict_types=1);

/**
 * Copyright 2024 - 2026, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2024 - 2026, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Storage;

use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\Http\Client\Request;
use Psr\Http\Message\RequestInterface;

class S3Adapter implements MultipartUploadAdapterInterface
{
    /**
     * @param \Aws\S3\S3Client|null $client
     */
    public function __construct(private ?S3Client $client = null)
    {
    }

    /**
     * @return \Aws\S3\S3Client
     */
    private function getClient(): S3Client
    {
        return $this->client ??= new S3Client(Configure::readOrFail('Uppy.S3.config'));
    }

    /**
     * @return string
     */
    private function bucket(): string
    {
        return (string)Configure::readOrFail('Uppy.S3.bucket');
    }

    /**
     * @param string $path
     * @param string $contentType
     * @return \Psr\Http\Message\RequestInterface
     */
    public function createPresignedRequest(string $path, string $contentType): RequestInterface
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return new Request('https://example.com/dummy/' . $path, 'PUT');
        }

        $command = $this->getClient()->getCommand('PutObject', [
            'Bucket' => $this->bucket(),
            'Key' => $path,
            'ContentType' => $contentType,
            'Body' => '',
        ]);

        return $this->getClient()->createPresignedRequest(
            $command,
            Configure::readOrFail('Uppy.S3.constants.lifeTimePutObject'),
        );
    }

    /**
     * @param string $path
     * @param int|null $ttlSeconds
     * @return string
     */
    public function presignedUrl(string $path, ?int $ttlSeconds = null): string
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return 'https://example.com';
        }

        $ttl = $ttlSeconds !== null
            ? "+{$ttlSeconds} seconds"
            : Configure::read('Uppy.S3.constants.lifeTimeGetObject', '+1 hour');

        $cmd = $this->getClient()->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key' => $path,
        ]);
        $request = $this->getClient()->createPresignedRequest($cmd, $ttl);

        return (string)$request->getUri();
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deleteObject(string $path): bool
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return true;
        }

        $client = $this->getClient();
        if (!$client->doesObjectExist($this->bucket(), $path)) {
            return true;
        }

        $client->deleteObject(['Bucket' => $this->bucket(), 'Key' => $path]);

        return !$client->doesObjectExist($this->bucket(), $path);
    }

    /**
     * @param string $key
     * @param string $contentType
     * @return array{uploadId: string, key: string}
     */
    public function createMultipartUpload(string $key, string $contentType): array
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return [
                'uploadId' => 'dummy-upload-id',
                'key' => $key,
            ];
        }

        $result = $this->getClient()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        return [
            'uploadId' => (string)$result['UploadId'],
            'key' => $key,
        ];
    }

    /**
     * @param string $key
     * @param string $uploadId
     * @param int $partNumber
     * @return \Psr\Http\Message\RequestInterface
     */
    public function createPresignedUploadPart(string $key, string $uploadId, int $partNumber): RequestInterface
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return new Request('https://example.com/part', 'PUT');
        }

        $command = $this->getClient()->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        return $this->getClient()->createPresignedRequest(
            $command,
            Configure::read('Uppy.S3.constants.lifeTimeUploadPart', '+20 minutes'),
        );
    }

    /**
     * @param string $key
     * @param string $uploadId
     * @param array<int, array{PartNumber: int, ETag: string}> $parts
     * @return array{location: string}
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): array
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return [
                'location' => 'https://example.com/' . $key,
            ];
        }

        $result = $this->getClient()->completeMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => [
                'Parts' => $parts,
            ],
        ]);

        return [
            'location' => (string)$result['Location'],
        ];
    }

    /**
     * @param string $key
     * @param string $uploadId
     * @return void
     */
    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return;
        }

        $this->getClient()->abortMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }
}
