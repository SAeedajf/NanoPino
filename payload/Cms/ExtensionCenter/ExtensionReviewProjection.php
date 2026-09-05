<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageInspection;
use App\com_pinoox_cms\Cms\ExtensionCenter\Review\ExtensionInstallReview;

final readonly class ExtensionReviewProjection
{
    public function __construct(
        public ExtensionPackageInspection $inspection,
        public ExtensionInstallReview $review,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'package' => $this->inspection->publicData(),
            'review' => [
                'decision' => $this->review->decision->value,
                'can_proceed_without_approval' => $this->review->canExecute(false),
                'requires_approval' => !$this->review->canExecute(false)
                    && $this->review->decision->value === 'approval_required',
                'new_permissions' => $this->review->newPermissions,
                'warnings' => $this->review->warnings,
                'dependencies' => [
                    'can_proceed' => $this->review->dependencies->canProceed(),
                    'issues' => array_map(
                        static fn ($issue): array => [
                            'code' => $issue->code,
                            'target' => $issue->target,
                            'message' => $issue->message,
                            'blocking' => $issue->blocking,
                        ],
                        $this->review->dependencies->issues,
                    ),
                ],
                'permissions' => [
                    'highest_risk' => strtolower($this->review->permissions->highestRisk()->name),
                    'has_unknown' => $this->review->permissions->hasUnknown(),
                    'items' => array_map(
                        static fn ($item): array => [
                            'permission' => $item->permission,
                            'known' => $item->known,
                            'risk' => strtolower($item->risk->name),
                            'description' => $item->description,
                        ],
                        $this->review->permissions->items,
                    ),
                ],
            ],
        ];
    }
}
