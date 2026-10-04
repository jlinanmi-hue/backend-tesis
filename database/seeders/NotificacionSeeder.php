<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificacionSeeder extends Seeder
{
    /**
     * Auto-generated from database table `Notificacion`.
     * Total records: 8
     */
    public function run(): void
    {
        // Desactivar restricciones de claves foráneas temporalmente
        DB::statement('EXEC sp_msforeachtable "ALTER TABLE ? NOCHECK CONSTRAINT all"');

        // Limpiar tabla antes de poblar
        DB::table('Notificacion')->delete();

        $rows = [
            [
                'NotificacionId' => 'NOT-00001',
                'NotificacionTitulo' => 'Dañado Registrada: Aceite Chef 900ml',
                'NotificacionMensaje' => 'Se descontaron 2.00 UND. Pérdida económica estimada: S/ 11.60.',
                'NotificacionTipo' => 'merma',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'AJU-00002',
                'NotificacionDatos' => '{"badge":{"texto":"Da\\u00f1ado Confirmada","tipo":"warning","color":"#F59E0B","icono":"alert_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"AJU-00002","flujo":{"origen":"Stock Anterior: 10","destino":"Stock Actual: 8","indicador":"-2.00 UND"},"impacto_financiero":{"costo_total_perdido":11.6,"venta_total_perdida":13,"moneda":"S\\/","costo_unitario":5.8},"accion":{"texto":"Ver Detalles de Merma","url":"\\/api\\/ajustes\\/AJU-00002"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-08 15:27:16.533',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00002',
                'NotificacionTitulo' => 'Vencido Registrada: Guarana  500ml',
                'NotificacionMensaje' => 'Se descontaron 15.00 UND. Pérdida económica estimada: S/ 27.00.',
                'NotificacionTipo' => 'merma',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'AJU-00003',
                'NotificacionDatos' => '{"badge":{"texto":"Vencido Confirmada","tipo":"warning","color":"#F59E0B","icono":"alert_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"AJU-00003","flujo":{"origen":"Stock Anterior: 351","destino":"Stock Actual: 336","indicador":"-15.00 UND"},"impacto_financiero":{"costo_total_perdido":27,"venta_total_perdida":30,"moneda":"S\\/","costo_unitario":1.8},"accion":{"texto":"Ver Detalles de Merma","url":"\\/api\\/ajustes\\/AJU-00003"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-08 15:35:24.263',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00003',
                'NotificacionTitulo' => 'Ajuste Revertido: Arroz Delite',
                'NotificacionMensaje' => 'Se reintegraron 1.00 UND al stock tras la anulación del ajuste AJU-00001.',
                'NotificacionTipo' => 'reversion',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'AJU-00001',
                'NotificacionDatos' => '{"badge":{"texto":"Stock Reintegrado","tipo":"success","color":"#10B981","icono":"check_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"AJU-00001","flujo":{"origen":"Stock: 95","destino":"Stock: 96","indicador":"+1.00 UND"},"impacto_financiero":null,"accion":{"texto":"Ver Producto","url":"\\/api\\/inventario\\/productos\\/PROD-00001"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-08 15:36:21.580',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00004',
                'NotificacionTitulo' => 'Merma Registrada: Arroz Delite',
                'NotificacionMensaje' => 'Se descontaron 5.00 UND. Pérdida económica estimada: S/ 19.00.',
                'NotificacionTipo' => 'merma',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'AJU-00004',
                'NotificacionDatos' => '{"badge":{"texto":"Merma Confirmada","tipo":"warning","color":"#F59E0B","icono":"alert_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"AJU-00004","flujo":{"origen":"Stock Anterior: 96","destino":"Stock Actual: 91","indicador":"-5.00 UND"},"impacto_financiero":{"costo_total_perdido":19,"venta_total_perdida":20,"moneda":"S\\/","costo_unitario":3.8},"accion":{"texto":"Ver Detalles de Merma","url":"\\/api\\/ajustes\\/AJU-00004"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-08 15:37:09.743',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00005',
                'NotificacionTitulo' => 'Merma Registrada: Aceite Chef 900ml',
                'NotificacionMensaje' => 'Se descontaron 1.00 UND. Pérdida económica estimada: S/ 5.80.',
                'NotificacionTipo' => 'merma',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'AJU-00005',
                'NotificacionDatos' => '{"badge":{"texto":"Merma Confirmada","tipo":"warning","color":"#F59E0B","icono":"alert_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"AJU-00005","flujo":{"origen":"Stock Anterior: 8","destino":"Stock Actual: 7","indicador":"-1.00 UND"},"impacto_financiero":{"costo_total_perdido":5.8,"venta_total_perdida":6.5,"moneda":"S\\/","costo_unitario":5.8},"accion":{"texto":"Ver Detalles de Merma","url":"\\/api\\/ajustes\\/AJU-00005"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-08 20:26:19.667',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00006',
                'NotificacionTitulo' => 'Ajuste Revertido: Aceite Chef 900ml',
                'NotificacionMensaje' => 'Se reintegraron 1.00 UND al stock tras la anulación del ajuste AJU-00005.',
                'NotificacionTipo' => 'reversion',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'AJU-00005',
                'NotificacionDatos' => '{"badge":{"texto":"Stock Reintegrado","tipo":"success","color":"#10B981","icono":"check_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"AJU-00005","flujo":{"origen":"Stock: 7","destino":"Stock: 8","indicador":"+1.00 UND"},"impacto_financiero":null,"accion":{"texto":"Ver Producto","url":"\\/api\\/inventario\\/productos\\/PROD-00005"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-08 20:26:19.683',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00007',
                'NotificacionTitulo' => '⚠️ Orden PED-00004 por expirar',
                'NotificacionMensaje' => 'La orden PED-00004 (Bodega Los Amigos) expira en 1.5 horas. Complete el cobro o confirme el pedido para no perder la reserva de stock.',
                'NotificacionTipo' => 'warning',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'PED-00004',
                'NotificacionDatos' => '{"badge":{"texto":"Por Expirar","tipo":"warning","color":"#F59E0B","icono":"check_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"PED-00004","flujo":{"origen":"\\u00d3rdenes de Cliente","destino":"Ventas y Mostrador","indicador":"Resta: 1.5h"},"impacto_financiero":null,"accion":{"texto":"Gestionar Pedido","url":"\\/pedidos\\/PED-00004"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-09 03:49:49.797',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
            [
                'NotificacionId' => 'NOT-00008',
                'NotificacionTitulo' => '⚠️ Orden PED-00004 por expirar',
                'NotificacionMensaje' => 'La orden PED-00004 (Bodega Los Amigos) expira en 1.5 horas. Complete el cobro o confirme el pedido para no perder la reserva de stock.',
                'NotificacionTipo' => 'warning',
                'NotificacionCategoria' => 'INVENTARIO',
                'NotificacionReferenciaId' => 'PED-00004',
                'NotificacionDatos' => '{"badge":{"texto":"Por Expirar","tipo":"warning","color":"#F59E0B","icono":"check_circle"},"entidad":"COMERCIAL VALENCIA","referencia_codigo":"PED-00004","flujo":{"origen":"\\u00d3rdenes de Cliente","destino":"Ventas y Mostrador","indicador":"Resta: 1.5h"},"impacto_financiero":null,"accion":{"texto":"Gestionar Pedido","url":"\\/pedidos\\/PED-00004"},"modo_audio":"audible"}',
                'NotificacionLeida' => 'N',
                'NotificacionSilenciosa' => 'N',
                'NotificacionFecha' => '2026-09-09 03:50:12.983',
                'NotificacionUsuarioId' => 'TODOS',
                'NotificacionEliminado' => 'N',
            ],
        ];

        // Inserción en bloques con Query Builder nativo
        foreach (array_chunk($rows, 25) as $chunk) {
            DB::table('Notificacion')->insert($chunk);
        }

        // Reactivar restricciones de claves foráneas
        // DB::statement(CHECK CONSTRAINT all);
    }
}
