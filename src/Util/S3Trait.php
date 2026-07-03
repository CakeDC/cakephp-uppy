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
namespace CakeDC\Uppy\Util;

use Aws\S3\S3Client;
use Cake\Core\Configure;
use Cake\Utility\Text;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

trait S3Trait
{
    /**
     * @var \Aws\S3\S3Client
     */
    private S3Client $_s3Client;

    /**
     * @return \Aws\S3\S3Client
     */
    private function _getS3Client(): S3Client
    {
        if (isset($this->_s3Client)) {
            return $this->_s3Client;
        }

        $config = Configure::read('Uppy.S3.config');
        $endpoint = $config['endpoint'] ?? null;
        $bucket = Configure::read('Uppy.S3.bucket');

        if (
            !array_key_exists('use_path_style_endpoint', $config)
            && $endpoint
            && $bucket
            && str_starts_with(basename(rtrim((string)$endpoint, '/')), (string)$bucket)
        ) {
            $config['use_path_style_endpoint'] = true;
        }

        return $this->_s3Client = new S3Client($config);
    }

    /**
     * delete Object directly in S3
     *
     * @see /config/cors.xml
     * @param string $path string used as path in S3
     * @param string $name string filename
     * @return bool result operation
     */
    protected function deleteObject(string $path, string $name): bool
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return true;
        } else {
            $s3Client = $this->_getS3Client();
            $exist = $s3Client->doesObjectExist(Configure::read('Uppy.S3.bucket'), $path);
            if ($exist) {
                $result = $s3Client->deleteObject([
                    'Bucket' => Configure::read('Uppy.S3.bucket'),
                    'Key' => $path,
                ]);
                if ($s3Client->doesObjectExist(Configure::read('Uppy.S3.bucket'), $path)) {
                    return false;
                }
            }

            return true;
        }
    }

    /**
     * Generate a signed GET uRI to acces files in S3, note CORS must be configured for the domain
     *
     * @see /config/cors.xml
     * @param string $path string used as path in S3
     * @param string $name string filename
     * @return string
     */
    protected function presignedUrl(string $path, string $name): string
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return 'https://example.com';
        } else {
            $s3Client = $this->_getS3Client();
            $cmd = $s3Client->getCommand('GetObject', [
                'Bucket' => Configure::read('Uppy.S3.bucket'),
                'Key' => $path,
            ]);
            $request = $s3Client->createPresignedRequest($cmd, Configure::read('Uppy.S3.constants.lifeTimeGetObject'));

            return (string)$request->getUri();
        }
    }

    /**
     * Build the S3 object key for a new upload.
     *
     * @param string $originalFilename Original file name from the client.
     * @param string|null $prefix Optional path prefix (e.g. alerrt/ResourceFiles).
     * @return string
     */
    protected function buildStorageKey(string $originalFilename, ?string $prefix = null): string
    {
        $key = Text::uuid() . '-' . Text::slug($originalFilename);
        if (!empty($prefix)) {
            $key = trim($prefix, '/') . '/' . $key;
        }

        return $key;
    }

    /**
     * Validate content type against configured accepted types.
     *
     * @param string $contentType MIME type.
     * @return void
     */
    protected function assertAcceptedContentType(string $contentType): void
    {
        $accepted = Configure::read('Uppy.AcceptedContentTypes') ?? [];
        if (!in_array($contentType, $accepted, true)) {
            throw new \InvalidArgumentException(__('contentType {0} is not valid', $contentType));
        }
    }

    /**
     * Validate declared file size against Uppy.MaxFileSize when configured.
     *
     * @param int|null $filesize File size in bytes.
     * @return void
     */
    protected function assertMaxFileSize(?int $filesize): void
    {
        $max = Configure::read('Uppy.MaxFileSize');
        if ($max === null || $filesize === null) {
            return;
        }
        if ($filesize > (int)$max) {
            throw new \InvalidArgumentException(__('File exceeds maximum size of {0} bytes', $max));
        }
    }

    /**
     * Start a multipart upload in S3.
     *
     * @param string $key Object key.
     * @param string $contentType MIME type.
     * @return array{uploadId: string, key: string}
     */
    protected function createMultipartUpload(string $key, string $contentType): array
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return [
                'uploadId' => 'dummy-upload-id',
                'key' => $key,
            ];
        }

        $s3Client = $this->_getS3Client();
        $result = $s3Client->createMultipartUpload([
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        return [
            'uploadId' => $result['UploadId'],
            'key' => $key,
        ];
    }

    /**
     * Presign a single multipart upload part.
     *
     * @param string $key Object key.
     * @param string $uploadId Multipart upload id.
     * @param int $partNumber Part number (1-based).
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function createPresignedUploadPart(string $key, string $uploadId, int $partNumber): RequestInterface
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return new Request(
                'PUT',
                'https://example.com/dummy/' . rawurlencode($key) . '?partNumber=' . $partNumber
            );
        }

        $s3Client = $this->_getS3Client();
        $command = $s3Client->getCommand('UploadPart', [
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);
        $lifetime = Configure::read('Uppy.S3.constants.lifeTimeUploadPart')
            ?? Configure::read('Uppy.S3.constants.lifeTimePutObject');

        return $s3Client->createPresignedRequest($command, $lifetime);
    }

    /**
     * Complete a multipart upload in S3.
     *
     * @param string $key Object key.
     * @param string $uploadId Multipart upload id.
     * @param array<int, array<string, mixed>> $parts S3 parts with PartNumber and ETag.
     * @return array{location: string|null}
     */
    protected function completeMultipartUpload(string $key, string $uploadId, array $parts): array
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return [
                'location' => 'https://example.com/' . $key,
            ];
        }

        $s3Client = $this->_getS3Client();
        $result = $s3Client->completeMultipartUpload([
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]);

        return [
            'location' => $result['Location'] ?? null,
        ];
    }

    /**
     * Abort a multipart upload in S3.
     *
     * @param string $key Object key.
     * @param string $uploadId Multipart upload id.
     * @return void
     */
    protected function abortMultipartUpload(string $key, string $uploadId): void
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return;
        }

        $s3Client = $this->_getS3Client();
        $s3Client->abortMultipartUpload([
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }

    /**
     * Generate a presigned PUT request to send files to S3, note CORS must be configured for the domain
     *
     * @see /config/cors.xml
     * @param string $path string used as path in S3
     * @param string $contentType string contenttype
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function createPresignedRequest(string $path, string $contentType): RequestInterface
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return new Request('PUT', 'https://example.com/dummy/' . rawurlencode($path));
        }

        $s3Client = $this->_getS3Client();
        $command = $s3Client->getCommand('putObject', [
                'Bucket' => Configure::read('Uppy.S3.bucket'),
                'Key' => $path,
                'ContentType' => $contentType,
                'Body' => '',
            ]);

        return $s3Client->createPresignedRequest($command, Configure::read('Uppy.S3.constants.lifeTimePutObject'));
    }

    /**
     * Upload source dir to target path using transfer options
     *
     * @see /config/cors.xml
     * @param string $source string used as filesystem source dir
     * @param string $target string used as path in S3
     * @return void
     */
    public function uploadDir(string $source, string $target): void
    {
        $s3Client = $this->_getS3Client();
        $dest = 's3://' . Configure::read('Uppy.S3.bucket') . DS . $target;
        $manager = new \Aws\S3\Transfer($s3Client, $source, $dest);
        $manager->transfer();

        $promise = $manager->promise();

        $promise->then(function () {
            //Do nothing
        });

        $promise->otherwise(function ($reason) {
            throw new \Exception('Transfer failed. Please try again.');
        });
    }

    /**
     * Upload file using S3 putObject method
     *
     * @see /config/cors.xml
     * @param string $sourceFilePath string used as filesystem source file
     * @param string $destinationS3Path string used as path in S3
     * @return void
     */
    public function uploadFile(string $sourceFilePath, string $destinationS3Path): void
    {
        $s3Client = $this->_getS3Client();
        $s3Options = [
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $destinationS3Path,
            'SourceFile' => $sourceFilePath,
        ];
        $result = $s3Client->putObject($s3Options);
        $metadata = $result['@metadata'] ?? null;
        if (!is_array($metadata) || !isset($metadata['statusCode'])) {
            throw new \Exception('Error on response data. Please try again.');
        }
        if ($metadata['statusCode'] !== 200) {
            throw new \Exception('Error coping/moving file. Please try again.');
        }
    }

    /**
     * Delete file using S3 deleteObject method
     *
     * @see /config/cors.xml
     * @param string $fileS3Path string used as path in S3
     * @return void
     */
    public function deleteFile(string $fileS3Path): void
    {
        $s3Client = $this->_getS3Client();
        $s3Options = [
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $fileS3Path,
        ];
        $s3Client->deleteObject($s3Options);
    }

    /**
     * Delete dir on S3 usign matching rule
     *
     * @see /config/cors.xml
     * @param string $fileS3Path string used as path in S3
     * @return void
     */
    public function deleteDir(string $fileS3Path): void
    {
        $s3Client = $this->_getS3Client();
        if ($this->folderExists($fileS3Path)) {
            $s3Client->deleteMatchingObjects(Configure::read('Uppy.S3.bucket'), $fileS3Path);
        } else {
            throw new \Exception('File doesn\'t exist. Please try again.');
        }
    }

    /**
     * Check if file exists in S3 bucket
     *
     * @param string $filename filename
     * @return bool
     */
    public function fileExists(string $filename): bool
    {
        $s3Client = $this->_getS3Client();

        return $s3Client->doesObjectExist(Configure::read('Uppy.S3.bucket'), $filename);
    }

    /**
     * Check if folder exists in S3 bucket
     *
     * @param string $filename filename
     * @return bool
     */
    public function folderExists(string $filename): bool
    {
        $s3Client = $this->_getS3Client();
        $list = $s3Client->listObjectsV2([
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Prefix' => $filename,
        ]);
        if ($list['Contents'] > 0) {
            return true;
        } else {
            throw new \Exception('Folder doesn\'t exist. Please try again.');
        }
    }
}
