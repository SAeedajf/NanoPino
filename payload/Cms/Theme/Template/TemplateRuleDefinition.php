<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class TemplateRuleDefinition implements OwnedDefinitionInterface
{
    /**
     * @param list<string> $patterns
     */
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $kind,
        public array $patterns,
        public int $priority = 100,
    ) {}

    public function identifier(): string { return $this->id; }
    public function owner(): string { return $this->ownerId; }
}
