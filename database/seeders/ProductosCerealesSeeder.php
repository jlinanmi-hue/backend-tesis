<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;

/**
 * 16 cereales de desayuno (CAT-00007): Kellogg's, Nestlé, Ángel, Quaker.
 * Reglas: base + caja/display real, mayor con -2.5% redondeado YA aplicado.
 * Idempotente por nombre.
 */
class ProductosCerealesSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // [nombre, marca, descripcion, stock, min, max, baseC, baseV, [um, factor, cMayor, vMayor]]
        $items = [
            ['Zucaritas Kelloggs 480g', 'Kelloggs', 'Cereal azucarado caja 480g', 70, 8, 220, 15.00, 16.50, ['UND-00016', 12, 170, 187]],
            ['Corn Flakes Kelloggs 405g', 'Kelloggs', 'Hojuelas de maiz caja 405g', 65, 8, 220, 12.50, 14.00, ['UND-00016', 12, 141, 158]],
            ['Froot Loops Kelloggs 400g', 'Kelloggs', 'Aros frutales caja 400g', 55, 6, 180, 13.00, 14.50, ['UND-00016', 12, 146, 164]],
            ['Chocapic Nestle 540g', 'Nestle', 'Cereal chocolate caja 540g', 60, 6, 180, 19.00, 20.80, ['UND-00016', 12, 215, 236]],
            ['Chocapic Nestle 310g', 'Nestle', 'Cereal chocolate caja 310g', 65, 8, 200, 11.50, 12.50, ['UND-00016', 12, 130, 141]],
            ['Nesquik Nestle 480g', 'Nestle', 'Cereal chocolate caja 480g', 55, 6, 180, 14.00, 15.50, ['UND-00016', 12, 159, 176]],
            ['Estrellitas Nestle 500g', 'Nestle', 'Cereal estrellas miel caja 500g', 50, 6, 160, 13.00, 14.50, ['UND-00016', 12, 147, 164]],
            ['Fitness Nestle 400g', 'Nestle', 'Cereal fitness caja 400g', 40, 5, 140, 14.00, 15.50, ['UND-00016', 12, 159, 176]],
            ['Cereal Angel Flakes 500g', 'Angel', 'Hojuelas de maiz caja 500g', 70, 8, 220, 13.00, 14.50, ['UND-00016', 12, 147, 164]],
            ['Angel Copos Avena 380g', 'Angel', 'Copos con avena bolsa 380g', 75, 8, 220, 9.50, 10.80, ['UND-00016', 12, 107, 122]],
            ['Angel Copix Chocolate 740g', 'Angel', 'Cereal chocolate malvavisco 740g', 45, 5, 150, 16.00, 18.00, ['UND-00015', 6, 91, 101]],
            ['Angel Sachet Flakes 20g', 'Angel', 'Cereal sachet individual 20g', 200, 20, 500, 1.50, 1.90, ['UND-00016', 12, 17, 21]],
            ['Avena Quaker 900g', 'Quaker', 'Avena tradicional bolsa 900g', 90, 10, 280, 12.00, 13.50, ['UND-00015', 6, 67, 76]],
            ['Avena 3 Ositos 900g', '3 Ositos', 'Avena precocida bolsa 900g', 100, 10, 300, 9.00, 9.90, ['UND-00015', 6, 51, 56]],
            ['Avena Quaker Maca 330g', 'Quaker', 'Avena con maca bolsa 330g', 70, 8, 220, 7.50, 8.50, ['UND-00016', 12, 85, 97]],
            ['Kiwicha Pop Angel 300g', 'Angel', 'Kiwicha pop andina bolsa 300g', 60, 6, 180, 6.50, 7.50, ['UND-00016', 12, 73, 85]],
        ];

        $creados = 0;
        $omitidos = 0;
        foreach ($items as $it) {
            [$nombre, $marca, $desc, $stock, $min, $max, $cb, $vb, $mayor] = $it;

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
            ], [
                ['unidades_medidaId' => 'UND-00001', 'factor_conversion' => 1, 'precio_compra' => $cb, 'precio_venta' => $vb],
                ['unidades_medidaId' => $mayor[0], 'factor_conversion' => $mayor[1], 'precio_compra' => $mayor[2], 'precio_venta' => $mayor[3]],
            ], ['PRV-00004', 'PRV-PYME-VECINA']);

            $creados++;
        }

        $this->command->info("Cereales: {$creados} creados, {$omitidos} omitidos (ya existían).");
    }
}
