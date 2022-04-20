<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Util;

use Cake\Core\Configure;
use Psr\Http\Message\RequestInterface;

trait S3Trait
{
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
        $s3Client = new \Aws\S3\S3Client(Configure::read('Uppy.S3.config'));

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

    /**
     * Generate a signed GET uRI to acces files in S3, note CORS must be configured for the domain
     *
     * @see /config/cors.xml
     * @param string $path string used as path in S3
     * @param string $name string filename
     * @return \Psr\Http\Message\RequestInterface
     */
    protected function presignedUrl(string $path, string $name): string
    {
        $s3Client = new \Aws\S3\S3Client(Configure::read('Uppy.S3.config'));

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $path,
        ]);

        $request = $s3Client->createPresignedRequest($cmd, Configure::read('Uppy.S3.contants.lifeTimeGetObject'));

        return (string)$request->getUri();
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
        $s3Client = new \Aws\S3\S3Client(Configure::read('Uppy.S3.config'));
        $command = $s3Client->getCommand('putObject', [
            'Bucket' => Configure::read('Uppy.S3.bucket'),
            'Key' => $path,
            'ContentType' => $contentType,
            'Body' => '',
        ]);

        return $s3Client->createPresignedRequest($command, Configure::read('Uppy.S3.contants.lifeTimePutObject'));
    }
}
