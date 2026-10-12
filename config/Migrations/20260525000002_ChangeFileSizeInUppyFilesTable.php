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
     * Aborts when any row holds a value that would not fit in the original
     * INT(10) column (see guardAgainstFilesizeOverflow()).
     *
     * @return void
     * @throws \RuntimeException When rows with a filesize above the INT range exist.
     */
    public function down(): void
    {
        $this->guardAgainstFilesizeOverflow();

        $this->table('uppy_files')
            ->changeColumn('filesize', 'integer', [
                'default' => null,
                'limit' => 10,
                'null' => true,
            ])
            ->update();
    }

    /**
     * Abort the rollback when filesize values would not fit in the original INT(10).
     *
     * `up()` widened the column to BIGINT to support files larger than 2 GB.
     * Restoring `integer` (INT) would silently truncate any value above the
     * 32-bit signed maximum (2,147,483,647 bytes). Refuse instead, so the
     * operator can adjust or remove those rows first.
     *
     * @return void
     * @throws \RuntimeException When rows with filesize > 2 GB exist.
     */
    private function guardAgainstFilesizeOverflow(): void
    {
        $max = 2147483647;
        $row = $this->fetchRow(
            sprintf('SELECT COUNT(*) AS count FROM uppy_files WHERE filesize > %d', $max),
        );
        $count = is_array($row) ? (int)($row['count'] ?? 0) : 0;
        if ($count > 0) {
            throw new RuntimeException(sprintf(
                'Refusing to roll back %s: %d row(s) have a filesize greater than %d bytes. '
                . 'Restoring the integer column would truncate them; adjust or remove those rows first.',
                static::class,
                $count,
                $max,
            ));
        }
    }
}
