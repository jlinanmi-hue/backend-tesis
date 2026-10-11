<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ampliación Limpieza (CAT-00005): pastas Colgate/línea conocida, Suave x2 + plancha,
 * lejías chicas y bidón, Poett/aromáticos y rango ACE hasta sacos.
 * Reglas: base + mayor real, mayor con -2.5% redondeado YA aplicado, etiqueta
 * parecida + factor real. Idempotente por nombre.
 */
class ProductosLimpieza2Seeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // [nombre, marca, descripcion, ubi, stock, min, max, baseC, baseV, [um, factor, cMayor, vMayor], provs]
        $items = [
            ['Pasta Dental Colgate Triple 90g', 'Colgate', 'Pasta dental triple accion tubo 90g', 'UBI-00002', 110, 12, 350, 6.00, 7.00, ['UND-00016', 12, 66, 78], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Pasta Dental Kolynos 90g', 'Kolynos', 'Pasta dental limpieza completa 90g', 'UBI-00002', 120, 12, 350, 3.00, 3.50, ['UND-00016', 12, 33, 39], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Pasta Dental Close-Up 70g', 'Close-Up', 'Pasta dental rojo tubo 70g', 'UBI-00002', 90, 10, 300, 3.80, 4.40, ['UND-00016', 12, 42, 49], ['PRV-00004']],
            ['Pasta Dental Oral-B 70g', 'Oral-B', 'Pasta dental tubo 70g', 'UBI-00002', 80, 10, 300, 4.20, 4.80, ['UND-00016', 12, 47, 54], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['PH Suave x2 rollos', 'Suave', 'Papel higienico paquetito 2 rollos', 'UBI-00001', 140, 15, 450, 3.50, 4.00, ['UND-00016', 10, 32, 37], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Lejia Clorox 280g', 'Clorox', 'Lejia tradicional botella 280g', 'UBI-00002', 130, 12, 400, 2.00, 2.50, ['UND-00019', 24, 44, 55], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Lejia Clorox Galon 4kg', 'Clorox', 'Lejia tradicional galon 4 kg', 'UBI-00001', 45, 5, 120, 9.00, 10.00, ['UND-00015', 6, 50, 56], ['PRV-00004']],
            ['Lejia Liguria Bidon 5L', 'Liguria', 'Lejia bidon 5 litros', 'UBI-00001', 35, 4, 100, 12.00, 14.00, ['UND-00026', 4, 45, 52], ['PRV-PYME-VECINA']],
            ['Poett Lavanda 900ml', 'Poett', 'Limpiador aromatizante lavanda 900ml', 'UBI-00001', 90, 10, 280, 7.00, 7.90, ['UND-00016', 12, 78, 88], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Poett Galon 3.6L', 'Poett', 'Limpiador aromatizante galon 3.6L', 'UBI-00001', 30, 4, 90, 22.00, 25.00, ['UND-00026', 4, 82, 93], ['PRV-00004']],
            ['Ambientador Sapolio 360ml', 'Sapolio', 'Ambientador aerosol 360ml', 'UBI-00002', 75, 8, 220, 6.00, 7.00, ['UND-00016', 12, 66, 78], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Pastilla WC Sapolio', 'Sapolio', 'Pastilla desinfectante para WC', 'UBI-00002', 100, 10, 300, 2.50, 3.00, ['UND-00016', 12, 27, 33], ['PRV-00004']],
            ['Detergente ACE 1kg', 'ACE', 'Detergente en polvo bolsa 1kg', 'UBI-00001', 90, 10, 280, 9.50, 10.80, ['UND-00015', 6, 53, 60], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Detergente ACE 800g', 'ACE', 'Detergente en polvo bolsa 800g', 'UBI-00001', 100, 10, 300, 7.80, 8.80, ['UND-00015', 6, 43, 49], ['PRV-00004']],
            ['Detergente ACE 2kg', 'ACE', 'Detergente en polvo bolsa 2kg', 'UBI-00001', 55, 6, 150, 21.00, 23.00, ['UND-00026', 4, 78, 86], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Detergente ACE Magico Saco 15kg', 'ACE', 'Detergente granel saco 15kg precio x kilo', 'UBI-00001', 30, 5, 80, 4.50, 6.00, ['UND-00017', 15, 66, 73], ['PRV-00004']],
        ];

        $creados = 0;
        $omitidos = 0;
        foreach ($items as $it) {
            [$nombre, $marca, $desc, $ubi, $stock, $min, $max, $cb, $vb, $mayor, $provs] = $it;

            if (\App\Models\Producto::where('ProductoNombre', $nombre)->exists()) {
                $omitidos++;
                continue;
            }

            // ACE Mágico se vende por kilo: base en KG (unidad base del sistema).
            $umBase = str_contains($nombre, 'Magico') ? 'UND-00006' : 'UND-00001';

            $svc->crearProducto([
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => 'CAT-00005',
                'ProductoMarca' => $marca,
                'producto_descripcion' => $desc,
                'Producto_producto_ubi_id' => $ubi,
                'ProductoStockMinimo' => $min,
                'ProductoStockMaximo' => $max,
                'stockInicial' => $stock,
            ], [
                ['unidades_medidaId' => $umBase, 'factor_conversion' => 1, 'precio_compra' => $cb, 'precio_venta' => $vb],
                ['unidades_medidaId' => $mayor[0], 'factor_conversion' => $mayor[1], 'precio_compra' => $mayor[2], 'precio_venta' => $mayor[3]],
            ], $provs);

            $creados++;
        }

        // Saco Marsella 13.5kg = 30 bolsas de 450g sobre PROD-00083 (etiqueta x12, factor real 30).
        $marsella = \App\Models\Producto::where('ProductoNombre', 'Detergente Marsella 450g')->first();
        if ($marsella && !DB::table('Detalle_Producto_medida')
            ->where('Detalle_Producto_medida_ProductoId', $marsella->ProductoId)
            ->where('Detalle_Producto_medida_unidades_medidaId', 'UND-00019')->exists()) {
            DB::table('Detalle_Producto_medida')->insert([
                'Detalle_Producto_medida_ProductoId' => $marsella->ProductoId,
                'Detalle_Producto_medida_unidades_medidaId' => 'UND-00019',
                'Detalle_Producto_medida_factor_conversion' => 30,
                'Detalle_Producto_medida_precio_venta' => 76,
                'Detalle_Producto_medida_precio_compra' => 68,
                'Detalle_Producto_medidaEliminado' => 'N',
                'Detalle_Producto_medidaUsuarioCreacion' => 'SYSTEM',
                'Detalle_Producto_medidaHostCreacion' => '127.0.0.1',
                'Detalle_Producto_medidaFechaCreacion' => now(),
            ]);
            $this->command->info('Saco Marsella 13.5kg (x30 bolsas) agregado a ' . $marsella->ProductoId);
        }

        $this->command->info("Limpieza 2: {$creados} creados, {$omitidos} omitidos (ya existían).");
    }
}
