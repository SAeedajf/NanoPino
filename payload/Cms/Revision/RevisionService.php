<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Content\ContentMutation;
use App\com_pinoox_cms\Cms\Content\ContentRepositoryInterface;
use App\com_pinoox_cms\Cms\Content\ContentStatus;
use App\com_pinoox_cms\Cms\Content\ContentTypeRegistry;
use App\com_pinoox_cms\Cms\Content\ContentValidationException;
use App\com_pinoox_cms\Cms\Field\FieldEngine;
use App\com_pinoox_cms\Cms\Field\FieldStorageStrategy;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;
use App\com_pinoox_cms\Cms\Taxonomy\TermRepositoryInterface;

final class RevisionService implements ContentRevisionRecorderInterface
{
    public function __construct(
        private readonly RevisionRepositoryInterface $revisions,
        private readonly ContentRepositoryInterface $content,
        private readonly ContentTypeRegistry $contentTypes,
        private readonly FieldEngine $fields,
        private readonly TaxonomyRegistry $taxonomies,
        private readonly TermRepositoryInterface $terms,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly RevisionChecksum $checksum = new RevisionChecksum(),
        private readonly RevisionComparer $comparer = new RevisionComparer(),
    ) {}

    public function record(
        \App\com_pinoox_cms\Cms\Content\ContentRecord $content,
        RevisionKind $kind,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): RevisionRecord {
        $snapshot = RevisionSnapshot::fromContent($content);
        $record = $this->revisions->append(
            $snapshot,
            $kind,
            $this->checksum->make($snapshot),
            $actorId,
            $content->revisionId,
        );

        $this->audit->log(
            'revision.capture',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $content->siteId,
            'content_revision',
            $record->id,
            $correlationId,
            [
                'content_id' => $content->id,
                'kind' => $kind->value,
                'checksum' => $record->checksum,
                'schema_version' => $snapshot->schemaVersion,
            ],
        );

        return $record;
    }

    /**
     * Autosave receives a complete editor snapshot. It is stored as a revision only;
     * canonical content is never mutated by this method.
     *
     * @param array<string,mixed> $document
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $fields
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $terms
     */
    public function autosave(
        int $contentId,
        array $document,
        array $metadata,
        array $fields,
        array $relations,
        array $terms,
        string $title,
        string $excerpt,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): RevisionRecord {
        $current = $this->content->find($contentId)
            ?? throw new ContentValidationException('Content not found: ' . $contentId);

        $type = $this->contentTypes->definition($current->type)
            ?? throw new ContentValidationException('Content type not registered.');

        $this->authorizeOwnedContent($type->permissions['update'], $current->siteId, $current->id, $current->authorId, $actorId);

        $validatedPayload = $this->validateSnapshotPayload(
            $current,
            $document,
            $metadata,
            $fields,
            $relations,
            $terms,
            $title,
            $excerpt,
        );

        $snapshot = new RevisionSnapshot(
            $current->id,
            $current->siteId,
            $current->type,
            $current->status->value,
            $validatedPayload['title'],
            $current->slug,
            $validatedPayload['excerpt'],
            $current->authorId,
            $current->parentId,
            $current->locale,
            $validatedPayload['document'],
            $validatedPayload['metadata'],
            $validatedPayload['fields'],
            $validatedPayload['relations'],
            $validatedPayload['terms'],
            $current->publishedAt,
            $current->scheduledAt,
        );

        $hash = $this->checksum->make($snapshot);
        $latest = $this->revisions->latestAutosave($contentId, $actorId);
        if ($latest !== null) {
            $this->verify($latest);
        }
        if ($latest !== null && hash_equals($latest->checksum, $hash)) {
            return $latest;
        }

        $record = $this->revisions->append(
            $snapshot,
            RevisionKind::Autosave,
            $hash,
            $actorId,
            $current->revisionId,
        );

        $this->audit->log(
            'revision.autosave',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $current->siteId,
            'content_revision',
            $record->id,
            $correlationId,
            ['content_id' => $contentId, 'checksum' => $hash],
        );

        return $record;
    }

    public function revisionForContent(
        int $contentId,
        int $revisionId,
        ?int $actorId = null,
    ): RevisionRecord {
        $current = $this->content->find($contentId)
            ?? throw new ContentValidationException('Content not found.');

        $type = $this->contentTypes->definition($current->type)
            ?? throw new ContentValidationException('Content type not registered.');

        $this->authorizeOwnedContent(
            $type->permissions['read'],
            $current->siteId,
            $current->id,
            $current->authorId,
            $actorId,
        );

        $revision = $this->revisions->find($revisionId)
            ?? throw new ContentValidationException('Revision not found: ' . $revisionId);

        $this->verify($revision);
        if ($revision->snapshot->contentId !== $contentId) {
            throw new ContentValidationException('Revision does not belong to requested content.');
        }

        return $revision;
    }

