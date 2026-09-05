<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Exception;

use App\com_pinoox_cms\Cms\Lifecycle\ExtensionState;
use LogicException;

final class InvalidLifecycleTransitionException extends LogicException
{
    public function __construct(public readonly ExtensionState $from, public readonly ExtensionState $to)
    {
        parent::__construct(sprintf('Invalid extension lifecycle transition: %s -> %s', $from->value, $to->value));
    }
}
