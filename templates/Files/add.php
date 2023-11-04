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
 * @var \App\View\AppView $this
 * @var \CakeDC\Uppy\Model\Entity\File $file
 */
use Cake\Core\Configure;
?>
<div class="row content">
    <div class="column-responsive column-80">
        <h2><?= __('Example add file')?></h2>
        <?php if (
            !Configure::read('Uppy.S3.config.credentials.key') ||
            !Configure::read('Uppy.S3.config.credentials.secret')
) : ?>
            <div class="file form">
                <?= __('You need configure S3 Credentials in config/uppy.php') ?>
            </div>
        <?php else : ?>
            <div class="file form">
                <?= $this->Form->create($file) ?>
                <?= $this->Form->control('model', ['type' => 'text', 'name' => 'model']) ?>
                <?= $this->Form->control('foreign_key', ['type' => 'text', 'name' => 'foreign_key']) ?>
                <div class="Uppy">
                    <?= $this->Form->control('files', [
                        'type' => 'file',
                        'name' => 'files[]',
                        'multiple' => 'multiple',
                    ]) ?>
                </div>
                <?= $this->Form->end() ?>
                <div class="UppyProgressBar"></div>
                <div class="uploaded-files">
                    <h5><?= __('Uploaded files:') ?></h5>
                    <ol></ol>
                </div>
                <div>
                    <h5><?= __('Response:') ?></h5>
                    <p class="uploaded-response"></p>
                </div>
            </div>
            <?= $this->element('CakeDC/Uppy.assets') ?>
        <?php endif; ?>
    </div>
</div>
