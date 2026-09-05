<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

use InvalidArgumentException;

final readonly class MediaVariantSpec
{
    public function __construct(
        public string $key,
        public int $width,
        public ?int $height = null,
        public MediaVariantMode $mode = MediaVariantMode::ScaleDown,
        public string $format = 'webp',
        public int $quality = 82,
        public int $offsetX = 0,
        public int $offsetY = 0,
        public string $position = 'center',
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $key) !== 1) {
            throw new InvalidArgumentException('Invalid media variant key.');
        }
        if ($width < 1 || $width > 8192) {
            throw new InvalidArgumentException('Variant width must be between 1 and 8192.');
        }
        if ($height !== null && ($height < 1 || $height > 8192)) {
            throw new InvalidArgumentException('Variant height must be between 1 and 8192.');
        }
        if ($mode !== MediaVariantMode::ScaleDown && $height === null) {
            throw new InvalidArgumentException('Cover/crop variants require height.');
        }
        if (!in_array(strtolower($format), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('Unsupported media variant format.');
        }
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException('Variant quality must be between 1 and 100.');
        }
        if ($offsetX < 0 || $offsetY < 0) {
            throw new InvalidArgumentException('Crop offsets may not be negative.');
        }
    }

    public function extension(): string
    {
        return strtolower($this->format) === 'jpeg' ? 'jpg' : strtolower($this->format);
    }
}
