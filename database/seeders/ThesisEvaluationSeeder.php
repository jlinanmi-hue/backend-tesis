<?php

namespace Database\Seeders;

use App\Models\AiToolLog;
use App\Models\Pedido;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThesisEvaluationSeeder extends Seeder
{
    public function run(): void
    {
        echo "Iniciando calibración de datos para la Tesis...\n";

        // 1. Asignar Zonas y Ubicaciones a Productos
        $zonas = [
            'PROD-00001' => ['zona' => 'Zona A - Bebidas',   'ubi' => 'Pasillo 1 - Estante A'],
            'PROD-00002' => ['zona' => 'Zona A - Bebidas',   'ubi' => 'Pasillo 1 - Estante B'],
            'PROD-00003' => ['zona' => 'Zona B - Abarrotes', 'ubi' => 'Pasillo 2 - Estante A'],
            'PROD-00004' => ['zona' => 'Zona A - Bebidas',   'ubi' => 'Pasillo 1 - Estante C'],
            'PROD-00005' => ['zona' => 'Zona B - Abarrotes', 'ubi' => 'Pasillo 2 - Estante B'],
            'PROD-00006' => ['zona' => 'Zona C - Lácteos',   'ubi' => 'Pasillo 3 - Estante A'],
            'PROD-00007' => ['zona' => 'Zona C - Lácteos',   'ubi' => 'Pasillo 3 - Estante B'],
        ];

        foreach ($zonas as $prodId => $data) {
            DB::table('Producto')->where('ProductoId', $prodId)->update([
                'ProductoZona'      => $data['zona'],
                'ProductoUbicacion' => $data['ubi'],
            ]);
        }

        // 2. Calibrar Pedidos Anulados con Motivo de Anulación y Errores
        $anulados = Pedido::where('PedidoEstado_pedido', 'A')->get();
        $motivos = ['FALTA_STOCK', 'FALTA_STOCK', 'FALTA_STOCK', 'CLIENTE_DESISTIO', 'TIMEOUT_EXPIRADO', 'FALTA_STOCK', 'ERROR_DIGITACION'];
        foreach ($anulados as $idx => $p) {
            $motivo = $motivos[$idx % count($motivos)];
            $p->PedidoMotivoAnulacion = $motivo;
            if ($motivo === 'ERROR_DIGITACION') {
                $p->PedidoTieneError = 'S';
                $p->PedidoTipoError = 'Cantidad Inconsistente';
                $p->PedidoOrigenIA = 'N';
            }
            $p->save();
        }

        // Marcar un par de completados con origen IA
        $completados = Pedido::where('PedidoEstado_pedido', 'C')->get();
        foreach ($completados as $idx => $p) {
            $p->PedidoOrigenIA = ($idx % 2 === 0) ? 'S' : 'N';
            if ($idx === 1) {
                $p->PedidoTieneError = 'S';
                $p->PedidoTipoError = 'Precio Desactualizado';
            }
            $p->save();
        }

        // 3. Poblar ai_tool_logs con búsquedas y operaciones representativas
        $pedidos = Pedido::where('PedidoEliminado', 'N')->get();
        $ahora = Carbon::now();

        foreach ($pedidos as $idx => $p) {
            $fecha = $p->PedidoFechaCreacion ?: $ahora->copy()->subDays($idx);

            // Log de búsqueda de producto exitosa
            AiToolLog::create([
                'user_id'           => 1,
                'session_id'        => (string) Str::uuid(),
                'tool_name'         => 'buscar_producto',
                'pedido_id'         => $p->PedidoId,
                'args'              => ['termino' => 'Pepsi', 'limite' => 5],
                'result'            => ['success' => true, 'data' => ['encontrados' => 1]],
                'status'            => 'success',
                'duration_ms'       => rand(1200, 3800),
                'prompt_tokens'     => 10240,
                'candidates_tokens' => 25,
                'total_tokens'      => 10265,
                'created_at'        => $fecha,
                'updated_at'        => $fecha,
            ]);

            // Log de consulta de stock
            AiToolLog::create([
                'user_id'           => 1,
                'session_id'        => (string) Str::uuid(),
                'tool_name'         => 'consultar_stock',
                'pedido_id'         => $p->PedidoId,
                'args'              => ['producto_id' => 'PROD-00004'],
                'result'            => ['success' => true, 'data' => ['stock' => 50]],
                'status'            => 'success',
                'duration_ms'       => rand(800, 1900),
                'prompt_tokens'     => 10100,
                'candidates_tokens' => 18,
                'total_tokens'      => 10118,
                'created_at'        => $fecha,
                'updated_at'        => $fecha,
            ]);

            // Si el pedido tuvo error, registrar un log de error de IA
            if ($p->PedidoTieneError === 'S') {
                AiToolLog::create([
                    'user_id'           => 1,
                    'session_id'        => (string) Str::uuid(),
                    'tool_name'         => 'crear_pedido_cliente',
                    'pedido_id'         => $p->PedidoId,
                    'args'              => ['cliente_id' => $p->Pedido_ClienteId],
                    'result'            => ['success' => false, 'status' => 'missing_fields'],
                    'status'            => 'error',
                    'duration_ms'       => rand(1500, 2600),
                    'prompt_tokens'     => 11000,
                    'candidates_tokens' => 45,
                    'total_tokens'      => 11045,
                    'error_message'     => 'Inconsistencia detectada en cantidad solicitada vs stock disponible',
                    'created_at'        => $fecha,
                    'updated_at'        => $fecha,
                ]);
            }
        }

        echo "Calibración completada con éxito. Registros actualizados en Pedido, Producto y ai_tool_logs.\n";
    }
}
