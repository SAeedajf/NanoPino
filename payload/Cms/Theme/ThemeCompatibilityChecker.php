<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use App\com_pinoox_cms\Cms\Dependency\VersionConstraintEvaluatorInterface;

final class ThemeCompatibilityChecker
{
    public function __construct(private readonly VersionConstraintEvaluatorInterface $semver) {}

    public function check(ThemeDefinition $theme, string $cmsVersion): ThemeCompatibilityResult
    {
        $issues = [];
        $requires = is_array($theme->cms['requires'] ?? null) ? $theme->cms['requires'] : [];
        $cmsConstraint = $requires['cms'] ?? null;

        if (is_string($cmsConstraint) && trim($cmsConstraint) !== '') {
            if (!$this->semver->satisfies($cmsVersion, $cmsConstraint)) {
                $issues[] = 'CMS version does not satisfy theme constraint: ' . $cmsConstraint;
            }

            return new ThemeCompatibilityResult($issues === [], $issues);
        }

        // 0.x migration fallback for early Phase-11 flat/min-max profiles.
        $themeProfile = is_array($theme->cms['theme'] ?? null) ? $theme->cms['theme'] : $theme->cms;
        $min = $themeProfile['minimum_cms'] ?? null;
        $max = $themeProfile['maximum_cms'] ?? null;

        if (is_string($min) && $min !== '' && !$this->semver->satisfies($cmsVersion, '>=' . $min)) {
            $issues[] = 'CMS version is below theme minimum: ' . $min;
        }

        if (is_string($max) && $max !== '' && !$this->semver->satisfies($cmsVersion, '<=' . $max)) {
            $issues[] = 'CMS version is above theme maximum: ' . $max;
        }

        return new ThemeCompatibilityResult($issues === [], $issues);
    }
}
