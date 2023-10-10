<?php
declare(strict_types=1);

/**
 * Copyright 2013 - 2023, Cake Development Corporation, Las Vegas, Nevada (702) 425-5085 https://www.cakedc.com
 * Use and restrictions are governed by Section 8.5 of The Professional Services Agreement.
 * Redistribution is prohibited. All Rights Reserved.
 *
 * @copyright Copyright 2013 - 2023, Cake Development Corporation (https://www.cakedc.com) All Rights Reserved.
 */
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
