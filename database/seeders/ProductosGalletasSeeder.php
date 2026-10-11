<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;

/**
 * 18 galletas comerciales (CAT-00006): Costa, San Jorge, Sayón, Nestlé, Mondelez, Casino.
 * Regla: unidad + sixpack + caja (familiar solo unidad + caja). Mayoristas con -2.5%
 * redondeado YA aplicado. Sixpack SJ 6+1 = factor real 7 con etiqueta x6.
 * Idempotente por nombre.
 */
class ProductosGalletasSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // [nombre, marca, descripcion, stock, min, max, baseC, baseV, [sixUM, sixF, sixC, sixV], [cajaUM, cajaF, cajaC, cajaV]]
        // six/caja null = no aplica esa presentación.
        $items = [
            ['Soda San Jorge paq', 'San Jorge', 'Galleta de soda paquetito', 180, 15, 500, 0.50, 0.70, ['UND-00015', 7, 3, 4], ['UND-00019', 28, 13, 15]],
            ['Vainilla San Jorge paq', 'San Jorge', 'Galleta de vainilla paquetito', 180, 15, 500, 0.50, 0.70, ['UND-00015', 7, 3, 4], ['UND-00019', 28, 13, 15]],
            ['Soda Familiar San Jorge', 'San Jorge', 'Galleta soda familiar', 120, 10, 300, 1.30, 1.60, null, ['UND-00018', 20, 25, 29]],
            ['Rellenita Fresa San Jorge', 'San Jorge', 'Galleta rellenita sabor fresa', 140, 12, 400, 0.80, 1.00, ['UND-00015', 6, 4, 5], ['UND-00016', 12, 47, 54]],
            ['Rellenita Chocolate San Jorge', 'San Jorge', 'Galleta rellenita sabor chocolate', 140, 12, 400, 0.80, 1.00, ['UND-00015', 6, 4, 5], ['UND-00016', 12, 47, 54]],
            ['Animalitos San Jorge', 'San Jorge', 'Galleta animalitos bolsa', 100, 10, 300, 1.50, 1.80, ['UND-00015', 6, 8, 9], ['UND-00016', 12, 96, 107]],
            ['Soda Costa paq', 'Costa', 'Galleta de soda paquetito', 170, 15, 500, 0.50, 0.70, ['UND-00015', 6, 3, 4], ['UND-00019', 24, 13, 15]],
            ['Vainilla Costa paq', 'Costa', 'Galleta de vainilla paquetito', 170, 15, 500, 0.50, 0.70, ['UND-00015', 6, 3, 4], ['UND-00019', 24, 13, 15]],
            ['Rellenita Fresa Costa', 'Costa', 'Galleta rellenita sabor fresa', 130, 12, 400, 0.80, 1.00, ['UND-00015', 6, 4, 5], ['UND-00016', 12, 47, 54]],
            ['Picaras Clasicas 38g', 'Picaras', 'Galleta bañada chocolate 38g', 150, 12, 400, 0.90, 1.10, ['UND-00015', 6, 5, 6], ['UND-00016', 12, 56, 64]],
            ['Picaras XL 60g', 'Picaras', 'Galleta bañada chocolate XL 60g', 110, 10, 300, 2.60, 2.90, ['UND-00015', 6, 14, 16], ['UND-00023', 8, 107, 119]],
            ['Morochas Nestle 30g', 'Nestle', 'Galleta vainilla chocolate 30g', 150, 12, 400, 0.70, 0.90, ['UND-00015', 6, 4, 5], ['UND-00016', 12, 50, 58]],
            ['Oreo Original 36g', 'Oreo', 'Galleta sandwich chocolate 36g', 140, 12, 400, 2.60, 2.90, ['UND-00015', 6, 14, 16], ['UND-00023', 8, 107, 119]],
            ['Oreo Vainilla 36g', 'Oreo', 'Galleta sandwich vainilla 36g', 130, 12, 400, 2.60, 2.90, ['UND-00015', 6, 14, 16], ['UND-00023', 8, 107, 119]],
            ['Casino Chocolate sixpack', 'Casino', 'Galleta casino chocolate sixpack', 120, 10, 350, 0.70, 0.90, ['UND-00015', 6, 4, 5], ['UND-00023', 8, 35, 40]],
            ['Casino Menta sixpack', 'Casino', 'Galleta casino menta sixpack', 110, 10, 350, 0.70, 0.90, ['UND-00015', 6, 4, 5], ['UND-00023', 8, 35, 40]],
            ['Soda Sayon paq', 'Sayon', 'Galleta de soda paquetito', 150, 12, 450, 0.50, 0.70, ['UND-00015', 6, 3, 4], ['UND-00019', 24, 12, 14]],
            ['Vainilla Sayon paq', 'Sayon', 'Galleta de vainilla paquetito', 150, 12, 450, 0.50, 0.70, ['UND-00015', 6, 3, 4], ['UND-00019', 24, 12, 14]],
        ];

        $creados = 0;
        $omitidos = 0;
        foreach ($items as $it) {
            [$nombre, $marca, $desc, $stock, $min, $max, $cb, $vb, $six, $caja] = $it;

            if (\App\Models\Producto::where('ProductoNombre', $nombre)->exists()) {
                $omitidos++;
                continue;
            }

            $detalles = [
                ['unidades_medidaId' => 'UND-00001', 'factor_conversion' => 1, 'precio_compra' => $cb, 'precio_venta' => $vb],
            ];
            if ($six) {
                $detalles[] = ['unidades_medidaId' => $six[0], 'factor_conversion' => $six[1], 'precio_compra' => $six[2], 'precio_venta' => $six[3]];
            }
            if ($caja) {
                $detalles[] = ['unidades_medidaId' => $caja[0], 'factor_conversion' => $caja[1], 'precio_compra' => $caja[2], 'precio_venta' => $caja[3]];
            }

            $svc->crearProducto([
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => 'CAT-00006',
                'ProductoMarca' => $marca,
                'producto_descripcion' => $desc,
                'Producto_producto_ubi_id' => 'UBI-00002',
                'ProductoStockMinimo' => $min,
                'ProductoStockMaximo' => $max,
                'stockInicial' => $stock,
            ], $detalles, ['PRV-00004', 'PRV-PYME-VECINA']);

            $creados++;
        }

        $this->command->info("Galletas: {$creados} creadas, {$omitidos} omitidas (ya existían).");
    }
}
