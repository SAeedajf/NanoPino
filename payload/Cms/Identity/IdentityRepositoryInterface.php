<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

interface IdentityRepositoryInterface
{
    /** @return array<string,mixed>|null */
    public function currentUser(): ?array;

    /** @return list<array<string,mixed>> */
    public function users(
        int $limit = 100,
        int $offset = 0,
        ?string $query = null,
        ?string $status = null,
        ?string $role = null,
    ): array;

    public function count(?string $query = null, ?string $status = null, ?string $role = null): int;

    /** @return array{total:int,active:int,inactive:int,suspend:int,pending:int} */
    public function summary(): array;

    /** @return list<array<string,mixed>> */
    public function roles(): array;
}
