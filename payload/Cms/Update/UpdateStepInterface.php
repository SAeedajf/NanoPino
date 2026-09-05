<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Update;

interface UpdateStepInterface
{
    public function id(): string;
    public function execute(UpdateContext $context): void;
    public function rollback(UpdateContext $context): void;
}
