<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Util;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Util\S3Trait;
use Psr\Http\Message\RequestInterface;

class S3TraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Configure::write('Uppy.S3', [
            'constants' => [
                'lifeTimeGetObject' => '+20 minutes',
                'lifeTimePutObject' => '+5 minutes',
            ],
            'config' => [
                'version' => 'latest',
                'region' => 'us-east-1',
                'connection' => 'dummy',
                'credentials' => ['key' => 'fake', 'secret' => 'fake'],
            ],
            'bucket' => 'test-bucket',
        ]);
    }

    protected function tearDown(): void
    {
        Configure::delete('Uppy.S3');
        parent::tearDown();
    }

    public function testPresignedUrlReadsConstantsKey(): void
    {
        // Only constants key set — contants key absent — must not throw
        Configure::write('Uppy.S3.constants', [
            'lifeTimeGetObject' => '+20 minutes',
            'lifeTimePutObject' => '+5 minutes',
        ]);
        Configure::delete('Uppy.S3.contants');

        $subject = new class {
            use S3Trait;

            public function callPresignedUrl(string $path, string $name): string
            {
                return $this->presignedUrl($path, $name);
            }
        };

        // dummy connection returns 'https://example.com'
        $url = $subject->callPresignedUrl('uuid-test.png', 'test.png');
        $this->assertSame('https://example.com', $url);
    }

    public function testCreatePresignedRequestReadsConstantsKey(): void
    {
        Configure::write('Uppy.S3.constants', [
            'lifeTimeGetObject' => '+20 minutes',
            'lifeTimePutObject' => '+5 minutes',
        ]);
        Configure::delete('Uppy.S3.contants');

        $subject = new class {
            use S3Trait;

            public function callCreatePresignedRequest(string $path, string $ct): RequestInterface
            {
                return $this->createPresignedRequest($path, $ct);
            }
        };

        $req = $subject->callCreatePresignedRequest('uuid-test.png', 'image/png');
        $this->assertSame('https://example.com', (string)$req->getUri());
    }
}
