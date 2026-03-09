<?php

/**
 * Sample Unit Test - Database Connection
 * Run: ./vendor/bin/phpunit tests/unit/DatabaseTest.php
 */

use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    public function testDatabaseConnection()
    {
        require_once __DIR__ . '/../../includes/database.php';

        $this->assertInstanceOf('Database', db());
        $this->assertNotNull(db());
    }

    public function testDatabaseQuery()
    {
        require_once __DIR__ . '/../../includes/database.php';

        $result = db()->fetch("SELECT 1 as test");
        $this->assertEquals(1, $result['test']);
    }

    public function testPreparedStatements()
    {
        require_once __DIR__ . '/../../includes/database.php';

        $result = db()->fetch("SELECT ? as test", [42]);
        $this->assertEquals(42, $result['test']);
    }
}
