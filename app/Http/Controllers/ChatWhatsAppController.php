<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppConversationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChatWhatsAppController extends Controller
{
    public function __construct(
        protected WhatsAppConversationService $whatsappService
    ) {}

    /**
     * Endpoint principal para el webhook de WhatsApp (consumido por n8n).
     * POST /api/chat/whatsapp
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone_number' => 'required|string|max:30',
                'message'      => 'required|string|max:2000',
            ], [
                'phone_number.required' => 'El número de teléfono es obligatorio.',
                'message.required'      => 'El mensaje es obligatorio.',
            ]);

            $result = $this->whatsappService->processMessage(
                $validated['phone_number'],
                $validated['message']
            );

            return response()->json($result, $result['success'] ? 200 : 422);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Validation Error',
                'message' => $e->validator->errors()->first(),
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('ChatWhatsAppController::handle error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'phone' => $request->input('phone_number'),
            ]);

            return response()->json([
                'success'  => false,
                'response' => '¡Hola! En este momento estoy experimentando un inconveniente técnico momentáneo. Por favor, vuelve a escribirme en un par de minutos. 😊',
                'error'    => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint para consultar la auditoría de conversación de un cliente por su número.
     * GET /api/chat/whatsapp/audit/{phone}
     */
    public function audit(string $phone): JsonResponse
    {
        $trail = $this->whatsappService->getAuditTrail($phone);

        return response()->json([
            'success'      => true,
            'phone_number' => $phone,
            'total'        => count($trail),
            'audit'        => $trail,
        ], 200);
    }
}
