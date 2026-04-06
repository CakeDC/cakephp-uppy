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
namespace CakeDC\Uppy\Util;

use Aws\S3\S3Client;
use Aws\S3\Transfer;
use Cake\Core\Configure;
use Cake\Http\Client\Request;
use Exception;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\RequestInterface;

/**
 * Backward compatibility shim for applications using the old trait directly.
 *
 * @deprecated 2.next-cake5 Use storage adapters instead.
 */
trait S3Trait
{
    protected function getS3Client(): S3Client
    {
        return new S3Client(Configure::readOrFail('Uppy.S3.config'));
    }

    protected function getS3Lifetime(string $operation): string
    {
        $newKey = "Uppy.S3.constants.{$operation}";
        $legacyKey = "Uppy.S3.contants.{$operation}";

        return (string)(
            Configure::read($newKey)
            ?? Configure::readOrFail($legacyKey)
        );
    }

    /**
     * @see /config/cors.xml
     * @param string|null $path string used as path in S3
     * @param string|null $name string filename
     * @return bool result operation
     */
    protected function deleteObject(?string $path, ?string $name): bool
    {
        $path = $path ?? '';
        if (Configure::read('Uppy.S3.config.connection') !== 'dummy') {
            $s3Client = $this->getS3Client();
            $exist = $s3Client->doesObjectExist(Configure::readOrFail('Uppy.S3.bucket'), $path);
            if ($exist) {
                $s3Client->deleteObject([
                    'Bucket' => Configure::readOrFail('Uppy.S3.bucket'),
                    'Key' => $path,
                ]);
                if ($s3Client->doesObjectExist(Configure::readOrFail('Uppy.S3.bucket'), $path)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @see /config/cors.xml
     * @param string|null $path string used as path in S3
     * @param string|null $name string filename
     * @return string
     */
    protected function presignedUrl(?string $path, ?string $name): string
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return 'https://example.com';
        }

        $s3Client = $this->getS3Client();
        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => Configure::readOrFail('Uppy.S3.bucket'),
            'Key' => $path,
        ]);
        $request = $s3Client->createPresignedRequest($cmd, $this->getS3Lifetime('lifeTimeGetObject'));

        return (string)$request->getUri();
    }

    /**
     * @see /config/cors.xml
     * @param string $path string used as path in S3
     * @param string $contentType string contenttype
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function createPresignedRequest(string $path, string $contentType): RequestInterface
    {
        if (Configure::read('Uppy.S3.config.connection') === 'dummy') {
            return (new Request())
                ->withUri(new Uri('https://example.com'));
        }

        $s3Client = $this->getS3Client();
        $command = $s3Client->getCommand('putObject', [
            'Bucket' => Configure::readOrFail('Uppy.S3.bucket'),
            'Key' => $path,
            'ContentType' => $contentType,
            'Body' => '',
        ]);

        return $s3Client->createPresignedRequest($command, $this->getS3Lifetime('lifeTimePutObject'));
    }

    /**
     * @see /config/cors.xml
     * @param string $source string used as filesystem source dir
     * @param string $target string used as path in S3
     * @return void
     */
    public function uploadDir(string $source, string $target): void
    {
        $s3Client = $this->getS3Client();
        $dest = 's3://' . Configure::readOrFail('Uppy.S3.bucket') . DS . $target;
        $manager = new Transfer($s3Client, $source, $dest);
        $manager->transfer();

        $promise = $manager->promise();

        $promise->then(function (): void {
        });

        $promise->otherwise(function ($reason): void {
            throw new Exception('Transfer failed. Please try again.');
        });
    }

    /**
     * @param string $sourceFilePath string used as filesystem source file
     * @param string $destinationS3Path string used as path in S3
     * @return void
     * @throws \Exception
     * @see /config/cors.xml
     */
    public function uploadFile(string $sourceFilePath, string $destinationS3Path): void
    {
        $s3Client = $this->getS3Client();
        $s3Options = [
            'Bucket' => Configure::readOrFail('Uppy.S3.bucket'),
            'Key' => $destinationS3Path,
            'SourceFile' => $sourceFilePath,
        ];
        $result = $s3Client->putObject($s3Options)->toArray();

        if (
            !array_key_exists('@metadata', $result) ||
            !array_key_exists('statusCode', $result['@metadata'])
        ) {
            throw new Exception('Error on response data. Please try again.');
        }
        if ($result['@metadata']['statusCode'] !== 200) {
            throw new Exception('Error coping/moving file. Please try again.');
        }
    }

    /**
     * @see /config/cors.xml
     * @param string $fileS3Path string used as path in S3
     * @return void
     */
    public function deleteFile(string $fileS3Path): void
    {
        $s3Client = $this->getS3Client();
        $s3Options = [
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $fileS3Path,
        ];
        $s3Client->deleteObject($s3Options);
    }

    /**
     * @param string $fileS3Path string used as path in S3
     * @return void
     * @throws \Exception
     * @see /config/cors.xml
     */
    public function deleteDir(string $fileS3Path): void
    {
        $s3Client = $this->getS3Client();
        if ($this->folderExists($fileS3Path)) {
            $s3Client->deleteMatchingObjects(Configure::readOrFail('Uppy.S3.bucket'), $fileS3Path);
        } else {
            throw new Exception('File doesn\'t exist. Please try again.');
        }
    }

    /**
     * @param string $filename filename
     * @return bool
     */
    public function fileExists(string $filename): bool
    {
        $s3Client = $this->getS3Client();

        return $s3Client->doesObjectExist(Configure::readOrFail('Uppy.S3.bucket'), $filename);
    }

    /**
     * @param string $filename filename
     * @return bool
     * @throws \Exception
     */
    public function folderExists(string $filename): bool
    {
        $s3Client = $this->getS3Client();
        $list = $s3Client->listObjectsV2([
            'Bucket' => Configure::readOrFail('Uppy.S3.bucket'),
            'Prefix' => $filename,
        ]);
        if ($list['Contents'] > 0) {
            return true;
        }

        throw new Exception('Folder doesn\'t exist. Please try again.');
    }
}
