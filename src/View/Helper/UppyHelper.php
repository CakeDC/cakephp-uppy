<?php
declare(strict_types=1);

namespace CakeDC\Uppy\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;

/**
 * Uppy helper
 *
 * @property \Cake\View\Helper\HtmlHelper $Html
 * @property \Cake\View\Helper\FormHelper $Form
 */
class UppyHelper extends Helper
{
    /**
     * @var array<int, string>
     */
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
            'target' => null,
        ],
    ];

    /**
     * Load Uppy CSS and expose the Uppy namespace as window.Uppy.
     *
     * Pass `['dashboard' => ['target' => '#my-element']]` to also initialise
     * the Dashboard plugin. Without a target the Dashboard is not loaded,
     * avoiding the "Invalid target" error on file-input–based upload forms.
     *
     * @param array<string, mixed> $options Uppy and Dashboard options.
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

        $uploadConfigJson = json_encode($this->getUploadConfig());
        $configScript = "window.UppyUploadConfig = {$uploadConfigJson};";

        $dashboardTarget = $options['dashboard']['target'] ?? $this->getConfig('dashboard.target') ?? null;

        if ($dashboardTarget) {
            $uppyOptions = array_merge(
                ['debug' => Configure::read('debug'), 'autoProceed' => true],
                $options['uppy'] ?? []
            );
            $dashboardOptions = array_merge($this->getConfig('dashboard'), $options['dashboard'] ?? []);
            $uppyOptionsJson = json_encode($uppyOptions);
            $dashboardOptionsJson = json_encode($dashboardOptions);
            $script = <<<JS
                {$configScript}
                import * as Uppy from '{$jsUrl}';
                window.Uppy = Uppy;
                window.uppy = new Uppy.Uppy({$uppyOptionsJson});
                window.uppy.use(Uppy.Dashboard, {$dashboardOptionsJson});
            JS;
        } else {
            $script = "{$configScript}\nimport * as Uppy from '{$jsUrl}'; window.Uppy = Uppy;";
        }

        $this->Html->scriptBlock($script, ['block' => 'script', 'type' => 'module']);
    }

    /**
     * Render a file input and Dashboard container for Uppy.
     *
     * @param string $fieldName Form field name.
     * @param array<string, mixed> $options Widget options.
     * @return string
     */
    public function widget(string $fieldName, array $options = []): string
    {
        $options['multiple'] = $options['multiple'] ?? false;
        $fileInput = $this->Form->control($fieldName, [
            'type' => 'file',
            'name' => 'files[]',
            'multiple' => $options['multiple'],
        ]);

        $dashboardContainer = $this->Html->div('Uppy', '');

        return $fileInput . $dashboardContainer;
    }

    /**
     * Upload limits and multipart settings for the Uppy AwsS3 client.
     *
     * @return array{maxFileSize: int|null, multipartThreshold: int|null}
     */
    public function getUploadConfig(): array
    {
        $maxFileSize = Configure::read('Uppy.MaxFileSize');
        $multipartThreshold = Configure::read('Uppy.MultipartThreshold');

        return [
            'maxFileSize' => $maxFileSize !== null ? (int)$maxFileSize : null,
            'multipartThreshold' => $multipartThreshold !== null ? (int)$multipartThreshold : null,
        ];
    }
}
