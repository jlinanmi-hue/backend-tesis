<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateAjusteInventarioRequest;
use App\Services\AjusteInventarioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AjusteInventarioController extends Controller
{
    public function __construct(
        protected AjusteInventarioService $ajusteService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['productoId', 'tipo', 'fechaDesde', 'fechaHasta', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $ajustes = $this->ajusteService->listarAjustes($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $ajustes,
                'message' => 'Historial de ajustes de inventario obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener ajustes de inventario: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function tipos(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->ajusteService->obtenerTiposAjuste(),
            'message' => 'Catálogo de tipos de ajuste obtenido correctamente.',
        ], 200);
    }

    public function resumen(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->ajusteService->obtenerResumen(),
                'message' => 'Resumen de ajustes de inventario obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener resumen de ajustes: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function impactoFinanciero(Request $request): JsonResponse
    {
        try {
            $filtros = $request->only(['productoId', 'tipo', 'fechaDesde', 'fechaHasta']);
            $impacto = $this->ajusteService->obtenerImpactoFinanciero($filtros);

            return response()->json([
                'success' => true,
                'data' => $impacto,
                'message' => 'Impacto financiero de ajustes y mermas calculado correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al calcular impacto financiero: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reportePdf(Request $request)
    {
        try {
            $filtros = $request->only(['productoId', 'tipo', 'fechaDesde', 'fechaHasta']);
            $resumen = $this->ajusteService->obtenerImpactoFinanciero($filtros);
            $ajustes = $this->ajusteService->obtenerAjustesParaReporte($filtros);

            $pdf = Pdf::loadView('pdf.reporte_ajustes', [
                'resumen' => $resumen,
                'ajustes' => $ajustes,
                'filtros' => $filtros,
            ])->setPaper('a4', 'portrait');

            $filename = 'reporte-mermas-' . now()->format('Ymd-His') . '.pdf';

            if ($request->boolean('download', false)) {
                return $pdf->download($filename);
            }

            return $pdf->stream($filename);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reporteExcel(Request $request)
    {
        try {
            $filtros = $request->only(['productoId', 'tipo', 'fechaDesde', 'fechaHasta']);
            $content = $this->ajusteService->generarReporteExcel($filtros);
            $filename = 'reporte-mermas-' . now()->format('Ymd-His') . '.xls';

            return response($content, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateAjusteInventarioRequest $request): JsonResponse
    {
        try {
            $ajuste = $this->ajusteService->crearAjuste($request->validated());

            return response()->json([
                'success' => true,
                'data' => $ajuste,
                'message' => 'Ajuste de inventario registrado y stock descontado exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al registrar ajuste.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar ajuste de inventario: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $ajuste = $this->ajusteService->obtenerAjustePorId($id);

            return response()->json([
                'success' => true,
                'data' => $ajuste,
                'message' => 'Detalle del ajuste de inventario obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $this->ajusteService->eliminarAjuste($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Ajuste de inventario anulado lógicamente y stock revertido exitosamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al anular ajuste: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $this->ajusteService->restaurarAjuste($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Ajuste de inventario restaurado y stock descontado exitosamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar ajuste: ' . $e->getMessage(),
            ], 500);
        }
    }
}
