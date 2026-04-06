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

class S3Adapter implements StorageAdapterInterface
{
    public function __construct(private ?S3Client $client = null)
    {
    }

    private function getClient(): S3Client
    {
        return $this->client ??= new S3Client(Configure::readOrFail('Uppy.S3.config'));
    }

    private function bucket(): string
    {
        return (string)Configure::readOrFail('Uppy.S3.bucket');
    }

    private function getLegacyAwareLifetime(string $operation): string
    {
        $newKey = "Uppy.S3.constants.{$operation}";
        $legacyKey = "Uppy.S3.contants.{$operation}";

        return (string)(
            Configure::read($newKey)
            ?? Configure::readOrFail($legacyKey)
        );
    }

    public function createPresignedRequest(string $path, string $contentType): RequestInterface
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return (new Request())->withUri(new Uri('https://example.com'));
        }

        $command = $this->getClient()->getCommand('putObject', [
            'Bucket' => $this->bucket(),
            'Key' => $path,
            'ContentType' => $contentType,
            'Body' => '',
        ]);

        return $this->getClient()->createPresignedRequest(
            $command,
            $this->getLegacyAwareLifetime('lifeTimePutObject'),
        );
    }

    public function presignedUrl(string $path, int $ttlSeconds = 3600): string
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return 'https://example.com';
        }

        $cmd = $this->getClient()->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key' => $path,
        ]);
        $expires = $ttlSeconds === 3600
            ? $this->getLegacyAwareLifetime('lifeTimeGetObject')
            : "+{$ttlSeconds} seconds";
        $request = $this->getClient()->createPresignedRequest($cmd, $expires);

        return (string)$request->getUri();
    }

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
}
