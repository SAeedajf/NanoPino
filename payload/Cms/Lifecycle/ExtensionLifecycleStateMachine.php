<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Lifecycle;

use App\com_pinoox_cms\Cms\Exception\InvalidLifecycleTransitionException;

final class ExtensionLifecycleStateMachine
{
    /** @var array<string,list<ExtensionState>> */
    private const TRANSITIONS = [
        'discovered' => [ExtensionState::Validated, ExtensionState::Failed],
        'validated' => [ExtensionState::Installing, ExtensionState::Failed],
        'installing' => [ExtensionState::Installed, ExtensionState::Failed, ExtensionState::Rollback],
        'installed' => [ExtensionState::Activating, ExtensionState::Updating, ExtensionState::Uninstalling, ExtensionState::Failed],
        'activating' => [ExtensionState::Active, ExtensionState::Failed, ExtensionState::Rollback],
        'active' => [ExtensionState::Deactivating, ExtensionState::Updating, ExtensionState::Repairing, ExtensionState::Failed],
        'deactivating' => [ExtensionState::Inactive, ExtensionState::Failed, ExtensionState::Rollback],
        'inactive' => [ExtensionState::Activating, ExtensionState::Updating, ExtensionState::Repairing, ExtensionState::Uninstalling, ExtensionState::Failed],
        'updating' => [ExtensionState::Installed, ExtensionState::Failed, ExtensionState::Rollback],
        'repairing' => [ExtensionState::Active, ExtensionState::Inactive, ExtensionState::Failed, ExtensionState::Rollback],
        'uninstalling' => [ExtensionState::Removed, ExtensionState::Failed, ExtensionState::Rollback],
        'failed' => [ExtensionState::Repairing, ExtensionState::Rollback],
        'rollback' => [ExtensionState::Active, ExtensionState::Inactive, ExtensionState::Installed, ExtensionState::Removed, ExtensionState::Failed],
        'removed' => [],
    ];

    /** @var list<LifecycleTransition> */
    private array $history = [];

    public function __construct(private ExtensionState $state = ExtensionState::Discovered)
    {
    }

    public function state(): ExtensionState
    {
        return $this->state;
    }

    public function canTransitionTo(ExtensionState $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->state->value] ?? [], true);
    }

    public function transitionTo(ExtensionState $to, ?string $reason = null): LifecycleTransition
    {
        if (!$this->canTransitionTo($to)) {
            throw new InvalidLifecycleTransitionException($this->state, $to);
        }

        $transition = new LifecycleTransition($this->state, $to, microtime(true), $reason);
        $this->state = $to;
        $this->history[] = $transition;
        return $transition;
    }

    /** @return list<LifecycleTransition> */
    public function history(): array
    {
        return $this->history;
    }
}