    public function compare(int $fromRevisionId, int $toRevisionId, ?int $actorId = null): RevisionDiff
    {
        $from = $this->revisions->find($fromRevisionId)
            ?? throw new ContentValidationException('Revision not found: ' . $fromRevisionId);
        $to = $this->revisions->find($toRevisionId)
            ?? throw new ContentValidationException('Revision not found: ' . $toRevisionId);

        $this->verify($from);
        $this->verify($to);

        if ($from->snapshot->contentId !== $to->snapshot->contentId) {
            throw new ContentValidationException('Cannot compare revisions from different content.');
        }

        $type = $this->contentTypes->definition($from->snapshot->contentType)
            ?? throw new ContentValidationException('Content type not registered.');

        $this->authorizeOwnedContent($type->permissions['read'], $from->snapshot->siteId, $from->snapshot->contentId, $from->snapshot->authorId, $actorId);

        return $this->comparer->compare($from, $to);
    }

    public function restore(
        int $revisionId,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): \App\com_pinoox_cms\Cms\Content\ContentRecord {
        $revision = $this->revisions->find($revisionId)
            ?? throw new ContentValidationException('Revision not found: ' . $revisionId);

        return $this->restoreResolvedRevision(
            $revision->snapshot->contentId,
            $revision,
            $actorId,
            $correlationId,
        );
    }

    public function restoreForContent(
        int $contentId,
        int $revisionId,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): \App\com_pinoox_cms\Cms\Content\ContentRecord {
        $revision = $this->revisions->find($revisionId)
            ?? throw new ContentValidationException('Revision not found: ' . $revisionId);

        if ($revision->snapshot->contentId !== $contentId) {
            throw new ContentValidationException('Revision does not belong to requested content.');
        }

        return $this->restoreResolvedRevision($contentId, $revision, $actorId, $correlationId);
    }

    private function authorizeOwnedContent(
        string $capability,
        int $siteId,
        int $contentId,
        ?int $ownerId,
        ?int $actorId,
    ): void {
        $this->authorization->authorize(new AuthorizationRequest(
            $capability,
            $actorId,
            ScopeType::Site,
            $siteId,
            'content',
            $contentId,
            $ownerId,
        ));

        if ($actorId !== $ownerId) {
            $this->authorization->authorize(new AuthorizationRequest(
                'content.manage_others',
                $actorId,
                ScopeType::Site,
                $siteId,
                'content',
                $contentId,
                $ownerId,
            ));
        }
    }

    private function restoreResolvedRevision(
        int $contentId,
        RevisionRecord $revision,
        ?int $actorId,
        ?string $correlationId,
    ): \App\com_pinoox_cms\Cms\Content\ContentRecord {
        $this->verify($revision);

        if ($revision->snapshot->contentId !== $contentId) {
            throw new ContentValidationException('Revision does not belong to requested content.');
        }

        $current = $this->content->find($contentId)
            ?? throw new ContentValidationException('Content not found.');

        $type = $this->contentTypes->definition($current->type)
            ?? throw new ContentValidationException('Content type not registered.');

        $this->authorizeOwnedContent($type->permissions['update'], $current->siteId, $current->id, $current->authorId, $actorId);

        if ($revision->kind === RevisionKind::Autosave) {
            throw new ContentValidationException(
                'Autosave revisions must be applied through the validated editor update flow.'
            );
        }

        $this->record($current, RevisionKind::PreRestore, $actorId, $correlationId);

        $snapshot = $revision->snapshot;
        $validatedPayload = $this->validateSnapshotPayload(
            $current,
            $snapshot->document,
            $snapshot->metadata,
            $snapshot->fields,
            $snapshot->relations,
            $snapshot->terms,
            $snapshot->title,
            $snapshot->excerpt,
        );

        $mutation = new ContentMutation(
            $current->siteId,
            $current->type,
            $current->status,
            $validatedPayload['title'],
            $current->slug,
            $validatedPayload['excerpt'],
            $current->authorId,
            $current->parentId,
            $current->locale,
            $validatedPayload['document'],
            $validatedPayload['metadata'],
            $revision->id,
            $current->publishedAt,
            $current->scheduledAt,
            $validatedPayload['fields'],
            $validatedPayload['relations'],
            $validatedPayload['terms'],
        );

        $restored = $this->content->update($current->id, $mutation);
        $this->record($restored, RevisionKind::Restored, $actorId, $correlationId);

        $this->audit->log(
            'revision.restore',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $restored->siteId,
            'content',
            $restored->id,
            $correlationId,
            ['revision_id' => $revision->id],
        );

        return $restored;
    }

