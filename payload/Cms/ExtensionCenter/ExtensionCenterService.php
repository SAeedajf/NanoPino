<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Dependency\ExtensionCatalog;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationCoordinator;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationRecord;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationRequest;
use App\com_pinoox_cms\Cms\ExtensionCenter\Operation\ExtensionOperationType;
use App\com_pinoox_cms\Cms\ExtensionCenter\Grant\ExtensionPermissionGrantService;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageInspection;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageInspectorInterface;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageReference;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageUploadPolicy;
use App\com_pinoox_cms\Cms\ExtensionCenter\Review\ExtensionInstallReviewService;
use App\com_pinoox_cms\Cms\ExtensionCenter\Review\ExtensionReviewTicketService;
use App\com_pinoox_cms\Cms\Recovery\SafeModeState;

final readonly class ExtensionCenterService
{
    public function __construct(
        private ExtensionCenterCatalogService $catalog,
        private ExtensionPackageUploadPolicy $uploads,
        private ExtensionPackageInspectorInterface $inspector,
        private ExtensionInstallReviewService $reviews,
        private ExtensionReviewTicketService $tickets,
        private ExtensionOperationCoordinator $operations,
        private ExtensionCatalog $installed,
        private AuthorizationManager $authorization,
        private ?ExtensionPermissionGrantService $grants = null,
    ) {}

    /** @return list<array<string,mixed>> */
    public function list(?int $actorId = null, ?SafeModeState $safeMode = null): array
    {
        $this->authorize('extensions.read', $actorId);

        return array_map(
            static fn (ExtensionCenterItem $item): array => $item->toArray(),
            $this->catalog->catalog($safeMode),
        );
    }

    /**
     * Inspect/review is read-only. It never installs the package.
     *
     * @param list<string> $currentlyGrantedPermissions
     */
    public function inspectAndReview(
        string $localPath,
        string $displayName,
        int $size,
        ?int $actorId = null,
        array $currentlyGrantedPermissions = [],
        bool $requireSignature = false,
        bool $isUpdate = false,
        bool $isDowngrade = false,
    ): ExtensionReviewProjection {
        $this->authorize($isUpdate ? 'extensions.update' : 'extensions.install', $actorId);

        $reference = $this->uploads->validate($localPath, $displayName, $size);
        $inspection = $this->inspector->inspect($reference);

        if ($currentlyGrantedPermissions === [] && $this->grants !== null) {
            $currentlyGrantedPermissions = $this->grants->currentPermissions(
                $inspection->manifest->identifier()
            );
        }

        $review = $this->reviews->review(
            $inspection->manifest,
            $this->installed,
            $inspection->trust,
            $currentlyGrantedPermissions,
            $requireSignature,
            $isUpdate,
            $isDowngrade,
            $inspection->security,
        );

        return new ExtensionReviewProjection($inspection, $review);
    }

    /**
     * Re-inspects the exact file immediately before ticket issuance.
     *
     * @param list<string> $currentlyGrantedPermissions
     * @return array{token:string,expires_at:float,review:array<string,mixed>}
     */
    public function issueReviewTicket(
        string $localPath,
        string $displayName,
        int $size,
        bool $approved,
        ?int $actorId = null,
        array $currentlyGrantedPermissions = [],
        bool $requireSignature = false,
        bool $isUpdate = false,
        bool $isDowngrade = false,
    ): array {
        $projection = $this->inspectAndReview(
            $localPath,
            $displayName,
            $size,
            $actorId,
            $currentlyGrantedPermissions,
            $requireSignature,
            $isUpdate,
            $isDowngrade,
        );

        $issued = $this->tickets->issue(
            $projection->inspection,
            $projection->review,
            $approved,
        );

        return [
            'token' => $issued['token'],
            'expires_at' => $issued['ticket']->expiresAt,
            'review' => $projection->toArray(),
        ];
    }

    /**
     * Install/update re-inspects and re-reviews the package immediately before mutation.
     *
     * @param list<string> $currentlyGrantedPermissions
     */
    public function executePackageOperation(
        ExtensionOperationType $type,
        string $localPath,
        string $displayName,
        int $size,
        string $reviewToken,
        ?int $actorId = null,
        array $currentlyGrantedPermissions = [],
        bool $requireSignature = false,
        bool $isDowngrade = false,
    ): ExtensionOperationRecord {
        if (!in_array($type, [ExtensionOperationType::Install, ExtensionOperationType::Update], true)) {
            throw new \InvalidArgumentException('Package operation must be install or update.');
        }

        $capability = $type === ExtensionOperationType::Install
            ? 'extensions.install'
            : 'extensions.update';
        $this->authorize($capability, $actorId);

        $reference = $this->uploads->validate($localPath, $displayName, $size);
        $inspection = $this->inspector->inspect($reference);

        if ($currentlyGrantedPermissions === [] && $this->grants !== null) {
            $currentlyGrantedPermissions = $this->grants->currentPermissions(
                $inspection->manifest->identifier()
            );
        }

        $review = $this->reviews->review(
            $inspection->manifest,
            $this->installed,
            $inspection->trust,
            $currentlyGrantedPermissions,
            $requireSignature,
            $type === ExtensionOperationType::Update,
            $isDowngrade,
            $inspection->security,
        );

        if ($review->decision->value === 'block') {
            throw new \RuntimeException('Extension review is now blocked.');
        }

        $this->tickets->consume(
            $reviewToken,
            $reference,
            $inspection->manifest->identifier(),
        );

        $operationOptions = [
            'actor_id' => $actorId,
            'require_signature' => $requireSignature,
        ];
        if ($type === ExtensionOperationType::Update) {
            $installedMatches = $this->installed->find($inspection->manifest->identifier());
            $current = $installedMatches[0] ?? null;
            if ($current !== null) {
                $operationOptions['from_version'] = $current->version;
                $operationOptions['from_version_code'] = $current->versionCode;
            }
        }

        $operation = $this->operations->run(
            new ExtensionOperationRequest(
                $type,
                $inspection->manifest->identifier(),
                $reference,
                $inspection,
                options: $operationOptions,
            ),
            $review,
            true,
        );

        if ($operation->status->value === 'succeeded' && $this->grants !== null) {
            $this->grants->approve(
                $inspection->manifest->identifier(),
                $inspection->manifest->version(),
                $inspection->manifest->versionCode(),
                $inspection->packageSha256,
                $inspection->manifest->permissions(),
                $actorId,
            );
        }

        return $operation;
    }

    public function executeInstalledOperation(
        ExtensionOperationType $type,
        string $extensionId,
        ?string $recoveryPointId = null,
        ?int $actorId = null,
    ): ExtensionOperationRecord {
        $capability = match ($type) {
            ExtensionOperationType::Activate => 'extensions.activate',
            ExtensionOperationType::Deactivate => 'extensions.deactivate',
            ExtensionOperationType::Rollback,
            ExtensionOperationType::Repair => 'extensions.repair',
            ExtensionOperationType::Uninstall => 'extensions.uninstall',
            default => throw new \InvalidArgumentException('Unsupported installed-extension operation.'),
        };

        $this->authorize($capability, $actorId);

        if (
            $this->catalog->isCoreModule($extensionId)
            && in_array($type, [ExtensionOperationType::Deactivate, ExtensionOperationType::Uninstall], true)
        ) {
            throw new \InvalidArgumentException(
                'Core modules cannot be deactivated or uninstalled from Extension Center.'
            );
        }

        $operation = $this->operations->run(
            new ExtensionOperationRequest(
                $type,
                $extensionId,
                recoveryPointId: $recoveryPointId,
            ),
        );

        if (
            $type === ExtensionOperationType::Uninstall
            && $operation->status->value === 'succeeded'
            && $this->grants !== null
        ) {
            $this->grants->revoke($extensionId);
        }

        return $operation;
    }

    private function authorize(string $capability, ?int $actorId): void
    {
        $this->authorization->authorize(new AuthorizationRequest(
            $capability,
            $actorId,
        ));
    }
}
