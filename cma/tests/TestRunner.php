<?php
/**
 * Lightweight PHPUnit-compatible Test Runner
 *
 * Runs test classes that follow PHPUnit conventions (setUp, test* methods, assertions)
 * without requiring PHPUnit's DOM/XML dependencies.
 *
 * Usage: php tests/TestRunner.php [TestClass] [--filter=methodName]
 */

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private int $errors = 0;
    private array $failures = [];
    private float $startTime;

    public function run(array $testFiles, ?string $filter = null): int
    {
        $this->startTime = microtime(true);

        $classesBefore = get_declared_classes();

        foreach ($testFiles as $file) {
            require_once $file;
        }

        // Find only newly declared test classes
        $classesAfter = get_declared_classes();
        $newClasses = array_diff($classesAfter, $classesBefore);
        $testClasses = array_filter($newClasses, fn($c) => str_ends_with($c, 'Test') && $c !== 'TestRunner');

        foreach ($testClasses as $className) {
            $this->runTestClass($className, $filter);
        }

        $this->printResults();
        return $this->failed + $this->errors > 0 ? 1 : 0;
    }

    private function runTestClass(string $className, ?string $filter): void
    {
        $ref = new ReflectionClass($className);
        $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
        $testMethods = array_filter($methods, fn($m) => str_starts_with($m->getName(), 'test'));

        if (empty($testMethods)) return;

        echo "\n{$className}\n";

        foreach ($testMethods as $method) {
            $name = $method->getName();
            if ($filter && !str_contains($name, $filter)) continue;

            $instance = new $className();

            // Call setUp if exists
            if ($ref->hasMethod('setUp')) {
                $instance->setUp();
            }

            try {
                $instance->$name();
                $this->passed++;
                echo "  \033[32m✓\033[0m {$name}\n";
            } catch (AssertionError $e) {
                $this->failed++;
                $this->failures[] = "{$className}::{$name}: {$e->getMessage()}";
                echo "  \033[31m✗\033[0m {$name}\n    {$e->getMessage()}\n";
            } catch (Throwable $e) {
                $this->errors++;
                $this->failures[] = "{$className}::{$name}: [ERROR] {$e->getMessage()}";
                echo "  \033[31m✗\033[0m {$name}\n    [ERROR] {$e->getMessage()}\n";
            }

            // Call tearDown if exists
            if ($ref->hasMethod('tearDown')) {
                $instance->tearDown();
            }
        }
    }

    private function printResults(): void
    {
        $elapsed = round(microtime(true) - $this->startTime, 3);
        $total = $this->passed + $this->failed + $this->errors;

        echo "\n" . str_repeat('-', 60) . "\n";
        echo "Tests: {$total}, Passed: \033[32m{$this->passed}\033[0m";
        if ($this->failed > 0) echo ", Failed: \033[31m{$this->failed}\033[0m";
        if ($this->errors > 0) echo ", Errors: \033[31m{$this->errors}\033[0m";
        echo " ({$elapsed}s)\n";

        if (!empty($this->failures)) {
            echo "\nFailures:\n";
            foreach ($this->failures as $i => $msg) {
                echo "  " . ($i + 1) . ") {$msg}\n";
            }
        }
    }
}

/**
 * Base test case with assertion methods (PHPUnit-compatible API)
 */
class TestCase
{
    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
            throw new AssertionError($msg);
        }
    }

    protected function assertSame($expected, $actual, string $message = ''): void
    {
        $this->assertEquals($expected, $actual, $message);
    }

    protected function assertTrue($value, string $message = ''): void
    {
        if ($value !== true) {
            throw new AssertionError($message ?: "Expected true, got " . var_export($value, true));
        }
    }

    protected function assertFalse($value, string $message = ''): void
    {
        if ($value !== false) {
            throw new AssertionError($message ?: "Expected false, got " . var_export($value, true));
        }
    }

    protected function assertNull($value, string $message = ''): void
    {
        if ($value !== null) {
            throw new AssertionError($message ?: "Expected null, got " . var_export($value, true));
        }
    }

    protected function assertNotNull($value, string $message = ''): void
    {
        if ($value === null) {
            throw new AssertionError($message ?: "Expected non-null value");
        }
    }

    protected function assertEmpty($value, string $message = ''): void
    {
        if (!empty($value)) {
            throw new AssertionError($message ?: "Expected empty, got " . var_export($value, true));
        }
    }

    protected function assertNotEmpty($value, string $message = ''): void
    {
        if (empty($value)) {
            throw new AssertionError($message ?: "Expected non-empty value");
        }
    }

    protected function assertCount(int $expected, $array, string $message = ''): void
    {
        $actual = is_countable($array) ? count($array) : 0;
        if ($expected !== $actual) {
            throw new AssertionError($message ?: "Expected count {$expected}, got {$actual}");
        }
    }

    protected function assertContains($needle, array $haystack, string $message = ''): void
    {
        if (!in_array($needle, $haystack, true)) {
            throw new AssertionError($message ?: var_export($needle, true) . " not found in array");
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new AssertionError($message ?: "'{$needle}' not found in '{$haystack}'");
        }
    }

    protected function assertGreaterThan($expected, $actual, string $message = ''): void
    {
        if ($actual <= $expected) {
            throw new AssertionError($message ?: "Expected > {$expected}, got {$actual}");
        }
    }

    protected function assertLessThan($expected, $actual, string $message = ''): void
    {
        if ($actual >= $expected) {
            throw new AssertionError($message ?: "Expected < {$expected}, got {$actual}");
        }
    }

    protected function assertIsArray($value, string $message = ''): void
    {
        if (!is_array($value)) {
            throw new AssertionError($message ?: "Expected array, got " . gettype($value));
        }
    }

    protected function assertIsString($value, string $message = ''): void
    {
        if (!is_string($value)) {
            throw new AssertionError($message ?: "Expected string, got " . gettype($value));
        }
    }

    protected function assertArrayHasKey($key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            throw new AssertionError($message ?: "Array does not have key '{$key}'");
        }
    }

    protected function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void
    {
        if (!preg_match($pattern, $string)) {
            throw new AssertionError($message ?: "'{$string}' does not match pattern '{$pattern}'");
        }
    }
}

if (!class_exists('AssertionError')) {
    class AssertionError extends \Error {}
}

// --- CLI entry point ---
if (php_sapi_name() === 'cli' && realpath($argv[0]) === realpath(__FILE__)) {
    // Set up autoloading
    $baseDir = dirname(__DIR__);
    require_once $baseDir . '/vendor/autoload.php';

    $filter = null;
    $specificTest = null;

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--filter=')) {
            $filter = substr($arg, 9);
        } else {
            $specificTest = $arg;
        }
    }

    // Collect test files
    $testDir = __DIR__;
    if ($specificTest) {
        $file = $testDir . '/' . $specificTest;
        if (!str_ends_with($file, '.php')) $file .= '.php';
        if (!file_exists($file)) {
            echo "Test file not found: {$file}\n";
            exit(1);
        }
        $files = [$file];
    } else {
        $files = glob($testDir . '/*Test.php');
        // Exclude legacy custom-format tests
        $files = array_filter($files, fn($f) => !in_array(basename($f), ['SqlParserTest.php', 'QueryBuilderTest.php']));
    }

    $runner = new TestRunner();
    exit($runner->run($files, $filter));
}
