<?php
declare(strict_types=1);

/**
 * Copyright 2023 - 2026, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023 - 2026, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Controller;

use Aws\Exception\AwsException;
use Cake\Core\Configure;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\UnauthorizedException;
use Cake\Http\Response;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use CakeDC\Uppy\Storage\AdapterFactory;
use CakeDC\Uppy\Storage\MultipartUploadAdapterInterface;
use CakeDC\Uppy\Storage\StorageAdapterInterface;
use InvalidArgumentException;
use UnexpectedValueException;
use function Cake\Collection\collection;
use function Cake\I18n\__;

/**
 * Files Controller
 *
 * @property \CakeDC\Uppy\Model\Table\FilesTable $Files
 * @method \Cake\Datasource\ResultSetInterface<\CakeDC\Uppy\Model\Entity\File> paginate(?object $object = null, array<string, mixed> $settings = [])
 */
class FilesController extends AppController
{
    private StorageAdapterInterface $storageAdapter;

    private const UPLOAD_SIGNING_ACTIONS = [
        'sign',
        'createMultipartUpload',
        'signPart',
        'completeMultipartUpload',
        'abortMultipartUpload',
        'save',
    ];

    /**
     * Initialize method
     *
     * Set initial controlller Security
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->storageAdapter = AdapterFactory::create();
        if (!$this->components()->has('FormProtection')) {
            $this->loadComponent('FormProtection');
        }
        $this->FormProtection->setConfig('unlockedActions', [
            ...self::UPLOAD_SIGNING_ACTIONS,
            'delete',
        ]);
        if (!$this->components()->has('Flash')) {
            $this->loadComponent('Flash');
        }
    }

    /**
     * Returns the current authenticated user's identifier.
     *
     * Resolution order:
     *
     * 1. `Uppy.currentUserIdResolver` — a callable that receives the
     *    `ServerRequest` and returns a string, an integer or null. When set, it
     *    is authoritative (a null result means "no user") and lets applications
     *    with non-standard auth resolve the id without subclassing.
     * 2. The PSR-7 'identity' request attribute set by the
     *    cakephp/authentication middleware.
     * 3. The 'Auth.userId' session key, which is not written by the standard
     *    auth components and exists only for applications (and tests) that set
     *    it explicitly.
     *
     * @return string|int|null
     * @throws \UnexpectedValueException When the configured resolver returns a non-scalar value.
     */
    protected function getCurrentUserId(): string|int|null
    {
        $resolver = Configure::read('Uppy.currentUserIdResolver');
        if (is_callable($resolver)) {
            $resolved = $resolver($this->getRequest());
            if ($resolved !== null && !is_string($resolved) && !is_int($resolved)) {
                throw new UnexpectedValueException(
                    __('Uppy.currentUserIdResolver must return a string, an integer or null.'),
                );
            }

            return $resolved;
        }

        $identity = $this->getRequest()->getAttribute('identity');
        if ($identity !== null) {
            return $identity->getIdentifier();
        }

        return $this->getRequest()->getSession()->read('Auth.userId');
    }

    /**
     * Return the authenticated user's identifier or abort the request.
     *
     * @return string|int
     * @throws \Cake\Http\Exception\UnauthorizedException When no user is authenticated.
     */
    protected function requireCurrentUserId(): string|int
    {
        $currentUserId = $this->getCurrentUserId();
        if ($currentUserId === null) {
            throw new UnauthorizedException();
        }

        return $currentUserId;
    }

    /**
     * Return a JSON 401 envelope when there is no authenticated user.
     *
     * The JSON endpoints use the plugin's response envelope instead of the
     * framework's error format so the bundled JavaScript can report it.
     *
     * @return \Cake\Http\Response|null Response to send, or null when authenticated.
     */
    protected function unauthenticatedJsonResponse(): ?Response
    {
        if ($this->getCurrentUserId() !== null) {
            return null;
        }

        return $this->jsonResponse([
            'error' => true,
            'code' => 401,
            'message' => __('Authentication is required.'),
        ], 401);
    }

