<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Preview;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentLoader;
use App\com_pinoox_cms\Cms\Block\Document\BlockDocumentSerializer;
use App\com_pinoox_cms\Cms\Block\Render\BlockDocumentRenderer;
use App\com_pinoox_cms\Cms\Block\Render\BlockRenderContext;
use App\com_pinoox_cms\Cms\Builder\GlobalBlock\GlobalBlockReferenceExpander;

final readonly class BuilderPreviewService
{
    public function __construct(
        private BlockDocumentLoader $loader,
        private BlockDocumentSerializer $serializer,
        private BlockDocumentRenderer $renderer,
        private AuthorizationManager $authorization,
        private ?GlobalBlockReferenceExpander $globals = null,
    ) {}

    /**
     * Preview never persists the supplied draft.
     *
     * @param array<string,mixed> $rawDocument
     */
    public function preview(
        int $siteId,
        array $rawDocument,
        BlockRenderContext $context,
        ?int $actorId = null,
    ): BuilderPreviewResult {
        $this->authorization->authorize(new AuthorizationRequest(
            'builder.preview',
            $actorId,
            ScopeType::Site,
            $siteId,
            'builder_preview',
        ));

        $document = $this->loader->fromArray($rawDocument);
        if ($this->globals !== null) {
            $document = $this->globals->expand($document, $siteId);
        }
        $rendered = $this->renderer->render($document, $context);

        return new BuilderPreviewResult(
            $rendered->html,
            $this->serializer->checksum($document),
            $rendered->cacheTags,
        );
    }
}
