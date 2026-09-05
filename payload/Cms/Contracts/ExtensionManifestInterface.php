<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Contracts;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Manifest\ExtensionRequirementSet;

interface ExtensionManifestInterface
{
    public function schemaVersion(): int;
    public function identifier(): string;
    public function owner(): string;
    public function package(): string;
    public function extensionType(): ExtensionType;
    public function version(): string;
    public function versionCode(): int;
    public function publisher(): string;
    public function requirements(): ExtensionRequirementSet;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
