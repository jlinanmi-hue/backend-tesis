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
            // 1. Índices de aceleración en tablas base de inventario
            DB::statement('CREATE INDEX IF NOT EXISTS idx_mov_prod_fecha ON "Movimiento_producto" ("Movimiento_productoFecha_Movimiento" DESC);');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_mov_prod_eliminado ON "Movimiento_producto" ("Movimiento_productoEliminado");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_mov_prod_prodid ON "Movimiento_producto" ("Movimiento_producto_ProductoId");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_mov_prod_tipo ON "Movimiento_producto" ("Movimiento_productoTipoMovimiento");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_mov_prod_subtipo ON "Movimiento_producto" ("Movimiento_productoSubtipo");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_mov_prod_doc ON "Movimiento_producto" ("Movimiento_productoDocumentoOperacionId");');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_producto_eliminado ON "Producto" ("ProductoEliminado");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_producto_categoria ON "Producto" ("Producto_Categoria_ProductoId");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_producto_ubicacion ON "Producto" ("Producto_producto_ubi_id");');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_producto_fechacreacion ON "Producto" ("ProductoFechaCreacion" DESC);');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_dpm_prod_elim ON "Detalle_Producto_medida" ("Detalle_Producto_medida_ProductoId", "Detalle_Producto_medidaEliminado");');

            // 2. Vista optimizada para Stock y Catálogo Maestro de Productos
            DB::statement('
                CREATE OR REPLACE VIEW vista_stock_productos AS
                SELECT
                    p."ProductoId",
                    p."ProductoNombre",
                    p."ProductoMarca",
                    p."producto_descripcion",
                    p."producto_imagen",
                    p."ProductoStockActual",
                    p."ProductoStockMinimo",
                    p."ProductoStockMaximo",
                    p."ProductoEstado",
                    p."ProductoEliminado",
                    p."ProductoFechaCreacion",
                    p."Producto_Categoria_ProductoId",
                    p."Producto_producto_ubi_id",
                    COALESCE(c."Categoria_ProductoDescripcion_categoria", \'Sin Categoría\') AS categoria_nombre,
                    COALESCE(u."producto_ubi_descripcion", \'Sin Ubicación\') AS ubicacion_descripcion,
                    u."producto_ubi_observacion" AS ubicacion_observacion,
                    COALESCE(base_medida.unidad_nombre, \'Unidad\') AS unidad_base_nombre,
                    COALESCE(base_medida.unidad_abrev, \'UND\') AS unidad_base_abreviatura,
                    COALESCE(base_medida.precio_compra, 0) AS precio_compra_base,
                    COALESCE(base_medida.precio_venta, 0) AS precio_venta_base,
                    CASE
                        WHEN COALESCE(p."ProductoStockActual", 0) <= 0 THEN \'AGOTADO\'
                        WHEN COALESCE(p."ProductoStockActual", 0) <= COALESCE(p."ProductoStockMinimo", 0) THEN \'STOCK_MINIMO\'
                        ELSE \'NORMAL\'
                    END AS alerta_stock,
                    CASE
                        WHEN COALESCE(p."ProductoStockActual", 0) <= 0 THEN \'rose\'
                        WHEN COALESCE(p."ProductoStockActual", 0) <= COALESCE(p."ProductoStockMinimo", 0) THEN \'amber\'
                        ELSE \'emerald\'
                    END AS alerta_color,
                    COALESCE(detalles_sub.detalles_json, \'[]\'::json) AS detalle_producto_medidas
                FROM "Producto" p
                LEFT JOIN "Categoria_Producto" c ON p."Producto_Categoria_ProductoId" = c."Categoria_ProductoId"
                LEFT JOIN "producto_ubi" u ON p."Producto_producto_ubi_id" = u."producto_ubi_id"
                LEFT JOIN LATERAL (
                    SELECT
                        dpm."Detalle_Producto_medida_precio_compra" AS precio_compra,
                        dpm."Detalle_Producto_medida_precio_venta" AS precio_venta,
                        um."unidades_medidaDescripcionUnidades" AS unidad_nombre,
                        COALESCE(um."unidades_medidaAbreviatura", um."unidades_medidaDescripcionUnidades", \'UND\') AS unidad_abrev
                    FROM "Detalle_Producto_medida" dpm
                    LEFT JOIN "unidades_medida" um ON dpm."Detalle_Producto_medida_unidades_medidaId" = um."unidades_medidaId"
                    WHERE dpm."Detalle_Producto_medida_ProductoId" = p."ProductoId"
                      AND dpm."Detalle_Producto_medidaEliminado" = \'N\'
                    ORDER BY (CASE WHEN dpm."Detalle_Producto_medida_factor_conversion" = 1 THEN 0 ELSE 1 END), dpm."Detalle_Producto_medida_factor_conversion" ASC
                    LIMIT 1
                ) base_medida ON true
                LEFT JOIN LATERAL (
                    SELECT json_agg(json_build_object(
                        \'Detalle_Producto_medida_ProductoId\', dpm."Detalle_Producto_medida_ProductoId",
                        \'Detalle_Producto_medida_unidades_medidaId\', dpm."Detalle_Producto_medida_unidades_medidaId",
                        \'Detalle_Producto_medida_factor_conversion\', dpm."Detalle_Producto_medida_factor_conversion",
                        \'Detalle_Producto_medida_precio_compra\', dpm."Detalle_Producto_medida_precio_compra",
                        \'Detalle_Producto_medida_precio_venta\', dpm."Detalle_Producto_medida_precio_venta",
                        \'unidad_medida\', json_build_object(
                            \'unidades_medidaId\', um."unidades_medidaId",
                            \'unidades_medidaDescripcionUnidades\', um."unidades_medidaDescripcionUnidades",
                            \'unidades_medidaAbreviatura\', um."unidades_medidaAbreviatura"
                        )
                    ) ORDER BY dpm."Detalle_Producto_medida_factor_conversion" ASC) AS detalles_json
                    FROM "Detalle_Producto_medida" dpm
                    LEFT JOIN "unidades_medida" um ON dpm."Detalle_Producto_medida_unidades_medidaId" = um."unidades_medidaId"
                    WHERE dpm."Detalle_Producto_medida_ProductoId" = p."ProductoId"
                      AND dpm."Detalle_Producto_medidaEliminado" = \'N\'
                ) detalles_sub ON true;
            ');

            // 3. Vista optimizada para Kárdex y Movimientos de Inventario
            DB::statement('
                CREATE OR REPLACE VIEW vista_kardex_movimientos AS
                SELECT
                    m."Movimiento_productoId",
                    m."Movimiento_productoDocumentoOperacionId",
                    m."Movimiento_productoTipoMovimiento",
                    m."Movimiento_productoSubtipo",
                    m."Movimiento_productoCantidadEntrada",
                    m."Movimiento_productoCantidadSalida",
                    m."Movimiento_productoCantidadSaldo",
                    m."Movimiento_productoCantidadPresentacion",
                    m."Movimiento_productoCostoPrecioUnitario",
                    m."Movimiento_productoFecha_Movimiento",
                    m."Movimiento_producto_ProductoId",
                    m."Movimiento_producto_Detalle_Producto_medida_unidades_medidaId",
                    m."Movimiento_productoEliminado",
                    m."Movimiento_productoFechaCreacion",
                    m."Movimiento_productoUsuarioCreacion",
                    m."Movimiento_productoReferenciaTipo",
                    m."Movimiento_productoReferenciaId",
                    m."Movimiento_productoMotivo",
                    COALESCE(p."ProductoNombre", \'Producto no disponible\') AS producto_nombre,
                    COALESCE(p."ProductoMarca", \'\') AS producto_marca,
                    COALESCE(c."Categoria_ProductoDescripcion_categoria", \'Sin Categoría\') AS categoria_nombre,
                    COALESCE(base_u.unidad_base_desc, \'Unidad\') AS unidad_base_nombre,
                    COALESCE(base_u.unidad_base_abrev, \'UND\') AS unidad_base_abreviatura,
                    COALESCE(um_mov."unidades_medidaDescripcionUnidades", base_u.unidad_base_desc, \'Unidad\') AS unidad_movimiento_nombre,
                    COALESCE(um_mov."unidades_medidaAbreviatura", base_u.unidad_base_abrev, \'UND\') AS unidad_movimiento_abreviatura,
                    CASE
                        WHEN m."Movimiento_productoTipoMovimiento" = \'E\' THEN \'ENTRADA\'
                        ELSE \'SALIDA\'
                    END AS tipo_movimiento_badge,
                    CASE
                        WHEN m."Movimiento_productoTipoMovimiento" = \'E\' THEN \'emerald\'
                        ELSE \'rose\'
                    END AS tipo_movimiento_color,
                    CASE
                        WHEN m."Movimiento_productoSubtipo" = \'RECEPCION_OC\' THEN \'Recepción de Orden de Compra\'
                        WHEN m."Movimiento_productoSubtipo" = \'ANULACION_RECEPCION\' THEN \'Contra-Movimiento (Anulación OC)\'
                        WHEN m."Movimiento_productoSubtipo" = \'COMPRA_RAPIDA\' THEN \'Compra Rápida a PYME Vecina\'
                        WHEN m."Movimiento_productoSubtipo" = \'AJUSTE_MANUAL\' THEN \'Ajuste Manual de Kárdex\'
                        WHEN m."Movimiento_productoSubtipo" = \'DEVOLUCION_CLIENTE\' THEN \'Devolución de Pedido Cliente\'
                        WHEN m."Movimiento_productoSubtipo" = \'INVENTARIO_INICIAL\' THEN \'Inventario Inicial\'
                        WHEN m."Movimiento_productoDocumentoOperacionId" LIKE \'AJU-%\' THEN \'Ajuste / Merma de Inventario\'
                        WHEN m."Movimiento_productoDocumentoOperacionId" LIKE \'REV-%\' THEN \'Reversión de Ajuste\'
                        WHEN m."Movimiento_productoDocumentoOperacionId" LIKE \'INV-%\' THEN \'Inventario Inicial\'
                        WHEN m."Movimiento_productoDocumentoOperacionId" LIKE \'PED-%\' THEN \'Salida por Pedido de Venta\'
                        WHEN m."Movimiento_productoTipoMovimiento" = \'E\' THEN \'Recepción / Compra\'
                        ELSE \'Salida de Mercadería\'
                    END AS tipo_operacion_texto
                FROM "Movimiento_producto" m
                LEFT JOIN "Producto" p ON m."Movimiento_producto_ProductoId" = p."ProductoId"
                LEFT JOIN "Categoria_Producto" c ON p."Producto_Categoria_ProductoId" = c."Categoria_ProductoId"
                LEFT JOIN "unidades_medida" um_mov ON m."Movimiento_producto_Detalle_Producto_medida_unidades_medidaId" = um_mov."unidades_medidaId"
                LEFT JOIN LATERAL (
                    SELECT
                        um_b."unidades_medidaDescripcionUnidades" AS unidad_base_desc,
                        COALESCE(um_b."unidades_medidaAbreviatura", um_b."unidades_medidaDescripcionUnidades", \'UND\') AS unidad_base_abrev
                    FROM "Detalle_Producto_medida" dpm_b
                    LEFT JOIN "unidades_medida" um_b ON dpm_b."Detalle_Producto_medida_unidades_medidaId" = um_b."unidades_medidaId"
                    WHERE dpm_b."Detalle_Producto_medida_ProductoId" = p."ProductoId"
                      AND dpm_b."Detalle_Producto_medidaEliminado" = \'N\'
                    ORDER BY (CASE WHEN dpm_b."Detalle_Producto_medida_factor_conversion" = 1 THEN 0 ELSE 1 END), dpm_b."Detalle_Producto_medida_factor_conversion" ASC
                    LIMIT 1
                ) base_u ON true;
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS vista_kardex_movimientos CASCADE;');
            DB::statement('DROP VIEW IF EXISTS vista_stock_productos CASCADE;');

            DB::statement('DROP INDEX IF EXISTS idx_mov_prod_fecha;');
            DB::statement('DROP INDEX IF EXISTS idx_mov_prod_eliminado;');
            DB::statement('DROP INDEX IF EXISTS idx_mov_prod_prodid;');
            DB::statement('DROP INDEX IF EXISTS idx_mov_prod_tipo;');
            DB::statement('DROP INDEX IF EXISTS idx_mov_prod_subtipo;');
            DB::statement('DROP INDEX IF EXISTS idx_mov_prod_doc;');

            DB::statement('DROP INDEX IF EXISTS idx_producto_eliminado;');
            DB::statement('DROP INDEX IF EXISTS idx_producto_categoria;');
            DB::statement('DROP INDEX IF EXISTS idx_producto_ubicacion;');
            DB::statement('DROP INDEX IF EXISTS idx_producto_fechacreacion;');

            DB::statement('DROP INDEX IF EXISTS idx_dpm_prod_elim;');
        }
    }
};
