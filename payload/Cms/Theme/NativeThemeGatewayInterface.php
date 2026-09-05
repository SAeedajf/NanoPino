<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

interface NativeThemeGatewayInterface
{
    /** @return list<NativeThemeDescriptor> */
    public function discover(string $package): array;

    public function find(string $package, string $themeName): ?NativeThemeDescriptor;

    public function stack(string $package, ?string $context = null): NativeThemeStack;
}
