<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Recovery;

use App\com_pinoox_cms\Cms\Recovery\FaultInjection\FaultInjectorInterface;
use App\com_pinoox_cms\Cms\Recovery\FaultInjection\NullFaultInjector;
use RuntimeException;

final class SafeModeManager
{
    private readonly FaultInjectorInterface $faults;

    public function __construct(
        private readonly string $stateFile,
        ?FaultInjectorInterface $faults = null,
    ) {
        $this->faults = $faults ?? new NullFaultInjector();
    }

    public function state(): SafeModeState
    {
        if (!is_file($this->stateFile)) {
            return new SafeModeState();
        }
        if (is_link($this->stateFile)) {
            return SafeModeState::failClosed('Safe Mode state is a symbolic link; manual recovery is required.');
        }

        $raw = @file_get_contents($this->stateFile);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return SafeModeState::failClosed('Safe Mode state is unreadable; manual recovery is required.');
        }

        try {
            return SafeModeState::fromArray($data);
        } catch (\Throwable) {
            return SafeModeState::failClosed('Safe Mode state is invalid; manual recovery is required.');
        }
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
        // Safe Mode is a fail-closed boot profile: only explicitly core
        // packages may register. The quarantine list remains diagnostic.
        return false;
    }

    private function save(SafeModeState $state): void
    {
        $directory = dirname($this->stateFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create safe mode state directory.');
        }

        $tmp = $this->stateFile . '.tmp-' . bin2hex(random_bytes(4));
        $json = json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        try {
            $this->faults->checkpoint('safe_mode.persist.before');
            if (!is_string($json) || file_put_contents($tmp, $json, LOCK_EX) === false) {
                throw new RuntimeException('Unable to persist safe mode state.');
            }
            @chmod($tmp, 0600);
            $this->faults->checkpoint('safe_mode.persist.after_write');
            if (!@rename($tmp, $this->stateFile)) {
                throw new RuntimeException('Unable to persist safe mode state.');
            }
        } catch (\Throwable $error) {
            @unlink($tmp);
            if ($error instanceof RuntimeException) {
                throw $error;
            }
            throw new RuntimeException('Unable to persist safe mode state.', 0, $error);
        }
    }
}
