<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;

/**
 * 24 productos de Limpieza y Desinfección (CAT-00005).
 * Reglas aplicadas desde el registro:
 *  - Ningún producto solo con unidad base: todos llevan base + presentación mayor real.
 *  - Presentaciones de distribuidor (fardo/caja/bulto/display) verificadas.
 *  - Precios mayoristas con descuento distribuidora 2.5% + redondeo a entero YA aplicado.
 * Idempotente: omite nombres existentes.
 */
class ProductosLimpiezaSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // [nombre, marca, descripcion, ubi, stock, min, max, baseCompra, baseVenta, [umMayor, factor, compraMayor, ventaMayor], proveedores]
        $items = [
            ['PH Suave x4 rollos', 'Suave', 'Papel higienico suave paquete 4 rollos', 'UBI-00001', 120, 12, 400, 7.50, 8.50, ['UND-00016', 12, 85, 96], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['PH Elite x4 rollos 23m', 'Elite', 'Papel higienico elite paquete 4 rollos', 'UBI-00001', 100, 12, 350, 9.00, 10.00, ['UND-00016', 12, 101, 112], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['PH Elite x12 rollos 23m', 'Elite', 'Papel higienico elite paquete 12 rollos', 'UBI-00001', 70, 8, 250, 15.50, 16.90, ['UND-00015', 6, 88, 96], ['PRV-00004']],
            ['PH Paracas x4 rollos', 'Paracas', 'Papel higienico paracas paquete 4 rollos', 'UBI-00002', 110, 12, 350, 6.50, 7.20, ['UND-00016', 12, 73, 81], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Servilleta Elite x100 und', 'Elite', 'Servilletas elite paquete 100 und', 'UBI-00002', 80, 8, 250, 3.50, 4.00, ['UND-00016', 12, 39, 45], ['PRV-00004']],
            ['Toalla Nova x2 rollos', 'Nova', 'Toalla de papel nova paquete 2 rollos', 'UBI-00002', 60, 6, 200, 5.50, 6.20, ['UND-00015', 6, 31, 35], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Lejia Clorox 1L', 'Clorox', 'Lejia tradicional botella 1 litro', 'UBI-00001', 150, 15, 400, 4.00, 4.50, ['UND-00016', 12, 44, 50], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Lejia Clorox 2kg', 'Clorox', 'Lejia tradicional botella 2 kg', 'UBI-00001', 90, 10, 250, 7.50, 8.50, ['UND-00015', 6, 42, 48], ['PRV-00004']],
            ['Lejia Clorox Gel 1L', 'Clorox', 'Lejia en gel original botella 1L', 'UBI-00001', 70, 8, 200, 8.00, 8.90, ['UND-00016', 12, 90, 99], ['PRV-00004']],
            ['Lejia Liguria 1L', 'Liguria', 'Lejia botella 1 litro', 'UBI-00002', 120, 12, 350, 3.20, 3.80, ['UND-00016', 12, 35, 42], ['PRV-PYME-VECINA']],
            ['Lejia Clorox Galon 4L', 'Clorox', 'Lejia tradicional galon 4 litros', 'UBI-00001', 40, 5, 120, 9.00, 10.00, ['UND-00026', 4, 33, 37], ['PRV-00004']],
            ['Detergente Bolivar 850g', 'Bolivar', 'Detergente en polvo bolsa 850g', 'UBI-00001', 140, 15, 400, 8.50, 9.50, ['UND-00016', 12, 96, 106], ['PRV-00002', 'PRV-PYME-VECINA']],
            ['Detergente Ariel 850g', 'Ariel', 'Detergente en polvo bolsa 850g', 'UBI-00001', 100, 10, 300, 11.50, 12.80, ['UND-00016', 10, 107, 120], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Detergente Opal 2kg', 'Opal', 'Detergente en polvo bolsa 2kg', 'UBI-00001', 60, 8, 200, 16.50, 18.00, ['UND-00026', 4, 61, 67], ['PRV-00002']],
            ['Detergente Sapolio 730g', 'Sapolio', 'Detergente en polvo bolsa 730g', 'UBI-00002', 130, 12, 350, 5.50, 6.00, ['UND-00016', 12, 61, 67], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Detergente Marsella 450g', 'Marsella', 'Detergente en polvo bolsa 450g', 'UBI-00002', 110, 10, 300, 4.50, 5.00, ['UND-00016', 12, 50, 56], ['PRV-00002']],
            ['Lavavajilla Ayudin 500ml', 'Ayudin', 'Lavavajilla liquida botella 500ml', 'UBI-00001', 100, 10, 300, 5.00, 5.60, ['UND-00016', 12, 56, 62], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Lavavajilla Sapolio 900ml', 'Sapolio', 'Lavavajilla liquida botella 900ml', 'UBI-00001', 80, 8, 250, 8.00, 9.00, ['UND-00016', 12, 90, 100], ['PRV-00004']],
            ['Desinfectante Pinesol 900ml', 'Pinesol', 'Desinfectante pino botella 900ml', 'UBI-00001', 90, 10, 250, 4.30, 5.00, ['UND-00016', 12, 48, 56], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Desinfectante Sapolio 900ml', 'Sapolio', 'Desinfectante pino botella 900ml', 'UBI-00002', 85, 10, 250, 5.50, 6.10, ['UND-00016', 12, 61, 68], ['PRV-00004']],
            ['Jabon Neko 90g', 'Neko', 'Jabon de tocador barra 90g', 'UBI-00002', 150, 15, 400, 1.80, 2.20, ['UND-00019', 24, 40, 49], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Jabon Palmolive 85g x3', 'Palmolive', 'Jabon de tocador pack 3 barras 85g', 'UBI-00002', 70, 8, 200, 7.50, 8.50, ['UND-00023', 8, 56, 63], ['PRV-00004']],
            ['Shampoo Konzil 350ml', 'Konzil', 'Shampoo frasco 350ml', 'UBI-00001', 50, 6, 150, 12.00, 13.50, ['UND-00016', 12, 136, 152], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Pasta Dental Dento 90g', 'Dento', 'Pasta dental tubo 90g', 'UBI-00002', 120, 12, 350, 4.50, 5.20, ['UND-00016', 12, 50, 59], ['PRV-00004']],
        ];

        $creados = 0;
        $omitidos = 0;
        foreach ($items as $it) {
            [$nombre, $marca, $desc, $ubi, $stock, $min, $max, $cb, $vb, $mayor, $provs] = $it;

            if (\App\Models\Producto::where('ProductoNombre', $nombre)->exists()) {
                $omitidos++;
                continue;
            }

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
                ['unidades_medidaId' => 'UND-00001', 'factor_conversion' => 1, 'precio_compra' => $cb, 'precio_venta' => $vb],
                ['unidades_medidaId' => $mayor[0], 'factor_conversion' => $mayor[1], 'precio_compra' => $mayor[2], 'precio_venta' => $mayor[3]],
            ], $provs);

            $creados++;
        }

        $this->command->info("Productos Limpieza: {$creados} creados, {$omitidos} omitidos (ya existían).");
    }
}
