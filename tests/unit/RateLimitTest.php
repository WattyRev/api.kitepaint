<?php

use PHPUnit\Framework\TestCase;

class RateLimitTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kpratelimit-' . uniqid();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    public function testAllowsUpToTheMaximumThenDenies(): void
    {
        $now = 1000000;
        $this->assertTrue(rate_limit_allow('login', '1.2.3.4', 2, 60, $now, $this->dir));
        $this->assertTrue(rate_limit_allow('login', '1.2.3.4', 2, 60, $now, $this->dir));
        $this->assertFalse(rate_limit_allow('login', '1.2.3.4', 2, 60, $now, $this->dir));
    }

    public function testKeysAreCountedSeparately(): void
    {
        $now = 1000000;
        rate_limit_allow('login', '1.2.3.4', 1, 60, $now, $this->dir);
        $this->assertFalse(rate_limit_allow('login', '1.2.3.4', 1, 60, $now, $this->dir));
        $this->assertTrue(rate_limit_allow('login', '5.6.7.8', 1, 60, $now, $this->dir));
    }

    public function testActionsAreCountedSeparately(): void
    {
        $now = 1000000;
        rate_limit_allow('login', '1.2.3.4', 1, 60, $now, $this->dir);
        $this->assertFalse(rate_limit_allow('login', '1.2.3.4', 1, 60, $now, $this->dir));
        $this->assertTrue(rate_limit_allow('register', '1.2.3.4', 1, 60, $now, $this->dir));
    }

    public function testCountResetsAfterTheWindow(): void
    {
        $start = 1000000;
        $this->assertTrue(rate_limit_allow('login', 'key', 1, 60, $start, $this->dir));
        $this->assertFalse(rate_limit_allow('login', 'key', 1, 60, $start + 59, $this->dir));
        $this->assertTrue(rate_limit_allow('login', 'key', 1, 60, $start + 60, $this->dir));
    }

    public function testFailsOpenWhenCounterLocationIsUnusable(): void
    {
        // A regular file where the directory should be can't hold counters.
        $blocker = tempnam(sys_get_temp_dir(), 'kpblock');
        try {
            $this->assertTrue(rate_limit_allow('login', 'key', 1, 60, 1000000, $blocker));
            $this->assertTrue(rate_limit_allow('login', 'key', 1, 60, 1000000, $blocker));
        } finally {
            @unlink($blocker);
        }
    }
}
