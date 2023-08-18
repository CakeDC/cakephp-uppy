<?php
declare(strict_types=1);

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
use CakeDC\Uppy\Util\S3Trait;

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
 * @method \CakeDC\Uppy\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \CakeDC\Uppy\Model\Entity\File[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
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
     * @param \CakeDC\Uppy\Model\Entity\File $entity The entity that is going to be saved
     * @param \ArrayObject $options options
     * @return void
     */
    public function afterDelete(EventInterface $event, File $entity, ArrayObject $options): void
    {
        if (Configure::read('Uppy.Props.deleteFileS3')) {
            $this->deleteObject($entity->path, $entity->filename);
        }
    }

    /**
     * Finder method to retrieve query with filter applied
     *
     * @param \Cake\ORM\Query\SelectQuery $query default query
     * @param int|string $patient_id
     * @param array|null $q
     * @param string|null $from_date
     * @param string|null $to_date
     * @return \Cake\ORM\Query\SelectQuery $query wih applied filters
     */
    public function findDatatable(
        SelectQuery $query,
        int|string $patient_id,
        ?array $q = [],
        ?string $from_date = null,
        ?string $to_date = null
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
                'datetime'
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
                    $row['signedUrl'] = $this->presignedUrl($file->path, $file->filename);
                    $row['filesize'] = Number::toReadableSize($file->filesize);
                    $row['created'] = $file->created->i18nFormat('yyyy-MM-dd');
                    $row['id'] = $file->id;

                    return $row;
                })
            );
    }
}
