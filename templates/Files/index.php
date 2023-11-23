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
 * @var array<\CakeDC\Uppy\Model\Entity\File> $files
 */
?>
<div class="files index content">
    <?= $this->Html->link(__('Example Add New File'), ['action' => 'add'], ['class' => 'button']) ?>
    <?= $this->Html->link(__('Example Drag & Drop New File'), ['action' => 'drag'], ['class' => 'button']) ?>
    <h3><?= __('Files') ?></h3>
    <div class="table-responsive">
        <table class="table align-middle gs-0 gy-5">
            <thead>
                <tr>
                    <th><?= $this->Paginator->sort('filename') ?></th>
                    <th><?= $this->Paginator->sort('filesize') ?></th>
                    <th><?= $this->Paginator->sort('extension') ?></th>
                    <th><?= $this->Paginator->sort('created') ?></th>
                    <th class="actions"><?= __('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file) : ?>
                <tr>
                    <td><?= h($file->filename) ?></td>
                    <td><?= $this->Number->format($file->filesize) ?></td>
                    <td><?= h($file->extension) ?></td>
                    <td><?= h($file->created) ?></td>
                    <td class="actions">
                        <?= $this->Html->link(__('View'), ['action' => 'view', $file->id], ['target' => '_blank']) ?>
                        <?= $this->Form->postLink(
                            __('Delete'),
                            ['action' => 'delete', $file->id],
                            ['confirm' => __('Are you sure you want to delete # {0}?', $file->id)]
                        ) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->first('<< ' . __('first')) ?>
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
            <?= $this->Paginator->last(__('last') . ' >>') ?>
        </ul>
        <p><?= $this->Paginator->counter(
            __('Page {{page}} of {{pages}}, showing {{current}} record(s) out of {{count}} total')
        ) ?></p>
    </div>
</div>
