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

use Cake\Core\Configure;
use Cake\Http\Client\Request;
use DateTime;
use Google\Cloud\Core\Exception\GoogleException;
use Google\Cloud\Core\Exception\NotFoundException;
use Google\Cloud\Storage\StorageClient;
use Psr\Http\Message\RequestInterface;

class GcsAdapter implements StorageAdapterInterface
{
    /**
     * @param \Google\Cloud\Storage\StorageClient|null $client
     */
    public function __construct(private ?StorageClient $client = null)
    {
    }

    /**
     * @return \Google\Cloud\Storage\StorageClient
     */
    private function getClient(): StorageClient
    {
        return $this->client ??= new StorageClient([
            'projectId' => Configure::readOrFail('Uppy.GCS.projectId'),
            'keyFilePath' => Configure::readOrFail('Uppy.GCS.keyFilePath'),
        ]);
    }

    /**
     * @return string
     */
    private function bucket(): string
    {
        return (string)Configure::readOrFail('Uppy.GCS.bucket');
    }

    /**
     * @param string $path
     * @param string $contentType
     * @return \Psr\Http\Message\RequestInterface
     * @throws \Exception
     */
    public function createPresignedRequest(string $path, string $contentType): RequestInterface
    {
        $expires = new DateTime(Configure::readOrFail('Uppy.GCS.lifeTimePutObject'));
        $signedUrl = $this->getClient()
            ->bucket($this->bucket())
            ->object($path)
            ->signedUrl($expires, [
                'method' => 'PUT',
                'contentType' => $contentType,
                'version' => 'v4',
            ]);

        return (new Request($signedUrl, 'PUT'))->withHeader('Content-Type', $contentType);
    }

    /**
     * @param string $path
     * @param int|null $ttlSeconds
     * @return string
     */
    public function presignedUrl(string $path, ?int $ttlSeconds = null): string
    {
        $ttlString = $ttlSeconds !== null
            ? "+{$ttlSeconds} seconds"
            : Configure::read('Uppy.GCS.lifeTimeGetObject', '+1 hour');
        $expires = new DateTime($ttlString);

        return (string)$this->getClient()
            ->bucket($this->bucket())
            ->object($path)
            ->signedUrl($expires, [
                'method' => 'GET',
                'version' => 'v4',
            ]);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function deleteObject(string $path): bool
    {
        try {
            $this->getClient()->bucket($this->bucket())->object($path)->delete();

            return true;
        } catch (NotFoundException) {
            return true; // already gone — idempotent
        } catch (GoogleException) {
            return false;
        }
    }
}
