<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Model\Table;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\I18n\FrozenTime;
use Cake\I18n\Number;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use CakeDC\Uppy\Util\S3Trait;

/**
 * Files Model
 *
 * @method \UppyManager\Model\Entity\File newEmptyEntity()
 * @method \UppyManager\Model\Entity\File newEntity(array $data, array $options = [])
 * @method \UppyManager\Model\Entity\File[] newEntities(array $data, array $options = [])
 * @method \UppyManager\Model\Entity\File get($primaryKey, $options = [])
 * @method \UppyManager\Model\Entity\File findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \UppyManager\Model\Entity\File patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \UppyManager\Model\Entity\File[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \UppyManager\Model\Entity\File|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \UppyManager\Model\Entity\File saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \UppyManager\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \UppyManager\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \UppyManager\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \UppyManager\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class FilesTable extends Table
{
    use S3Trait;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable(Configure::read('Uppy.Props.tableFiles'));
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo(Configure::read('Uppy.Props.usersAliasModel'), [
            'foreignKey' => 'user_id',
            'className' => Configure::read('Uppy.Props.usersModel'),
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
                'rule' => function ($value, array $context) {
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
            ->inList('mime_type', Configure::read('Uppy.AcceptedContentTypes'))
            ->maxLength('mime_type', 128)
            ->allowEmptyString('mime_type');

        $validator
            ->scalar('extension')
            ->inList('extension', Configure::read('Uppy.AcceptedExtensions'))
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
        $rules->add($rules->existsIn('user_id', Configure::read('Uppy.Props.usersAliasModel')), ['errorField' => 'user_id']);

        return $rules;
    }

    /**
     * If it's configured prop deleteFileS3 delete file in S3 repository
     *
     * @param \Cake\Event\EventInterface $event The beforeSave event that was fired
     * @param \Cake\Datasource\EntityInterface $entity The entity that is going to be saved
     * @param \ArrayObject $options options
     * @return void
     */
    public function afterDelete(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        if (Configure::read('Uppy.Props.deleteFileS3')) {
            $this->deleteObject($entity->path, $entity->filename);
        }
    }

    /**
     * Finder method to retrieve query with filter applied
     *
     * @param \Cake\ORM\Query $query defult query
     * @param array $options options to filter
     * @return \Cake\ORM\Query $query wih applied filters
     */
    public function findDatatable(Query $query, array $options): Query
    {
        if ($options['q']['value'] ?? false) {
            $query->where(function (QueryExpression $exp, Query $q) use ($options) {
                return $exp->like('filename', "%{$options['q']['value']}%");
            });
        }

        $query->where(function (QueryExpression $exp, Query $q) use ($options) {
            return $exp->eq('user_id', "{$options['patient_id']}");
        });

        if ($options['from_date'] ?? false) {
            $query->where(function (QueryExpression $exp, Query $q) use ($options) {
                return $exp->between(
                    'created',
                    FrozenTime::parse($options['from_date'])->startOfDay(),
                    FrozenTime::parse($options['to_date'])->endOfDay(),
                    'datetime'
                );
            });
        }

        $query
            ->select([
                'id',
                'filename',
                'filesize',
                'extension',
                'path',
                'created',
            ]);

        return $query->formatResults(function (\Cake\Collection\CollectionInterface $results) {
            return $results->map(function ($file) {
                $row['filename'] = $file->filename;
                $row['extension'] = $file->extension;
                $row['filesize'] = Number::toReadableSize($file->filesize);
                $row['created'] = $file->created->i18nFormat('yyyy-MM-dd');
                $row['id'] = $file->id;

                return $row;
            });
        });
    }
}
