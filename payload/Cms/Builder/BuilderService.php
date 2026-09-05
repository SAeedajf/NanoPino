<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionKind;
use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionRecord;
use App\com_pinoox_cms\Cms\Builder\Revision\BuilderRevisionRepositoryInterface;
use App\com_pinoox_cms\Cms\Builder\Transaction\BuilderTransactionInterface;
use App\com_pinoox_cms\Cms\Builder\Transaction\DirectBuilderTransaction;

final class BuilderService
{
    public function __construct(
        private readonly BuilderDocumentRepositoryInterface $documents,
        private readonly BuilderRevisionRepositoryInterface $revisions,
        private readonly BlockDocumentLoader $loader,
        private readonly BlockDocumentSerializer $serializer,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly BuilderTransactionInterface $transaction = new DirectBuilderTransaction(),
    ) {}

    /** @param array<string,mixed> $rawDocument */
    public function create(
        BuilderTarget $target,
        array $rawDocument,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): BuilderDocumentRecord {
        $this->authorize('builder.edit', $target->siteId, $actorId, 'builder_target', $target->identifier());

        $document = $this->loader->fromArray($rawDocument);
        $checksum = $this->serializer->checksum($document);

        $record = $this->transaction->run(function () use (
            $target,
            $document,
            $checksum,
            $actorId,
        ): BuilderDocumentRecord {
            $record = $this->documents->create($target, $document, $checksum, $actorId);

            $this->revisions->append(
                $record->id,
                BuilderRevisionKind::Initial,
                $record->document,
                $record->checksum,
                $actorId,
            );

            return $record;
        });

        $this->audit->log(
            'builder.create',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $target->siteId,
            'builder',
            $record->id,
            $correlationId,
            ['target' => $target->identifier(), 'checksum' => $checksum],
        );

        return $record;
    }

    public function findByTarget(BuilderTarget $target, ?int $actorId = null): ?BuilderDocumentRecord
    {
        $record=$this->documents->findByTarget($target);
        if($record===null)return null;
        $this->authorize('builder.read',$record->target->siteId,$actorId,'builder',$record->id);
        return $record;
    }

    public function find(int $id, ?int $actorId = null): ?BuilderDocumentRecord
    {
        $record = $this->documents->find($id);
        if ($record === null) return null;

        $this->authorize('builder.read', $record->target->siteId, $actorId, 'builder', $record->id);
        return $record;
    }

    /** @param array<string,mixed> $rawDocument */
    public function save(
        int $id,
        array $rawDocument,
        int $expectedVersion,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): BuilderDocumentRecord {
        $current = $this->requireDocument($id);
        $this->authorize('builder.edit', $current->target->siteId, $actorId, 'builder', $id);

        $document = $this->loader->fromArray($rawDocument);
        $checksum = $this->serializer->checksum($document);

        $saved = $this->transaction->run(function () use (
            $id,
            $document,
            $checksum,
            $expectedVersion,
            $actorId,
            $current,
        ): BuilderDocumentRecord {
            $saved = $this->documents->save(
                $id,
                $document,
                $checksum,
                $expectedVersion,
                BuilderStatus::Draft,
                $actorId,
                $current->publishedAt,
            );

            $this->revisions->append(
                $id,
                BuilderRevisionKind::Manual,
                $saved->document,
                $saved->checksum,
                $actorId,
            );

            return $saved;
        });

        $this->audit->log(
            'builder.save',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $saved->target->siteId,
            'builder',
            $saved->id,
            $correlationId,
            ['version' => $saved->version, 'checksum' => $saved->checksum],
        );

        return $saved;
    }

    /** @param array<string,mixed> $rawDocument */
    public function autosave(
        int $id,
        array $rawDocument,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): BuilderRevisionRecord {
        $current = $this->requireDocument($id);
        $this->authorize('builder.edit', $current->target->siteId, $actorId, 'builder', $id);

        $document = $this->loader->fromArray($rawDocument);
        $checksum = $this->serializer->checksum($document);

        $latest = $this->revisions->latestAutosave($id, $actorId);
        if ($latest !== null && hash_equals($latest->checksum, $checksum)) {
            return $latest;
        }

        $revision = $this->revisions->append(
            $id,
            BuilderRevisionKind::Autosave,
            $document,
            $checksum,
            $actorId,
        );

        $this->audit->log(
            'builder.autosave',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $current->target->siteId,
            'builder',
            $id,
            $correlationId,
            ['revision_id' => $revision->id, 'checksum' => $checksum],
        );

        return $revision;
    }

