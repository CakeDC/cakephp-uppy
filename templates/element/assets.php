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
?>
<?php
echo $this->Html->scriptBlock(
    sprintf('let debug = %s;', Configure::read('debug') == '1' ? 'true' : 'false')
);
echo $this->Html->scriptBlock(
    sprintf('let csrfToken = %s;', json_encode($this->getRequest()->getAttribute('csrfToken')))
);
echo $this->Html->scriptBlock(
    sprintf(
        'let signUrl = "%s";',
        $this->Url->build([
            'prefix' => false,
            'plugin' => 'CakeDC/Uppy',
            'controller' => 'Files',
            'action' => 'sign',
        ])
    )
);
echo $this->Html->scriptBlock(
    sprintf(
        'let saveUrl = "%s";',
        $this->Url->build([
            'prefix' => false,
            'plugin' => 'CakeDC/Uppy',
            'controller' => 'Files',
            'action' => 'save',
        ])
    )
);
echo $this->Html->scriptBlock(
    sprintf('let file_not_saved = "%s";', __('The file could not be saved. Please, try again.'))
);
?>
<?= $this->Html->css('CakeDC/Uppy.uppy.min.css', ['block' => true]) ?>
<?= $this->Html->script('CakeDC/Uppy.uppy.min.js', ['block' => true]) ?>
<?= $this->Html->script('CakeDC/Uppy.add.js', ['block' => 'bottom_script']);
