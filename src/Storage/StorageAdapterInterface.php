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

use Psr\Http\Message\RequestInterface;

interface StorageAdapterInterface
{
    /**
     * Create a presigned PUT request for a direct browser upload.
     *
     * @param string $path Object storage path (e.g. 'users/uuid/uuid.jpg')
     * @param string $contentType MIME type of the file
     * @return \Psr\Http\Message\RequestInterface PSR-7 request with method and signed URI
     */
    public function createPresignedRequest(string $path, string $contentType): RequestInterface;

    /**
     * Generate a presigned GET URL for reading a stored object.
     *
     * @param string $path Object storage path
     * @param int $ttlSeconds URL lifetime in seconds (default 3600)
     * @return string Signed URL
     */
    public function presignedUrl(string $path, int $ttlSeconds = 3600): string;

    /**
     * Delete an object from storage.
     *
     * @param string $path Object storage path
     * @return bool True on success or when the object did not exist (idempotent), false only on deletion failure
     */
    public function deleteObject(string $path): bool;
}
