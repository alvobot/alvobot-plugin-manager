<?php
/**
 * Base test case for Brain Monkey tests.
 * Sets up and tears down Brain Monkey for each test.
 */

use PHPUnit\Framework\TestCase;
use Brain\Monkey;

abstract class BrainMonkeyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
