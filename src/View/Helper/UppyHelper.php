<?php
declare(strict_types=1);

namespace CakeDC\Uppy\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;

/**
 * Uppy helper
 */
class UppyHelper extends Helper
{
    public $helpers = ['Html', 'Form'];
    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    protected $_defaultConfig = [
        'uppy' => [
            'version' => '5.2.0',
            'css' => 'https://releases.transloadit.com/uppy/v{version}/uppy.min.css',
            'js' => 'https://releases.transloadit.com/uppy/v{version}/uppy.min.mjs',
            'css_dev' => 'https://releases.transloadit.com/uppy/v{version}/uppy.css',
            'js_dev' => 'https://releases.transloadit.com/uppy/v{version}/uppy.min.mjs',
        ],
        'dashboard' => [
            'inline' => true,
            'target' => '.Uppy',
        ],
    ];

    /**
     * @param array $options
     * @return void
     */
    public function assets(array $options = []): void
    {
        $config = $this->getConfig('uppy');
        $version = $config['version'];

        if (Configure::read('debug')) {
            $cssUrl = str_replace('{version}', $version, $config['css_dev']);
            $jsUrl = str_replace('{version}', $version, $config['js_dev']);
        } else {
            $cssUrl = str_replace('{version}', $version, $config['css']);
            $jsUrl = str_replace('{version}', $version, $config['js']);
        }

        $this->Html->css($cssUrl, ['block' => 'css']);

        if (isset($options['multiple']) && !$options['multiple']) {
            $options['uppy'] = array_merge($options['uppy'] ?? [], ['restrictions' => ['maxNumberOfFiles' => 1]]);
        }
        $uppyOptions = array_merge(['debug' => Configure::read('debug'), 'autoProceed' => true], $options['uppy'] ?? []);
        $dashboardOptions = array_merge($this->getConfig('dashboard'), $options['dashboard'] ?? []);
        $uppyOptionsJson = json_encode($uppyOptions);
        $dashboardOptionsJson = json_encode($dashboardOptions);

        $script = <<<JS
            import * as Uppy from '$jsUrl';
            window.Uppy = Uppy;
            window.uppy = new Uppy.Uppy($uppyOptionsJson);
            window.uppy.use(Uppy.Dashboard, $dashboardOptionsJson);
        JS;

        $this->Html->scriptBlock($script, ['block' => 'script', 'type' => 'module']);
    }

    /**
     * @param string $fieldName
     * @param array $options
     * @return string
     */
    public function widget(string $fieldName, array $options = []): string
    {
        $script = $this->Html->scriptBlock(sprintf('let csrfToken = %s;', json_encode($this->getView()->getRequest()->getAttribute('csrfToken'))));
        $script .= $this->Html->scriptBlock(sprintf('let signUrl = "%s";', \Cake\Routing\Router::url(['prefix' => false, 'plugin' => 'CakeDC/Uppy', 'controller' => 'Files', 'action' => 'sign'])));
        $dashboardContainer = $this->Html->div('Uppy', '');

        return $dashboardContainer . $script;
    }
}
