<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestSuite;

use Aws\S3\S3Client;

/**
 * Test stub exposing the AWS operations used by S3Trait tests as real methods.
 *
 * The AWS SDK resolves operations through __call(), so PHPUnit's addMethods()
 * was needed to mock them; addMethods() is deprecated in PHPUnit 11. Declaring
 * the operations here lets tests use onlyMethods() instead.
 */
class AwsStubS3Client extends S3Client
{
    public function __construct()
    {
    }

    public function listObjectsV2(array $args = []): mixed
    {
        return null;
    }

    public function putObject(array $args = []): mixed
    {
        return null;
    }

    public function putObjectAcl(array $args = []): mixed
    {
        return null;
    }
}
