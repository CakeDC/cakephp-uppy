<?php
declare(strict_types=1);

/**
 * Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Model\Table;

use ArrayObject;
use Cake\Collection\CollectionInterface;
use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Event\EventInterface;
use Cake\I18n\DateTime;
use Cake\I18n\Number;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use CakeDC\Uppy\Model\Entity\File;
use CakeDC\Uppy\Storage\AdapterFactory;
use CakeDC\Uppy\Storage\StorageAdapterInterface;
use function Cake\I18n\__;

/**
 * Files Model
 *
 * @method \CakeDC\Uppy\Model\Entity\File newEmptyEntity()
 * @method \CakeDC\Uppy\Model\Entity\File newEntity(array $data, array $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File[] newEntities(array $data, array $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class FilesTable extends Table
{
    private StorageAdapterInterface $storageAdapter;

    /**
     * @return \CakeDC\Uppy\Storage\StorageAdapterInterface
     */
    public function getStorageAdapter(): StorageAdapterInterface
    {
        if (!isset($this->storageAdapter)) {
            $this->storageAdapter = AdapterFactory::create();
        }

        return $this->storageAdapter;
    }

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable(Configure::readOrFail('Uppy.Props.tableFiles'));
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo(Configure::readOrFail('Uppy.Props.usersAliasModel'), [
            'foreignKey' => 'user_id',
            'className' => Configure::readOrFail('Uppy.Props.usersModel'),
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->uuid('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('model')
            ->maxLength('model', 128)
            ->allowEmptyString('model');

        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->add('filename', 'validFilename', [
                'rule' => function ($value) {
                    if (strcmp(basename($value), $value) === 0) {
                        return true;
                    }

                    return __('filename value is not a valid name.');
                }]);
        $validator
            ->integer('filesize')
            ->allowEmptyFile('filesize');

        $validator
            ->scalar('mime_type')
            ->inList('mime_type', Configure::read('Uppy.AcceptedContentTypes', []))
            ->maxLength('mime_type', 128)
            ->allowEmptyString('mime_type');

        $validator
            ->scalar('extension')
            ->inList('extension', Configure::read('Uppy.AcceptedExtensions', []))
            ->maxLength('extension', 32)
            ->allowEmptyString('extension');

        $validator
            ->scalar('hash')
            ->maxLength('hash', 64)
            ->allowEmptyString('hash');

        $validator
            ->scalar('path')
            ->maxLength('path', 255)
            ->allowEmptyString('path');

        $validator
            ->scalar('adapter')
            ->maxLength('adapter', 32)
            ->allowEmptyString('adapter');

        $validator
            ->scalar('metadata')
            ->allowEmptyString('metadata');

        $validator
            ->integer('foreign_key')
            ->requirePresence('foreign_key', 'create')
            ->notEmptyString('foreign_key');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            $rules->existsIn(
                'user_id',
                Configure::readOrFail('Uppy.Props.usersAliasModel'),
            ),
            ['errorField' => 'user_id'],
        );

        return $rules;
    }

    /**
     * If it's configured prop deleteFileStorage delete file from storage
     *
     * @param \Cake\Event\EventInterface $event The beforeSave event that was fired
     * @param \CakeDC\Uppy\Model\Entity\File $entity The entity that is going to be saved
     * @param \ArrayObject $options options
     * @return void
     */
    public function afterDelete(EventInterface $event, File $entity, ArrayObject $options): void
    {
        $shouldDelete = Configure::read(
            'Uppy.Props.deleteFileStorage',
            Configure::read('Uppy.Props.deleteFileS3', false),
        );
        if ($shouldDelete) {
            $this->getStorageAdapter()->deleteObject($entity->path);
        }
    }

    /**
     * Finder method to retrieve query with filter applied
     *
     * @param \Cake\ORM\Query\SelectQuery $query default query
     * @param string|int $patient_id
     * @param array $q
     * @param string|null $from_date
     * @param string|null $to_date
     * @return \Cake\ORM\Query\SelectQuery $query wih applied filters
     */
    public function findDatatable(
        SelectQuery $query,
        int|string $patient_id,
        array $q = [],
        ?string $from_date = null,
        ?string $to_date = null,
    ): SelectQuery {
        if ($q['value'] ?? false) {
            $query->where(fn(QueryExpression $exp): QueryExpression => $exp
                ->like($this->aliasField('filename'), "%{$q['value']}%"));
        }

        $query->where(fn(QueryExpression $exp): QueryExpression => $exp
            ->eq($this->aliasField('user_id'), $patient_id));

        if ($from_date && $to_date) {
            $query->where(fn(QueryExpression $exp): QueryExpression => $exp->between(
                $this->aliasField('created'),
                DateTime::parse($from_date)->startOfDay(),
                DateTime::parse($to_date)->endOfDay(),
                'datetime',
            ));
        }

        return $query
            ->select([
                'id',
                'filename',
                'filesize',
                'extension',
                'path',
                'created',
            ])
            ->formatResults(fn(CollectionInterface $results): CollectionInterface => $results
                ->map(function (File $file): array {
                    $row = [];
                    $row['filename'] = $file->filename;
                    $row['extension'] = $file->extension;
                    $row['signedUrl'] = $this->getStorageAdapter()->presignedUrl($file->path);
                    $row['filesize'] = Number::toReadableSize($file->filesize ?? 0);
                    $row['created'] = $file->created?->i18nFormat('yyyy-MM-dd');
                    $row['id'] = $file->id;

                    return $row;
                }));
    }
}
