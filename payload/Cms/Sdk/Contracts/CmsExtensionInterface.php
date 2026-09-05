<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Contracts;

use App\com_pinoox_cms\Cms\Sdk\ExtensionContext;

interface CmsExtensionInterface
{
    public function register(ExtensionContext $context): void;
}
