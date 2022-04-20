<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Controller;

use Cake\Core\Configure;
use Cake\Datasource\Exception\PageOutOfBoundsException;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\Exception\MissingTableClassException;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use CakeDC\Uppy\Util\S3Trait;

/**
 * Files Controller
 *
 * @method \UppyManager\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
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
        $this->Security->setConfig('unlockedActions', ['sign','save']);
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
        }

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
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function sign()
    {
        $this->request->allowMethod('post');

        if ($this->request->getData('filename') === null) {
            throw new PageOutOfBoundsException(__('filename is required'));
        }
        $filename = Text::uuid() . '-' . Text::slug($this->request->getData('filename'));

        $contentType = $this->request->getData('contentType');
        if (!in_array($contentType, Configure::read('Uppy.AcceptedContentTypes'))) {
            throw new PageOutOfBoundsException(__('contenType {0} is not valid', $contentType));
        }

        $presignedRequest = $this->createPresignedRequest($filename, $contentType);

        return $this->response
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
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->request->allowMethod('get');

        $file = $this->Files->newEmptyEntity();

        $this->set(compact('file'));
    }
}