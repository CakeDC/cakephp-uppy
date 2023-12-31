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
namespace CakeDC\Uppy\Model\Entity;

use Cake\ORM\Entity;

/**
 * File Entity
 *
 * @property string $id
 * @property string|null $user_id
 * @property string|null $model
 * @property string|null $filename
 * @property int|null $filesize
 * @property string|null $mime_type
 * @property string|null $extension
 * @property string|null $hash
 * @property string|null $path
 * @property string|null $adapter
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property string|null $metadata
 * @property int $foreign_key
 *
 * @property \Cake\Datasource\EntityInterface $user
 */
class File extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'user_id' => false,
        'model' => false,
        'filename' => false,
        'filesize' => false,
        'mime_type' => true,
        'extension' => true,
        'hash' => true,
        'path' => true,
        'adapter' => true,
        'created' => true,
        'modified' => true,
        'metadata' => true,
        'foreign_key' => true,
        'user' => true,
    ];
}
