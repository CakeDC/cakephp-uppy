<?php
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
use Cake\Routing\Router;
use Cake\Utility\Text;
?>
<div class="row content">
    <div class="column-responsive column-80">
        <h2><?php echo __('Example drag file');?></h2>
        <?php if (!Configure::read('Uppy.S3.config.credentials.key') || !Configure::read('Uppy.S3.config.credentials.secret')) : ?>
            <div>
                <?php echo __('You need configure S3 Credentials in config/uppy.php'); ?>
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
                <h5><?php echo __('Response:');?></h5>
                <p class="uploaded-response"></p>
            </p>
            <div id="drag-drop-area"></div>

            <?php
            $this->Uppy->assets([
                'uppy' => [
                    'restrictions' => ['maxNumberOfFiles' => 1],
                    'id' => 'uppyFile',
                ],
                'dashboard' => [
                    'target' => '#drag-drop-area',
                ],
            ]);
            echo $this->Html->scriptBlock(sprintf('let formId = "%s";', $formId));
            echo $this->Html->scriptBlock(sprintf('let csrfToken = %s;', json_encode($this->request->getAttribute('csrfToken'))));
            echo $this->Html->scriptBlock(sprintf('let signUrl = "%s";', Router::url(['prefix' => false, 'plugin' => 'CakeDC/Uppy', 'controller' => 'Files', 'action' => 'sign'])));
            echo $this->Html->scriptBlock(sprintf('let saveUrl = "%s";', Router::url(['prefix' => false, 'plugin' => 'CakeDC/Uppy', 'controller' => 'Files', 'action' => 'save'])));
            echo $this->Html->scriptBlock(sprintf('let file_not_saved = "%s";', __('The file could not be saved. Please, try again.')));
            ?>

            <?php $this->start('bottom_script'); ?>
                <?php echo $this->Html->script('CakeDC/Uppy.drag.js', ['type' => 'module']); ?>
            <?php $this->end(); ?>

        <?php endif; ?>
    </div>
</div>

