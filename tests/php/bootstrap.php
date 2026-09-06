<?php
declare(strict_types=1);

const NANOPINO_ROOT = __DIR__ . '/../..';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\com_pinoox_cms\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = NANOPINO_ROOT . '/payload/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

final class NanoPinoTestFailure extends RuntimeException {}

function np_assert_true(bool $condition, string $message = 'Expected condition to be true.'): void
{
    if (!$condition) {
        throw new NanoPinoTestFailure($message);
    }
}

function np_assert_false(bool $condition, string $message = 'Expected condition to be false.'): void
{
    np_assert_true(!$condition, $message);
}

function np_assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new NanoPinoTestFailure($message !== ''
            ? $message
            : 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function np_assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new NanoPinoTestFailure($message !== '' ? $message : 'Expected string to contain: ' . $needle);
    }
}

function np_assert_matches(string $pattern, string $value, string $message = ''): void
{
    if (preg_match($pattern, $value) !== 1) {
        throw new NanoPinoTestFailure($message !== '' ? $message : 'Value did not match pattern: ' . $pattern);
    }
}

function np_assert_throws(callable $callback, string $class, ?string $messageContains = null): Throwable
{
    try {
        $callback();
    } catch (Throwable $error) {
        if (!$error instanceof $class) {
            throw new NanoPinoTestFailure('Expected ' . $class . ', got ' . $error::class . '.');
        }
        if ($messageContains !== null && !str_contains($error->getMessage(), $messageContains)) {
            throw new NanoPinoTestFailure('Exception message did not contain: ' . $messageContains);
        }
        return $error;
    }

    throw new NanoPinoTestFailure('Expected exception ' . $class . ' was not thrown.');
}