    /**
     * @param array<string,mixed> $document
     * @param array<string,mixed> $metadata
     * @param array<string,mixed> $metaFields
     * @param array<string,list<int>> $relations
     * @param array<string,list<int>> $terms
     * @return array{
     *   title:string,excerpt:string,document:array<string,mixed>,
     *   metadata:array<string,mixed>,fields:array<string,mixed>,
     *   relations:array<string,list<int>>,terms:array<string,list<int>>
     * }
     */
    private function validateSnapshotPayload(
        \App\com_pinoox_cms\Cms\Content\ContentRecord $current,
        array $document,
        array $metadata,
        array $metaFields,
        array $relations,
        array $terms,
        string $title,
        string $excerpt,
    ): array {
        $type = $this->contentTypes->definition($current->type)
            ?? throw new ContentValidationException('Content type not registered.');

        $title = trim($title);
        $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
        if ($title === '' || $titleLength > 255) {
            throw new ContentValidationException('Invalid revision title.');
        }

        $excerptLength = function_exists('mb_strlen') ? mb_strlen($excerpt) : strlen($excerpt);
        if ($excerptLength > 5000) {
            throw new ContentValidationException('Revision excerpt exceeds maximum length.');
        }

        $logical = [];
        foreach ($type->fields as $fieldKey) {
            $definition = $this->fields->definition($fieldKey);
            if ($definition === null) {
                throw new ContentValidationException('Revision references missing field definition.');
            }

            $source = match ($definition->storage) {
                FieldStorageStrategy::Document => $document,
                FieldStorageStrategy::Meta => $metaFields,
                default => [],
            };

            if (array_key_exists($fieldKey, $source)) {
                $logical[$fieldKey] = $source[$fieldKey];
            }
        }

        $prepared = $this->fields->prepare($type, $logical, true);

        $validatedRelations = [];
        foreach ($relations as $fieldKey => $targetIds) {
            $definition = $this->fields->definition($fieldKey);
            if (
                $definition === null
                || $definition->storage !== FieldStorageStrategy::Relation
                || !in_array($fieldKey, $type->fields, true)
            ) {
                throw new ContentValidationException('Invalid revision relation field: ' . $fieldKey);
            }

            $targetTypes = is_array($definition->options['target_types'] ?? null)
                ? $definition->options['target_types']
                : [];

            $ids = array_values(array_unique(array_map('intval', $targetIds)));
            if (count($ids) > 100) {
                throw new ContentValidationException('Revision relation exceeds item limit.');
            }

            foreach ($ids as $targetId) {
                if ($targetId === $current->id) {
                    throw new ContentValidationException('Revision relation cannot target itself.');
                }
                $target = $this->content->find($targetId);
                if ($target === null || $target->siteId !== $current->siteId) {
                    throw new ContentValidationException('Revision contains invalid related content.');
                }
                if ($targetTypes !== [] && !in_array($target->type, $targetTypes, true)) {
                    throw new ContentValidationException('Revision related content type is invalid.');
                }
            }
            $validatedRelations[$fieldKey] = $ids;
        }

        $validatedTerms = [];
        foreach ($terms as $taxonomyKey => $termIds) {
            $taxonomy = $this->taxonomies->definition($taxonomyKey);
            if ($taxonomy === null || !$taxonomy->supports($current->type)) {
                throw new ContentValidationException('Revision taxonomy is incompatible.');
            }

            $ids = array_values(array_unique(array_map('intval', $termIds)));
            if (count($ids) > 200) {
                throw new ContentValidationException('Revision taxonomy exceeds item limit.');
            }

            foreach ($ids as $termId) {
                $term = $this->terms->find($termId);
                if (
                    $term === null
                    || $term->siteId !== $current->siteId
                    || $term->taxonomy !== $taxonomyKey
                    || $term->locale !== $current->locale
                ) {
                    throw new ContentValidationException('Revision contains invalid term reference.');
                }
            }
            $validatedTerms[$taxonomyKey] = $ids;
        }

        try {
            $metadataJson = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException) {
            throw new ContentValidationException('Revision metadata is invalid.');
        }
        if (strlen($metadataJson) > 262144) {
            throw new ContentValidationException('Revision metadata exceeds 256 KiB.');
        }

        $payloadJson = json_encode(
            [$prepared->document, $prepared->meta, $validatedRelations, $validatedTerms],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if (strlen($payloadJson) > 2_097_152) {
            throw new ContentValidationException('Revision payload exceeds 2 MiB.');
        }

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'document' => $prepared->document,
            'metadata' => $metadata,
            'fields' => $prepared->meta,
            'relations' => $validatedRelations,
            'terms' => $validatedTerms,
        ];
    }

    /** @return list<RevisionRecord> */
    public function history(int $contentId, ?int $actorId = null, int $limit = 100): array
    {
        $current = $this->content->find($contentId)
            ?? throw new ContentValidationException('Content not found.');

        $type = $this->contentTypes->definition($current->type)
            ?? throw new ContentValidationException('Content type not registered.');

        $this->authorizeOwnedContent($type->permissions['read'], $current->siteId, $current->id, $current->authorId, $actorId);

        $records = $this->revisions->forContent($contentId, $limit);
        foreach ($records as $record) {
            $this->verify($record);
        }

        return $records;
    }

    private function verify(RevisionRecord $record): void
    {
        $expected = $this->checksum->make($record->snapshot);
        if (!hash_equals($record->checksum, $expected)) {
            throw new RevisionIntegrityException(
                'Revision integrity check failed for revision #' . $record->id
            );
        }
    }
}
