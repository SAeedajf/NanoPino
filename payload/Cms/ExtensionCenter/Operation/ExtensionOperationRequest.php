<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageInspection;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageReference;

final readonly class ExtensionOperationRequest
{
    /** @param array<string,mixed> $options */
    public function __construct(
        public ExtensionOperationType $type,
        public string $extensionId,
        public ?ExtensionPackageReference $package = null,
        public ?ExtensionPackageInspection $inspection = null,
        public ?string $recoveryPointId = null,
        public array $options = [],
    ) {
        if (
            $extensionId === ''
            || strlen($extensionId) > 190
            || preg_match('#^[A-Za-z0-9][A-Za-z0-9._:/-]{0,189}$#', $extensionId) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid extension operation target.');
        }
    }
}
