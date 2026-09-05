<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Media;

final class ResponsiveImagePlan
{
    /**
     * @param list<int> $widths
     * @return list<MediaVariantSpec>
     */
    public function scaleWidths(
        array $widths = [320, 640, 1024, 1600],
        string $format = 'webp',
        int $quality = 82,
    ): array {
        $result = [];
        foreach (array_values(array_unique(array_map('intval', $widths))) as $width) {
            if ($width < 1 || $width > 8192) {
                throw new MediaValidationException('Responsive width outside supported range.');
            }
            $result[] = new MediaVariantSpec(
                'responsive-' . $width,
                $width,
                null,
                MediaVariantMode::ScaleDown,
                $format,
                $quality,
            );
        }

        usort($result, static fn (MediaVariantSpec $a, MediaVariantSpec $b): int => $a->width <=> $b->width);
        return $result;
    }
}
