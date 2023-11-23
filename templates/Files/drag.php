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
 */
use Cake\Core\Configure;
use Cake\Utility\Text;
?>
<div class="row content">
    <div class="column-responsive column-80">
        <h2><?= __('Example drag file') ?></h2>
        <?php if (
            !Configure::read('Uppy.S3.config.credentials.key') ||
            !Configure::read('Uppy.S3.config.credentials.secret')
) : ?>
            <div>
                <?= __('You need configure S3 Credentials in config/uppy.php') ?>
            </div>
        <?php else : ?>
            <?php
            $formId = 'form-' . Text::uuid();
            $options = [
                'id' => $formId,
            ];
            echo $this->Form->create(null, $options);
            echo $this->Form->control('model', ['type' => 'text', 'name' => 'model']);
            echo $this->Form->control('foreign_key', ['type' => 'text', 'name' => 'foreign_key']);
            echo $this->Form->end();
            ?>
            <p>
                <h5><?= __('Response:') ?></h5>
                <p class="uploaded-response"></p>
            </p>
            <div id="drag-drop-area"></div>
            <?= $this->element('CakeDC/Uppy.assets') ?>
        <?php endif; ?>
    </div>
</div>
