<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Controller;

use App\Application;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Http\ServerRequest;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use CakeDC\Uppy\Model\Table\FilesTable;
use PHPUnit\Framework\Attributes\DataProvider;
use UnexpectedValueException;

class FilesControllerSecurityTest extends TestCase
{
    use IntegrationTestTrait;

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

    /**
     * Decode the JSON body of the last response.
     *
     * @return array<string, mixed>
     */
    protected function responseBody(): array
    {
        $this->assertNotNull($this->_response);
        $decoded = json_decode((string)$this->_response->getBody(), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

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
        $conn = $this->connection();
        $conn->execute('DROP TABLE IF EXISTS uppy_files');
        $conn->execute('
            CREATE TABLE uppy_files (
                id          VARCHAR(36)     NOT NULL,
                user_id     INTEGER          NULL,
                model       VARCHAR(128)     NULL,
                filename    VARCHAR(255)     NULL,
                filesize    INTEGER          NULL,
                mime_type   VARCHAR(128)     NULL,
                extension   VARCHAR(32)      NULL,
                hash        VARCHAR(64)      NULL,
                path        VARCHAR(255)     NULL,
                adapter     VARCHAR(32)      NULL,
                created     TIMESTAMP        NULL,
                modified    TIMESTAMP        NULL,
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

        // The raw DDL above cannot express a uuid column portably across MySQL,
        // SQLite and PostgreSQL. Declare it explicitly so the schema mirrors the
        // real migration (where `id` is a uuid) and the ORM generates the key.
        TableRegistry::getTableLocator()
            ->get('CakeDC/Uppy.Files')
            ->getSchema()
            ->setColumnType('id', 'uuid');
    }

    protected function tearDown(): void
    {
        $conn = $this->connection();
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
        $this->post('/uppy/files/save', (string)json_encode([
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
        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('NonExistentTable99', $body['result']['message']);
    }

    public function testSaveRejectsPathNotSignedByServer(): void
    {
        $this->loginAs(1);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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

        $body = $this->responseBody();
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
        $this->post('/uppy/files/save', (string)json_encode([
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

        $body = $this->responseBody();
        $this->assertFalse($body['result']['error']);
    }

    public function testMultipartCompletionRegistersPathForSave(): void
    {
        $this->loginAs(1);
        $key = 'alerrt/ResourceFiles/multipart-complete.png';
        // createMultipartUpload() must have registered the upload for this session.
        $this->session([
            'Auth.userId' => 1,
            'Uppy.multipartUploads' => ['dummy-upload-id' => $key],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/complete-multipart-upload', (string)json_encode([
            'uploadId' => 'dummy-upload-id',
            'key' => $key,
            'parts' => [['PartNumber' => 1, 'ETag' => '"abc"']],
        ]));

        $complete = $this->responseBody();
        $this->assertFalse($complete['error']);
        $this->assertSame($key, $complete['key']);

        // Regression: completing a multipart upload must register its key as a
        // server-issued pending upload, otherwise the follow-up save() rejects it
        // with "Invalid or unrecognized file path".
        // Server::run closes the session at the end of the request, so read the
        // persisted data from $_SESSION (the Session object would start a new one).
        $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
        $this->assertArrayHasKey($key, $pending);

        // IntegrationTestTrait does not carry controller-side session writes to the
        // next request; forward the server-issued token to the save() request.
        $this->session(['Auth.userId' => 1, 'Uppy.pendingUploads' => $pending]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'multipart-complete.png',
                'filesize' => 524288000,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $key,
            ]],
        ]));

        $save = $this->responseBody();
        $this->assertFalse($save['result']['error']);
        $this->assertCount(1, $save['result']['saved_file_ids']);

        $rows = $this->connection()
            ->execute('SELECT path FROM uppy_files WHERE path = :path', ['path' => $key])
            ->fetchAll('assoc');
        $this->assertNotEmpty($rows, 'The multipart file metadata should be persisted');
    }

    public function testSignPartRejectsUnknownUploadId(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign-part', (string)json_encode([
            'uploadId' => 'forged-upload-id',
            'key' => 'alerrt/ResourceFiles/forged.pdf',
            'partNumber' => 1,
        ]));

        $this->assertResponseCode(403);
        $this->assertSame(403, $this->responseBody()['code']);
    }

    public function testCompleteMultipartUploadRejectsUnknownUploadId(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/complete-multipart-upload', (string)json_encode([
            'uploadId' => 'forged-upload-id',
            'key' => 'alerrt/ResourceFiles/forged.pdf',
            'parts' => [['PartNumber' => 1, 'ETag' => '"abc"']],
        ]));

        $this->assertResponseCode(403);
        $this->assertSame(403, $this->responseBody()['code']);
    }

    public function testCompleteMultipartUploadRejectsKeyMismatch(): void
    {
        // The uploadId is known to the session, but the client sends another key.
        $this->session([
            'Auth.userId' => 1,
            'Uppy.multipartUploads' => ['dummy-upload-id' => 'alerrt/ResourceFiles/real.pdf'],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/complete-multipart-upload', (string)json_encode([
            'uploadId' => 'dummy-upload-id',
            'key' => 'alerrt/ResourceFiles/other.pdf',
            'parts' => [['PartNumber' => 1, 'ETag' => '"abc"']],
        ]));

        $this->assertResponseCode(403);
    }

    public function testAbortMultipartUploadRejectsUnknownUploadId(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/abort-multipart-upload', (string)json_encode([
            'uploadId' => 'forged-upload-id',
            'key' => 'alerrt/ResourceFiles/forged.pdf',
        ]));

        $this->assertResponseCode(403);
        $this->assertSame(403, $this->responseBody()['code']);
    }

    public function testCreateMultipartUploadBindsUploadIdToSession(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/create-multipart-upload', (string)json_encode([
            'filename' => 'large.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseOk();
        $body = $this->responseBody();
        $this->assertFalse($body['error']);

        $uploads = (array)($_SESSION['Uppy']['multipartUploads'] ?? []);
        $this->assertArrayHasKey('dummy-upload-id', $uploads);
        $this->assertSame($body['key'], $uploads['dummy-upload-id']);
    }

    public function testMultipartUploadIsBoundToCreatingSession(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);

        // 1. createMultipartUpload() registers the upload for this session.
        $this->post('/uppy/files/create-multipart-upload', (string)json_encode([
            'filename' => 'large.png',
            'contentType' => 'image/png',
        ]));
        $created = $this->responseBody();
        $this->assertFalse($created['error']);
        $uploadId = $created['uploadId'];
        $key = $created['key'];

        // IntegrationTestTrait does not carry controller-side session writes
        // forward; re-seed them for the next requests.
        $multipart = (array)($_SESSION['Uppy']['multipartUploads'] ?? []);

        // 2. sign-part accepts the bound uploadId/key.
        $this->session(['Auth.userId' => 1, 'Uppy.multipartUploads' => $multipart]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign-part', (string)json_encode([
            'uploadId' => $uploadId,
            'key' => $key,
            'partNumber' => 1,
        ]));
        $this->assertResponseOk();
        $this->assertFalse($this->responseBody()['error']);

        // 3. complete accepts it and drops the multipart entry.
        $this->session(['Auth.userId' => 1, 'Uppy.multipartUploads' => $multipart]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/complete-multipart-upload', (string)json_encode([
            'uploadId' => $uploadId,
            'key' => $key,
            'parts' => [['PartNumber' => 1, 'ETag' => '"abc"']],
        ]));
        $this->assertResponseOk();
        $this->assertFalse($this->responseBody()['error']);

        $after = (array)($_SESSION['Uppy']['multipartUploads'] ?? []);
        $this->assertArrayNotHasKey($uploadId, $after, 'The multipart entry must be dropped after completion');
    }

    public function testSaveRejectsAlreadyUsedPath(): void
    {
        $signedKey = 'some-uuid-test.png';

        $payload = (string)json_encode([
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
        $first = $this->responseBody();
        $this->assertFalse($first['result']['error'], 'First save should succeed');

        // Forward the session exactly as the controller left it after the
        // successful save: the one-time token must have been consumed.
        $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
        $this->assertArrayNotHasKey(
            $signedKey,
            $pending,
            'The token must be consumed by a successful save',
        );
        $this->session(['Auth.userId' => 1, 'Uppy.pendingUploads' => $pending]);

        // Second save with same path — session token consumed, must be rejected
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', $payload);
        $second = $this->responseBody();
        $this->assertTrue($second['result']['error'], 'Second save with same path should fail');
    }

    public function testSignResponseIncludesKey(): void
    {
        $this->loginAs(1);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'photo.png',
            'contentType' => 'image/png',
        ]));

        $body = $this->responseBody();
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

        // After delete, redirects to the canonical index URL.
        $this->assertResponseCode(302);
        $this->assertRedirect('/uppy/files');
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
        $this->post('/uppy/files/save', (string)json_encode([
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

        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('not authorized', strtolower($body['result']['message']));
    }

    public function testSaveUsesConfiguredOwnerIdResolver(): void
    {
        // Logged in as user 2; the resolver overrides the default `user_id`
        // column and declares that the record (foreign_key 1, owned by user 1)
        // belongs to user 2.
        Configure::write(
            'Uppy.ownerIdResolver',
            static fn(EntityInterface $record, ServerRequest $request): int => 2,
        );

        $signedKey = 'owner-resolver.png';
        $this->session([
            'Auth.userId' => 2,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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

        $body = $this->responseBody();
        $this->assertFalse($body['result']['error']);

        $row = $this->connection()
            ->execute('SELECT user_id FROM uppy_files WHERE path = :path', ['path' => $signedKey])
            ->fetchAll('assoc');
        $this->assertNotEmpty($row);
        $this->assertSame(2, (int)$row[0]['user_id']);
    }

    public function testSaveOwnerIdResolverReturningNullRejects(): void
    {
        Configure::write(
            'Uppy.ownerIdResolver',
            static fn(EntityInterface $record, ServerRequest $request): ?int => null,
        );

        $signedKey = 'owner-resolver-null.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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

        $this->assertTrue($this->responseBody()['result']['error']);
    }

    public function testSaveOwnerIdResolverRejectsNonScalar(): void
    {
        Configure::write(
            'Uppy.ownerIdResolver',
            static fn(EntityInterface $record, ServerRequest $request): array => ['bad'],
        );

        $signedKey = 'owner-resolver-bad.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->disableErrorHandlerMiddleware();
        $this->expectException(UnexpectedValueException::class);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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
    }

    public function testSaveDoesNotPersistClientProvidedHash(): void
    {
        $signedKey = 'hash-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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
        $body = $this->responseBody();
        $this->assertFalse($body['result']['error']);

        $row = $this->connection()
            ->execute('SELECT hash, metadata, path FROM uppy_files WHERE path = :path', ['path' => $signedKey])
            ->fetchAll('assoc');

        $this->assertNotEmpty($row, 'File should have been saved');
        $this->assertNotSame('attacker-controlled-hash', $row[0]['hash']);
        $this->assertNull($row[0]['metadata']);
        $this->assertSame($signedKey, $row[0]['path']); // path must be the server-validated key
    }

    public function testSaveTokenIsPreservedAfterFailedValidation(): void
    {
        $signedKey = 'reuse-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        // First attempt: valid path token but an invalid model — should fail validation
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'NonExistentModel99',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));
        $first = $this->responseBody();
        $this->assertTrue($first['result']['error'], 'First attempt must fail due to invalid model');

        // A failed save must not consume the token, so the uploaded object can
        // be retried instead of being orphaned in storage.
        $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
        $this->assertArrayHasKey(
            $signedKey,
            $pending,
            'The token must be preserved after a failed save',
        );
        $this->session(['Auth.userId' => 1, 'Uppy.pendingUploads' => $pending]);

        // Retry with the same token and a valid payload must succeed.
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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
        $second = $this->responseBody();
        $this->assertFalse($second['result']['error'], 'Retry with the preserved token must succeed');
    }

    public function testSaveTokenIsPreservedWhenSaveManyFails(): void
    {
        $signedKey = 'save-fail.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        // Force the saveMany() === false branch (e.g. a DB-level persistence
        // failure) to verify the token is not consumed.
        $table = $this->getMockBuilder(FilesTable::class)
            ->onlyMethods(['saveMany'])
            ->getMock();
        $table->method('saveMany')->willReturn(false);
        $this->getTableLocator()->set('CakeDC/Uppy.Files', $table);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'file.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));

        $body = $this->responseBody();
        $this->assertTrue($body['result']['error'], 'A failed saveMany must be reported');

        // The token must survive a persistence failure so the upload can be retried.
        $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
        $this->assertArrayHasKey(
            $signedKey,
            $pending,
            'The token must be preserved when saveMany() fails',
        );
    }

    public function testViewBlocksUnauthenticatedUser(): void
    {
        // Insert a file with no owner (user_id = NULL)
        $fileId = $this->insertFile(['user_id' => null]);

        // Do NOT call loginAs() — simulate unauthenticated request
        $this->get('/uppy/files/view/' . $fileId);

        // Unauthenticated (no user at all) is 401, not 403.
        $this->assertResponseCode(401);
    }

    public function testDeleteBlocksUnauthenticatedUser(): void
    {
        $id = Text::uuid();
        $this->connection()->execute(
            'INSERT INTO uppy_files (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES (:id,:user_id,:model,:filename,:filesize,:mime_type,:extension,:hash,:path,:adapter,:created,:modified,:metadata,:foreign_key)',
            ['id' => $id,'user_id' => null,'model' => 'Users','filename' => 'test.png','filesize' => 1024,'mime_type' => 'image/png','extension' => 'png','hash' => 'abc','path' => 'uuid-no-owner.png','adapter' => 's3','created' => '2024-01-01 00:00:00','modified' => '2024-01-01 00:00:00','metadata' => null,'foreign_key' => 1],
        );

        // No loginAs() — unauthenticated
        $this->delete('/uppy/files/delete/' . $id);

        // Unauthenticated (no user at all) is 401, not 403.
        $this->assertResponseCode(401);
    }

    public function testSignBlocksUnauthenticatedUser(): void
    {
        // No loginAs() — unauthenticated requests must not get upload URLs.
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'test.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseCode(401);
        $body = $this->responseBody();
        $this->assertTrue($body['error']);
        $this->assertSame(401, $body['code']);
    }

    public function testMultipartBlocksUnauthenticatedUser(): void
    {
        // No loginAs() — unauthenticated requests must not start multipart uploads.
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/create-multipart-upload', (string)json_encode([
            'filename' => 'large.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseCode(401);
        $body = $this->responseBody();
        $this->assertTrue($body['error']);
        $this->assertSame(401, $body['code']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function multipartEndpointProvider(): array
    {
        return [
            'sign-part' => ['sign-part'],
            'complete-multipart-upload' => ['complete-multipart-upload'],
            'abort-multipart-upload' => ['abort-multipart-upload'],
        ];
    }

    #[DataProvider('multipartEndpointProvider')]
    public function testMultipartEndpointBlocksUnauthenticatedUser(string $endpoint): void
    {
        // No loginAs() — unauthenticated requests must not use multipart endpoints.
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/' . $endpoint, (string)json_encode([]));

        $this->assertResponseCode(401);
        $body = $this->responseBody();
        $this->assertTrue($body['error']);
        $this->assertSame(401, $body['code']);
    }

    public function testIndexScopesToCurrentUser(): void
    {
        $this->insertFile(['user_id' => 1, 'filename' => 'mine.png']);
        $this->insertFile(['user_id' => 2, 'filename' => 'theirs.png']);

        $this->loginAs(1);
        $this->get('/uppy/files');

        $this->assertResponseOk();
        $this->assertResponseContains('mine.png');
        $this->assertResponseNotContains('theirs.png');
    }

    public function testIndexBlocksUnauthenticatedUser(): void
    {
        $this->insertFile(['user_id' => 1, 'filename' => 'mine.png']);

        // No loginAs() — unauthenticated
        $this->get('/uppy/files');

        // Unauthenticated (no user at all) is 401, not 403.
        $this->assertResponseCode(401);
    }

    public function testSignUsesIdentityRequestAttribute(): void
    {
        // No loginAs(): the identity request attribute is the supported source.
        $this->configRequest(['headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Test-Identity' => '1',
        ]]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'identity.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseOk();
        $this->assertFalse($this->responseBody()['error']);
    }

    public function testSignUsesConfiguredCurrentUserIdResolver(): void
    {
        // No loginAs(): the user id comes only from the configured resolver.
        Configure::write('Uppy.currentUserIdResolver', static fn(ServerRequest $request): int => 1);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'resolver.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseOk();
        $body = $this->responseBody();
        $this->assertFalse($body['error']);
        $this->assertArrayHasKey('key', $body);
    }

    public function testCurrentUserIdResolverReceivesServerRequest(): void
    {
        // The resolver reads the request, proving it receives the ServerRequest.
        Configure::write('Uppy.currentUserIdResolver', static function (ServerRequest $request): int {
            return (int)$request->getHeaderLine('X-Test-User');
        });

        $this->configRequest(['headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Test-User' => '2',
        ]]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'resolver.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseOk();
        $this->assertFalse($this->responseBody()['error']);
    }

    public function testCurrentUserIdResolverReturningNullBlocksRequest(): void
    {
        // A session user exists, but the configured resolver is authoritative:
        // returning null must still block the request.
        $this->loginAs(1);
        Configure::write('Uppy.currentUserIdResolver', static fn(ServerRequest $request): ?int => null);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'resolver.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseCode(401);
        $this->assertSame(401, $this->responseBody()['code']);
    }

    public function testNonScalarSessionUserIdIsRejected(): void
    {
        // A malformed session value must fail loudly instead of raising a TypeError.
        $this->session(['Auth.userId' => ['not', 'scalar']]);

        $this->disableErrorHandlerMiddleware();
        $this->expectException(UnexpectedValueException::class);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'test.png',
            'contentType' => 'image/png',
        ]));
    }

    public function testCurrentUserIdResolverRejectsNonScalar(): void
    {
        Configure::write('Uppy.currentUserIdResolver', static fn(ServerRequest $request): array => ['bad']);

        // Let the misconfiguration surface as an exception instead of a 500 page.
        $this->disableErrorHandlerMiddleware();
        $this->expectException(UnexpectedValueException::class);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'resolver.png',
            'contentType' => 'image/png',
        ]));
    }

    public function testIndexUsesConfiguredCurrentUserIdResolver(): void
    {
        $this->insertFile(['user_id' => 7, 'filename' => 'resolver-mine.png']);
        $this->insertFile(['user_id' => 8, 'filename' => 'resolver-theirs.png']);

        Configure::write('Uppy.currentUserIdResolver', static fn(ServerRequest $request): int => 7);

        $this->get('/uppy/files');

        $this->assertResponseOk();
        $this->assertResponseContains('resolver-mine.png');
        $this->assertResponseNotContains('resolver-theirs.png');
    }

    public function testDemoAddPageRendersInDebug(): void
    {
        // Exercises the assets element, which reverse-routes the `sign` and
        // `save` endpoints; this would fail if those routes were POST-only.
        $this->get('/uppy/files/add');

        $this->assertResponseOk();
        $this->assertResponseContains('/uppy/files/sign');
        $this->assertResponseContains('/uppy/files/save');
    }

    public function testSaveRejectsEmptyItems(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode(['items' => []]));

        $this->assertResponseOk();
        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
    }

    public function testSaveRejectsNonArrayItem(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode(['items' => ['not-an-object']]));

        $this->assertResponseOk();
        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
    }

    public function testSignRejectsMissingFilename(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode(['contentType' => 'image/png']));
        $this->assertResponseCode(400);
    }

    public function testSignRejectsInvalidContentType(): void
    {
        $this->loginAs(1);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'test.zip',
            'contentType' => 'application/zip',
        ]));
        $this->assertResponseCode(400);
    }

    public function testSaveMissingModelReturnsError(): void
    {
        $signedKey = 'model-missing-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));
        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('model', strtolower($body['result']['message']));
    }

    public function testSaveMissingForeignKeyReturnsError(): void
    {
        $signedKey = 'fk-missing-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));
        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('foreign key', strtolower($body['result']['message']));
    }

    public function testSaveForeignKeyNotFoundReturnsError(): void
    {
        $signedKey = 'fk-not-found-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 9999,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));
        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('9999', $body['result']['message']);
    }

    public function testSaveResultIncludesFileIds(): void
    {
        $signedKey = 'ids-test.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
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
        $body = $this->responseBody();
        $this->assertFalse($body['result']['error']);
        $this->assertArrayHasKey('saved_file_ids', $body['result']);
        $this->assertCount(1, $body['result']['saved_file_ids']);
    }

    public function testSaveMultipleItems(): void
    {
        $key1 = 'multi-item-1.png';
        $key2 = 'multi-item-2.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$key1 => time(), $key2 => time()],
        ]);
        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [
                [
                    'model' => 'Users',
                    'foreign_key' => 1,
                    'filename' => 'first.png',
                    'filesize' => 100,
                    'extension' => 'png',
                    'mime_type' => 'image/png',
                    'path' => $key1,
                ],
                [
                    'model' => 'Users',
                    'foreign_key' => 1,
                    'filename' => 'second.png',
                    'filesize' => 200,
                    'extension' => 'png',
                    'mime_type' => 'image/png',
                    'path' => $key2,
                ],
            ],
        ]));
        $body = $this->responseBody();
        $this->assertFalse($body['result']['error']);
        $this->assertCount(2, $body['result']['saved_file_ids']);
    }

    public function testCurrentUserIdResolverReturningEmptyStringBlocksRequest(): void
    {
        // An empty string means "no user"; it must not be treated as a valid id
        // (a session user exists, but the resolver is authoritative).
        $this->loginAs(1);
        Configure::write('Uppy.currentUserIdResolver', static fn(ServerRequest $request): string => '');

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'resolver.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseCode(401);
        $this->assertSame(401, $this->responseBody()['code']);
    }

    public function testEmptySessionUserIdBlocksRequest(): void
    {
        // An empty Auth.userId is equivalent to being logged out.
        $this->session(['Auth.userId' => '']);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/sign', (string)json_encode([
            'filename' => 'test.png',
            'contentType' => 'image/png',
        ]));

        $this->assertResponseCode(401);
        $this->assertSame(401, $this->responseBody()['code']);
    }

    public function testEmptyUserIdResolverCannotViewNullOwnerFile(): void
    {
        // A file with no owner must not be reachable through an empty user id.
        $fileId = $this->insertFile(['user_id' => null]);
        Configure::write('Uppy.currentUserIdResolver', static fn(ServerRequest $request): string => '');

        $this->get('/uppy/files/view/' . $fileId);

        $this->assertResponseCode(401);
    }

    public function testViewForbidsNullOwnerFileForValidUser(): void
    {
        // Even a valid, authenticated user cannot view a file with no owner.
        $fileId = $this->insertFile(['user_id' => null]);
        $this->loginAs(1);

        $this->get('/uppy/files/view/' . $fileId);

        $this->assertResponseCode(403);
    }

    public function testDeleteForbidsNullOwnerFileForValidUser(): void
    {
        // A file with no owner cannot be deleted, even by an authenticated user.
        $fileId = $this->insertFile(['user_id' => null]);
        $this->loginAs(1);

        $this->delete('/uppy/files/delete/' . $fileId);

        $this->assertResponseCode(403);
    }

    public function testSaveRejectsNullOwnerRecord(): void
    {
        // The related record exists but has no owner (user_id NULL): a valid
        // user must not be able to attach files to it.
        $this->connection()->execute('INSERT INTO users (id, user_id) VALUES (3, NULL)');

        $signedKey = 'null-owner.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$signedKey => time()],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 3,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $signedKey,
            ]],
        ]));

        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('not authorized', strtolower($body['result']['message']));
    }

    public function testSaveRejectsExpiredToken(): void
    {
        // A token older than the default TTL (1 hour) must not be accepted.
        $expiredKey = 'expired.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$expiredKey => time() - 7200],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $expiredKey,
            ]],
        ]));

        $body = $this->responseBody();
        $this->assertTrue($body['result']['error']);
        $this->assertStringContainsString('unrecognized', strtolower($body['result']['message']));
    }

    public function testExpiredTokensArePrunedFromSession(): void
    {
        // Reading the store prunes expired entries; the fresh token is consumed.
        $expiredKey = 'old.png';
        $freshKey = 'fresh.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [
                $expiredKey => time() - 7200,
                $freshKey => time(),
            ],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $freshKey,
            ]],
        ]));

        $this->assertFalse($this->responseBody()['result']['error']);

        $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
        $this->assertArrayNotHasKey($expiredKey, $pending, 'Expired tokens must be pruned');
        $this->assertArrayNotHasKey($freshKey, $pending, 'The consumed token must be removed');
    }

    public function testPendingUploadsTtlZeroDisablesExpiry(): void
    {
        // With expiry disabled (TTL 0), an old token is still accepted.
        Configure::write('Uppy.pendingUploadsTtl', 0);
        $oldKey = 'old-but-valid.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$oldKey => time() - 7200],
        ]);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $oldKey,
            ]],
        ]));

        $this->assertFalse($this->responseBody()['result']['error']);
    }

    public function testSavePreservesTokenRegisteredDuringSave(): void
    {
        // A concurrent sign() registers a token while saveMany() runs: the
        // re-read-before-write must keep it, while the consumed token is removed.
        $consumedKey = 'consume-me.png';
        $concurrentKey = 'concurrent.png';
        $this->session([
            'Auth.userId' => 1,
            'Uppy.pendingUploads' => [$consumedKey => time()],
        ]);

        $table = $this->getMockBuilder(FilesTable::class)
            ->onlyMethods(['saveMany'])
            ->getMock();
        $table->method('saveMany')->willReturnCallback(function (iterable $entities) use ($concurrentKey): iterable {
            $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
            $pending[$concurrentKey] = time();
            $_SESSION['Uppy']['pendingUploads'] = $pending;

            return $entities;
        });
        $this->getTableLocator()->set('CakeDC/Uppy.Files', $table);

        $this->configRequest(['headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json']]);
        $this->post('/uppy/files/save', (string)json_encode([
            'items' => [[
                'model' => 'Users',
                'foreign_key' => 1,
                'filename' => 'test.png',
                'filesize' => 100,
                'extension' => 'png',
                'mime_type' => 'image/png',
                'path' => $consumedKey,
            ]],
        ]));

        $this->assertFalse($this->responseBody()['result']['error']);

        $pending = (array)($_SESSION['Uppy']['pendingUploads'] ?? []);
        $this->assertArrayNotHasKey($consumedKey, $pending, 'The consumed token must be removed');
        $this->assertArrayHasKey($concurrentKey, $pending, 'A token registered during the save must survive');
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

        $this->connection()->execute(
            'INSERT INTO uppy_files
                (id,user_id,model,filename,filesize,mime_type,extension,hash,path,adapter,created,modified,metadata,foreign_key)
             VALUES
                (:id,:user_id,:model,:filename,:filesize,:mime_type,:extension,:hash,:path,:adapter,:created,:modified,:metadata,:foreign_key)',
            $row,
        );

        return $id;
    }
}
