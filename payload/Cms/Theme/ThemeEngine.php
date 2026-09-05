<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme;

use App\com_pinoox_cms\Cms\Theme\Design\DesignTokenResolver;
use App\com_pinoox_cms\Cms\Theme\Pattern\ThemePatternLoader;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateHierarchyResolver;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateLocator;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;

final class ThemeEngine
{
    public function __construct(
        private readonly ThemeRegistry $themes,
        private readonly ThemeInheritanceResolver $inheritance,
        private readonly TemplateHierarchyResolver $hierarchy,
        private readonly TemplateLocator $templates = new TemplateLocator(),
        private readonly DesignTokenResolver $design = new DesignTokenResolver(),
        private readonly ThemePatternLoader $patterns = new ThemePatternLoader(),
        private readonly CmsThemeProfileFactory $profiles = new CmsThemeProfileFactory(),
    ) {}

    /**
     * @param array<string,mixed> $runtimeDesignOverrides
     */
    public function resolve(
        string $package,
        string $themeName,
        TemplateRequest $request,
        ?string $context = null,
        ?string $styleVariation = null,
        array $runtimeDesignOverrides = [],
    ): ThemeView {
        $definition = $this->themes->byReference($package, $themeName)
            ?? throw new ThemeInheritanceException('Theme is not registered.');

        $stack = $this->inheritance->resolve($package, $themeName, $context);
        $profile = $this->profiles->fromNativeMeta($definition->raw);
        $candidates = $this->hierarchy->candidates($request);

        $templateDirectory = $request->kind === 'part'
            ? $profile->partDirectory
            : $profile->templateDirectory;

        $template = $this->templates->resolve(
            $stack->paths,
            $candidates,
            $templateDirectory,
            $profile->templateExtensions,
        );

        $design = $this->design->resolve(
            $stack->paths,
            $profile->designFile,
            $profile->variationDirectory,
            $styleVariation,
            $runtimeDesignOverrides,
        );

        $patterns = $this->patterns->discover($stack->paths, $profile->patternDirectory);

        return new ThemeView($definition, $stack, $template, $design, $patterns);
    }
}
