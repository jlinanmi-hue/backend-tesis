<?php

namespace App\Http\Controllers;

use App\Services\PurchasePredictionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PurchasePredictionController
 *
 * Gestiona los endpoints de Predicciones Semanales de Compra para Comercial Valencia.
 *
 * Rutas (bajo auth:sanctum):
 *   GET /api/ai/predicciones-compra                         → index
 *   GET /api/ai/predicciones-compra/pdf/categoria/{catId}   → pdfCategoria
 *   GET /api/ai/predicciones-compra/pdf/general              → pdfGeneral
 *   GET /api/ai/predicciones-compra/pdf/producto/{prodId}   → pdfProducto
 *   GET /api/ai/predicciones-compra/csv                     → csv
 */
class PurchasePredictionController extends Controller
{
    public function __construct(protected PurchasePredictionService $service) {}

    // =========================================================================
    // 1. INDEX — Consulta JSON (con filtros opcionales)
    // =========================================================================

    /**
     * GET /api/ai/predicciones-compra
     *
     * Query params:
     *   - categoria_id (string): Filtrar por categoría
     *   - producto_id  (string): Predicción de un producto específico
     *   - semana       (string): Semana ISO "YYYY-WNN". Default: semana actual
     */
    public function index(Request $request): JsonResponse
    {
        $semana      = $request->query('semana');
        $categoriaId = $request->query('categoria_id');
        $productoId  = $request->query('producto_id');

        if ($productoId) {
            $prediccion = $this->service->predecirProducto($productoId, $semana);
            return response()->json([
                'success'        => true,
                'semana'         => $semana ?? $this->service->semanaActual(),
                'predicciones'   => $prediccion ? [$prediccion] : [],
                'total_estimado' => $prediccion['total_estimado'] ?? 0.0,
                'total_productos'  => $prediccion ? 1 : 0,
                'criticos'         => ($prediccion && $prediccion['estado_critico']) ? 1 : 0,
            ]);
        }

        if ($categoriaId) {
            $datos = $this->service->generarPrediccionesPorCategoria($categoriaId, $semana);
        } else {
            $datos = $this->service->generarPrediccionesSemanales($semana);
        }

        return response()->json([
            'success'          => true,
            'semana'           => $datos['semana'],
            'generado_en'      => $datos['generado_en'],
            'predicciones'     => $datos['predicciones'],
            'total_estimado'   => $datos['total_estimado'],
            'total_productos'  => $datos['total_productos'],
            'total_a_reponer'  => $datos['total_a_reponer'] ?? count(array_filter($datos['predicciones'], fn($p) => ($p['cantidad_empaques'] ?? 0) > 0)),
            'criticos'         => $datos['criticos'],
        ]);
    }

    // =========================================================================
    // 2. PDF POR CATEGORÍA — Streaming on-demand
    // =========================================================================

    /**
     * GET /api/ai/predicciones-compra/pdf/categoria/{categoriaId}
     */
    public function pdfCategoria(string $categoriaId): \Illuminate\Http\Response
    {
        $datos = $this->service->generarPrediccionesPorCategoria($categoriaId);

        // Nombre de la categoría para el título del PDF
        $categoria = DB::table('Categoria_Producto')
            ->where('Categoria_ProductoId', $categoriaId)
            ->value('Categoria_ProductoDescripcion_categoria') ?? $categoriaId;

        $empresa = $this->datosEmpresa();

        $pdf = Pdf::loadView('pdf.predicciones_categoria', [
            'datos'     => $datos,
            'categoria' => $categoria,
            'empresa'   => $empresa,
        ])->setPaper('a4', 'portrait');

        $filename = 'predicciones_' . str_replace([' ', '/'], '_', strtolower($categoria)) . '_' . $datos['semana'] . '.pdf';

        return $pdf->stream($filename);
    }

    // =========================================================================
    // 3. PDF GENERAL — Consolidado de todas las categorías
    // =========================================================================