    public function publish(
        int $id,
        int $expectedVersion,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): BuilderDocumentRecord {
        $current = $this->requireDocument($id);
        $this->authorize('builder.publish', $current->target->siteId, $actorId, 'builder', $id);

        $publishedAt = gmdate('Y-m-d H:i:s');

        $result = $this->transaction->run(function () use (
            $id,
            $expectedVersion,
            $actorId,
            $current,
            $publishedAt,
        ): array {
            $saved = $this->documents->save(
                $id,
                $current->document,
                $current->checksum,
                $expectedVersion,
                BuilderStatus::Published,
                $actorId,
                $publishedAt,
            );

            $revision = $this->revisions->append(
                $id,
                BuilderRevisionKind::Published,
                $saved->document,
                $saved->checksum,
                $actorId,
            );

            return [$saved, $revision];
        });

        /** @var BuilderDocumentRecord $saved */
        [$saved, $revision] = $result;

        $this->audit->log(
            'builder.publish',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $saved->target->siteId,
            'builder',
            $id,
            $correlationId,
            ['revision_id' => $revision->id, 'checksum' => $saved->checksum],
        );

        return $saved;
    }

    public function restore(
        int $id,
        int $revisionId,
        int $expectedVersion,
        ?int $actorId = null,
        ?string $correlationId = null,
    ): BuilderDocumentRecord {
        $current = $this->requireDocument($id);
        $this->authorize('builder.edit', $current->target->siteId, $actorId, 'builder', $id);

        $revision = $this->revisions->find($revisionId)
            ?? throw new \RuntimeException('Builder revision not found.');

        if ($revision->builderId !== $id) {
            throw new \RuntimeException('Builder revision belongs to another document.');
        }

        // Re-run migrations/validation before old revision data becomes canonical.
        $document = $this->loader->fromArray($revision->document->toArray());
        $checksum = $this->serializer->checksum($document);

        $result = $this->transaction->run(function () use (
            $id,
            $revisionId,
            $expectedVersion,
            $actorId,
            $current,
            $document,
            $checksum,
        ): array {
            $preRestore = $this->revisions->append(
                $id,
                BuilderRevisionKind::PreRestore,
                $current->document,
                $current->checksum,
                $actorId,
                $revisionId,
            );

            $saved = $this->documents->save(
                $id,
                $document,
                $checksum,
                $expectedVersion,
                BuilderStatus::Draft,
                $actorId,
                $current->publishedAt,
            );

            $restored = $this->revisions->append(
                $id,
                BuilderRevisionKind::Restored,
                $saved->document,
                $saved->checksum,
                $actorId,
                $revisionId,
            );

            return [$saved, $preRestore, $restored];
        });

        /** @var BuilderDocumentRecord $saved */
        [$saved, $preRestore, $restored] = $result;

        $this->audit->log(
            'builder.restore',
            'cms.core',
            AuditOutcome::Success,
            $actorId,
            ScopeType::Site,
            $saved->target->siteId,
            'builder',
            $id,
            $correlationId,
            [
                'source_revision_id' => $revisionId,
                'pre_restore_revision_id' => $preRestore->id,
                'revision_id' => $restored->id,
            ],
        );

        return $saved;
    }

    /** @return list<BuilderDocumentRecord> */
    public function listDocuments(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
        int $limit = 100,
        int $offset = 0,
        ?int $actorId = null,
    ): array {
        $this->authorize('builder.read', $siteId, $actorId, 'builder_target', '*');
        return $this->documents->list($siteId, $type, $locale, max(1, min(200, $limit)), max(0, $offset));
    }

    public function countDocuments(
        int $siteId,
        ?BuilderTargetType $type = null,
        ?string $locale = null,
        ?int $actorId = null,
    ): int {
        $this->authorize('builder.read', $siteId, $actorId, 'builder_target', '*');
        return $this->documents->count($siteId, $type, $locale);
    }

    /** @return list<BuilderRevisionRecord> */
    public function history(int $id, int $limit = 100, ?int $actorId = null): array
    {
        $record = $this->requireDocument($id);
        $this->authorize('builder.read', $record->target->siteId, $actorId, 'builder', $id);

        return $this->revisions->forBuilder($id, $limit);
    }

    public function published(int $id, ?int $actorId = null): ?BuilderRevisionRecord
    {
        $record = $this->requireDocument($id);
        $this->authorize('builder.read', $record->target->siteId, $actorId, 'builder', $id);

        return $this->revisions->latestPublished($id);
    }

    private function requireDocument(int $id): BuilderDocumentRecord
    {
        return $this->documents->find($id)
            ?? throw new \RuntimeException('Builder document not found.');
    }

    private function authorize(
        string $capability,
        int $siteId,
        ?int $actorId,
        string $targetType,
        string|int|null $targetId,
    ): void {
        $this->authorization->authorize(new AuthorizationRequest(
            $capability,
            $actorId,
            ScopeType::Site,
            $siteId,
            $targetType,
            $targetId,
        ));
    }
}
