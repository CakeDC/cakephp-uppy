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
use Migrations\AbstractMigration;

class ChangeFileSizeInUppyFilesTable extends AbstractMigration
{
    /**
     * Widen filesize column from INT(10) to BIGINT to support files larger than 2 GB.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('uppy_files')
            ->changeColumn('filesize', 'biginteger', [
                'default' => null,
                'null' => true,
            ])
            ->update();
    }

    /**
     * Restore filesize to the original INT(10) definition from CreateUppyFiles.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('uppy_files')
            ->changeColumn('filesize', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->update();
    }
}
