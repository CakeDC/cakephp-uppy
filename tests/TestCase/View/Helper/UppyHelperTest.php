<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\View\Helper;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use CakeDC\Uppy\View\Helper\UppyHelper;

/**
 * CakeDC\Uppy\View\Helper\UppyHelper Test Case
 */
class UppyHelperTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \CakeDC\Uppy\View\Helper\UppyHelper
     */
    protected $Uppy;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->Uppy = new UppyHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Uppy);
        Configure::write('debug', true);
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function testGetUploadConfig(): void
    {
        Configure::write('Uppy.MaxFileSize', 1073741824);
        Configure::write('Uppy.MultipartThreshold', 104857600);

        $config = $this->Uppy->getUploadConfig();

        $this->assertSame(1073741824, $config['maxFileSize']);
        $this->assertSame(104857600, $config['multipartThreshold']);
    }

    /**
     * Test assets method
     *
     * @return void
     */
    public function testAssets(): void
    {
        Configure::write('debug', true);
        $this->Uppy->assets();
        $result = $this->Uppy->getView()->fetch('css');
        $this->assertStringContainsString('uppy.css', $result);
        $this->assertStringNotContainsString('uppy.min.css', $result);

        $result = $this->Uppy->getView()->fetch('script');
        $this->assertStringContainsString('uppy.min.mjs', $result);
        $this->assertStringContainsString('window.Uppy', $result);
        $this->assertStringContainsString('window.UppyUploadConfig', $result);

        Configure::write('debug', false);
        $this->Uppy->assets();
        $result = $this->Uppy->getView()->fetch('css');
        $this->assertStringContainsString('uppy.min.css', $result);

        $result = $this->Uppy->getView()->fetch('script');
        $this->assertStringContainsString('uppy.min.mjs', $result);
    }

    /**
     * Test widget method
     *
     * @return void
     */
    public function testWidget(): void
    {
        $result = $this->Uppy->widget('my-field');
        $this->assertStringContainsString('name="files[]"', $result);
        $this->assertStringContainsString('<div class="Uppy"></div>', $result);
    }
}
