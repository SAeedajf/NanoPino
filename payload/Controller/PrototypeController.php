<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Controller;

use Pinoox\Component\Kernel\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

final class PrototypeController extends Controller
{
    private const ALLOWED_ASSETS = [
        'styles.css' => 'text/css; charset=UTF-8',
        'polish.css' => 'text/css; charset=UTF-8',
        'app.js' => 'application/javascript; charset=UTF-8',
    ];

    public function index(): Response
    {
        return $this->fileResponse('index.html', 'text/html; charset=UTF-8');
    }

    public function asset(string $asset): Response
    {
        $asset = basename($asset);
        if (!isset(self::ALLOWED_ASSETS[$asset])) {
            return new Response('Not found', Response::HTTP_NOT_FOUND);
        }

        return $this->fileResponse($asset, self::ALLOWED_ASSETS[$asset]);
    }

    private function fileResponse(string $file, string $contentType): Response
    {
        $path = dirname(__DIR__) . '/prototype/' . $file;
        if (!is_file($path)) {
            return new Response('Prototype asset unavailable', Response::HTTP_NOT_FOUND);
        }

        $response = new Response((string) file_get_contents($path));
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        return $response;
    }
}
