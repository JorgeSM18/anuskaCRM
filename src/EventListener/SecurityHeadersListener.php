<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Añade cabeceras de seguridad a todas las respuestas (defensa en profundidad).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // Anti-clickjacking: nadie puede embeber el CRM en un iframe.
        $headers->set('X-Frame-Options', 'DENY');
        // No adivinar el tipo de contenido (refuerza el nosniff de las descargas).
        $headers->set('X-Content-Type-Options', 'nosniff');
        // No filtrar la URL completa como referer a sitios externos.
        $headers->set('Referrer-Policy', 'same-origin');
        // Desactiva APIs del navegador que la app no usa.
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');

        // CSP: 'unsafe-inline' en script/style es necesario por el importmap de AssetMapper
        // y los estilos en línea; el resto queda restringido al propio origen.
        // (La app no tiene XSS: Twig autoescapa y no hay |raw; la CSP es defensa extra.)
        $headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            // 'unsafe-inline' y data: los exige el importmap de AssetMapper; la protección
            // real de esta CSP está en frame-ancestors/base-uri/form-action/connect-src.
            "script-src 'self' 'unsafe-inline' data:",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));

        // HSTS solo bajo HTTPS (evita romper HTTP en desarrollo).
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
