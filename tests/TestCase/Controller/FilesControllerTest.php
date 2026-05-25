<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Controller;

use App\Application;
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

        $this->configApplication(Application::class, [CONFIG]);

        // Routes only register when debug=true
        Configure::write('debug', true);

        // Full Uppy config with dummy S3 (no real AWS calls)
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

        // In-memory SQLite tables for this test session
        $conn = ConnectionManager::get('test');
        $conn->execute('DROP TABLE IF EXISTS uppy_files');
        $conn->execute('
            CREATE TABLE uppy_files (
                id          CHAR(36)     NOT NULL,
                user_id     INTEGER          NULL,
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
        ');
        $conn->execute('DROP TABLE IF EXISTS users');
        $conn->execute('
            CREATE TABLE users (
                id      INTEGER NOT NULL,
                user_id INTEGER     NULL,
                PRIMARY KEY (id)
            )
        ');
        $conn->execute('INSERT INTO users (id, user_id) VALUES (1, 1)');
        $conn->execute('INSERT INTO users (id, user_id) VALUES (2, 2)');

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

    /** Simulate login by writing userId to session. */
    protected function loginAs(int $userId): void
    {
        $this->session(['Auth.userId' => $userId]);
    }

    public function testSaveReturnsJsonErrorForUnknownTable(): void
    {
        $signedKey = 'uuid-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', json_encode([
            'items' => [[
                'model' => 'NonExistentTable99',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('NonExistentTable99', $body['result']['message']);
    }

    public function testSaveRejectsPathNotSignedByServer(): void
    {
        $this->loginAs(1);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => 'attacker-crafted-path.png', // not in session
            ]],
        ]));

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('path', strtolower($body['result']['message']));
    }

    public function testSaveAcceptsPathPreviouslySignedByServer(): void
    {
        $signedKey = 'some-uuid-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['result']['error']);
    }

    public function testSaveRejectsAlreadyUsedPath(): void
    {
        $signedKey = 'some-uuid-test.png';

        $payload = json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]);

        // First save — must succeed
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', $payload);
        $first = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($first['result']['error'], 'First save should succeed');

        // Simulate the consumed state: clear pending uploads (token was used on first save)
        $this->session(['Uppy.pendingUploads' => []]);

        // Second save with same path — session token consumed, must be rejected
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', $payload);
        $second = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($second['result']['error'], 'Second save with same path should fail');
    }

    public function testSignResponseIncludesKey(): void
    {
        $this->loginAs(1);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', json_encode([
            'filename' => 'photo.png',
            'contentType' => 'image/png',
        ]));

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['error']);
        $this->assertArrayHasKey('key', $body);
        $this->assertMatchesRegularExpression('/^[a-f0-9\-]+-photo-png$/', $body['key']);
    }

    public function testViewForbiddenForOtherUserFile(): void
    {
        // File belongs to user 1, but user 2 is logged in
        $fileId = $this->insertFile(['user_id' => 1, 'path' => 'uuid-test.png']);
        $this->loginAs(2);

        $this->get('/uppy/files/view/' . $fileId);

        $this->assertResponseCode(403);
    }

    public function testViewAllowedForOwner(): void
    {
        $fileId = $this->insertFile(['user_id' => 1, 'path' => 'uuid-test.png']);
        $this->loginAs(1);

        $this->get('/uppy/files/view/' . $fileId);

        // Controller redirects to presigned URL
        $this->assertResponseCode(302);
    }

    public function testDeleteForbiddenForOtherUserFile(): void
    {
        $fileId = $this->insertFile(['user_id' => 1]);
        $this->loginAs(2);

        $this->delete('/uppy/files/delete/' . $fileId);

        $this->assertResponseCode(403);
    }

    public function testDeleteAllowedForOwner(): void
    {
        $fileId = $this->insertFile(['user_id' => 1]);
        $this->loginAs(1);

        $this->delete('/uppy/files/delete/' . $fileId);

        // After delete, redirects to index
        $this->assertResponseCode(302);
    }

    public function testSaveRejectsWhenForeignKeyBelongsToAnotherUser(): void
    {
        // User 2 is logged in but supplies foreign_key=1 which belongs to user 1
        $signedKey = 'test-owned-by-other.png';
        $this->session([
            'Auth.userId' => 2, // logged in as user 2
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1, // user 1's record
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));

        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('not authorized', strtolower($body['result']['message']));
    }

    public function testSaveDoesNotPersistClientProvidedHash(): void
    {
        $signedKey = 'hash-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
                'hash' => 'attacker-controlled-hash', // should be ignored
                'metadata' => '{"evil":"payload"}', // should be ignored
            ]],
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $this->assertFalse($body['result']['error']);

        $row = ConnectionManager::get('test')
            ->execute("SELECT hash, metadata, path FROM uppy_files WHERE path = '{$signedKey}'")
            ->fetchAll('assoc');

        $this->assertNotEmpty($row, 'File should have been saved');
        $this->assertNotSame('attacker-controlled-hash', $row[0]['hash']);
        $this->assertNull($row[0]['metadata']);
        $this->assertSame($signedKey, $row[0]['path']); // path must be the server-validated key
    }

    /** Insert a file row directly and return its ID. */
    protected function insertFile(array $overrides = []): string
    {
        $id = Text::uuid();
        $row = array_merge([
            'id' => $id,
            'user_id' => 1,
            'model' => 'Users',
            'filename' => 'test.png',
            'filesize' => 1024,
            'mime_type' => 'image/png',
            'extension' => 'png',
            'hash' => 'abc123',
            'path' => 'some-uuid-test.png',
            'adapter' => 's3',
            'created' => '2024-01-01 00:00:00',
            'modified' => '2024-01-01 00:00:00',
            'metadata' => null,
            'foreign_key' => 1,
        ], $overrides);

        ConnectionManager::get('test')->execute(
            'INSERT INTO uppy_files
                (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES
                (:id,:user_id,:model,:filename,:filesize,:mime_type,:extension,:hash,:path,:adapter,:created,:modified,:metadata,:foreign_key)',
            $row,
        );

        return $id;
    }
}