    /**
     * Index method
     *
     * Lists only the files owned by the authenticated user.
     */
    public function index(): void
    {
        $currentUserId = $this->requireCurrentUserId();
        $files = $this->paginate($this->Files->find()->where(['user_id' => $currentUserId]));

        $this->set(compact('files'));
    }

    /**
     * View method
     */
    public function view(string|int|null $id = null): ?Response
    {
        /** @var \CakeDC\Uppy\Model\Entity\File $file */
        $file = $this->Files->get($id);

        $currentUserId = $this->getCurrentUserId();
        if ($currentUserId === null) {
            throw new UnauthorizedException();
        }
        if ((string)$file->user_id !== (string)$currentUserId) {
            throw new ForbiddenException();
        }

        $presignedUrl = $this->storageAdapter->presignedUrl($file->path ?? '');

        return $this->redirect($presignedUrl);
    }

    /**
     * Save method
     *
     * Save files data received in database, assign default model configured, if not foreign_key received assign first object in table
     *
     * @throws \Exception
     */
    public function save(): void
    {
        $this->getRequest()->allowMethod('post');
        $this->viewBuilder()->setClassName('Json');

        $items = $this->getRequest()->getData('items');

        if (!is_array($items) || $items === []) {
            $result = [
                'error' => true,
                'message' => __('items must be a non-empty array'),
            ];
            $this->set('result', $result);
            $this->viewBuilder()->setOption('serialize', ['result']);

            return;
        }

        $files = [];
        $result = [];
        $session = $this->getRequest()->getSession();
        // Working copy of the session tokens. They are only committed back to the
        // session after a successful save, so a failed batch can be retried
        // without orphaning the objects already uploaded to storage.
        $remainingUploads = (array)$session->read('Uppy.pendingUploads', []);
        foreach ($items as $item) {
            if (!is_array($item)) {
                $result['error'] = true;
                $result['message'] = __('each item must be an object');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            $path = isset($item['path']) && is_string($item['path']) ? $item['path'] : null;
            if ($path === null || $path === '' || !array_key_exists($path, $remainingUploads)) {
                $result['error'] = true;
                $result['message'] = __('Invalid or unrecognized file path');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            // Mark the token as used for this batch (one-time use).
            unset($remainingUploads[$path]);
            $tableAlias = isset($item['model']) ? (string)$item['model'] : '';
            if ($tableAlias === '') {
                $result['error'] = true;
                $result['message'] = __('model is required');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            try {
                $relationTable = $this->fetchTable($tableAlias);
            } catch (MissingTableClassException | UnexpectedValueException) {
                $result['error'] = true;
                $result['message'] = __('there is no table {0} to associate the file', $tableAlias);
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            $foreignKey = isset($item['foreign_key']) ? (string)$item['foreign_key'] : '';
            if ($foreignKey === '') {
                $result['error'] = true;
                $result['message'] = __('foreign key is required');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            try {
                $register = $relationTable->get($foreignKey);
            } catch (RecordNotFoundException) {
                $result['error'] = true;
                $result['message'] = __('there is no record with id {0} to associate the file', $foreignKey);
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            // @phpstan-ignore-next-line DatabaseException is thrown by the ORM when the table cannot be described.
            } catch (DatabaseException) {
                $result['error'] = true;
                $result['message'] = __('Could not retrieve record with id {0} from {1}', $foreignKey, $tableAlias);
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            // Issue #4 fix: verify the related record belongs to the current user
            $model = Configure::readOrFail('Uppy.Props.usersModel');
            $relation_key = Inflector::singularize(mb_strtolower($model)) . '_id';
            $recordOwnerId = $register->{$relation_key};
            $currentUserId = $this->getCurrentUserId();

            if ($currentUserId === null || (string)$recordOwnerId !== (string)$currentUserId) {
                $result['error'] = true;
                $result['message'] = __('You are not authorized to associate files with this record');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }

            $file = $this->Files->newEntity($item, ['associated' => []]);
            $file->filename = isset($item['filename']) ? (string)$item['filename'] : null;
            $file->filesize = isset($item['filesize']) ? (int)$item['filesize'] : null;
            $file->extension = isset($item['extension']) ? (string)$item['extension'] : null;
            $file->user_id = $recordOwnerId; // already verified above
            $file->model = $tableAlias;
            $file->path = $path; // server-validated path (from session token)
            $files[] = $file;
        }

        $savedFiles = $this->Files->saveMany($files);
        if ($savedFiles !== false) {
            // Commit the consumed one-time tokens only after a successful save.
            $session->write('Uppy.pendingUploads', $remainingUploads);
            $result['error'] = false;
            $result['message'] = __('The association has been be saved correctly');
            $result['saved_file_ids'] = collection($savedFiles)->extract('id')->toArray();
        } else {
            $result['error'] = true;
            $result['message'] = __('The association to file could not be saved');
        }

        $this->set('result', $result);
        $this->viewBuilder()->setOption('serialize', ['result']);
    }

    /**
     * Test method
     */
    public function drag(): void
    {
        $this->getRequest()->allowMethod('get');

        $file = $this->Files->newEmptyEntity();

        $this->set(compact('file'));
    }

    /**
     * Delete method
     *
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(string|int|null $id = null): ?Response
    {
        $this->getRequest()->allowMethod(['post', 'delete']);
        $file = $this->Files->get($id);

        $currentUserId = $this->getCurrentUserId();
        if ($currentUserId === null) {
            throw new UnauthorizedException();
        }
        if ((string)$file->user_id !== (string)$currentUserId) {
            throw new ForbiddenException();
        }

        if ($this->Files->delete($file)) {
            $this->Flash->success(__('The file has been deleted.'));
        } else {
            $this->Flash->error(__('The file could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Sign method
     *
     * Generate presigned url and method and return the same body with signed url to upload from front to S3 directly
     *
     * @return \Cake\Http\Response
     */
    public function sign(): Response
    {
        $this->getRequest()->allowMethod('post');
        $unauthenticated = $this->unauthenticatedJsonResponse();
        if ($unauthenticated !== null) {
            return $unauthenticated;
        }

        $filename = $this->getRequest()->getData('filename');
        if ($filename === null || $filename === '') {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('filename is required'),
            ], 400);
        }

        $contentType = (string)$this->getRequest()->getData('contentType');
        try {
            $this->assertAcceptedContentType($contentType);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => $exception->getMessage(),
            ], 400);
        }

        $filesize = $this->getRequest()->getData('filesize');
        if ($filesize !== null && $filesize !== '') {
            try {
                $this->assertMaxFileSize((int)$filesize);
            } catch (InvalidArgumentException $exception) {
                return $this->jsonResponse([
                    'error' => true,
                    'code' => 400,
                    'message' => $exception->getMessage(),
                ], 400);
            }
        }

        $storageKey = $this->buildStorageKey(
            (string)$this->getRequest()->getData('filename'),
            $this->getRequest()->getData('prefix') ? (string)$this->getRequest()->getData('prefix') : null,
        );

        // Store the generated key in session so save() can verify it was server-issued.
        $this->rememberPendingUpload($storageKey);

        $presignedRequest = $this->storageAdapter->createPresignedRequest($storageKey, $contentType);

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'method' => $presignedRequest->getMethod(),
            'url' => (string)$presignedRequest->getUri(),
            'key' => $storageKey,
            'fields' => [],
            // Also set the content-type header on the request, to make sure that it is the same as the one we used to generate the signature.
            // Else, the browser picks a content-type as it sees fit.
            'headers' => [
                'content-type' => $contentType,
            ],
        ]);
    }

