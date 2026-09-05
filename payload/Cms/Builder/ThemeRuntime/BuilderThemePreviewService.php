<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\ThemeRuntime;

use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Render\BlockRenderContext;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\GlobalBlockReferenceExpander;
use App\com_pinoox_cms\Cms\Builder\Preview\BuilderPreviewService;
use App\com_pinoox_cms\Cms\Theme\Design\DesignTokenCssCompiler;
use App\com_pinoox_cms\Cms\Theme\Design\GlobalStyleResolver;
use App\com_pinoox_cms\Cms\Theme\Responsive\BreakpointResolver;
use App\com_pinoox_cms\Cms\Theme\Responsive\ResponsiveStyleCompiler;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;
use App\com_pinoox_cms\Cms\Theme\ThemeEngine;

final readonly class BuilderThemePreviewService
{
    public function __construct(
        private BuilderPreviewService $preview,
        private BlockDocumentLoader $loader,
        private ThemeEngine $themes,
        private GlobalStyleResolver $globalStyles,
        private DesignTokenCssCompiler $designCss,
        private BreakpointResolver $breakpoints,
        private ResponsiveStyleCompiler $responsiveCss,
        private ?GlobalBlockReferenceExpander $globals = null,
    ) {}

    /**
     * @param array<string,mixed> $rawDocument
     * @param array<string,mixed> $ephemeralDesignOverrides
     */
    public function preview(
        int $siteId,
        string $package,
        string $themeName,
        TemplateRequest $request,
        array $rawDocument,
        BlockRenderContext $renderContext,
        ?int $actorId = null,
        ?string $themeContext = null,
        ?string $styleVariation = null,
        array $ephemeralDesignOverrides = [],
    ): BuilderThemeRenderResult {
        $themeRef = $package . ':' . $themeName;
        $overrides = $this->globalStyles->overrides(
            $siteId,
            $themeRef,
            $actorId,
            $ephemeralDesignOverrides,
        );

        $theme = $this->themes->resolve(
            $package,
            $themeName,
            $request,
            $themeContext,
            $styleVariation,
            $overrides,
        );

        $preview = $this->preview->preview(
            $siteId,
            $rawDocument,
            $renderContext,
            $actorId,
        );

        $document = $this->loader->fromArray($rawDocument);
        if ($this->globals !== null) {
            $document = $this->globals->expand($document, $siteId);
        }

        $breakpoints = $this->breakpoints->fromDesign($theme->design);

        return new BuilderThemeRenderResult(
            $preview->html,
            $this->designCss->compile($theme->design),
            $this->responsiveCss->compile($document, $breakpoints),
            $preview->checksum,
            $theme,
            $preview->cacheTags,
        );
    }
}
