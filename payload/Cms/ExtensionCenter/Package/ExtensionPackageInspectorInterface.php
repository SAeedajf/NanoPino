<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Package;

interface ExtensionPackageInspectorInterface
{
    /**
     * Production adapter must use canonical PINX Reader/Verifier and CMS manifest enrichment.
     */
    public function inspect(ExtensionPackageReference $package): ExtensionPackageInspection;
}
