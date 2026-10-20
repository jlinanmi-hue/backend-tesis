<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $createFn = <<<SQL
CREATE OR REPLACE FUNCTION fix_mojibake(input_text text)
RETURNS text LANGUAGE plpgsql AS $$
BEGIN
    IF input_text IS NULL OR (input_text NOT LIKE '%Ã%' AND input_text NOT LIKE '%Â%') THEN
        RETURN input_text;
    END IF;
    BEGIN
        RETURN convert_from(convert_to(input_text, 'WIN1252'), 'UTF8');
    EXCEPTION WHEN OTHERS THEN
        BEGIN
            RETURN convert_from(convert_to(input_text, 'LATIN1'), 'UTF8');
        EXCEPTION WHEN OTHERS THEN
            RETURN input_text;
        END;
    END;
END;
$$;
SQL;

        DB::statement($createFn);

        $columnsToFix = [
            'Ajuste_inventario' => ['Ajuste_inventario_motivo', 'Ajuste_inventario_tipo'],
            'Categoria_Producto' => ['Categoria_ProductoDescripcion_categoria'],
            'Cliente' => ['ClienteDireccion', 'ClienteNombre'],
            'Consulta_Ruc' => ['ConsultaRucRazonSocial', 'ConsultaRucDireccion'],
            'Detalle_Pedido_Productos' => ['Detalle_Pedido_Productos_observacion_item'],
            'Empleado' => ['EmpleadoApellidos', 'EmpleadoNombres', 'EmpleadoDireccion'],
            'Empresa' => ['EmpresaMensajeTicket', 'EmpresaRazonSocial', 'EmpresaDireccion'],
            'Movimiento_producto' => ['Movimiento_productoMotivo'],
            'Notificacion' => ['NotificacionDatos', 'NotificacionMensaje', 'NotificacionTitulo'],
            'Orden_Compra' => ['Orden_CompraMotivoAnulacion', 'Orden_CompraObservacion'],
            'Pedido' => ['PedidoAcuerdo_Comercial', 'PedidoDireccionEntrega', 'PedidoMotivoAnulacion', 'PedidoObservacionDespacho', 'PedidoObservaciones'],
            'Producto' => ['ProductoMarca', 'ProductoNombre', 'ProductoZona', 'producto_descripcion'],
            'Proveedor' => ['ProveedorActividadEconomica', 'ProveedorTipoContribuyente', 'ProveedorRazonSocial', 'ProveedorDireccion'],
            'Rol_User' => ['Rol_UserRol_Usercol'],
            'Zona_Delivery' => ['Zona_DeliveryNombre', 'Zona_DeliveryPoligonoGeoJSON'],
            'ai_generation_feedback' => ['comentario', 'motivo_dislike'],
            'ai_intent_logs' => ['query'],
            'ai_tool_logs' => ['args', 'error_message', 'result'],
            'cargo' => ['cargoDescripcion'],
            'detalle_rotura_stock' => ['observaciones'],
            'pedido_historial_correccion' => ['motivo', 'valor_anterior', 'valor_nuevo'],
        ];

        foreach ($columnsToFix as $table => $cols) {
            foreach ($cols as $col) {
                try {
                    $sql = "UPDATE \"{$table}\" SET \"{$col}\" = fix_mojibake(\"{$col}\") WHERE \"{$col}\" LIKE '%Ã%' OR \"{$col}\" LIKE '%Â%'";
                    DB::statement($sql);
                } catch (\Throwable $e) {
                    // Ignorar si la tabla o columna no existe en algún entorno
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // La corrección de caracteres es unidireccional (corrige caracteres dañados)
    }
};
