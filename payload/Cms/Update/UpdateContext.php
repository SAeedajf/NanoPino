<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Update;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionLifecycleStateMachine;

final class UpdateContext
{
    /** @var array<string,mixed> */
    public array $data = [];

    public function __construct(
        public readonly string $extensionId,
        public readonly ExtensionLifecycleStateMachine $lifecycle,
    ) {}
}
