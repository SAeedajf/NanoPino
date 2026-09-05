<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

final class DeepTokenMerge
{
    /**
     * Theme token semantics: associative objects merge recursively;
     * lists/scalars replace atomically.
     *
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    public function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                isset($base[$key])
                && is_array($base[$key])
                && is_array($value)
                && !array_is_list($base[$key])
                && !array_is_list($value)
            ) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
