<?php
declare(strict_types=1);

namespace CakeDC\Uppy\Test\TestCase\Model\Table;

use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use CakeDC\Uppy\Model\Table\FilesTable;

/**
 * CakeDC\Uppy\Model\Table\FilesTable Test Case
 */
class FilesTableTest extends TestCase
{
    /**
     * Test subject
     */
    protected Table|FilesTable $Files;

    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
        'plugin.Uppy.Files',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Files') ? [] : ['className' => FilesTable::class];
        $this->Files = $this->getTableLocator()->get('Files', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Files);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \CakeDC\Uppy\Model\Table\FilesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
