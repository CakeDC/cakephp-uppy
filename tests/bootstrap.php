<?php
declare(strict_types=1);

/**
 * Load schema from a SQL dump file.
 *
 * If your plugin does not use database fixtures you can
 * safely delete this.
 *
 * If you want to support multiple databases, consider
 * using migrations to provide schema for your plugin,
 * and using \Migrations\TestSuite\Migrator to load schema.
 */
use Cake\TestSuite\Fixture\SchemaLoader;
use function Cake\Core\env;

/**
 * Test suite bootstrap for AdamTest.
 *
 * This function is used to find the location of CakePHP whether CakePHP
 * has been installed as a dependency of the plugin, or the plugin is itself
 * installed as a dependency of an application.
 */

if (env('FIXTURE_SCHEMA_METADATA')) {
    $loader = new SchemaLoader();
    $loader->loadInternalFile(env('FIXTURE_SCHEMA_METADATA'));
}
