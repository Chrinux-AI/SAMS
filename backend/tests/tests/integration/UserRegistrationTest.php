<?php

/**
 * Sample Integration Test - User Registration
 */

use PHPUnit\Framework\TestCase;

class UserRegistrationTest extends TestCase
{
    private $testUserId;

    public function testUserCanRegister()
    {
        require_once __DIR__ . '/../../includes/database.php';

        $email = 'test' . time() . '@example.com';

        $userId = db()->insert('users', [
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'student',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->testUserId = $userId;
        $this->assertGreaterThan(0, $userId);

        $user = db()->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
        $this->assertEquals($email, $user['email']);
    }

    protected function tearDown(): void
    {
        if ($this->testUserId) {
            db()->query("DELETE FROM users WHERE id = ?", [$this->testUserId]);
        }
    }
}
