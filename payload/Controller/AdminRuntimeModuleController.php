<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Controller;

use Pinoox\Component\Kernel\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

final class AdminRuntimeModuleController extends Controller
{
    public function module(string $asset): Response
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,60}\.mjs$/', $asset) !== 1) {
            return $this->notFound();
        }

        $base = realpath(dirname(__DIR__) . '/theme/cms-admin/runtime');
        if ($base === false || !is_dir($base)) {
            return $this->notFound();
        }

        $path = realpath($base . '/' . $asset);
        if (
            $path === false || !is_file($path) || is_link($path)
            || !str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $base), '/') . '/')
        ) {
            return $this->notFound();
        }

        $size = filesize($path);
        if (!is_int($size) || $size < 1 || $size > 524288) {
            return $this->notFound();
        }

        $body = file_get_contents($path);
        if (!is_string($body)) {
            return $this->notFound();
        }

        return new Response($body, 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'X-Pinoox-CMS-Admin-Module' => 'control-plane',
        ]);
    }

    private function notFound(): Response
    {
        return new Response('', 404, ['Cache-Control' => 'no-store']);
    }
}
