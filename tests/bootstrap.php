<?php
/**
 * Unified test bootstrap — dispatches to the correct bootstrap based on suite.
 *
 * Usage:
 *   Default (legacy stubs):      phpunit
 *   Brain Monkey suite:          PHPUNIT_SUITE=brain-monkey phpunit --testsuite=BrainMonkey
 */

$suite = getenv('PHPUNIT_SUITE');

if ($suite === 'brain-monkey') {
    require_once __DIR__ . '/bootstrap-brain-monkey.php';
} else {
    require_once __DIR__ . '/bootstrap-stubs.php';
}
