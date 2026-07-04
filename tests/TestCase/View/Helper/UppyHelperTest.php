<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\View\Helper;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use CakeDC\Uppy\View\Helper\UppyHelper;

class UppyHelperTest extends TestCase
{
    private UppyHelper $helper;

    protected function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->helper = new UppyHelper($view);

        Configure::write('Uppy.MaxFileSize', 524288000); // 500 MB
        Configure::write('Uppy.MultipartThreshold', 104857600); // 100 MiB
    }

    protected function tearDown(): void
    {
        unset($this->helper);
        Configure::delete('Uppy.MaxFileSize');
        Configure::delete('Uppy.MultipartThreshold');
        parent::tearDown();
    }

    public function testGetUploadConfigReturnsExpectedStructure(): void
    {
        $config = $this->helper->getUploadConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('maxFileSize', $config);
        $this->assertArrayHasKey('multipartThreshold', $config);
    }

    public function testGetUploadConfigReturnsConfiguredValues(): void
    {
        $config = $this->helper->getUploadConfig();

        $this->assertSame(524288000, $config['maxFileSize']);
        $this->assertSame(104857600, $config['multipartThreshold']);
    }

    public function testGetUploadConfigWithNullMaxFileSize(): void
    {
        Configure::write('Uppy.MaxFileSize', null);

        $config = $this->helper->getUploadConfig();

        $this->assertNull($config['maxFileSize']);
        $this->assertSame(104857600, $config['multipartThreshold']);
    }

    public function testGetUploadConfigWithCustomThreshold(): void
    {
        Configure::write('Uppy.MultipartThreshold', 50000000); // 50 MB

        $config = $this->helper->getUploadConfig();

        $this->assertSame(50000000, $config['multipartThreshold']);
    }

    public function testGetUploadConfigUsesDefaultThresholdWhenNotSet(): void
    {
        Configure::delete('Uppy.MultipartThreshold');

        $config = $this->helper->getUploadConfig();

        $this->assertSame(104857600, $config['multipartThreshold']); // Default 100 MiB
    }

    public function testAssetsIncludesCssLink(): void
    {
        $output = $this->helper->assets();

        $this->assertStringContainsString('<link', $output);
        $this->assertStringContainsString('uppy.min.css', $output);
        $this->assertStringContainsString('releases.transloadit.com', $output);
    }

    public function testAssetsIncludesJsScript(): void
    {
        $output = $this->helper->assets();

        $this->assertStringContainsString('<script', $output);
        $this->assertStringContainsString('uppy.min.js', $output);
    }

    public function testAssetsExposesUploadConfigToWindow(): void
    {
        $output = $this->helper->assets();

        $this->assertStringContainsString('window.UppyUploadConfig', $output);
        $this->assertStringContainsString('"maxFileSize":524288000', $output);
        $this->assertStringContainsString('"multipartThreshold":104857600', $output);
    }

    public function testAssetsUsesCustomVersion(): void
    {
        $output = $this->helper->assets(['version' => '4.0.0']);

        $this->assertStringContainsString('/uppy/v4.0.0/', $output);
    }

    public function testAssetsUsesCustomConfigVarName(): void
    {
        $output = $this->helper->assets(['configVarName' => 'MyCustomConfig']);

        $this->assertStringContainsString('window.MyCustomConfig', $output);
        $this->assertStringNotContainsString('window.UppyUploadConfig', $output);
    }

    public function testWidgetIncludesDashboardDiv(): void
    {
        $output = $this->helper->widget();

        $this->assertStringContainsString('<div id="uppy-dashboard">', $output);
    }

    public function testWidgetIncludesHiddenFileInput(): void
    {
        $output = $this->helper->widget();

        $this->assertStringContainsString('<input type="file"', $output);
        $this->assertStringContainsString('name="files[]"', $output);
        $this->assertStringContainsString('multiple', $output);
        $this->assertStringContainsString('display: none', $output);
    }

    public function testWidgetUsesCustomId(): void
    {
        $output = $this->helper->widget(['id' => 'my-uppy']);

        $this->assertStringContainsString('id="my-uppy"', $output);
        $this->assertStringNotContainsString('id="uppy-dashboard"', $output);
    }

    public function testWidgetUsesCustomInputName(): void
    {
        $output = $this->helper->widget(['name' => 'attachments[]']);

        $this->assertStringContainsString('name="attachments[]"', $output);
        $this->assertStringNotContainsString('name="files[]"', $output);
    }
}
