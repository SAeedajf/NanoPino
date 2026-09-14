<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use Illuminate\Database\Eloquent\Builder;
use App\com_pinoox_cms\Cms\Support\SearchTerm;
use App\com_pinoox_cms\Cms\Support\QueryBounds;
use Pinoox\Model\RoleModel;
use Pinoox\Model\UserModel;
use Pinoox\Portal\Auth;

final class PinooxIdentityRepository implements IdentityRepositoryInterface
{
    public function currentUser(): ?array
    {
        return Auth::clientUser();
    }

    public function users(
        int $limit = 100,
        int $offset = 0,
        ?string $query = null,
        ?string $status = null,
        ?string $role = null,
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = QueryBounds::offset($offset);

        return $this->filtered($query, $status, $role)
            ->with('roles')
            ->orderBy('user_id', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(static function (UserModel $user): array {
                $client = Auth::clientUser($user) ?? [];
                $client['roles'] = $user->roles
                    ? $user->roles->pluck('role_key')->values()->all()
                    : [];
                $client['created_at'] = $user->created_at?->format('c');
                return $client;
            })
            ->all();
    }

    public function count(?string $query = null, ?string $status = null, ?string $role = null): int
    {
        return (int)$this->filtered($query, $status, $role)->count();
    }

    public function summary(): array
    {
        $row = UserModel::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS active', ['active'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS inactive', ['inactive'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS suspend', ['suspend'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', ['pending'])
            ->first();

        return [
            'total' => (int)($row?->getAttribute('total') ?? 0),
            'active' => (int)($row?->getAttribute('active') ?? 0),
            'inactive' => (int)($row?->getAttribute('inactive') ?? 0),
            'suspend' => (int)($row?->getAttribute('suspend') ?? 0),
            'pending' => (int)($row?->getAttribute('pending') ?? 0),
        ];
    }

    public function roles(): array
    {
        return RoleModel::with('permissions')
            ->orderBy('role_key')
            ->get()
            ->map(static fn (RoleModel $role): array => [
                'id' => (int)$role->role_id,
                'key' => (string)$role->role_key,
                'name' => (string)$role->name,
                'description' => (string)($role->description ?? ''),
                'permissions' => $role->permissions->pluck('permission_key')->values()->all(),
            ])
            ->all();
    }

    private function filtered(?string $query, ?string $status, ?string $role): Builder
    {
        $builder = UserModel::query();

        $status = trim((string)$status);
        if ($status !== '' && in_array($status, ['active', 'inactive', 'suspend', 'pending'], true)) {
            $builder->where('status', $status);
        }

        $role = trim((string)$role);
        if ($role !== '') {
            $builder->whereHas('roles', static fn (Builder $roles): Builder => $roles->where('role_key', $role));
        }

        $query = trim((string)$query);
        $needle = SearchTerm::contains($query);
        if ($needle !== null) {
            $builder->where(static function (Builder $q) use ($needle): void {
                $q->where('username', 'like', $needle)
                    ->orWhere('email', 'like', $needle)
                    ->orWhere('mobile', 'like', $needle)
                    ->orWhere('fname', 'like', $needle)
                    ->orWhere('lname', 'like', $needle);
            });
        }

        return $builder;
    }
}
