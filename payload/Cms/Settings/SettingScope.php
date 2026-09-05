<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use InvalidArgumentException;

final readonly class SettingScope
{
    public function __construct(
        public ScopeType $type = ScopeType::Global,
        public string|int|null $id = null,
    ) {
        if ($type === ScopeType::Global && $id !== null && $id !== '') {
            throw new InvalidArgumentException('Global setting scope may not have an id.');
        }

        if ($type !== ScopeType::Global && ($id === null || $id === '')) {
            throw new InvalidArgumentException('Non-global setting scope requires an id.');
        }
    }

    public static function global(): self
    {
        return new self(ScopeType::Global);
    }

    public function normalizedId(): string
    {
        return $this->type === ScopeType::Global ? '' : (string)$this->id;
    }

    /** @return array{type:string,id:string|null} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'id' => $this->type === ScopeType::Global ? null : (string)$this->id,
        ];
    }
}
