<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use Pinoox\Model\RoleModel;
use Pinoox\Portal\Access;
use RuntimeException;

final class PinooxRoleTemplateProvisioner implements RoleTemplateProvisionerInterface
{
    public function provision(RoleTemplateDefinition $template): void
    {
        $role = RoleModel::firstOrCreate(
            ['role_key' => $template->identifier()],
            ['name' => $template->name, 'description' => $template->description],
        );

        if (!$role) {
            throw new RuntimeException('Unable to provision role template: ' . $template->identifier());
        }

        foreach ($template->capabilities as $capability) {
            if (!Access::givePermissionToRole($template->identifier(), $capability)) {
                throw new RuntimeException(sprintf(
                    'Unable to attach capability "%s" to role "%s".',
                    $capability,
                    $template->identifier(),
                ));
            }
        }
    }
}
