<?php
declare(strict_types=1);

/**
 * Copyright 2024, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2024, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Storage;

use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\Http\Client\Request;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\RequestInterface;

class R2Adapter implements StorageAdapterInterface
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
        return $this->client ??= new S3Client(Configure::read('Uppy.R2.config'));
    }

    /**
     * @return string
     */
    private function bucket(): string
    {
        return (string)Configure::readOrFail('Uppy.R2.bucket');
    }

    /**
     * @param string $path
     * @param string $contentType
     * @return \Psr\Http\Message\RequestInterface
     */
    public function createPresignedRequest(string $path, string $contentType): RequestInterface
    {
        $client = $this->getClient();

        $command = $client->getCommand('PutObject', [
            'Bucket' => $this->bucket(),
            'Key' => $path,
            'ContentType' => $contentType,
        ]);

        return $client->createPresignedRequest(
            $command,
            Configure::readOrFail('Uppy.R2.constants.lifeTimePutObject'),
        );
    }

    /**
     * @param string $path
     * @param int $ttlSeconds
     * @return string
     */
    public function presignedUrl(string $path, int $ttlSeconds = 3600): string
    {
        $client = $this->getClient();

        $cmd = $client->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key' => $path,
        ]);

        $request = $client->createPresignedRequest($cmd, "+{$ttlSeconds} seconds");

        return (string)$request->getUri();
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deleteObject(string $path): bool
    {
        if (Configure::read('Uppy.R2.connection') === 'dummy') {
            return true;
        }

        $client = $this->getClient();
        if (!$client->doesObjectExist($this->bucket(), $path)) {
            return true;
        }

        $client->deleteObject(['Bucket' => $this->bucket(), 'Key' => $path]);

        return !$client->doesObjectExist($this->bucket(), $path);
    }
}
