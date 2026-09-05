<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

final readonly class PreparedFieldValues
{
    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $meta
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $taxonomies Field key => term ids
     */
    public function __construct(
        public array $document = [],
        public array $meta = [],
        public array $relations = [],
        public array $taxonomies = [],
    ) {}
}
