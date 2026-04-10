<?php

declare(strict_types=1);

namespace Arkhe\Main\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectArkheStyles
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($response)) {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || ! str_contains($content, '</head>')) {
            return $response;
        }

        $cssUrl = route('arkhe-main.asset.css');
        $linkTag = '<link rel="stylesheet" href="'.$cssUrl.'">';

        $response->setContent(
            str_replace('</head>', $linkTag."\n".'</head>', $content)
        );

        return $response;
    }

    private function shouldInject(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html')
            && $response->getStatusCode() === 200;
    }
}
