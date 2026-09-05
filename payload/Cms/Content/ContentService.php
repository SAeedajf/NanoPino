<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Field\FieldEngine;
use App\com_pinoox_cms\Cms\Identity\UserLookupInterface;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;
use App\com_pinoox_cms\Cms\Taxonomy\TermRepositoryInterface;
use DateTimeImmutable;
use App\com_pinoox_cms\Cms\Revision\ContentRevisionRecorderInterface;
use App\com_pinoox_cms\Cms\Revision\RevisionKind;

final class ContentService
{
    public function __construct(
        private readonly ContentTypeRegistry $contentTypes,
        private readonly FieldEngine $fields,
        private readonly TaxonomyRegistry $taxonomies,
        private readonly ContentRepositoryInterface $repository,
        private readonly TermRepositoryInterface $terms,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly Slugger $slugger = new Slugger(),
        private readonly ContentWorkflow $workflow = new ContentWorkflow(),
        private readonly ?ContentRevisionRecorderInterface $revisions = null,
        private readonly ?UserLookupInterface $users = null,
    ) {}

    /**
     * @param array{
     *   site_id?:int,type:string,title:string,slug?:string,excerpt?:string,
     *   author_id?:int|null,parent_id?:int|null,locale?:string,
     *   fields?:array<string,mixed>,metadata?:array<string,mixed>
     * } $input
     */
    public function create(array $input, ?int $actorId = null, ?string $correlationId = null): ContentRecord
    {
        $type = $this->type((string)($input['type'] ?? ''));
        $siteId = max(1, (int)($input['site_id'] ?? 1));

        $this->authorization->authorize(new AuthorizationRequest(
            $type->permissions['create'],
            $actorId,
            ScopeType::Site,
            $siteId,
            'content_type',
            $type->identifier(),
        ));

        $title = $this->title((string)($input['title'] ?? ''));

        $locale = $this->locale((string)($input['locale'] ?? 'fa'));
        $prepared = $this->fields->prepare(
            $type,
            is_array($input['fields'] ?? null) ? $input['fields'] : [],
            false,
        );
        $this->assertRelations($prepared->relations, $siteId);
        $preparedTerms = $this->prepareTaxonomyAssignments(
            $type,
            $siteId,
            $locale,
            $prepared->taxonomies,
        );

        $candidate = trim((string)($input['slug'] ?? '')) ?: $title;
        $slug = $this->slugger->unique(
            $this->repository,
            $siteId,
            $type->identifier(),
            $locale,
            $candidate,
        );

        $parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
        $this->assertParent($type, $parentId, $siteId);

        $authorId = $this->resolveAuthorId($input, $actorId, $siteId);

        $mutation = new ContentMutation(
            $siteId,
            $type->identifier(),
            ContentStatus::Draft,
            $title,
            $slug,
            $this->excerpt((string)($input['excerpt'] ?? '')),
            $authorId,
            $parentId,
            $locale,
            $prepared->document,
            $this->metadata(is_array($input['metadata'] ?? null) ? $input['metadata'] : []),
            fields: $prepared->meta,
            relations: $prepared->relations,
            terms: $preparedTerms,
        );

        $record = $this->repository->create($mutation);
        $this->revisions?->record($record, RevisionKind::Initial, $actorId, $correlationId);

        $this->audit->log(
            'content.create',
            $type->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $siteId,
            'content',
            $record->id,
            $correlationId,
            [
                'content_type' => $record->type,
                'status' => $record->status->value,
                'locale' => $record->locale,
            ],
        );

        return $record;
    }

