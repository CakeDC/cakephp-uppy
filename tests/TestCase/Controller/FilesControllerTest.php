<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * CakeDC\Uppy\Controller\FilesController Test Case
 *
 * @uses \CakeDC\Uppy\Controller\FilesController
 */
class FilesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
        'plugin.UppyManager.Files',
    ];

    /**
     * Test index method
     *
     * @return void
     * @uses \CakeDC\Uppy\Controller\FilesController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \CakeDC\Uppy\Controller\FilesController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \CakeDC\Uppy\Controller\FilesController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \CakeDC\Uppy\Controller\FilesController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \CakeDC\Uppy\Controller\FilesController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
