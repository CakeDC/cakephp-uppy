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

        $useFileInput = ($options['ui'] ?? '') === 'fileInput';

        if ($useFileInput) {
            $this->assetsFileInput($jsUrl, $options);
            return;
        }

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
     * Load Uppy for fileInput mode: no Dashboard, expose createFileInput() to bind existing file input to Uppy + AwsS3.
     *
     * @param string $jsUrl Uppy bundle URL
     * @param array $options
     * @return void
     */
    protected function assetsFileInput(string $jsUrl, array $options): void
    {
        $signUrl = \Cake\Routing\Router::url(['prefix' => false, 'plugin' => 'CakeDC/Uppy', 'controller' => 'Files', 'action' => 'sign']);
        $csrfToken = json_encode($this->getView()->getRequest()->getAttribute('csrfToken'));
        $maxFiles = isset($options['multiple']) && $options['multiple'] ? 0 : 1;
        $maxFilesJson = json_encode($maxFiles);

        $script = <<<JS
            import * as Uppy from '$jsUrl';
            window.Uppy = Uppy;
            window.uppySignUrl = "$signUrl";
            window.uppyCsrfToken = $csrfToken;
            window.UppyHelper = {
                createFileInput(opts) {
                    const signUrl = opts.signUrl || window.uppySignUrl;
                    const csrfToken = opts.csrfToken != null ? opts.csrfToken : window.uppyCsrfToken;
                    const maxFiles = opts.maxFiles != null ? opts.maxFiles : $maxFilesJson;
                    const uppy = new Uppy.Uppy({
                        debug: false,
                        autoProceed: true,
                        restrictions: maxFiles ? { maxNumberOfFiles: maxFiles } : {}
                    });
                    uppy.use(Uppy.AwsS3, {
                        getUploadParameters(file) {
                            const body = JSON.stringify({ filename: file.name, contentType: file.type });
                            return fetch(signUrl, {
                                method: 'post',
                                credentials: 'same-origin',
                                headers: {
                                    accept: 'application/json',
                                    'content-type': 'application/json',
                                    'X-CSRF-Token': csrfToken
                                },
                                body: body
                            })
                                .then(r => r.json())
                                .then(data => {
                                    if (data.error || (data.code != 200 && data.message)) return false;
                                    return { method: data.method, url: data.url, fields: data.fields, headers: data.headers };
                                });
                        }
                    });
                    return uppy;
                }
            };
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
