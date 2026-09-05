<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\FullSite;

use App\com_pinoox_cms\Cms\Builder\BuilderTarget;
use App\com_pinoox_cms\Cms\Builder\BuilderTargetType;
use InvalidArgumentException;

final class FullSiteTemplateTargetFactory
{
    public function template(int $siteId, string $candidate, string $locale = 'fa'): BuilderTarget
    {
        $candidate = $this->candidate($candidate);
        return new BuilderTarget($siteId, BuilderTargetType::Template, $candidate, $locale);
    }

    public function part(int $siteId, string $part, string $locale = 'fa'): BuilderTarget
    {
        $part = $this->candidate($part);
        return new BuilderTarget($siteId, BuilderTargetType::TemplatePart, $part, $locale);
    }

    private function candidate(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,190}$/', $value) !== 1 || str_contains($value, '..')) {
            throw new InvalidArgumentException('Invalid Full Site template target.');
        }
        return $value;
    }
}
