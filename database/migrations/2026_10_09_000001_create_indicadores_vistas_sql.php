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
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // 1. Vista diaria en PostgreSQL
            DB::statement('
                CREATE OR REPLACE VIEW v_indicadores_diarios AS
                SELECT 
                    CAST(p."PedidoFechaCreacion" AS DATE) AS fecha,
                    COUNT(DISTINCT p."PedidoId") AS total_pedidos,
                    COUNT(DISTINCT p."PedidoId") AS total_ordenes,
                    SUM(CASE WHEN p."PedidoEstado_pedido" = \'C\' OR p."PedidoEstadoDespacho" = \'ENTREGADO\' THEN 1 ELSE 0 END) AS despachadas_exitosamente,
                    SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1 ELSE 0 END) AS pedidos_con_rotura,
                    ROUND(SUM(CASE WHEN CAST(COALESCE(p."PedidoTiempoRegistroSeg", 0) AS NUMERIC) > 0 THEN CAST(p."PedidoTiempoRegistroSeg" AS NUMERIC) / 60.0 ELSE 0.75 END), 2) AS tiempo_total_min,
                    COUNT(p."PedidoId") AS ordenes_registradas,
                    ROUND((SUM(CASE WHEN p."PedidoEstado_pedido" = \'C\' OR p."PedidoEstadoDespacho" = \'ENTREGADO\' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 2) AS pode,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 2) AS prs,
                    ROUND((SUM(CASE WHEN CAST(COALESCE(p."PedidoTiempoRegistroSeg", 0) AS NUMERIC) > 0 THEN CAST(p."PedidoTiempoRegistroSeg" AS NUMERIC) / 60.0 ELSE 0.75 END) / NULLIF(COUNT(p."PedidoId"), 0)), 2) AS tbpp,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1.0 ELSE 0.0 END) / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 4) AS promedio_roturas_pedido
                FROM "Pedido" p
                LEFT JOIN (
                    SELECT pedido_id, COUNT(*) as cant_roturas
                    FROM detalle_rotura_stock
                    GROUP BY pedido_id
                ) r ON p."PedidoId" = r.pedido_id
                WHERE p."PedidoEliminado" = \'N\'
                GROUP BY CAST(p."PedidoFechaCreacion" AS DATE);
            ');

            // 2. Vista semanal en PostgreSQL
            DB::statement('
                CREATE OR REPLACE VIEW v_indicadores_semanales AS
                SELECT 
                    CAST(EXTRACT(YEAR FROM p."PedidoFechaCreacion") AS INTEGER) AS anio,
                    CAST(EXTRACT(WEEK FROM p."PedidoFechaCreacion") AS INTEGER) AS semana,
                    COUNT(DISTINCT p."PedidoId") AS total_pedidos,
                    COUNT(DISTINCT p."PedidoId") AS total_ordenes,
                    SUM(CASE WHEN p."PedidoEstado_pedido" = \'C\' OR p."PedidoEstadoDespacho" = \'ENTREGADO\' THEN 1 ELSE 0 END) AS despachadas_exitosamente,
                    SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1 ELSE 0 END) AS pedidos_con_rotura,
                    ROUND(SUM(CASE WHEN CAST(COALESCE(p."PedidoTiempoRegistroSeg", 0) AS NUMERIC) > 0 THEN CAST(p."PedidoTiempoRegistroSeg" AS NUMERIC) / 60.0 ELSE 0.75 END), 2) AS tiempo_total_min,
                    COUNT(p."PedidoId") AS ordenes_registradas,
                    ROUND((SUM(CASE WHEN p."PedidoEstado_pedido" = \'C\' OR p."PedidoEstadoDespacho" = \'ENTREGADO\' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 2) AS pode,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 2) AS prs,
                    ROUND((SUM(CASE WHEN CAST(COALESCE(p."PedidoTiempoRegistroSeg", 0) AS NUMERIC) > 0 THEN CAST(p."PedidoTiempoRegistroSeg" AS NUMERIC) / 60.0 ELSE 0.75 END) / NULLIF(COUNT(p."PedidoId"), 0)), 2) AS tbpp,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1.0 ELSE 0.0 END) / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 4) AS promedio_roturas_pedido
                FROM "Pedido" p
                LEFT JOIN (
                    SELECT pedido_id, COUNT(*) as cant_roturas
                    FROM detalle_rotura_stock
                    GROUP BY pedido_id
                ) r ON p."PedidoId" = r.pedido_id
                WHERE p."PedidoEliminado" = \'N\'
                GROUP BY EXTRACT(YEAR FROM p."PedidoFechaCreacion"), EXTRACT(WEEK FROM p."PedidoFechaCreacion");
            ');

            // 3. Vista mensual en PostgreSQL
            DB::statement('
                CREATE OR REPLACE VIEW v_indicadores_mensuales AS
                SELECT 
                    CAST(EXTRACT(YEAR FROM p."PedidoFechaCreacion") AS INTEGER) AS anio,
                    CAST(EXTRACT(MONTH FROM p."PedidoFechaCreacion") AS INTEGER) AS mes,
                    COUNT(DISTINCT p."PedidoId") AS total_pedidos,
                    COUNT(DISTINCT p."PedidoId") AS total_ordenes,
                    SUM(CASE WHEN p."PedidoEstado_pedido" = \'C\' OR p."PedidoEstadoDespacho" = \'ENTREGADO\' THEN 1 ELSE 0 END) AS despachadas_exitosamente,
                    SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1 ELSE 0 END) AS pedidos_con_rotura,
                    ROUND(SUM(CASE WHEN CAST(COALESCE(p."PedidoTiempoRegistroSeg", 0) AS NUMERIC) > 0 THEN CAST(p."PedidoTiempoRegistroSeg" AS NUMERIC) / 60.0 ELSE 0.75 END), 2) AS tiempo_total_min,
                    COUNT(p."PedidoId") AS ordenes_registradas,
                    ROUND((SUM(CASE WHEN p."PedidoEstado_pedido" = \'C\' OR p."PedidoEstadoDespacho" = \'ENTREGADO\' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 2) AS pode,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 2) AS prs,
                    ROUND((SUM(CASE WHEN CAST(COALESCE(p."PedidoTiempoRegistroSeg", 0) AS NUMERIC) > 0 THEN CAST(p."PedidoTiempoRegistroSeg" AS NUMERIC) / 60.0 ELSE 0.75 END) / NULLIF(COUNT(p."PedidoId"), 0)), 2) AS tbpp,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p."PedidoMotivoAnulacion" = \'FALTA_STOCK\' THEN 1.0 ELSE 0.0 END) / NULLIF(COUNT(DISTINCT p."PedidoId"), 0)), 4) AS promedio_roturas_pedido
                FROM "Pedido" p
                LEFT JOIN (
                    SELECT pedido_id, COUNT(*) as cant_roturas
                    FROM detalle_rotura_stock
                    GROUP BY pedido_id
                ) r ON p."PedidoId" = r.pedido_id
                WHERE p."PedidoEliminado" = \'N\'
                GROUP BY EXTRACT(YEAR FROM p."PedidoFechaCreacion"), EXTRACT(MONTH FROM p."PedidoFechaCreacion");
            ');
        } else {
            // 1. Vista diaria en SQL Server
            DB::statement("
                CREATE OR ALTER VIEW v_indicadores_diarios AS
                SELECT 
                    CAST(p.PedidoFechaCreacion AS DATE) AS fecha,
                    COUNT(DISTINCT p.PedidoId) AS total_pedidos,
                    COUNT(DISTINCT p.PedidoId) AS total_ordenes,
                    SUM(CASE WHEN p.PedidoEstado_pedido = 'C' OR p.PedidoEstadoDespacho = 'ENTREGADO' THEN 1 ELSE 0 END) AS despachadas_exitosamente,
                    SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1 ELSE 0 END) AS pedidos_con_rotura,
                    ROUND(SUM(CASE WHEN CAST(COALESCE(p.PedidoTiempoRegistroSeg, 0) AS FLOAT) > 0 THEN CAST(p.PedidoTiempoRegistroSeg AS FLOAT) / 60.0 ELSE 0.75 END), 2) AS tiempo_total_min,
                    COUNT(p.PedidoId) AS ordenes_registradas,
                    ROUND((SUM(CASE WHEN p.PedidoEstado_pedido = 'C' OR p.PedidoEstadoDespacho = 'ENTREGADO' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 2) AS pode,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 2) AS prs,
                    ROUND((SUM(CASE WHEN CAST(COALESCE(p.PedidoTiempoRegistroSeg, 0) AS FLOAT) > 0 THEN CAST(p.PedidoTiempoRegistroSeg AS FLOAT) / 60.0 ELSE 0.75 END) / NULLIF(COUNT(p.PedidoId), 0)), 2) AS tbpp,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1.0 ELSE 0.0 END) / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 4) AS promedio_roturas_pedido
                FROM Pedido p
                LEFT JOIN (
                    SELECT pedido_id, COUNT(*) as cant_roturas
                    FROM detalle_rotura_stock
                    GROUP BY pedido_id
                ) r ON p.PedidoId = r.pedido_id
                WHERE p.PedidoEliminado = 'N'
                GROUP BY CAST(p.PedidoFechaCreacion AS DATE);
            ");

            // 2. Vista semanal en SQL Server
            DB::statement("
                CREATE OR ALTER VIEW v_indicadores_semanales AS
                SELECT 
                    YEAR(p.PedidoFechaCreacion) AS anio,
                    DATEPART(WEEK, p.PedidoFechaCreacion) AS semana,
                    COUNT(DISTINCT p.PedidoId) AS total_pedidos,
                    COUNT(DISTINCT p.PedidoId) AS total_ordenes,
                    SUM(CASE WHEN p.PedidoEstado_pedido = 'C' OR p.PedidoEstadoDespacho = 'ENTREGADO' THEN 1 ELSE 0 END) AS despachadas_exitosamente,
                    SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1 ELSE 0 END) AS pedidos_con_rotura,
                    ROUND(SUM(CASE WHEN CAST(COALESCE(p.PedidoTiempoRegistroSeg, 0) AS FLOAT) > 0 THEN CAST(p.PedidoTiempoRegistroSeg AS FLOAT) / 60.0 ELSE 0.75 END), 2) AS tiempo_total_min,
                    COUNT(p.PedidoId) AS ordenes_registradas,
                    ROUND((SUM(CASE WHEN p.PedidoEstado_pedido = 'C' OR p.PedidoEstadoDespacho = 'ENTREGADO' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 2) AS pode,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 2) AS prs,
                    ROUND((SUM(CASE WHEN CAST(COALESCE(p.PedidoTiempoRegistroSeg, 0) AS FLOAT) > 0 THEN CAST(p.PedidoTiempoRegistroSeg AS FLOAT) / 60.0 ELSE 0.75 END) / NULLIF(COUNT(p.PedidoId), 0)), 2) AS tbpp,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1.0 ELSE 0.0 END) / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 4) AS promedio_roturas_pedido
                FROM Pedido p
                LEFT JOIN (
                    SELECT pedido_id, COUNT(*) as cant_roturas
                    FROM detalle_rotura_stock
                    GROUP BY pedido_id
                ) r ON p.PedidoId = r.pedido_id
                WHERE p.PedidoEliminado = 'N'
                GROUP BY YEAR(p.PedidoFechaCreacion), DATEPART(WEEK, p.PedidoFechaCreacion);
            ");

            // 3. Vista mensual en SQL Server
            DB::statement("
                CREATE OR ALTER VIEW v_indicadores_mensuales AS
                SELECT 
                    YEAR(p.PedidoFechaCreacion) AS anio,
                    MONTH(p.PedidoFechaCreacion) AS mes,
                    COUNT(DISTINCT p.PedidoId) AS total_pedidos,
                    COUNT(DISTINCT p.PedidoId) AS total_ordenes,
                    SUM(CASE WHEN p.PedidoEstado_pedido = 'C' OR p.PedidoEstadoDespacho = 'ENTREGADO' THEN 1 ELSE 0 END) AS despachadas_exitosamente,
                    SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1 ELSE 0 END) AS pedidos_con_rotura,
                    ROUND(SUM(CASE WHEN CAST(COALESCE(p.PedidoTiempoRegistroSeg, 0) AS FLOAT) > 0 THEN CAST(p.PedidoTiempoRegistroSeg AS FLOAT) / 60.0 ELSE 0.75 END), 2) AS tiempo_total_min,
                    COUNT(p.PedidoId) AS ordenes_registradas,
                    ROUND((SUM(CASE WHEN p.PedidoEstado_pedido = 'C' OR p.PedidoEstadoDespacho = 'ENTREGADO' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 2) AS pode,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1.0 ELSE 0.0 END) * 100.0 / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 2) AS prs,
                    ROUND((SUM(CASE WHEN CAST(COALESCE(p.PedidoTiempoRegistroSeg, 0) AS FLOAT) > 0 THEN CAST(p.PedidoTiempoRegistroSeg AS FLOAT) / 60.0 ELSE 0.75 END) / NULLIF(COUNT(p.PedidoId), 0)), 2) AS tbpp,
                    ROUND((SUM(CASE WHEN r.pedido_id IS NOT NULL OR p.PedidoMotivoAnulacion = 'FALTA_STOCK' THEN 1.0 ELSE 0.0 END) / NULLIF(COUNT(DISTINCT p.PedidoId), 0)), 4) AS promedio_roturas_pedido
                FROM Pedido p
                LEFT JOIN (
                    SELECT pedido_id, COUNT(*) as cant_roturas
                    FROM detalle_rotura_stock
                    GROUP BY pedido_id
                ) r ON p.PedidoId = r.pedido_id
                WHERE p.PedidoEliminado = 'N'
                GROUP BY YEAR(p.PedidoFechaCreacion), MONTH(p.PedidoFechaCreacion);
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_indicadores_diarios");
        DB::statement("DROP VIEW IF EXISTS v_indicadores_semanales");
        DB::statement("DROP VIEW IF EXISTS v_indicadores_mensuales");
    }
};
