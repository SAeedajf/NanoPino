<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

use RuntimeException;

final readonly class FileExtensionUpdatePolicyRepository implements ExtensionUpdatePolicyRepositoryInterface
{
    public function __construct(private string $directory) {}

    public function find(string $extensionId): ?ExtensionUpdatePolicy
    {
        $file = $this->path($extensionId);
        if (!is_file($file)) return null;
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? ExtensionUpdatePolicy::fromArray($data) : null;
    }

    public function save(ExtensionUpdatePolicy $policy): void
    {
        $this->ensureDirectory();
        $file = $this->path($policy->extensionId);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($policy->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to persist update policy.');
        }
    }

    public function delete(string $extensionId): void
    {
        $file = $this->path($extensionId);
        if (is_file($file) && !@unlink($file)) {
            throw new RuntimeException('Unable to delete update policy.');
        }
    }

    public function all(): array
    {
        if (!is_dir($this->directory)) return [];
        $items = [];
        foreach (glob(rtrim($this->directory, '/\\') . '/*.json') ?: [] as $file) {
            $data = json_decode((string)file_get_contents($file), true);
            if (is_array($data)) $items[] = ExtensionUpdatePolicy::fromArray($data);
        }
        usort($items, static fn ($a, $b): int => strcmp($a->extensionId, $b->extensionId));
        return $items;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create update policy directory.');
        }
    }

    private function path(string $extensionId): string
    {
        if ($extensionId === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,189}$/', $extensionId) !== 1) {
            throw new RuntimeException('Invalid update policy extension id.');
        }
        return rtrim($this->directory, '/\\') . '/' . hash('sha256', $extensionId) . '.json';
    }
}
