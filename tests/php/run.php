<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$caseFiles = glob(__DIR__ . '/cases/*Test.php') ?: [];
sort($caseFiles, SORT_STRING);

if ($caseFiles === []) {
    fwrite(STDERR, "ERROR: PHP test suite contains zero cases.\n");
    exit(2);
}

$tests = [];
foreach ($caseFiles as $file) {
    $suite = require $file;
    if (!is_array($suite)) {
        fwrite(STDERR, "ERROR: Test file must return an array: {$file}\n");
        exit(2);
    }
    foreach ($suite as $name => $callback) {
        if (!is_string($name) || !is_callable($callback)) {
            fwrite(STDERR, "ERROR: Invalid test declaration in {$file}\n");
            exit(2);
        }
        $tests[] = [$name, $callback, basename($file)];
    }
}

$failed = 0;
$started = hrtime(true);

foreach ($tests as $index => [$name, $callback, $file]) {
    try {
        $callback();
        printf("ok %d - %s [%s]\n", $index + 1, $name, $file);
    } catch (Throwable $error) {
        $failed++;
        printf("not ok %d - %s [%s]\n", $index + 1, $name, $file);
        fwrite(STDERR, '  ' . $error::class . ': ' . $error->getMessage() . PHP_EOL);
    }
}

$durationMs = (hrtime(true) - $started) / 1_000_000;
printf("php_tests=%d pass=%d fail=%d duration_ms=%.2f php=%s\n",
    count($tests),
    count($tests) - $failed,
    $failed,
    $durationMs,
    PHP_VERSION,
);

exit($failed === 0 ? 0 : 1);
