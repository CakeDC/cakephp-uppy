<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Model\Entity;

use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Model\Entity\File;

class FileEntityTest extends TestCase
{
    public function testProtectedFieldsAreNotMassAssignable(): void
    {
        $entity = new File([
            'user_id'   => 'should-be-ignored',
            'model'     => 'should-be-ignored',
            'filename'  => 'should-be-ignored',
            'filesize'  => 99999,
            'hash'      => 'attacker-hash',
            'path'      => 'attacker-path',
            'adapter'   => 'attacker-adapter',
            'created'   => '2000-01-01',
            'modified'  => '2000-01-01',
            'metadata'  => '{"evil":true}',
        ], ['guard' => true]);

        $this->assertNull($entity->user_id);
        $this->assertNull($entity->model);
        $this->assertNull($entity->filename);
        $this->assertNull($entity->filesize);
        $this->assertNull($entity->hash);
        $this->assertNull($entity->path);
        $this->assertNull($entity->adapter);
        $this->assertNull($entity->created);
        $this->assertNull($entity->modified);
        $this->assertNull($entity->metadata);
    }

    public function testAccessibleFieldsAreMassAssignable(): void
    {
        $entity = new File([
            'mime_type'   => 'image/png',
            'extension'   => 'png',
            'foreign_key' => 42,
        ]);

        $this->assertSame('image/png', $entity->mime_type);
        $this->assertSame('png', $entity->extension);
        $this->assertSame(42, $entity->foreign_key);
    }
}