    /**
     * Add method
     */
    public function add(): void
    {
        $this->getRequest()->allowMethod('get');
        $file = $this->Files->newEmptyEntity();

        $this->set(compact('file'));
    }

    /**
     * Start a multipart upload and return upload id and object key.
     *
     * @return \Cake\Http\Response
     */
    public function createMultipartUpload(): Response
    {
        $this->getRequest()->allowMethod('post');
        $unauthenticated = $this->unauthenticatedJsonResponse();
        if ($unauthenticated !== null) {
            return $unauthenticated;
        }

        if (!$this->storageAdapter instanceof MultipartUploadAdapterInterface) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 501,
                'message' => __('Multipart uploads are not supported by the current storage driver'),
            ], 501);
        }

        try {
            $upload = $this->parseUploadRequest();
            $result = $this->storageAdapter->createMultipartUpload($upload['key'], $upload['contentType']);
        } catch (InvalidArgumentException $exception) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => $exception->getMessage(),
            ], 400);
        } catch (AwsException $exception) {
            return $this->s3ErrorResponse($exception);
        }

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'uploadId' => $result['uploadId'],
            'key' => $result['key'],
        ]);
    }

    /**
     * Create a presigned URL for uploading a single part.
     *
     * @return \Cake\Http\Response
     */
    public function signPart(): Response
    {
        $this->getRequest()->allowMethod('post');
        $unauthenticated = $this->unauthenticatedJsonResponse();
        if ($unauthenticated !== null) {
            return $unauthenticated;
        }

        if (!$this->storageAdapter instanceof MultipartUploadAdapterInterface) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 501,
                'message' => __('Multipart uploads are not supported by the current storage driver'),
            ], 501);
        }

        $uploadId = $this->getRequest()->getData('uploadId');
        $key = $this->getRequest()->getData('key');
        $partNumber = $this->getRequest()->getData('partNumber');

        if (
            $uploadId === null || $uploadId === '' ||
            $key === null || $key === '' ||
            $partNumber === null || $partNumber < 1
        ) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('uploadId, key, and partNumber are required'),
            ], 400);
        }

        try {
            $presignedRequest = $this->storageAdapter->createPresignedUploadPart(
                (string)$key,
                (string)$uploadId,
                (int)$partNumber,
            );
        } catch (AwsException $exception) {
            return $this->s3ErrorResponse($exception);
        }

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'url' => (string)$presignedRequest->getUri(),
            'headers' => [],
        ]);
    }

    /**
     * Complete a multipart upload with part ETags.
     *
     * @return \Cake\Http\Response
     */
    public function completeMultipartUpload(): Response
    {
        $this->getRequest()->allowMethod('post');
        $unauthenticated = $this->unauthenticatedJsonResponse();
        if ($unauthenticated !== null) {
            return $unauthenticated;
        }

        if (!$this->storageAdapter instanceof MultipartUploadAdapterInterface) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 501,
                'message' => __('Multipart uploads are not supported by the current storage driver'),
            ], 501);
        }

        $uploadId = $this->getRequest()->getData('uploadId');
        $key = $this->getRequest()->getData('key');
        $parts = $this->getRequest()->getData('parts');

        if ($uploadId === null || $uploadId === '' || $key === null || $key === '' || !is_array($parts)) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('uploadId, key, and parts are required'),
            ], 400);
        }

        // Validate that all parts have PartNumber and ETag
        foreach ($parts as $part) {
            if (!is_array($part) || !isset($part['PartNumber'], $part['ETag'])) {
                return $this->jsonResponse([
                    'error' => true,
                    'code' => 400,
                    'message' => __('each part must include PartNumber and ETag'),
                ], 400);
            }
        }

        try {
            $result = $this->storageAdapter->completeMultipartUpload(
                (string)$key,
                (string)$uploadId,
                $parts,
            );
        } catch (AwsException $exception) {
            return $this->s3ErrorResponse($exception);
        }

        // Register the completed key so the follow-up save() accepts it as a
        // server-issued path (one-time token, consumed by save()).
        $this->rememberPendingUpload((string)$key);

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'location' => $result['location'],
            'key' => (string)$key,
        ]);
    }

    /**
     * Abort an in-progress multipart upload.
     *
     * @return \Cake\Http\Response
     */
    public function abortMultipartUpload(): Response
    {
        $this->getRequest()->allowMethod('post');
        $unauthenticated = $this->unauthenticatedJsonResponse();
        if ($unauthenticated !== null) {
            return $unauthenticated;
        }

        if (!$this->storageAdapter instanceof MultipartUploadAdapterInterface) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 501,
                'message' => __('Multipart uploads are not supported by the current storage driver'),
            ], 501);
        }

        $uploadId = $this->getRequest()->getData('uploadId');
        $key = $this->getRequest()->getData('key');

        if ($uploadId === null || $uploadId === '' || $key === null || $key === '') {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('uploadId and key are required'),
            ], 400);
        }

        try {
            $this->storageAdapter->abortMultipartUpload(
                (string)$key,
                (string)$uploadId,
            );
        } catch (AwsException $exception) {
            return $this->s3ErrorResponse($exception);
        }

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
        ]);
    }

    /**
     * Register a server-issued storage key as a pending upload for this session.
     *
     * `save()` only accepts paths issued by the server in the same session and
     * consumes them one-time. Both `sign()` and `completeMultipartUpload()`
     * register their keys here.
     *
     * @param string $key Storage key issued by the server.
     * @return void
     */
    private function rememberPendingUpload(string $key): void
    {
        $session = $this->getRequest()->getSession();
        $pending = $session->read('Uppy.pendingUploads', []);
        $pending[$key] = time();
        $session->write('Uppy.pendingUploads', $pending);
    }

    /**
     * Parse and validate a new upload request payload.
     *
     * @return array{key: string, contentType: string}
     */
    private function parseUploadRequest(): array
    {
        $filename = $this->getRequest()->getData('filename');
        if ($filename === null || $filename === '') {
            throw new InvalidArgumentException(__('filename is required'));
        }

        $contentType = (string)$this->getRequest()->getData('contentType');
        $this->assertAcceptedContentType($contentType);

        $filesize = $this->getRequest()->getData('filesize');
        if ($filesize !== null && $filesize !== '') {
            $this->assertMaxFileSize((int)$filesize);
        }

        $prefix = $this->getRequest()->getData('prefix');

        return [
            'key' => $this->buildStorageKey((string)$filename, $prefix ? (string)$prefix : null),
            'contentType' => $contentType,
        ];
    }

    /**
     * Return a JSON response with a consistent envelope.
     *
     * @param array<string, mixed> $payload Response body.
     * @param int $status HTTP status code.
     * @return \Cake\Http\Response
     */
    private function jsonResponse(array $payload, int $status = 200): Response
    {
        return $this->getResponse()
            ->withStatus($status)
            ->withHeader('content-type', 'application/json')
            ->withStringBody((string)json_encode($payload));
    }

    /**
     * Return a JSON error response for upstream S3 failures.
     *
     * @param \Aws\Exception\AwsException $exception AWS SDK exception.
     * @return \Cake\Http\Response
     */
    private function s3ErrorResponse(AwsException $exception): Response
    {
        $message = $exception->getAwsErrorMessage() ?? $exception->getMessage();

        return $this->jsonResponse([
            'error' => true,
            'code' => 502,
            'message' => $message,
        ], 502);
    }

    /**
     * Build a storage key with UUID prefix and optional path prefix.
     *
     * @param string $filename Original filename.
     * @param string|null $prefix Optional path prefix.
     * @return string Storage key.
     */
    private function buildStorageKey(string $filename, ?string $prefix = null): string
    {
        $key = Text::uuid() . '-' . Text::slug($filename);

        if ($prefix !== null && $prefix !== '') {
            $key = trim($prefix, '/') . '/' . $key;
        }

        return $key;
    }

    /**
     * Assert that a content type is in the accepted list.
     *
     * @param string $contentType MIME type to validate.
     * @return void
     * @throws \InvalidArgumentException If content type is not accepted.
     */
    private function assertAcceptedContentType(string $contentType): void
    {
        $accepted = (array)Configure::read('Uppy.AcceptedContentTypes', []);

        if (!in_array($contentType, $accepted, true)) {
            throw new InvalidArgumentException(
                __('contentType {0} is not valid', $contentType),
            );
        }
    }

    /**
     * Assert that a file size does not exceed the configured maximum.
     *
     * @param int $filesize File size in bytes.
     * @return void
     * @throws \InvalidArgumentException If file size exceeds maximum.
     */
    private function assertMaxFileSize(int $filesize): void
    {
        $maxFileSize = Configure::read('Uppy.MaxFileSize');

        if ($maxFileSize !== null && $filesize > $maxFileSize) {
            throw new InvalidArgumentException(
                __('File size {0} exceeds maximum allowed size {1}', $filesize, $maxFileSize),
            );
        }
    }
}
