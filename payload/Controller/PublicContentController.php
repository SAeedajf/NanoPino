<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller;

use App\com_pinoox_cms\Cms\Admin\AdminRuntimeUrl;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Content\PinooxContentRepository;
use App\com_pinoox_cms\Cms\PublicSite\PublicContentUrl;
use App\com_pinoox_cms\Cms\PublicSite\PublicNavigation;
use App\com_pinoox_cms\Cms\PublicSite\PublicPageRenderer;
use App\com_pinoox_cms\Cms\PublicSite\PublicSiteContext;
use App\com_pinoox_cms\Cms\PublicSite\PublicSiteHomeRenderer;
use App\com_pinoox_cms\Cms\PublicSite\PublicSlugPolicy;
use App\com_pinoox_cms\Cms\PublicSite\PublicTaxonomyRenderer;
use App\com_pinoox_cms\Cms\Taxonomy\CoreTaxonomies;
use App\com_pinoox_cms\Cms\Taxonomy\PinooxTermRepository;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;
use App\com_pinoox_cms\Cms\Content\ContentProjection;
use App\com_pinoox_cms\Cms\Content\ContentQuery;
use App\com_pinoox_cms\Cms\Content\ContentStatus;
use App\com_pinoox_cms\Cms\Cache\CacheLayer;
use App\com_pinoox_cms\Cms\Logging\CorrelationId;
use App\com_pinoox_cms\Cms\Logging\LogLevel;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use App\com_pinoox_cms\Cms\Theme\Template\TemplateRequest;
use Pinoox\Component\Kernel\Controller\Controller;
use Pinoox\Component\Http\Response;

final class PublicContentController extends Controller
{
    public function page(string $slug): Response
    {
        return $this->show('page', $slug);
    }

    public function post(string $slug): Response
    {
        return $this->show('post', $slug);
    }

    public function taxonomy(string $taxonomy, string $termSlug): Response
    {
        if (preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $taxonomy) !== 1 || !PublicSlugPolicy::accepts($termSlug)) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $context = PublicSiteContext::fromRuntime($this->getRequest()->getHost());
            $registry = new TaxonomyRegistry();
            CoreTaxonomies::register($registry);
            $definition = $registry->definition($taxonomy);
            if ($definition === null || !$definition->public) {
                return new Response('Not found', Response::HTTP_NOT_FOUND);
            }

            $term = (new PinooxTermRepository())->findBySlug($context->siteId, $taxonomy, $context->locale, $termSlug);
            if ($term === null) {
                return new Response('Not found', Response::HTTP_NOT_FOUND);
            }

