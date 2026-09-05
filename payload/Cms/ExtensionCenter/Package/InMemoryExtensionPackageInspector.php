<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;

final readonly class InMemoryExtensionPackageInspector implements ExtensionPackageInspectorInterface
{
    public function __construct(private ExtensionPackageInspection $inspection) {}

    public function inspect(ExtensionPackageReference $package): ExtensionPackageInspection
    {
        return new ExtensionPackageInspection(
            $this->inspection->manifest,
            $this->inspection->trust,
            $package->displayName,
            $package->size,
            hash_file('sha256', $package->localPath)
                ?: throw new \RuntimeException('Unable to hash package.'),
        );
    }
}
