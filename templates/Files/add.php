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
?>
<div class="row content">
    <div class="column-responsive column-80">
        <h2><?php echo __('Example add file');?></h2>
        <?php if (!Configure::read('Uppy.S3.config.credentials.key') || !Configure::read('Uppy.S3.config.credentials.secret')) : ?>
            <div class="file form">
                <?php echo __('You need configure S3 Credentials in config/uppy.php'); ?>
            </div>
        <?php else : ?>
            <div class="file form">
                <?php echo $this->Form->create($file); ?>
                <?php echo $this->Form->control('model', ['type' => 'text', 'name' => 'model']); ?>
                <?php echo $this->Form->control('foreign_key', ['type' => 'text', 'name' => 'foreign_key']); ?>
                <div class="Uppy">
                    <?php echo $this->Form->control('files', ['type' => 'file', 'name' => 'files[]', 'multiple' => 'multiple']); ?>
                </div>
                <?php echo $this->Form->end(); ?>
                <div class="UppyProgressBar"></div>
                <div class="uploaded-files">
                    <h5><?php echo __('Uploaded files:');?></h5>
                    <ol></ol>
                </div>
                <div>
                    <h5><?php echo __('Response:');?></h5>
                    <p class="uploaded-response"></p>
                </div>
            </div>

            <?php
            echo $this->Html->scriptBlock(sprintf('let debug = %s;', (Configure::read('debug')=='1')?"true":"false"));
            echo $this->Html->scriptBlock(sprintf('let csrfToken = %s;', json_encode($this->request->getAttribute('csrfToken'))));
            echo $this->Html->scriptBlock(sprintf('let signUrl = "%s";', Router::url(['prefix' => false, 'plugin' => 'CakeDC/Uppy', 'controller' => 'Files', 'action' => 'sign'])));
            echo $this->Html->scriptBlock(sprintf('let saveUrl = "%s";', Router::url(['prefix' => false, 'plugin' => 'CakeDC/Uppy', 'controller' => 'Files', 'action' => 'save'])));
            echo $this->Html->scriptBlock(sprintf('let file_not_saved = "%s";', __('The file could not be saved. Please, try again.')));
            ?>

            <?php $this->start('css'); ?>
                <?php echo $this->Html->css('CakeDC/Uppy.uppy.min.css'); ?>
            <?php $this->end(); ?>

            <?php $this->start('script'); ?>
                <?php echo $this->Html->script('CakeDC/Uppy.uppy.min.js'); ?>
            <?php $this->end(); ?>

            <?php $this->start('bottom_script'); ?>
                <?php echo $this->Html->script('CakeDC/Uppy.add.js'); ?>
            <?php $this->end(); ?>

        <?php endif; ?>

    </div>
</div>