            $records = (new PinooxContentRepository())->search(new ContentQuery(
                siteId: $context->siteId,
                status: ContentStatus::Published,
                locale: $context->locale,
                limit: 24,
                projection: ContentProjection::List,
                termId: $term->id,
                taxonomy: $taxonomy,
            ));
            $theme = CmsRuntimeServices::publicThemeView(
                new TemplateRequest('taxonomy', ['taxonomy' => $taxonomy, 'term' => $termSlug]),
                $context->siteId,
            );
            $html = (new PublicTaxonomyRenderer(themeDesign: $theme?->design))->render(
                $term,
                $definition->label,
                $records,
                AdminRuntimeUrl::appPath('/' . $taxonomy . '/' . $termSlug, AdminRuntimeUrl::currentMountPath()),
                $this->publicNavigation($context),
                AdminRuntimeUrl::currentMountPath(),
                $context->locale,
            );
            return $this->htmlResponse($html);
        } catch (\Throwable $exception) {
            $this->reportPublicFailure('taxonomy', $taxonomy . ':' . $termSlug, $exception);
            return $this->unavailable('cms.public.taxonomy_failed');
        }
    }

    public function site(): Response
    {
        try {
            $context = PublicSiteContext::fromRuntime($this->getRequest()->getHost());
            $key = 'public:site:' . $context->siteId . ':' . $context->locale;
            $html = $this->cachedPage($key, ['content:site:' . $context->siteId], function () use ($context): string {
                $records = (new PinooxContentRepository())->search(new ContentQuery(
                    siteId: $context->siteId,
                    status: ContentStatus::Published,
                    locale: $context->locale,
                    limit: 12,
                    projection: ContentProjection::List,
                ));

                $theme = CmsRuntimeServices::publicThemeView(
                    new TemplateRequest('home'),
                    $context->siteId,
                );
                $renderer = new PublicSiteHomeRenderer();
                if ($theme !== null) {
                    $renderer = new PublicSiteHomeRenderer(themeDesign: $theme->design);
                }
                return $renderer->render(
                    $records,
                    AdminRuntimeUrl::appPath('/site', AdminRuntimeUrl::currentMountPath()),
                    $this->publicNavigation($context),
                    AdminRuntimeUrl::currentMountPath(),
                    $context->locale,
                );
            });
        } catch (\Throwable $exception) {
            $this->reportPublicFailure('site', '', $exception);
            return $this->unavailable('cms.public.site_failed');
        }

        return $this->htmlResponse($html);
    }

    private function show(string $type, string $slug): Response
    {
        if (!PublicSlugPolicy::accepts($slug)) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $context = PublicSiteContext::fromRuntime($this->getRequest()->getHost());
            $key = 'public:' . $type . ':' . $context->siteId . ':' . $context->locale . ':' . $slug;
            $html = $this->cachedPage($key, ['content:site:' . $context->siteId], function () use ($context, $type, $slug): string {
                $record = (new PinooxContentRepository())->findPublishedBySlug($context->siteId, $type, $context->locale, $slug);
                if ($record === null) return '';

                $path = PublicContentUrl::path($record, AdminRuntimeUrl::currentMountPath());
                if ($path === null) return '';

                $theme = CmsRuntimeServices::publicThemeView(
                    new TemplateRequest('single', ['type' => $type, 'slug' => $slug]),
                    $context->siteId,
                );
                return (new PublicPageRenderer(
                    blockLoader: CmsRuntimeServices::blockLoader(),
                    blockRenderer: CmsRuntimeServices::publicBlockRenderer(),
                    publishedBuilder: CmsRuntimeServices::publicBuilderPublishedResolver(),
                    globalBlocks: CmsRuntimeServices::publicGlobalBlockExpander(),
                    themeDesign: $theme?->design,
                ))->render(
                    $record,
                    $path,
                    $this->publicTaxonomyLinks($record),
                    $this->publicNavigation($context),
                    AdminRuntimeUrl::currentMountPath(),
                    $context->locale,
                );
            });

            if ($html === '') return new Response('Not found', Response::HTTP_NOT_FOUND);

            return $this->htmlResponse($html);
        } catch (\Throwable $exception) {
            $this->reportPublicFailure($type, $slug, $exception);
            return $this->unavailable('cms.public.content_failed');
        }
    }

    /** @return list<array{name:string,url:string}> */
    private function publicTaxonomyLinks(\App\com_pinoox_cms\Cms\Content\ContentRecord $record): array
    {
        if ($record->terms === []) return [];

        $registry = new TaxonomyRegistry();
        CoreTaxonomies::register($registry);
        $ids = [];
        foreach ($record->terms as $termIds) {
            foreach ($termIds as $id) $ids[] = (int)$id;
        }
        $terms = (new PinooxTermRepository())->findMany(array_values(array_unique($ids)));
        $links = [];
        foreach ($record->terms as $taxonomy => $termIds) {
            $definition = $registry->definition((string)$taxonomy);
            if ($definition === null || !$definition->public) continue;
            foreach ($termIds as $id) {
                $term = $terms[(int)$id] ?? null;
                if ($term === null || $term->siteId !== $record->siteId || $term->taxonomy !== $taxonomy || $term->locale !== $record->locale) continue;
                $links[] = [
                    'name' => $term->name,
                    'url' => AdminRuntimeUrl::appPath('/' . $taxonomy . '/' . $term->slug, AdminRuntimeUrl::currentMountPath()),
                ];
            }
        }
        return $links;
    }

    private function publicNavigation(PublicSiteContext $context): PublicNavigation
    {
        $record = CmsRuntimeServices::settingsRepository()->find(
            PublicNavigation::SETTING,
            new SettingScope(ScopeType::Site, $context->siteId),
        );

        // Navigation is optional presentation data. If an old/corrupt record
        // exists, discard only the menu and keep the published page available.
        try {
            return PublicNavigation::fromValue($record?->value ?? []);
        } catch (\Throwable) {
            return PublicNavigation::fromValue([]);
        }
    }

    private function htmlResponse(string $html, int $status = Response::HTTP_OK): Response
    {
        $response = new Response($html, $status);
        $response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        return $response;
    }

    /** @param list<string> $tags */
    private function cachedPage(string $key, array $tags, callable $render): string
    {
        $cached = CmsRuntimeServices::cache()->get(CacheLayer::Page, $key, $tags);
        if (is_string($cached)) return $cached;

        $html = (string) $render();
        if ($html !== '') {
            CmsRuntimeServices::cache()->set(CacheLayer::Page, $key, $html, 60, $tags);
        }
        return $html;
    }

    private function unavailable(string $operation): Response
    {
        $response = $this->htmlResponse('Service unavailable', Response::HTTP_SERVICE_UNAVAILABLE);
        $response->headers->set('X-CMS-Error-Code', $operation);
        return $response;
    }

    private function reportPublicFailure(string $type, string $slug, \Throwable $exception): void
    {
        try {
            CmsRuntimeServices::logger()->log(
                LogLevel::Error,
                'Public content rendering failed.',
                [
                    'operation' => 'cms.public.' . $type,
                    'slug' => $slug,
                    'exception' => $exception::class,
                    'source_file' => basename($exception->getFile()),
                    'source_line' => $exception->getLine(),
                ],
                CorrelationId::generate(),
                'cms.public',
            );
        } catch (\Throwable) {
            error_log('NanoPino public content rendering failed: ' . $exception::class);
        }
    }
}
