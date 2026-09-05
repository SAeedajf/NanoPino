<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Command;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocument;

interface BuilderCommandInterface
{
    public function name(): string;
    public function apply(BlockDocument $document): BlockDocument;
}