    /**
     * GET /api/ai/predicciones-compra/pdf/general
     */
    public function pdfGeneral(): \Illuminate\Http\Response
    {
        $datos   = $this->service->generarPrediccionesSemanales();
        $empresa = $this->datosEmpresa();

        // Agrupar predicciones por categoría
        $porCategoria = collect($datos['predicciones'])
            ->groupBy('categoria_nombre')
            ->toArray();

        $pdf = Pdf::loadView('pdf.predicciones_general', [
            'datos'        => $datos,
            'porCategoria' => $porCategoria,
            'empresa'      => $empresa,
        ])->setPaper('a4', 'portrait');

        $filename = 'predicciones_compra_' . $datos['semana'] . '.pdf';

        return $pdf->stream($filename);
    }

    // =========================================================================
    // 4. PDF PRODUCTO INDIVIDUAL — 1 sola página con talón de cotización
    // =========================================================================

    /**
     * GET /api/ai/predicciones-compra/pdf/producto/{productoId}
     */
    public function pdfProducto(string $productoId): \Illuminate\Http\Response
    {
        $prediccion = $this->service->predecirProducto($productoId);
        $empresa    = $this->datosEmpresa();

        if (!$prediccion) {
            abort(404, 'Producto no encontrado o sin datos suficientes para predicción.');
        }

        $pdf = Pdf::loadView('pdf.predicciones_producto', [
            'p'       => $prediccion,
            'empresa' => $empresa,
        ])->setPaper('a4', 'portrait');

        $safeNombre = str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $prediccion['producto_nombre']);
        $filename   = 'ficha_' . $safeNombre . '_' . $this->service->semanaActual() . '.pdf';

        return $pdf->stream($filename);
    }

    // =========================================================================
    // 5. CSV — Exportación Excel-compatible con BOM UTF-8
    // =========================================================================

    /**
     * GET /api/ai/predicciones-compra/csv
     */
    public function csv(Request $request): \Illuminate\Http\Response
    {
        $semana      = $request->query('semana');
        $categoriaId = $request->query('categoria_id');

        if ($categoriaId) {
            $datos = $this->service->generarPrediccionesPorCategoria($categoriaId, $semana);
        } else {
            $datos = $this->service->generarPrediccionesSemanales($semana);
        }

        $filename  = 'predicciones_compra_' . $datos['semana'] . '.csv';

        // BOM UTF-8 para Excel en Windows
        $output  = "\xEF\xBB\xBF";
        $output .= implode(';', [
            'Semana',
            'Producto',
            'Categoría',
            'Proveedor',
            'Stock Actual',
            'En Tránsito',
            'Stock Mínimo',
            'Demanda Semanal',
            'Cantidad Sugerida (Empaques)',
            'Cantidad Sugerida (Unidad Base)',
            'Empaque Preferido',
            'Precio Ref. Empaque (S/)',
            'Total Estimado (S/)',
            'Cobertura (días)',
            'Estado Crítico',
            'Score',
        ]) . "\r\n";

        foreach ($datos['predicciones'] as $p) {
            $output .= implode(';', [
                $datos['semana'],
                '"' . str_replace('"', '""', $p['producto_nombre']) . '"',
                '"' . str_replace('"', '""', $p['categoria_nombre']) . '"',
                '"' . str_replace('"', '""', $p['proveedor_nombre'] ?? 'Sin proveedor') . '"',
                str_replace('.', ',', (string)$p['stock_actual']),
                str_replace('.', ',', (string)$p['stock_transito']),
                str_replace('.', ',', (string)$p['stock_minimo']),
                str_replace('.', ',', (string)$p['demanda_semanal']),
                str_replace('.', ',', (string)$p['cantidad_empaques']),
                str_replace('.', ',', (string)$p['cantidad_base']),
                '"' . $p['empaque_preferido'] . '"',
                str_replace('.', ',', number_format($p['precio_referencia_empaque'], 2)),
                str_replace('.', ',', number_format($p['total_estimado'], 2)),
                '"' . $p['cobertura_dias_texto'] . '"',
                $p['estado_critico'] ? 'Sí' : 'No',
                str_replace('.', ',', number_format($p['score'], 4)),
            ]) . "\r\n";
        }

        return response($output, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    // =========================================================================
    // HELPER PRIVADO: Datos de empresa
    // =========================================================================

    /**
     * Carga los datos básicos de la empresa (RUC, nombre, dirección) para los PDFs.
     *
     * @return object|null
     */
    private function datosEmpresa(): ?object
    {
        return DB::table('Empresa')->first();
    }
}
