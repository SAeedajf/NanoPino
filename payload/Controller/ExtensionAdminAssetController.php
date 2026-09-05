<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Controller;

use App\com_pinoox_cms\Cms\Admin\ExtensionAdminModuleResolver;
use Pinoox\Component\Kernel\Controller\Controller;
use Pinoox\Portal\App\AppEngine;
use Symfony\Component\HttpFoundation\Response;

final class ExtensionAdminAssetController extends Controller
{
    private const MAX_MODULE_BYTES = 5_242_880;

    public function module(string $package, string $asset): Response
    {
        if (preg_match('/^com_[a-z0-9][a-z0-9_]*$/', $package) !== 1) {
            return $this->notFound();
        }

        try {
            $packageRoot = AppEngine::path($package);
        } catch (\Throwable) {
            return $this->notFound();
        }

        $resolved = (new ExtensionAdminModuleResolver())->resolve($packageRoot, $asset);
        if ($resolved === null) {
            return $this->notFound();
        }

        $size = filesize($resolved['path']);
        if (!is_int($size) || $size < 1 || $size > self::MAX_MODULE_BYTES) {
            return new Response('Asset rejected.', 413, $this->headers($resolved['layout']));
        }

        $content = file_get_contents($resolved['path']);
        if (!is_string($content)) {
            return $this->notFound();
        }

        return new Response($content, 200, $this->headers($resolved['layout']));
    }

    /** @return array<string,string> */
    private function headers(string $layout): array
    {
        return [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Pinoox-CMS-Extension-Asset' => $layout,
        ];
    }

    private function notFound(): Response
    {
        return new Response('Not found.', 404, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
