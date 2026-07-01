<?php
declare(strict_types=1);

/**
 * Copyright 2023 - 2026, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Controller;

use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\Paging\Exception\PageOutOfBoundsException;
use Cake\Http\Response;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\Utility\Inflector;
use CakeDC\Uppy\Util\S3Trait;

/**
 * Files Controller
 *
 * @method \CakeDC\Uppy\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class FilesController extends AppController
{
    use S3Trait {
        createMultipartUpload as protected s3CreateMultipartUpload;
        completeMultipartUpload as protected s3CompleteMultipartUpload;
        abortMultipartUpload as protected s3AbortMultipartUpload;
    }

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
        if ($this->components()->has('Security')) {
            $this->Security->setConfig('unlockedActions', self::UPLOAD_SIGNING_ACTIONS);
        } elseif ($this->components()->has('FormProtection')) {
            $this->FormProtection->setConfig('unlockedActions', self::UPLOAD_SIGNING_ACTIONS);
        }
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $files = $this->paginate($this->Files);

        $this->set(compact('files'));
    }

    /**
     * View method
     *
     * @param string|null $id File Storage id.
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function view($id)
    {
        $file = $this->Files->get($id, [
            'contain' => [],
        ]);

        $presignedUrl = $this->presignedUrl($file->path, $file->filename);

        $this->redirect($presignedUrl);
    }

    /**
     * Save method
     *
     * Save files data received in database, asign default model configured, if not foreign_key revceived assign first object in table
     *
     * @return void
     */
    public function save()
    {
        $this->request->allowMethod('post');

        $items = $this->request->getData('items');

        $files = [];
        foreach ($items as $item) {
            if (!isset($item['model'])) {
                $result['error'] = true;
                $result['message'] = __('model is required');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            try {
                $relationTable = $this->fetchTable($item['model']);
            } catch (MissingTableClassException $e) {
                $result['error'] = true;
                $result['message'] = __('there is no table {0} to associate the file', $item['model']);
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            if (!isset($item['foreign_key'])) {
                $result['error'] = true;
                $result['message'] = __('foreign key is required');
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            try {
                $register = $relationTable->get($item['foreign_key']);
            } catch (RecordNotFoundException $e) {
                $result['error'] = true;
                $result['message'] = __('there is no record {0} to associate the file', $item['foreign_key']);
                $this->set('result', $result);
                $this->viewBuilder()->setOption('serialize', ['result']);

                return;
            }
            $file = $this->Files->newEntity($item);
            $file->filename = $item['filename'];
            $file->filesize = $item['filesize'];
            $file->extension = $item['extension'];
            $model = Configure::read('Uppy.Props.usersModel');
            $relation_key = Inflector::singularize(mb_strtolower($model)) . '_id';
            $file->user_id = $register->{$relation_key};
            $file->model = $item['model'];
            $files[] = $file;
        }

        if ($this->Files->saveMany($files)) {
            $result['error'] = false;
            $result['message'] = __('The association has been be saved correctly');
        } else {
            $result['error'] = true;
            $result['message'] = __('The association to file could not be saved');
            $result['entities'] = $files;
        }

        $this->viewBuilder()->setClassName('Json');
        $this->set('result', $result);
        $this->viewBuilder()->setOption('serialize', ['result']);
    }

    /**
     * Test method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function drag()
    {
        $this->request->allowMethod('get');

        $file = $this->Files->newEmptyEntity();

        $this->set(compact('file'));
    }

    /**
     * Delete method
     *
     * @param string|null $id File id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $file = $this->Files->get($id, [
            'contain' => [],
        ]);

        $this->request->allowMethod(['post', 'delete']);
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
     * Generate preasigned url and method and return the same body with firmed url to upload from front to S3 directly
     *
     * @return \Cake\Http\Response
     */
    public function sign(): Response
    {
        $this->request->allowMethod('post');

        if ($this->request->getData('filename') === null) {
            throw new PageOutOfBoundsException(__('filename is required'));
        }

        $contentType = (string)$this->request->getData('contentType');
        try {
            $this->assertAcceptedContentType($contentType);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => $exception->getMessage(),
            ], 400);
        }

        $filesize = $this->request->getData('filesize');
        if ($filesize !== null && $filesize !== '') {
            try {
                $this->assertMaxFileSize((int)$filesize);
            } catch (\InvalidArgumentException $exception) {
                return $this->jsonResponse([
                    'error' => true,
                    'code' => 400,
                    'message' => $exception->getMessage(),
                ], 400);
            }
        }

        $storageKey = $this->buildStorageKey(
            (string)$this->request->getData('filename'),
            $this->request->getData('prefix') ? (string)$this->request->getData('prefix') : null
        );

        $presignedRequest = $this->createPresignedRequest($storageKey, $contentType);

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
     * Start a multipart upload and return upload id and object key.
     *
     * @return \Cake\Http\Response
     */
    public function createMultipartUpload(): Response
    {
        $this->request->allowMethod('post');

        try {
            $upload = $this->parseUploadRequest();
            $result = $this->s3CreateMultipartUpload($upload['key'], $upload['contentType']);
        } catch (\InvalidArgumentException $exception) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => $exception->getMessage(),
            ], 400);
        }

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'uploadId' => $result['uploadId'],
            'key' => $result['key'],
        ]);
    }

    /**
     * Presign a single multipart upload part.
     *
     * @return \Cake\Http\Response
     */
    public function signPart(): Response
    {
        $this->request->allowMethod('post');

        $uploadId = $this->request->getData('uploadId');
        $key = $this->request->getData('key');
        $partNumber = $this->request->getData('partNumber');

        if (empty($uploadId) || empty($key)) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('uploadId and key are required'),
            ], 400);
        }

        $partNumber = (int)$partNumber;
        if ($partNumber < 1) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('partNumber must be greater than zero'),
            ], 400);
        }

        $presignedRequest = $this->createPresignedUploadPart((string)$key, (string)$uploadId, $partNumber);

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'url' => (string)$presignedRequest->getUri(),
            'headers' => [],
        ]);
    }

    /**
     * Complete a multipart upload.
     *
     * @return \Cake\Http\Response
     */
    public function completeMultipartUpload(): Response
    {
        $this->request->allowMethod('post');

        $uploadId = $this->request->getData('uploadId');
        $key = $this->request->getData('key');
        $parts = $this->request->getData('parts');

        if (empty($uploadId) || empty($key)) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('uploadId and key are required'),
            ], 400);
        }

        if (!is_array($parts) || $parts === []) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('parts are required'),
            ], 400);
        }

        foreach ($parts as $part) {
            if (!is_array($part) || !isset($part['PartNumber'], $part['ETag'])) {
                return $this->jsonResponse([
                    'error' => true,
                    'code' => 400,
                    'message' => __('each part must include PartNumber and ETag'),
                ], 400);
            }
        }

        $result = $this->s3CompleteMultipartUpload((string)$key, (string)$uploadId, $parts);

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
            'location' => $result['location'],
            'key' => $key,
        ]);
    }

    /**
     * Abort a multipart upload.
     *
     * @return \Cake\Http\Response
     */
    public function abortMultipartUpload(): Response
    {
        $this->request->allowMethod('post');

        $uploadId = $this->request->getData('uploadId');
        $key = $this->request->getData('key');

        if (empty($uploadId) || empty($key)) {
            return $this->jsonResponse([
                'error' => true,
                'code' => 400,
                'message' => __('uploadId and key are required'),
            ], 400);
        }

        $this->s3AbortMultipartUpload((string)$key, (string)$uploadId);

        return $this->jsonResponse([
            'error' => false,
            'code' => 200,
        ]);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->request->allowMethod('get');

        $file = $this->Files->newEmptyEntity();

        $this->set(compact('file'));
    }

    /**
     * Parse and validate a new upload request payload.
     *
     * @return array{key: string, contentType: string}
     */
    private function parseUploadRequest(): array
    {
        $filename = $this->request->getData('filename');
        if ($filename === null || $filename === '') {
            throw new \InvalidArgumentException(__('filename is required'));
        }

        $contentType = (string)$this->request->getData('contentType');
        $this->assertAcceptedContentType($contentType);

        $filesize = $this->request->getData('filesize');
        if ($filesize !== null && $filesize !== '') {
            $this->assertMaxFileSize((int)$filesize);
        }

        $prefix = $this->request->getData('prefix');

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
        return $this->response
            ->withStatus($status)
            ->withHeader('content-type', 'application/json')
            ->withStringBody((string)json_encode($payload));
    }
}
