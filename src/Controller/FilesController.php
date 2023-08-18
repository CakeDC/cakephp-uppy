<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Controller;

use Cake\Core\Configure;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\Paging\Exception\PageOutOfBoundsException;
use Cake\Http\Response;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use CakeDC\Uppy\Util\S3Trait;

/**
 * Files Controller
 *
 * @method \CakeDC\Uppy\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 *
 * @property \CakeDC\Uppy\Model\Table\FilesTable $Files
 */
class FilesController extends AppController
{
    use S3Trait;

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
        $this->FormProtection->setConfig('unlockedActions', ['sign','save']);
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
    public function view($id): ?Response
    {
        /** @var \CakeDC\Uppy\Model\Entity\File $file */
        $file = $this->Files->get($id);

        $presignedUrl = $this->presignedUrl($file->path, $file->filename);

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

        $items = $this->getRequest()->getData('items');

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
            } catch (MissingTableClassException) {
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
            } catch (RecordNotFoundException) {
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
    public function delete($id = null): ?Response
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
     * Generate preasigned url and method and return the same body with firmed url to upload from front to S3 directly
     *
     * @return \Cake\Http\Response|null Redirects on successful add, renders view otherwise.
     */
    public function sign(): ?Response
    {
        $this->getRequest()->allowMethod('post');

        if ($this->getRequest()->getData('filename') === null) {
            throw new PageOutOfBoundsException(__('filename is required'));
        }
        $filename = Text::uuid() . '-' . Text::slug($this->getRequest()->getData('filename'));

        $contentType = $this->getRequest()->getData('contentType');
        if (!in_array($contentType, Configure::read('Uppy.AcceptedContentTypes'))) {
            throw new PageOutOfBoundsException(__('contenType {0} is not valid', $contentType));
        }

        $presignedRequest = $this->createPresignedRequest($filename, $contentType);

        return $this->getResponse()
            ->withHeader('content-type', 'application/json')
            ->withStringBody(json_encode([
                'error' => false,
                'code' => 200,
                'method' => $presignedRequest->getMethod(),
                'url' => (string)$presignedRequest->getUri(),
                'fields' => [],
                // Also set the content-type header on the request, to make sure that it is the same as the one we used to generate the signature.
                // Else, the browser picks a content-type as it sees fit.
                'headers' => [
                    'content-type' => $contentType,
                ],
            ]));
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
}
