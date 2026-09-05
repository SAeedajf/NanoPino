<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use RuntimeException;

final class SafeModeManager
{
    public function __construct(private readonly string $stateFile) {}

    public function state(): SafeModeState
    {
        if (!is_file($this->stateFile)) {
            return new SafeModeState();
        }
        $data = json_decode((string)file_get_contents($this->stateFile), true);
        return is_array($data) ? SafeModeState::fromArray($data) : new SafeModeState();
    }

    public function enable(string $reason, ?string $extensionId = null, ?string $recoveryPointId = null): SafeModeState
    {
        $state = $this->state();
        $state->enabled = true;
        $state->reason = $reason;
        $state->enabledAt = microtime(true);
        $state->recoveryPointId = $recoveryPointId;
        if ($extensionId !== null && $extensionId !== '') {
            $state->quarantined[] = $extensionId;
        }
        $state->quarantined = array_values(array_unique($state->quarantined));
        $this->save($state);
        return $state;
    }

    public function quarantine(string $extensionId, string $reason = 'extension quarantined'): SafeModeState
    {
        return $this->enable($reason, $extensionId, $this->state()->recoveryPointId);
    }

    public function disable(): SafeModeState
    {
        $state = new SafeModeState();
        $this->save($state);
        return $state;
    }

    public function isQuarantined(string $extensionId): bool
    {
        return in_array($extensionId, $this->state()->quarantined, true);
    }

    public function mayBoot(string $extensionId, bool $isCore = false): bool
    {
        $state = $this->state();
        if (!$state->enabled || $isCore) {
            return true;
        }
        return !$this->isQuarantined($extensionId);
    }

    private function save(SafeModeState $state): void
    {
        $directory = dirname($this->stateFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create safe mode state directory.');
        }

        $tmp = $this->stateFile . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $this->stateFile)) {
            @unlink($tmp);
            throw new RuntimeException('Unable to persist safe mode state.');
        }
    }
}
