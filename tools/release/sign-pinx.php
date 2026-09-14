<?php
declare(strict_types=1);

/**
 * Sign an already-built PINX without relying on the native builder's
 * insertion-order payload digest. Pincore verification sorts payload entries,
 * so release signing must use the same canonical order.
 */

if ($argc !== 5) {
    fwrite(STDERR, "Usage: php tools/release/sign-pinx.php <pinoox-root> <unsigned-pinx> <key-json> <signed-pinx>\n");
    exit(64);
}

$pinooxRoot = realpath($argv[1]);
$unsignedPath = realpath($argv[2]);
$keyPath = realpath($argv[3]);
$signedPath = $argv[4];

if ($pinooxRoot === false || !is_file($pinooxRoot . '/vendor/autoload.php')) {
    fwrite(STDERR, "Invalid Pinoox root: {$argv[1]}\n");
    exit(65);
}
if ($unsignedPath === false || !is_file($unsignedPath)) {
    fwrite(STDERR, "Unsigned PINX was not found: {$argv[2]}\n");
    exit(66);
}
if ($keyPath === false || !is_file($keyPath)) {
    fwrite(STDERR, "Signing key was not found: {$argv[3]}\n");
    exit(67);
}

$signedAbsolute = realpath(dirname($signedPath));
$signedAbsolute = ($signedAbsolute !== false ? $signedAbsolute : dirname($signedPath)) . '/' . basename($signedPath);
if ($signedAbsolute === $unsignedPath) {
    fwrite(STDERR, "Signed output must be different from the unsigned input.\n");
    exit(68);
}
if (is_link($signedAbsolute) || (file_exists($signedAbsolute) && !is_file($signedAbsolute))) {
    fwrite(STDERR, "Signed output must be a regular file path.\n");
    exit(69);
}

require $pinooxRoot . '/vendor/autoload.php';

use Pinoox\Component\Package\Pinx\PinxReader;
use Pinoox\Component\Package\Pinx\PinxSignKey;
use Pinoox\Component\Package\Pinx\PinxSignature;
use Pinoox\Component\Package\Pinx\PinxVerifier;

$reader = new PinxReader();
$reader->open($unsignedPath);

try {
    if ($reader->signature() !== null || $reader->zip()->hasEntry(PinxSignature::FILE)) {
        throw new RuntimeException('Input PINX is already signed.');
    }

    $manifestJson = $reader->manifestJson();
    $payloadHashes = PinxSignature::payloadHashes($reader->zip());
    $key = PinxSignKey::load($keyPath);
    $signature = PinxSignature::create($manifestJson, $payloadHashes, $key);
    $signatureJson = json_encode(
        $signature,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    );
} finally {
    $reader->close();
}

$parent = dirname($signedAbsolute);
if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
    throw new RuntimeException('Unable to create signed output directory.');
}
if (!copy($unsignedPath, $signedAbsolute)) {
    throw new RuntimeException('Unable to stage unsigned PINX for signing.');
}

$archive = new ZipArchive();
if ($archive->open($signedAbsolute) !== true) {
    @unlink($signedAbsolute);
    throw new RuntimeException('Unable to open staged PINX for signing.');
}

try {
    if ($archive->locateName(PinxSignature::FILE) !== false) {
        throw new RuntimeException('Staged PINX unexpectedly contains signature.json.');
    }
    if (!$archive->addFromString(PinxSignature::FILE, $signatureJson)) {
        throw new RuntimeException('Unable to add signature.json to PINX.');
    }
    if (!$archive->close()) {
        throw new RuntimeException('Unable to close signed PINX.');
    }
} catch (Throwable $error) {
    $archive->close();
    @unlink($signedAbsolute);
    throw $error;
}

$verification = new PinxReader();
try {
    $verification->open($signedAbsolute);
    PinxVerifier::verify(
        $verification->zip(),
        $verification->manifest(),
        $verification->manifestJson(),
        ['require_signature' => true],
    );
} finally {
    $verification->close();
}

$keyId = (string) ($signature['key_id'] ?? 'unknown');
$fingerprint = (string) ($signature['fingerprint'] ?? 'unknown');
fwrite(STDOUT, "pinx_signature=PASS key_id={$keyId} fingerprint={$fingerprint} output={$signedAbsolute}\n");
