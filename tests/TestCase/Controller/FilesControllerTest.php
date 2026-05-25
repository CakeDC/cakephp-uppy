<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;

class FilesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configApplication(\App\Application::class, [CONFIG]);

        // Routes only register when debug=true
        Configure::write('debug', true);

        // Full Uppy config with dummy S3 (no real AWS calls)
        Configure::write('Uppy', [
            'Props' => [
                'usersAliasModel' => 'Users',
                'usersModel'      => 'Users',
                'deleteFileS3'    => false,
                'tableFiles'      => 'uppy_files',
            ],
            'AcceptedContentTypes' => ['image/png', 'application/pdf'],
            'AcceptedExtensions'   => ['png', 'pdf'],
            'S3' => [
                'constants' => [
                    'lifeTimeGetObject' => '+20 minutes',
                    'lifeTimePutObject' => '+5 minutes',
                ],
                'config' => [
                    'version'     => 'latest',
                    'region'      => 'us-east-1',
                    'connection'  => 'dummy',
                    'credentials' => ['key' => 'fake', 'secret' => 'fake'],
                ],
                'bucket' => 'test-bucket',
            ],
        ]);

        // In-memory SQLite tables for this test session
        $conn = ConnectionManager::get('test');
        $conn->execute('DROP TABLE IF EXISTS uppy_files');
        $conn->execute('
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
                foreign_key VARCHAR(36)  NOT NULL,
                PRIMARY KEY (id)
            )
        ');
        $conn->execute('DROP TABLE IF EXISTS users');
        $conn->execute('
            CREATE TABLE users (
                id CHAR(36) NOT NULL,
                PRIMARY KEY (id)
            )
        ');
        $conn->execute("INSERT INTO users (id) VALUES ('user-1-uuid')");
        $conn->execute("INSERT INTO users (id) VALUES ('user-2-uuid')");

        TableRegistry::getTableLocator()->clear();
    }

    protected function tearDown(): void
    {
        $conn = ConnectionManager::get('test');
        $conn->execute('DROP TABLE IF EXISTS uppy_files');
        $conn->execute('DROP TABLE IF EXISTS users');
        TableRegistry::getTableLocator()->clear();
        parent::tearDown();
    }

    /** Simulate login by writing userId to session (FilesController::getCurrentUserId() reads this as fallback). */
    protected function loginAs(string $userId): void
    {
        $this->session(['Auth.userId' => $userId]);
    }

    public function testSaveReturnsJsonErrorForUnknownTable(): void
    {
        $this->loginAs('user-1-uuid');
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', json_encode([
            'items' => [[
                'model'       => 'NonExistentTable99',
                'foreign_key' => 'user-1-uuid',
                'filename'    => 'test.png',
                'filesize'    => 100,
                'extension'   => 'png',
                'mime_type'   => 'image/png',
                'path'        => 'uuid-test.png',
            ]],
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('NonExistentTable99', $body['result']['message']);
    }

    /** Insert a file row directly and return its ID. */
    protected function insertFile(array $overrides = []): string
    {
        $id = Text::uuid();
        $row = array_merge([
            'id'          => $id,
            'user_id'     => 'user-1-uuid',
            'model'       => 'Users',
            'filename'    => 'test.png',
            'filesize'    => 1024,
            'mime_type'   => 'image/png',
            'extension'   => 'png',
            'hash'        => 'abc123',
            'path'        => 'some-uuid-test.png',
            'adapter'     => 's3',
            'created'     => '2024-01-01 00:00:00',
            'modified'    => '2024-01-01 00:00:00',
            'metadata'    => null,
            'foreign_key' => 'user-1-uuid',
        ], $overrides);

        ConnectionManager::get('test')->execute(
            'INSERT INTO uppy_files
                (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES
                (:id,:user_id,:model,:filename,:filesize,:mime_type,:extension,:hash,:path,:adapter,:created,:modified,:metadata,:foreign_key)',
            $row
        );

        return $id;
    }
}
