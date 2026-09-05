<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use Closure;
use InvalidArgumentException;

final readonly class FieldDefinition implements OwnedDefinitionInterface
{
    public ?Closure $validator;

    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $ui
     * @param callable(mixed):bool|string|null $validator
     */
    public function __construct(
        private string $key,
        private string $owner,
        public FieldType $type,
        public string $label,
        public FieldStorageStrategy $storage = FieldStorageStrategy::Meta,
        public bool $required = false,
        public mixed $default = null,
        public bool $multiple = false,
        public bool $translatable = true,
        public array $options = [],
        public array $ui = [],
        callable|null $validator = null,
    ) {
        new OwnerIdentifier($owner);

        if (preg_match('/^[a-z][a-z0-9_.-]{1,127}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid field key: ' . $key);
        }

        if ($type === FieldType::Taxonomy && $storage !== FieldStorageStrategy::Taxonomy) {
            throw new InvalidArgumentException('Taxonomy fields must use taxonomy storage.');
        }

        if ($storage === FieldStorageStrategy::Taxonomy && $type !== FieldType::Taxonomy) {
            throw new InvalidArgumentException('Taxonomy storage is reserved for taxonomy fields.');
        }

        if (
            $multiple
            && !in_array($type, [
                FieldType::Relation,
                FieldType::Taxonomy,
                FieldType::Media,
                FieldType::Gallery,
                FieldType::Select,
            ], true)
        ) {
            throw new InvalidArgumentException('Multiple is not supported for this field type.');
        }

        $this->validator = $validator !== null ? Closure::fromCallable($validator) : null;
    }

    public function identifier(): string { return $this->key; }
    public function owner(): string { return $this->owner; }

    public function writable(): bool
    {
        return !in_array($this->storage, [
            FieldStorageStrategy::Virtual,
            FieldStorageStrategy::External,
        ], true);
    }
}
