<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsAppToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Webhook-Token');
        $expectedToken = config('services.whatsapp.webhook_token');

        if (empty($token) || empty($expectedToken) || !hash_equals($expectedToken, $token)) {
            return response()->json([
                'success' => false,
                'error'   => 'Unauthorized',
                'message' => 'Token de webhook de WhatsApp inválido o ausente.',
            ], 401);
        }

        return $next($request);
    }
}
