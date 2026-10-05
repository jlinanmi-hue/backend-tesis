<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $service) {}

    /**
     * Nivel 1: Los 4 indicadores principales de la Tesis (PODE, PEOR, PRS, TBPP)
     * GET /api/dashboard/indicators
     */
    public function indicators(Request $request): JsonResponse
    {
        try {
            $mes     = $request->query('mes');
            $semana  = $request->query('semana');
            $periodo = $request->query('periodo', $mes);

            $pode = $this->service->getPODE($mes);
            $peor = $this->service->getPEOR($mes);
            $prs  = $this->service->getPRS($semana);
            $tbpp = $this->service->getTBPP($periodo);

            return response()->json([
                'success' => true,
                'data'    => [
                    'pode' => $pode,
                    'peor' => $peor,
                    'prs'  => $prs,
                    'tbpp' => $tbpp,
                ],
                'message' => 'Indicadores principales calculados exitosamente.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular indicadores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Nivel 2: Subgráficos detallados (A, B, C, D) del indicador seleccionado
     * GET /api/dashboard/indicator/{id}
     */
    public function detail(Request $request, int $id): JsonResponse
    {
        try {
            $filters = $request->only(['canal', 'categoria', 'operario']);
            $mes     = $request->query('mes', now()->format('Y-m'));
            $semana  = $request->query('semana', now()->format('Y-\WW'));
            $periodo = $request->query('periodo', $mes);

            $data = match ($id) {
                1 => $this->service->getPODEDetail($mes, $filters),
                2 => $this->service->getPEORDetail($mes, $filters),
                3 => $this->service->getPRSDetail($semana, $filters),
                4 => $this->service->getTBPPDetail($periodo, $filters),
                default => null,
            };

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => "Indicador con ID {$id} no encontrado. Válidos: 1 (PODE), 2 (PEOR), 3 (PRS), 4 (TBPP).",
                ], 404);
            }

            return response()->json([
                'success'      => true,
                'indicator_id' => $id,
                'data'         => $data,
                'message'      => 'Detalle de subgráficos obtenido exitosamente.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle del indicador: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Compatibilidad Retrospectiva con la vista anterior
     * GET /api/dashboard/indicadores
     */
    public function indicadores(Request $request): JsonResponse
    {
        try {
            $filtros = [
                'dias'     => $request->query('dias', '7'),
                'canal_id' => $request->query('canal_id'),
            ];

            $datos = $this->service->obtenerIndicadores($filtros);

            return response()->json([
                'success' => true,
                'data'    => $datos,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular indicadores del dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consumo de Tokens de IA y auditoría de herramientas
     * GET /api/dashboard/tokens
     */
    public function tokenConsumption(Request $request): JsonResponse
    {
        try {
            $data = $this->service->getAiTokenConsumption($request->all());

            return response()->json([
                'success' => true,
                'data'    => $data,
                'message' => 'Métricas de consumo de tokens obtenidas exitosamente.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener métricas de tokens: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Nivel 3: Comparación de subgráficos y KPIs entre dos períodos (A y B)
     * GET /api/dashboard/compare
     */
    public function compare(Request $request): JsonResponse
    {
        try {
            $indicatorId = (int) $request->query('indicator_id', 1);
            if (!in_array($indicatorId, [1, 2, 3, 4], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El indicator_id debe ser 1 (PODE), 2 (PEOR), 3 (PRS) o 4 (TBPP).',
                ], 422);
            }

            $paramA = [
                'periodo' => $request->query('periodo_a'),
                'inicio'  => $request->query('inicio_a'),
                'fin'     => $request->query('fin_a'),
            ];

            $paramB = [
                'periodo' => $request->query('periodo_b'),
                'inicio'  => $request->query('inicio_b'),
                'fin'     => $request->query('fin_b'),
            ];

            $filters = $request->only(['canal', 'categoria', 'operario']);

            $data = $this->service->comparePeriods($indicatorId, $paramA, $paramB, $filters);

            return response()->json([
                'success' => true,
                'data'    => $data,
                'message' => 'Comparativa de subgráficos obtenida exitosamente.',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular comparativa de indicadores: ' . $e->getMessage(),
            ], 500);
        }
    }
}
