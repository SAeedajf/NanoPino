<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\GlobalBlock;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;

final readonly class GlobalBlockService
{
    public function __construct(
        private GlobalBlockRepositoryInterface $repository,
        private BlockDocumentLoader $loader,
        private BlockDocumentSerializer $serializer,
        private AuthorizationManager $authorization,
    ) {}

    /** @param array<string,mixed> $raw */
    public function create(int $siteId, string $name, array $raw, ?int $actorId = null): GlobalBlockRecord
    {
        $this->authorize('builder.edit', $siteId, $actorId, 'global_block_collection', null);
        $name = $this->name($name);
        $document = $this->loader->fromArray($raw);
        return $this->repository->create(
            $siteId,
            $name,
            $document,
            $this->serializer->checksum($document),
            $actorId,
        );
    }

    /** @param array<string,mixed> $raw */
    public function update(
        int $id,
        string $name,
        array $raw,
        int $expectedVersion,
        ?int $actorId = null,
    ): GlobalBlockRecord {
        $current = $this->require($id);
        $this->authorize('builder.edit', $current->siteId, $actorId, 'global_block', $id);
        $document = $this->loader->fromArray($raw);

        return $this->repository->save(
            $id,
            $this->name($name),
            $document,
            $this->serializer->checksum($document),
            $expectedVersion,
            $actorId,
        );
    }

    public function find(int $id, ?int $actorId = null): ?GlobalBlockRecord
    {
        $record = $this->repository->find($id);
        if ($record === null) return null;
        $this->authorize('builder.read', $record->siteId, $actorId, 'global_block', $id);
        return $record;
    }

    /** @return list<GlobalBlockRecord> */
    public function list(int $siteId, ?int $actorId = null): array
    {
        $this->authorize('builder.read', $siteId, $actorId, 'global_block_collection', null);
        return $this->repository->forSite($siteId);
    }

    private function require(int $id): GlobalBlockRecord
    {
        return $this->repository->find($id)
            ?? throw new \RuntimeException('Global Block not found.');
    }

    private function name(string $name): string
    {
        $name = trim($name);
        $length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($name === '' || $length > 190) {
            throw new \InvalidArgumentException('Global Block name is required and must be <= 190 characters.');
        }
        return $name;
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
