<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Permission;

final readonly class PermissionReview
{
    /** @param list<PermissionReviewItem> $items */
    public function __construct(public array $items)
    {
    }

    public function hasUnknown(): bool
    {
        foreach ($this->items as $item) {
            if (!$item->known) { return true; }
        }
        return false;
    }

    public function highestRisk(): ExtensionPermissionRisk
    {
        $risk = ExtensionPermissionRisk::Low;
        foreach ($this->items as $item) {
            if ($item->risk->value > $risk->value) { $risk = $item->risk; }
        }
        return $risk;
    }

    public function requiresExplicitApproval(): bool
    {
        return $this->hasUnknown() || $this->highestRisk()->value >= ExtensionPermissionRisk::High->value;
    }
}
