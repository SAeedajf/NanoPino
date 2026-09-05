<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;

final readonly class ThemeDefinition implements OwnedDefinitionInterface
{
    /**
     * @param list<string> $extends
     * @param array<string,mixed> $cms
     * @param array<string,mixed> $raw
     */
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $package,
        public string $name,
        public string $title,
        public string $description,
        public string $version,
        public int $versionCode,
        public array $extends = [],
        public string $cover = '',
        public array $cms = [],
        public array $raw = [],
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function owner(): string
    {
        return $this->ownerId;
    }

    public function reference(): string
    {
        return $this->package . ':' . $this->name;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner' => $this->ownerId,
            'package' => $this->package,
            'name' => $this->name,
            'reference' => $this->reference(),
            'title' => $this->title,
            'description' => $this->description,
            'version' => $this->version,
            'version_code' => $this->versionCode,
            'extends' => $this->extends,
            'cover' => $this->cover,
            'cms' => $this->cms,
        ];
    }
}
