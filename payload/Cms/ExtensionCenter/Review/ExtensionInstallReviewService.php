<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

use App\com_pinoox_cms\Cms\Dependency\ExtensionCatalog;
use App\com_pinoox_cms\Cms\Dependency\ExtensionDependencyResolver;
use App\com_pinoox_cms\Cms\ExtensionCenter\Trust\PackageTrustReport;
use App\com_pinoox_cms\Cms\Manifest\ExtensionManifest;
use App\com_pinoox_cms\Cms\Permission\ExtensionPermissionReviewer;
use App\com_pinoox_cms\Cms\Permission\ExtensionPermissionRisk;
use App\com_pinoox_cms\Cms\Security\Package\PackagePreflightReport;

final readonly class ExtensionInstallReviewService
{
    public function __construct(
        private ExtensionDependencyResolver $dependencies,
        private ExtensionPermissionReviewer $permissions,
    ) {}

    /**
     * @param list<string> $currentlyGrantedPermissions
     */
    public function review(
        ExtensionManifest $manifest,
        ExtensionCatalog $installed,
        PackageTrustReport $trust,
        array $currentlyGrantedPermissions = [],
        bool $requireSignature = false,
        bool $isUpdate = false,
        bool $isDowngrade = false,
        ?PackagePreflightReport $security = null,
    ): ExtensionInstallReview {
        $dependencyReview = $this->dependencies->resolve($manifest, $installed);
        $permissionReview = $this->permissions->review($manifest);

        $granted = array_fill_keys($currentlyGrantedPermissions, true);
        $newPermissions = array_values(array_filter(
            $manifest->permissions(),
            static fn (string $permission): bool => !isset($granted[$permission]),
        ));

        $warnings = [];
        if ($isDowngrade) {
            $warnings[] = 'Requested package version is lower than the currently installed version.';
        }

        if ($isUpdate && $newPermissions !== []) {
            $warnings[] = 'Update requests additional extension permissions.';
        }

        if ($security !== null && $security->findings !== []) {
            $warnings[] = sprintf(
                'Static package review reported %d finding(s); review code before approval.',
                count($security->findings),
            );
        }

        if ($trust->authenticityVerified) {
            $warnings[] = 'Verified publisher identity does not guarantee that extension code is safe.';
        }

        if (
            !$dependencyReview->canProceed()
            || !$trust->canProceed($requireSignature)
            || $permissionReview->hasUnknown()
            || ($security?->hasHighRisk() ?? false)
        ) {
            $decision = ExtensionReviewDecision::Block;
        } elseif (
            $permissionReview->highestRisk()->value >= ExtensionPermissionRisk::High->value
            || ($isUpdate && $newPermissions !== [])
            || $isDowngrade
        ) {
            $decision = ExtensionReviewDecision::ApprovalRequired;
        } else {
            $decision = ExtensionReviewDecision::Allow;
        }

        return new ExtensionInstallReview(
            $manifest,
            $dependencyReview,
            $permissionReview,
            $trust,
            $decision,
            $newPermissions,
            $warnings,
        );
    }
}
