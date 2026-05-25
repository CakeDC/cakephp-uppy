<?php
declare(strict_types=1);

/**
 * Copyright 2024, Cake Development Corporation (https://www.cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2024, Cake Development Corporation (https://www.cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

use Migrations\AbstractMigration;

class AddUserIdIndexToUppyFiles extends AbstractMigration
{
    public function up(): void
    {
        $this->table('uppy_files')
            ->addIndex(['user_id'])
            ->update();
    }

    public function down(): void
    {
        $this->table('uppy_files')
            ->removeIndex(['user_id'])
            ->update();
    }
}
