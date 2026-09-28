<?php

namespace App\Http\Controllers;

use App\Services\SunatService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SunatController extends Controller
{
    public function __construct(
        protected SunatService $sunatService
    ) {}

    /**
     * Consulta información de RUC ante SUNAT para el autocompletado en Cliente o Proveedor.
     *
     * @param Request $request
     * @param string $ruc
     * @return JsonResponse
     */
    public function consultarRuc(Request $request, string $ruc): JsonResponse
    {
        $ruc = trim($ruc);
        $target = $request->query('target', 'all');
        $forceRefresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);

        // Validar formato del RUC / DNI (8 dígitos DNI o 11 dígitos RUC o comodín)
        if ($ruc !== SunatService::GENERIC_RUC && (!ctype_digit($ruc) || !in_array(strlen($ruc), [8, 11]))) {
            return response()->json([
                'success' => false,
                'message' => 'El número de documento debe tener 8 dígitos (DNI) u 11 dígitos (RUC).',
            ], 422);
        }

        try {
            $resultado = $this->sunatService->consultarRuc($ruc, $target, $forceRefresh);

            return response()->json([
                'success' => true,
                'message' => 'Información de RUC obtenida exitosamente.',
                'data' => $resultado,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
