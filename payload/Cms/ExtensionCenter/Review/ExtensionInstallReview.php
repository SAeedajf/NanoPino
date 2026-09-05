<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

use App\com_pinoox_cms\Cms\Dependency\DependencyResolution;
use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustReport;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;
use App\com_pinoox_cms\Cms\Permission\PermissionReview;

final readonly class ExtensionInstallReview
{
    /**
     * @param list<string> $newPermissions
     * @param list<string> $warnings
     */
    public function __construct(
        public ExtensionManifest $manifest,
        public DependencyResolution $dependencies,
        public PermissionReview $permissions,
        public PackageTrustReport $trust,
        public ExtensionReviewDecision $decision,
        public array $newPermissions = [],
        public array $warnings = [],
    ) {}

    public function canExecute(bool $approved = false): bool
    {
        return match ($this->decision) {
            ExtensionReviewDecision::Allow => true,
            ExtensionReviewDecision::ApprovalRequired => $approved,
            ExtensionReviewDecision::Block => false,
        };
    }
}
