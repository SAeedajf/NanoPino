<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

interface RoleTemplateProvisionerInterface
{
    public function provision(RoleTemplateDefinition $template): void;
}
