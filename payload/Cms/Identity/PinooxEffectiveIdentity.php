<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Capability\CapabilityRegistry;

/**
 * Publishes the exact capabilities the native Pinoox gate allows for the
 * current actor. Native platform-super accounts intentionally have no role
 * rows, so Auth::clientUser() can legitimately contain an empty ability list
 * even while Access::can() allows the operation.
 */
final class PinooxEffectiveIdentity
{
    public static function withEffectiveAbilities(
        ?array $user,
        CapabilityRegistry $capabilities,
        AuthorizationManager $authorization,
    ): ?array {
        if (!is_array($user)) {
            return null;
        }

        $effective = [];
        foreach ($capabilities->definitions() as $definition) {
            try {
                if ($authorization->can(new AuthorizationRequest($definition->identifier()))) {
                    $effective[] = $definition->identifier();
                }
            } catch (\Throwable) {
                // A diagnostic envelope must never break the admin page.
            }
        }

        $user['abilities'] = array_values(array_unique($effective));
        return $user;
    }
}
