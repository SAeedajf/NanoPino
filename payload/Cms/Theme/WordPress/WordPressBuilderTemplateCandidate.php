<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use App\com_pinoox_cms\Cms\Builder\BuilderTarget;

final readonly class WordPressBuilderTemplateCandidate
{
    public function __construct(
        public BuilderTarget $target,
        public string $kind,
        public string $logicalName,
        public string $sourcePath,
        /** @var array<string,mixed> */
        public array $document,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'target' => [
                'site_id' => $this->target->siteId,
                'type' => $this->target->type->value,
                'key' => $this->target->key,
                'locale' => $this->target->locale,
                'identifier' => $this->target->identifier(),
            ],
            'kind' => $this->kind,
            'logical_name' => $this->logicalName,
            'source_path' => $this->sourcePath,
            'document' => $this->document,
        ];
    }
}
