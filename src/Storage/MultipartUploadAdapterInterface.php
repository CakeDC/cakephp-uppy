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

use Psr\Http\Message\RequestInterface;

/**
 * Interface for storage adapters that support multipart uploads.
 *
 * Multipart uploads allow large files to be uploaded in parts, which are then
 * assembled by the storage service. This is essential for files over 100MB to
 * avoid PHP memory/timeout limits.
 */
interface MultipartUploadAdapterInterface extends StorageAdapterInterface
{
    /**
     * Initiate a multipart upload.
     *
     * @param string $key Object storage key (e.g. 'users/uuid/uuid.mp4')
     * @param string $contentType MIME type of the file
     * @return array{uploadId: string, key: string} Upload ID and storage key
     */
    public function createMultipartUpload(string $key, string $contentType): array;

    /**
     * Create a presigned request for uploading a single part.
     *
     * @param string $key Object storage key
     * @param string $uploadId Multipart upload ID from createMultipartUpload
     * @param int $partNumber Part number (1-based, max 10000)
     * @return \Psr\Http\Message\RequestInterface PSR-7 request with presigned URL
     */
    public function createPresignedUploadPart(string $key, string $uploadId, int $partNumber): RequestInterface;

    /**
     * Complete a multipart upload.
     *
     * Assembles all uploaded parts into a single object.
     *
     * @param string $key Object storage key
     * @param string $uploadId Multipart upload ID
     * @param array<int, array{PartNumber: int, ETag: string}> $parts Array of parts with PartNumber and ETag
     * @return array{location: string} Object location URL
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): array;

    /**
     * Abort a multipart upload.
     *
     * Cancels the upload and removes all uploaded parts.
     *
     * @param string $key Object storage key
     * @param string $uploadId Multipart upload ID
     * @return void
     */
    public function abortMultipartUpload(string $key, string $uploadId): void;
}
