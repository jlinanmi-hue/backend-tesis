<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Arroces, azúcares y harinas con modelo Arroz Deleite (PROD-00001):
 * base kilo + saco 50kg + bolsa 25kg (mitad del saco) + bolsa 10kg (kilo x 10).
 * Harinas: solo kilo + saco 50kg. Precios distribuidora, sin descuento adicional
 * (los valores del saco son dato del negocio). Idempotente por nombre.
 */
class ProductosArrozAzucarHarinaSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // [nombre, marca, descripcion, stock, min, max, kiloC, kiloV, sacoC, sacoV, provs]
        $arroces = [
            ['Arroz Costeñita', 'Costeñita', 'Arroz extra costeñita grano largo', 420, 25, 800, 3.70, 4.00, 184, 200, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Arroz Gran Camilo', 'Gran Camilo', 'Arroz extra gran camilo', 380, 25, 800, 3.60, 3.90, 180, 196, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Arroz Pacasmayo Añejo', 'Pacasmayo', 'Arroz añejo pacasmayo rendidor', 350, 25, 800, 3.70, 4.00, 184, 200, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Arroz Pepita', 'Pepita', 'Arroz economico pepita granel', 450, 25, 900, 3.20, 3.50, 160, 176, ['PRV-PYME-VECINA']],
            ['Arroz Paisana', 'Paisana', 'Arroz extra paisana', 400, 25, 800, 3.50, 3.80, 174, 190, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Arroz Don Mario', 'Don Mario', 'Arroz extra don mario', 380, 25, 800, 3.40, 3.70, 170, 184, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Arroz Faraon Extra', 'Faraon', 'Arroz extra añejo faraon premium', 300, 20, 600, 4.00, 4.30, 200, 214, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Arroz Hojita Dorada', 'Hojita Dorada', 'Arroz extra hojita dorada 50kg', 330, 20, 700, 3.60, 3.90, 182, 198, ['PRV-00004', 'PRV-PYME-VECINA']],
        ];

        $azucares = [
            ['Azucar Manuelita Rubia', 'Manuelita', 'Azucar rubia saco 50kg', 350, 25, 700, 3.40, 3.60, 170, 180, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Azucar Cartavio Rubia', 'Cartavio', 'Azucar rubia saco 50kg', 350, 25, 700, 3.40, 3.60, 170, 178, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Azucar Casa Grande Rubia', 'Casa Grande', 'Azucar rubia saco 50kg', 320, 25, 700, 3.30, 3.60, 164, 176, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Azucar San Jacinto Rubia', 'San Jacinto', 'Azucar rubia saco 50kg', 300, 25, 700, 3.10, 3.40, 154, 168, ['PRV-PYME-VECINA']],
        ];

        // Harinas: [nombre, marca, descripcion, stock, min, max, kiloC, kiloV, sacoC, sacoV, provs]
        $harinas = [
            ['Harina Dulcino Trigo', 'Dulcino', 'Harina de trigo saco 50kg', 250, 20, 500, 2.40, 2.70, 120, 135, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Harina Blanca Flor', 'Blanca Flor', 'Harina de trigo saco 50kg', 220, 20, 500, 2.80, 3.10, 140, 155, ['PRV-00002', 'PRV-PYME-VECINA']],
            ['Harina Nicolini Premium', 'Nicolini', 'Harina de trigo premium saco 50kg', 200, 15, 400, 2.40, 2.60, 118, 130, ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Harina Anita Especial', 'Anita', 'Harina de trigo especial saco 50kg', 200, 15, 400, 2.70, 3.00, 135, 150, ['PRV-00004', 'PRV-PYME-VECINA']],
        ];

        $creados = 0;
        $omitidos = 0;

        $registrar = function ($nombre, $marca, $desc, $stock, $min, $max, $kc, $kv, $sc, $sv, $provs, $con25y10) use ($svc, &$creados, &$omitidos) {
            if (\App\Models\Producto::where('ProductoNombre', $nombre)->exists()) {
                $omitidos++;
                return;
            }
            $detalles = [
                ['unidades_medidaId' => 'UND-00001', 'factor_conversion' => 1, 'precio_compra' => $kc, 'precio_venta' => $kv],
                ['unidades_medidaId' => 'UND-00012', 'factor_conversion' => 50, 'precio_compra' => $sc, 'precio_venta' => $sv],
            ];
            if ($con25y10) {
                $detalles[] = ['unidades_medidaId' => 'UND-00013', 'factor_conversion' => 25, 'precio_compra' => $sc / 2, 'precio_venta' => $sv / 2];
                $detalles[] = ['unidades_medidaId' => 'UND-00014', 'factor_conversion' => 10, 'precio_compra' => $kc * 10, 'precio_venta' => $kv * 10];
            }
            $svc->crearProducto([
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => 'CAT-00002',
                'ProductoMarca' => $marca,
                'producto_descripcion' => $desc,
                'Producto_producto_ubi_id' => 'UBI-00001',
                'ProductoStockMinimo' => $min,
                'ProductoStockMaximo' => $max,
                'stockInicial' => $stock,
            ], $detalles, $provs);
            $creados++;
        };

        foreach ($arroces as $a) { [$n, $m, $d, $s, $mn, $mx, $kc, $kv, $sc, $sv, $p] = $a; $registrar($n, $m, $d, $s, $mn, $mx, $kc, $kv, $sc, $sv, $p, true); }
        foreach ($azucares as $a) { [$n, $m, $d, $s, $mn, $mx, $kc, $kv, $sc, $sv, $p] = $a; $registrar($n, $m, $d, $s, $mn, $mx, $kc, $kv, $sc, $sv, $p, true); }
        foreach ($harinas as $a) { [$n, $m, $d, $s, $mn, $mx, $kc, $kv, $sc, $sv, $p] = $a; $registrar($n, $m, $d, $s, $mn, $mx, $kc, $kv, $sc, $sv, $p, false); }

        // Deleite modelo: bolsa 10kg compra = kilo x 10 (3.80 x 10 = 38).
        DB::table('Detalle_Producto_medida')
            ->where('Detalle_Producto_medida_ProductoId', 'PROD-00001')
            ->where('Detalle_Producto_medida_unidades_medidaId', 'UND-00014')
            ->update(['Detalle_Producto_medida_precio_compra' => 38.00, 'Detalle_Producto_medidaUsuarioModificacion' => 'ADMIN', 'Detalle_Producto_medidaHostModificacion' => '127.0.0.1', 'Detalle_Producto_medidaFechaModificacion' => now()]);

        $this->command->info("Arroz/Azúcar/Harina: {$creados} creados, {$omitidos} omitidos. Deleite 10kg compra 36->38.");
    }
}
