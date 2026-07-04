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
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Http\Response;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use CakeDC\Uppy\Storage\AdapterFactory;
use CakeDC\Uppy\Storage\MultipartUploadAdapterInterface;
use CakeDC\Uppy\Storage\StorageAdapterInterface;
use InvalidArgumentException;
use UnexpectedValueException;
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
        $this->FormProtection->setConfig('unlockedActions', self::UPLOAD_SIGNING_ACTIONS);
    }

    /**
     * Index method
     */
    public function index(): void
    {
        $files = $this->paginate($this->Files);

        $this->set(compact('files'));
    }

    /**
     * View method
     */
    public function view(string|int|null $id = null): ?Response
    {
        /** @var \CakeDC\Uppy\Model\Entity\File $file */
        $file = $this->Files->get($id);

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

        $files = [];
        $result = [];
        foreach ($items as $item) {
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
            }
            $file = $this->Files->newEntity($item);
            $file->filename = $item['filename'];
            $file->filesize = $item['filesize'];
            $file->extension = $item['extension'];
            $model = Configure::readOrFail('Uppy.Props.usersModel');
            $relation_key = Inflector::singularize(mb_strtolower($model)) . '_id';
            $file->user_id = $register->{$relation_key};
            $file->model = $tableAlias;
            $files[] = $file;
        }

        if ($this->Files->saveMany($files) !== false) {
            $result['error'] = false;
            $result['message'] = __('The association has been be saved correctly');
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