    /**
     * @param array{
     *   title?:string,slug?:string,excerpt?:string,parent_id?:int|null,
     *   locale?:string,fields?:array<string,mixed>,metadata?:array<string,mixed>
     * } $input
     */
    public function update(
        int $id,
        array $input,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ContentRecord {
        $current = $this->requireContent($id);
        $type = $this->type($current->type);

        $this->authorizeOwnedContent($type->permissions['update'], $current, $actorId);

        $title = array_key_exists('title', $input)
            ? $this->title((string)$input['title'])
            : $current->title;

        $locale = array_key_exists('locale', $input)
            ? $this->locale((string)$input['locale'])
            : $current->locale;

        $fieldInput = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $prepared = $this->fields->prepare($type, $fieldInput, true);
        $this->assertRelations($prepared->relations, $current->siteId, $current->id);

        $document = array_replace($current->document, $prepared->document);
        $metaFields = array_replace($current->fields, $prepared->meta);
        $relations = array_replace($current->relations, $prepared->relations);
        $terms = array_replace(
            $current->terms,
            $this->prepareTaxonomyAssignments(
                $type,
                $current->siteId,
                $locale,
                $prepared->taxonomies,
            ),
        );

        $candidate = array_key_exists('slug', $input)
            ? (string)$input['slug']
            : ($locale !== $current->locale ? $title : $current->slug);

        $slug = $this->slugger->unique(
            $this->repository,
            $current->siteId,
            $current->type,
            $locale,
            $candidate,
            $current->id,
        );

        $parentId = array_key_exists('parent_id', $input)
            ? ($input['parent_id'] !== null ? (int)$input['parent_id'] : null)
            : $current->parentId;

        $this->assertParent($type, $parentId, $current->siteId, $current->id);

        $mutation = new ContentMutation(
            $current->siteId,
            $current->type,
            $current->status,
            $title,
            $slug,
            array_key_exists('excerpt', $input)
                ? $this->excerpt((string)$input['excerpt'])
                : $current->excerpt,
            $current->authorId,
            $parentId,
            $locale,
            $document,
            array_key_exists('metadata', $input) && is_array($input['metadata'])
                ? $this->metadata(array_replace($current->metadata, $input['metadata']))
                : $current->metadata,
            $current->revisionId,
            $current->publishedAt,
            $current->scheduledAt,
            $metaFields,
            $relations,
            $terms,
        );

        $record = $this->repository->update($id, $mutation);
        $this->revisions?->record($record, RevisionKind::Manual, $actorId, $correlationId);

        $this->audit->log(
            'content.update',
            $type->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $record->siteId,
            'content',
            $record->id,
            $correlationId,
            ['content_type' => $record->type, 'status' => $record->status->value],
        );

        return $record;
    }

    public function publish(
        int $id,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ContentRecord {
        return $this->transition(
            $id,
            ContentStatus::Published,
            null,
            $actorId,
            $correlationId,
        );
    }

    public function schedule(
        int $id,
        string $publishAt,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ContentRecord {
        try {
            $date = new DateTimeImmutable($publishAt);
        } catch (\Throwable) {
            throw new ContentValidationException('Invalid schedule date.');
        }

        if ($date->getTimestamp() <= time()) {
            throw new ContentValidationException('Scheduled publication must be in the future.');
        }

        return $this->transition(
            $id,
            ContentStatus::Scheduled,
            $date->format(DATE_ATOM),
            $actorId,
            $correlationId,
        );
    }

    public function moveToTrash(
        int $id,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ContentRecord {
        return $this->transition(
            $id,
            ContentStatus::Trash,
            null,
            $actorId,
            $correlationId,
        );
    }

    public function restoreDraft(
        int $id,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ContentRecord {
        return $this->transition(
            $id,
            ContentStatus::Draft,
            null,
            $actorId,
            $correlationId,
        );
    }

    /** @param list<int> $termIds */
    public function assignTerms(
        int $contentId,
        string $taxonomyKey,
        array $termIds,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): ContentRecord {
        $content = $this->requireContent($contentId);
        $contentType = $this->type($content->type);
        $taxonomy = $this->taxonomies->definition($taxonomyKey);

        if ($taxonomy === null || !$taxonomy->supports($content->type)) {
            throw new ContentValidationException('Taxonomy is not available for this content type.');
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $contentType->permissions['update'],
            $actorId,
            ScopeType::Site,
            $content->siteId,
            'content',
            $content->id,
            $content->authorId,
        ));

        $termIds = array_values(array_unique(array_map('intval', $termIds)));
        if (count($termIds) > 200) {
            throw new ContentValidationException('Too many term assignments.');
        }

        $termsById = $this->terms->findMany($termIds);
        foreach ($termIds as $termId) {
            $term = $termsById[$termId] ?? null;
            if (
                $term === null
                || $term->siteId !== $content->siteId
                || $term->taxonomy !== $taxonomyKey
                || $term->locale !== $content->locale
            ) {
                throw new ContentValidationException('Invalid term assignment: ' . $termId);
            }
        }

        $this->terms->assignToContent($contentId, $taxonomyKey, $termIds);

        if ($this->repository instanceof InMemoryContentRepository) {
            $allTerms = $content->terms;
            $allTerms[$taxonomyKey] = array_values(array_unique(array_map('intval', $termIds)));
            $content = $this->repository->replaceTerms($contentId, $allTerms);
        } else {
            $content = $this->requireContent($contentId);
        }

        $this->revisions?->record($content, RevisionKind::Manual, $actorId, $correlationId);

        $this->audit->log(
            'content.terms.assign',
            $taxonomy->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $content->siteId,
            'content',
            $content->id,
            $correlationId,
            [
                'taxonomy' => $taxonomyKey,
                'term_ids' => array_values(array_unique(array_map('intval', $termIds))),
            ],
        );

        return $content;
    }

    public function find(int $id, ?int $actorId = null): ?ContentRecord
    {
        $record = $this->repository->find($id);
        if ($record === null) return null;

        $type = $this->type($record->type);
        $this->authorizeOwnedContent($type->permissions['read'], $record, $actorId);

        return $record;
    }

    /** @return list<ContentRecord> */
    public function search(ContentQuery $query, ?int $actorId = null): array
    {
        $permission = $query->type !== null
            ? $this->type($query->type)->permissions['read']
            : 'content.read';

        $this->authorization->authorize(new AuthorizationRequest(
            $permission,
            $actorId,
            ScopeType::Site,
            $query->siteId,
            'content_collection',
        ));

        if (!$this->mayManageOthers($actorId, $query->siteId)) {
            if ($actorId === null || $actorId < 1) {
                throw new AuthorizationDeniedException(new \App\com_pinoox_cms\Cms\Authorization\AuthorizationResult(
                    false,
                    'OWNER_SCOPE_REQUIRES_SUBJECT',
                    true,
                    true,
                ));
            }
            if ($query->authorId !== null && $query->authorId !== $actorId) {
                throw new AuthorizationDeniedException(new \App\com_pinoox_cms\Cms\Authorization\AuthorizationResult(
                    false,
                    'RESOURCE_OWNER_DENIED',
                    true,
                    true,
                ));
            }
            $query = new ContentQuery(
                siteId: $query->siteId,
                type: $query->type,
                status: $query->status,
                locale: $query->locale,
                authorId: $actorId,
                parentId: $query->parentId,
                search: $query->search,
                limit: $query->limit,
                offset: $query->offset,
                projection: $query->projection,
                beforeId: $query->beforeId,
            );
        }

        return $this->repository->search($query);
    }

    private function transition(
        int $id,
        ContentStatus $to,
        ?string $scheduledAt,
        ?int $actorId,
        ?string $correlationId,
    ): ContentRecord {
        $current = $this->requireContent($id);
        $type = $this->type($current->type);

        $permission = $to === ContentStatus::Trash
            ? $type->permissions['delete']
            : ($to === ContentStatus::Draft
                ? $type->permissions['update']
                : $type->permissions['publish']);

        $this->authorizeOwnedContent($permission, $current, $actorId);

        $this->workflow->assert($current->status, $to);

        $publishedAt = $current->publishedAt;
        if ($to === ContentStatus::Published) {
            $publishedAt = gmdate(DATE_ATOM);
            $scheduledAt = null;
        } elseif ($to !== ContentStatus::Scheduled) {
            $scheduledAt = null;
        }

        $mutation = new ContentMutation(
            $current->siteId,
            $current->type,
            $to,
            $current->title,
            $current->slug,
            $current->excerpt,
            $current->authorId,
            $current->parentId,
            $current->locale,
            $current->document,
            $current->metadata,
            $current->revisionId,
            $publishedAt,
            $scheduledAt,
            $current->fields,
            $current->relations,
            $current->terms,
        );

        $record = $this->repository->update($id, $mutation);

        if ($this->revisions !== null) {
            $kind = match ($to) {
                ContentStatus::Published => RevisionKind::Published,
                ContentStatus::Scheduled => RevisionKind::Scheduled,
                default => RevisionKind::Manual,
            };
            $this->revisions->record($record, $kind, $actorId, $correlationId);
        }

        $this->audit->log(
            'content.status',
            $type->owner(),
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $record->siteId,
            'content',
            $record->id,
            $correlationId,
            [
                'from' => $current->status->value,
                'to' => $to->value,
                'scheduled_at' => $scheduledAt,
            ],
        );

        return $record;
    }



    /** @param array<string,list<int>> $relations */
    private function assertRelations(
        array $relations,
        int $siteId,
        ?int $currentId = null,
    ): void {
        $targetIds = [];
        foreach ($relations as $ids) {
            foreach ($ids as $id) {
                $targetIds[] = (int) $id;
            }
        }

        $targets = $this->repository->findMany(
            array_values(array_unique($targetIds)),
            ContentProjection::List,
        );

        foreach ($relations as $fieldKey => $fieldTargetIds) {
            $definition = $this->fieldsDefinition($fieldKey);
            $targetTypes = is_array($definition->options['target_types'] ?? null)
                ? $definition->options['target_types']
                : [];

            foreach ($fieldTargetIds as $targetId) {
                if ($currentId !== null && $targetId === $currentId) {
                    throw new ContentValidationException(
                        'Content relation cannot target itself: ' . $fieldKey
                    );
                }

                $target = $targets[(int) $targetId] ?? null;
                if ($target === null || $target->siteId !== $siteId) {
                    throw new ContentValidationException(
                        'Invalid related content target: ' . $targetId
                    );
                }

                if ($targetTypes !== [] && !in_array($target->type, $targetTypes, true)) {
                    throw new ContentValidationException(
                        'Related content target type is not allowed: ' . $target->type
                    );
                }
            }
        }
    }

    /**
     * @param array<string,list<int>> $prepared Field key => term ids
     * @return array<string,list<int>> Taxonomy key => validated term ids
     */
    private function prepareTaxonomyAssignments(
        ContentTypeDefinition $contentType,
        int $siteId,
        string $locale,
        array $prepared,
    ): array {
        $assignments = [];

        foreach ($prepared as $fieldKey => $termIds) {
            $field = $this->fieldsDefinition($fieldKey);
            $taxonomyKey = (string)($field->options['taxonomy'] ?? '');
            $taxonomy = $this->taxonomies->definition($taxonomyKey);

            if (
                $taxonomy === null
                || !in_array($taxonomyKey, $contentType->taxonomies, true)
                || !$taxonomy->supports($contentType->identifier())
            ) {
                throw new ContentValidationException(
                    'Taxonomy field is not compatible with content type: ' . $fieldKey
                );
            }

            $validated = [];
            $normalizedTermIds = array_values(array_unique(array_map('intval', $termIds)));
            $termsById = $this->terms->findMany($normalizedTermIds);
            foreach ($normalizedTermIds as $termId) {
                $term = $termsById[$termId] ?? null;
                if (
                    $term === null
                    || $term->siteId !== $siteId
                    || $term->taxonomy !== $taxonomyKey
                    || $term->locale !== $locale
                ) {
                    throw new ContentValidationException(
                        'Invalid taxonomy-field term: ' . $termId
                    );
                }
                $validated[] = $termId;
            }

            $assignments[$taxonomyKey] = $validated;
        }

        return $assignments;
    }

    private function fieldsDefinition(string $fieldKey): \App\com_pinoox_cms\Cms\Field\FieldDefinition
    {
        $definition = $this->fields->definition($fieldKey);
        if ($definition === null) {
            throw new ContentValidationException('Field is not registered: ' . $fieldKey);
        }
        return $definition;
    }

    /** @param array<string,mixed> $input */
    private function resolveAuthorId(array $input, ?int $actorId, int $siteId): ?int
    {
        if (!array_key_exists('author_id', $input)) {
            return $actorId;
        }

        if ($input['author_id'] === null || $input['author_id'] === '') {
            return $actorId;
        }

        $authorId = (int)$input['author_id'];
        if ($authorId < 1) {
            throw new ContentValidationException('Content author id must be a positive user id.');
        }

        if ($actorId === null || $authorId !== $actorId) {
            $this->authorization->authorize(new AuthorizationRequest(
                'content.assign_author',
                $actorId,
                ScopeType::Site,
                $siteId,
                'user',
                $authorId,
                $authorId,
            ));
        }

        if ($this->users !== null && !$this->users->exists($authorId)) {
            throw new ContentValidationException('Content author does not exist.');
        }

        return $authorId;
    }

    private function authorizeOwnedContent(string $capability, ContentRecord $content, ?int $actorId): void
    {
        $this->authorization->authorize(new AuthorizationRequest(
            $capability,
            $actorId,
            ScopeType::Site,
            $content->siteId,
            'content',
            $content->id,
            $content->authorId,
        ));

        if ($actorId !== $content->authorId) {
            $this->authorization->authorize(new AuthorizationRequest(
                'content.manage_others',
                $actorId,
                ScopeType::Site,
                $content->siteId,
                'content',
                $content->id,
                $content->authorId,
            ));
        }
    }

    private function mayManageOthers(?int $actorId, int $siteId): bool
    {
        return $this->authorization->can(new AuthorizationRequest(
            'content.manage_others',
            $actorId,
            ScopeType::Site,
            $siteId,
            'content_collection',
        ));
    }

    private function type(string $key): ContentTypeDefinition
    {
        $type = $this->contentTypes->definition($key);
        if ($type === null) {
            throw new ContentValidationException('Content type is not registered: ' . $key);
        }
        return $type;
    }

    private function requireContent(int $id): ContentRecord
    {
        $record = $this->repository->find($id);
        if ($record === null) {
            throw new ContentValidationException('Content not found: ' . $id);
        }
        return $record;
    }

    private function title(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            throw new ContentValidationException('Content title is required.');
        }

        if ($this->length($title) > 255) {
            throw new ContentValidationException('Content title exceeds 255 characters.');
        }

        return $title;
    }

    private function excerpt(string $excerpt): string
    {
        $excerpt = trim($excerpt);
        if ($this->length($excerpt) > 5000) {
            throw new ContentValidationException('Content excerpt exceeds 5000 characters.');
        }
        return $excerpt;
    }

    /** @param array<string,mixed> $metadata */
    private function metadata(array $metadata): array
    {
        try {
            $encoded = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException) {
            throw new ContentValidationException('Content metadata is not JSON serializable.');
        }

        if (strlen($encoded) > 262144) {
            throw new ContentValidationException('Content metadata exceeds 256 KiB.');
        }

        return $metadata;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function locale(string $locale): string
    {
        $locale = trim($locale);
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new ContentValidationException('Invalid content locale.');
        }
        return $locale;
    }

    private function assertParent(
        ContentTypeDefinition $type,
        ?int $parentId,
        int $siteId,
        ?int $currentId = null,
    ): void {
        if ($parentId === null) return;

        if (!$type->hierarchical) {
            throw new ContentValidationException('Content type does not support parents.');
        }

        if ($currentId !== null && $parentId === $currentId) {
            throw new ContentValidationException('Content cannot be its own parent.');
        }

        $parent = $this->repository->find($parentId, ContentProjection::List);
        if (
            $parent === null
            || $parent->siteId !== $siteId
            || $parent->type !== $type->identifier()
        ) {
            throw new ContentValidationException('Invalid content parent.');
        }

        $seen = [];
        while ($parent !== null && $parent->parentId !== null) {
            if (isset($seen[$parent->id]) || ($currentId !== null && $parent->parentId === $currentId)) {
                throw new ContentValidationException('Content hierarchy cycle detected.');
            }
            $seen[$parent->id] = true;
            $parent = $this->repository->find($parent->parentId, ContentProjection::List);
        }
    }
}
