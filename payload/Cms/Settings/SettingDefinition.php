<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use Closure;
use InvalidArgumentException;

final readonly class SettingDefinition implements OwnedDefinitionInterface
{
    public ?Closure $validator;

    /**
     * @param list<ScopeType> $scopes
     * @param callable(mixed,SettingScope):bool|string|null $validator
     * @param array<string,mixed> $ui
     */
    public function __construct(
        private string $key,
        private string $owner,
        public SettingType $type,
        public mixed $default = null,
        public array $scopes = [ScopeType::Global],
        public ?string $readPermission = 'settings.read',
        public ?string $writePermission = 'settings.manage',
        public string $group = 'general',
        public string $label = '',
        callable|null $validator = null,
        public array $ui = [],
        public bool $sensitive = false,
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,190}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid setting key: ' . $key);
        }

        if ($scopes === []) {
            throw new InvalidArgumentException('A setting must allow at least one scope.');
        }

        foreach ($scopes as $scope) {
            if (!$scope instanceof ScopeType) {
                throw new InvalidArgumentException('Setting scopes must contain ScopeType values.');
            }
        }

        $this->validator = $validator !== null ? Closure::fromCallable($validator) : null;
    }

    public function identifier(): string { return $this->key; }
    public function owner(): string { return $this->owner; }

    public function allowsScope(SettingScope $scope): bool
    {
        return in_array($scope->type, $this->scopes, true);
    }

    /** @return list<string> */
    public function scopeNames(): array
    {
        return array_map(static fn (ScopeType $scope): string => $scope->value, $this->scopes);
    }
}
