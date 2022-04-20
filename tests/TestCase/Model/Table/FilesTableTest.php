<?php
declare(strict_types=1);

namespace UppyManager\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use UppyManager\Model\Table\FilesTable;

/**
 * UppyManager\Model\Table\FilesTable Test Case
 */
class FilesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \UppyManager\Model\Table\FilesTable
     */
    protected $Files;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'plugin.UppyManager.Files',
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
     * @uses \UppyManager\Model\Table\FilesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
