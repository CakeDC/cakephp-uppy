<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Model\Table;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\ORM\Association\BelongsTo;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Model\Entity\File;
use CakeDC\Uppy\Model\Table\FilesTable;
use CakeDC\Uppy\Storage\StorageAdapterInterface;

class FilesTableTest extends TestCase
{
    protected FilesTable $filesTable;

    /**
     * Return the test connection narrowed to the concrete Connection class,
     * which exposes execute() (not part of ConnectionInterface).
     */
    protected function connection(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('test');

        return $connection;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('Uppy', [
            'Props' => [
                'usersAliasModel' => 'Users',
                'usersModel' => 'Users',
                'deleteFileS3' => false,
                'tableFiles' => 'uppy_files',
            ],
            'AcceptedContentTypes' => ['image/png', 'application/pdf'],
            'AcceptedExtensions' => ['png', 'pdf'],
            'S3' => [
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
            ],
        ]);

        $conn = $this->connection();
        $conn->execute('DROP TABLE IF EXISTS uppy_files');
        $conn->execute("
            CREATE TABLE uppy_files (
                id          CHAR(36)     NOT NULL,
                user_id     CHAR(36)         NULL,
                model       VARCHAR(128)     NULL,
                filename    VARCHAR(255)     NULL,
                filesize    INTEGER          NULL,
                mime_type   VARCHAR(128)     NULL,
                extension   VARCHAR(32)      NULL,
                hash        VARCHAR(64)      NULL,
                path        VARCHAR(255)     NULL,
                adapter     VARCHAR(32)      NULL,
                created     DATETIME         NULL,
                modified    DATETIME         NULL,
                metadata    TEXT             NULL,
                foreign_key INTEGER      NOT NULL,
                PRIMARY KEY (id)
            )
        ");
        $conn->execute('DROP TABLE IF EXISTS users');
        $conn->execute("
            CREATE TABLE users (
                id      CHAR(36) NOT NULL,
                user_id CHAR(36)     NULL,
                PRIMARY KEY (id)
            )
        ");
        $conn->execute("INSERT INTO users (id, user_id) VALUES ('user-uuid-1', 'user-uuid-1')");

        TableRegistry::getTableLocator()->clear();

        /** @var \CakeDC\Uppy\Model\Table\FilesTable $filesTable */
        $filesTable = TableRegistry::getTableLocator()->get('CakeDC/Uppy.Files', [
            'connection' => $conn,
        ]);
        $this->filesTable = $filesTable;
    }

    protected function tearDown(): void
    {
        $conn = $this->connection();
        $conn->execute('DROP TABLE IF EXISTS uppy_files');
        $conn->execute('DROP TABLE IF EXISTS users');
        TableRegistry::getTableLocator()->clear();
        parent::tearDown();
    }

    // ── initialize ────────────────────────────────────────────────────────────

    public function testInitializeWithTemplateConfigRegistersUsersAssociation(): void
    {
        Configure::delete('Uppy');
        Configure::write(require Plugin::path('CakeDC/Uppy') . 'config' . DIRECTORY_SEPARATOR . 'uppy.php');

        TableRegistry::getTableLocator()->clear();

        $table = TableRegistry::getTableLocator()->get('CakeDC/Uppy.Files', [
            'connection' => $this->connection(),
        ]);

        $this->assertSame('uppy_files', $table->getTable());
        $this->assertTrue($table->hasAssociation('Users'));
        $association = $table->getAssociation('Users');
        $this->assertInstanceOf(BelongsTo::class, $association);
        $this->assertSame('user_id', $association->getForeignKey());
        $this->assertSame('Users', $association->getTarget()->getAlias());
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function testValidationFilenameRejectsPathTraversal(): void
    {
        $entity = $this->filesTable->newEntity(['filename' => '../../etc/passwd', 'foreign_key' => 1]);
        $this->assertArrayHasKey('filename', $entity->getErrors());
    }

    public function testValidationFilenameAcceptsSimpleName(): void
    {
        $entity = $this->filesTable->newEntity(['filename' => 'photo.png', 'foreign_key' => 1]);
        $this->assertArrayNotHasKey('filename', $entity->getErrors());
    }

    public function testValidationRejectsMimeTypeNotInList(): void
    {
        $entity = $this->filesTable->newEntity(['mime_type' => 'application/zip', 'foreign_key' => 1]);
        $this->assertArrayHasKey('mime_type', $entity->getErrors());
    }

    public function testValidationAcceptsMimeTypeInList(): void
    {
        $entity = $this->filesTable->newEntity(['mime_type' => 'image/png', 'foreign_key' => 1]);
        $this->assertArrayNotHasKey('mime_type', $entity->getErrors());
    }

    public function testValidationRejectsExtensionNotInList(): void
    {
        $entity = $this->filesTable->newEntity(['extension' => 'zip', 'foreign_key' => 1]);
        $this->assertArrayHasKey('extension', $entity->getErrors());
    }

    public function testValidationAcceptsExtensionInList(): void
    {
        $entity = $this->filesTable->newEntity(['extension' => 'png', 'foreign_key' => 1]);
        $this->assertArrayNotHasKey('extension', $entity->getErrors());
    }

    public function testValidationRequiresForeignKeyOnCreate(): void
    {
        $entity = $this->filesTable->newEntity(['filename' => 'test.png']);
        $this->assertArrayHasKey('foreign_key', $entity->getErrors());
    }

    // ── afterDelete ───────────────────────────────────────────────────────────

    public function testAfterDeleteCallsDeleteObjectWhenEnabled(): void
    {
        Configure::write('Uppy.Props.deleteFileStorage', true);

        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->expects($this->once())
            ->method('deleteObject')
            ->with('uuid-file.png')
            ->willReturn(true);

        $table = $this->getMockBuilder(FilesTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStorageAdapter'])
            ->getMock();
        $table->method('getStorageAdapter')->willReturn($adapter);

        $entity = new File(['path' => 'uuid-file.png', 'filename' => 'photo.png']);
        $table->afterDelete(new Event('Model.afterDelete', $this->filesTable), $entity, new ArrayObject());
    }

    public function testAfterDeleteSkipsDeleteObjectWhenDisabled(): void
    {
        Configure::write('Uppy.Props.deleteFileStorage', false);
        Configure::write('Uppy.Props.deleteFileS3', false);

        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->expects($this->never())->method('deleteObject');

        $table = $this->getMockBuilder(FilesTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStorageAdapter'])
            ->getMock();
        $table->method('getStorageAdapter')->willReturn($adapter);

        $entity = new File(['path' => 'uuid-file.png', 'filename' => 'photo.png']);
        $table->afterDelete(new Event('Model.afterDelete', $this->filesTable), $entity, new ArrayObject());
    }

    public function testAfterDeleteHonorsLegacyDeleteFileS3Key(): void
    {
        Configure::delete('Uppy.Props.deleteFileStorage');
        Configure::write('Uppy.Props.deleteFileS3', true);

        $adapter = $this->createMock(StorageAdapterInterface::class);
        $adapter->expects($this->once())
            ->method('deleteObject')
            ->with('uuid-file.png')
            ->willReturn(true);

        $table = $this->getMockBuilder(FilesTable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStorageAdapter'])
            ->getMock();
        $table->method('getStorageAdapter')->willReturn($adapter);

        $entity = new File(['path' => 'uuid-file.png', 'filename' => 'photo.png']);
        $table->afterDelete(new Event('Model.afterDelete', $this->filesTable), $entity, new ArrayObject());
    }

    // ── findDatatable ─────────────────────────────────────────────────────────

    public function testFindDatatableAppliesUserIdFilter(): void
    {
        $conn = $this->connection();
        $conn->execute("INSERT INTO uppy_files (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES ('file-1','user-uuid-1','Users','photo.png',1024,'image/png','png','abc','path1.png','s3','2024-01-15 10:00:00','2024-01-15 10:00:00',NULL,1)");
        $conn->execute("INSERT INTO uppy_files (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES ('file-2','other-uuid','Users','other.png',512,'image/png','png','def','path2.png','s3','2024-01-15 10:00:00','2024-01-15 10:00:00',NULL,2)");

        $results = $this->filesTable->find('datatable', patient_id: 'user-uuid-1')->toArray();

        $this->assertCount(1, $results);
        $this->assertSame('photo.png', $results[0]['filename']);
    }

    public function testFindDatatableAppliesFilenameSearch(): void
    {
        $conn = $this->connection();
        $conn->execute("INSERT INTO uppy_files (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES ('file-1','user-uuid-1','Users','photo.png',1024,'image/png','png','abc','path1.png','s3','2024-01-15 10:00:00','2024-01-15 10:00:00',NULL,1)");
        $conn->execute("INSERT INTO uppy_files (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES ('file-2','user-uuid-1','Users','document.pdf',2048,'application/pdf','pdf','def','path2.pdf','s3','2024-01-15 10:00:00','2024-01-15 10:00:00',NULL,1)");

        $results = $this->filesTable->find('datatable', patient_id: 'user-uuid-1', q: ['value' => 'photo'])->toArray();

        $this->assertCount(1, $results);
        $this->assertSame('photo.png', $results[0]['filename']);
    }

    public function testFindDatatableFormatsResults(): void
    {
        $conn = $this->connection();
        $conn->execute("INSERT INTO uppy_files (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES ('file-1','user-uuid-1','Users','report.pdf',1048576,'application/pdf','pdf','abc','uuid-report.pdf','s3','2024-03-20 12:00:00','2024-03-20 12:00:00',NULL,1)");

        $results = $this->filesTable->find('datatable', patient_id: 'user-uuid-1')->toArray();

        $this->assertCount(1, $results);
        $row = $results[0];
        $this->assertSame('report.pdf', $row['filename']);
        $this->assertSame('pdf', $row['extension']);
        $this->assertSame('https://example.com', $row['signedUrl']);
        $this->assertNotSame('1048576', $row['filesize']); // converted to human-readable
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $row['created']);
        $this->assertSame('file-1', $row['id']);
    }
}
