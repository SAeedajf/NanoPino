<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;

final readonly class SystemUpdateBoundary
{
    public function __construct(private AuthorizationManager $authorization) {}

    public function authorize(UpdateTargetType $target, ?int $actorId = null): void
    {
        $capability = $target === UpdateTargetType::Extension
            ? 'extensions.update'
            : 'system.update';

        $this->authorization->authorize(new AuthorizationRequest($capability, $actorId));
    }

    public function runtimeBoundary(UpdateTargetType $target): string
    {
        return match ($target) {
            UpdateTargetType::Extension => 'cms-transactional-update+pinx-installer',
            UpdateTargetType::Cms => 'cms-package-update',
            UpdateTargetType::Pincore,
            UpdateTargetType::Platform => 'pincore-platform-updater/pinroll-deployment-boundary',
        };
    }
}
