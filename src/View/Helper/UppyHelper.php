<?php
declare(strict_types=1);

/**
 * Copyright 2024 - 2026, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2024 - 2026, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\View\Helper;

use Cake\Core\Configure;
use Cake\View\Helper;

/**
 * Uppy Helper
 *
 * Provides methods for integrating Uppy file uploader into CakePHP views.
 */
class UppyHelper extends Helper
{
    /**
     * Load Uppy assets (CSS and JS) from CDN and expose upload configuration.
     *
     * @param array<string, mixed> $options Configuration options for Uppy assets.
     * @return string HTML script and link tags for Uppy assets.
     */
    public function assets(array $options = []): string
    {
        $version = $options['version'] ?? '5.0.0';
        $configVarName = $options['configVarName'] ?? 'UppyUploadConfig';

        $uploadConfig = $this->getUploadConfig();
        $configJson = json_encode($uploadConfig);

        $html = [];

        // CSS
        $html[] = sprintf(
            '<link href="https://releases.transloadit.com/uppy/v%s/uppy.min.css" rel="stylesheet">',
            $version,
        );

        // Expose upload config to window
        $html[] = sprintf(
            '<script>window.%s = %s;</script>',
            $configVarName,
            $configJson,
        );

        // JS
        $html[] = sprintf(
            '<script src="https://releases.transloadit.com/uppy/v%s/uppy.min.js"></script>',
            $version,
        );

        return implode("\n", $html);
    }

    /**
     * Render the Uppy widget (file input and dashboard container).
     *
     * @param array<string, mixed> $options Widget configuration options.
     * @return string HTML for Uppy widget.
     */
    public function widget(array $options = []): string
    {
        $id = $options['id'] ?? 'uppy-dashboard';
        $inputName = $options['name'] ?? 'files[]';

        $html = [];

        // Dashboard container
        $html[] = sprintf('<div id="%s"></div>', $id);

        // Hidden file input
        $html[] = sprintf(
            '<input type="file" name="%s" multiple style="display: none;" />',
            $inputName,
        );

        return implode("\n", $html);
    }

    /**
     * Get upload configuration for client-side use.
     *
     * @return array{maxFileSize: int|null, multipartThreshold: int} Upload configuration.
     */
    public function getUploadConfig(): array
    {
        return [
            'maxFileSize' => Configure::read('Uppy.MaxFileSize'),
            'multipartThreshold' => (int)Configure::read('Uppy.MultipartThreshold', 104857600),
        ];
    }
}
