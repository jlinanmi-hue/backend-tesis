<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Avenas Grano de Oro + granel (CAT-00007) y corrección Maca 330g a precio real
 * (S/1.50/1.80). Granel con lógica arroz/azúcar: kilo x n exacto. Idempotente.
 */
class ProductosAvenaSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // Unidad exacta faltante para bolsa de 5kg.
        if (!DB::table('unidades_medida')->where('unidades_medidaId', 'UND-00034')->exists()) {
            DB::table('unidades_medida')->insert([
                'unidades_medidaId' => 'UND-00034',
                'unidades_medidaDescripcionUnidades' => 'BOLSA*5',
                'unidades_medidaAbreviatura' => 'BOL5',
                'unidades_medidaEsBase' => 0,
                'unidades_medidaEstadoUnidades' => 'A',
                'unidades_medidaEliminado' => 'N',
                'unidades_medidaUsuarioCreacion' => 'SYSTEM',
                'unidades_medidaHostCreacion' => '127.0.0.1',
                'unidades_medidaFechaCreacion' => now(),
            ]);
            $this->command->info('INS unidad UND-00034 BOLSA*5');
        }

        // Corrección Maca 330g: base 1.50/1.80, pack x12 con -2.5% (18/21).
        DB::table('Detalle_Producto_medida')
            ->where('Detalle_Producto_medida_ProductoId', 'PROD-00156')
            ->where('Detalle_Producto_medida_unidades_medidaId', 'UND-00001')
            ->update(['Detalle_Producto_medida_precio_compra' => 1.50, 'Detalle_Producto_medida_precio_venta' => 1.80, 'Detalle_Producto_medidaUsuarioModificacion' => 'ADMIN', 'Detalle_Producto_medidaHostModificacion' => '127.0.0.1', 'Detalle_Producto_medidaFechaModificacion' => now()]);
        DB::table('Detalle_Producto_medida')
            ->where('Detalle_Producto_medida_ProductoId', 'PROD-00156')
            ->where('Detalle_Producto_medida_unidades_medidaId', 'UND-00016')
            ->update(['Detalle_Producto_medida_precio_compra' => 18.00, 'Detalle_Producto_medida_precio_venta' => 21.00, 'Detalle_Producto_medidaUsuarioModificacion' => 'ADMIN', 'Detalle_Producto_medidaHostModificacion' => '127.0.0.1', 'Detalle_Producto_medidaFechaModificacion' => now()]);
        $this->command->info('FIX Maca 330g: base 1.50/1.80, pack x12 18/21');

        // [nombre, marca, descripcion, stock, min, max, detalles, provs]
        $items = [
            ['Avena Grano de Oro 1kg', 'Grano de Oro', 'Avena integral bolsa 1kg', 80, 8, 250, [
                ['UND-00001', 1, 6.00, 6.80],
                ['UND-00015', 6, 33, 38],
            ], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Avena Grano de Oro Sachet', 'Grano de Oro', 'Avena sachet individual', 240, 20, 600, [
                ['UND-00001', 1, 0.90, 1.00],
                ['UND-00019', 24, 20, 22],
            ], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Avena Granel x Kg', 'Grano de Oro', 'Avena a granel precio por kilo', 120, 15, 400, [
                ['UND-00006', 1, 3.50, 4.00],
                ['UND-00034', 5, 17.50, 20.00],
                ['UND-00014', 10, 35.00, 40.00],
                ['UND-00013', 25, 87.50, 100.00],
            ], ['PRV-PYME-VECINA']],
        ];

        $creados = 0;
        $omitidos = 0;
        foreach ($items as [$nombre, $marca, $desc, $stock, $min, $max, $dets, $provs]) {
            if (\App\Models\Producto::where('ProductoNombre', $nombre)->exists()) {
                $omitidos++;
                continue;
            }
            $svc->crearProducto([
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => 'CAT-00007',
                'ProductoMarca' => $marca,
                'producto_descripcion' => $desc,
                'Producto_producto_ubi_id' => 'UBI-00002',
                'ProductoStockMinimo' => $min,
                'ProductoStockMaximo' => $max,
                'stockInicial' => $stock,
            ], array_map(fn($d) => ['unidades_medidaId' => $d[0], 'factor_conversion' => $d[1], 'precio_compra' => $d[2], 'precio_venta' => $d[3]], $dets), $provs);
            $creados++;
        }

        $this->command->info("Avenas: {$creados} creadas, {$omitidos} omitidas (ya existían).");
    }
}
