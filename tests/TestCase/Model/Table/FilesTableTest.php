<?php
declare(strict_types=1);

/**
 * Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2023, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\Uppy\Test\TestCase\Model\Table;

use Cake\TestSuite\TestCase;
use Cake\Validation\Validator;
use CakeDC\Uppy\Model\Table\FilesTable;

/**
 * CakeDC\Uppy\Model\Table\FilesTable Test Case
 */
class FilesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \CakeDC\Uppy\Model\Table\FilesTable
     */
    protected $Files;

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->Files = new FilesTable();
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
        $validator = new Validator();
        $this->Files->validationDefault($validator);

        $errors = $validator->validate([
            'id' => '660e8400-e29b-41d4-a716-446655440001',
            'foreign_key' => 1,
            'filename' => 'valid.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ]);
        $this->assertEmpty($errors);

        $errors = $validator->validate([
            'id' => '660e8400-e29b-41d4-a716-446655440002',
            'foreign_key' => 1,
            'filename' => 'bad/path.pdf',
        ]);
        $this->assertArrayHasKey('filename', $errors);
    }
}
