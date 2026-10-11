<?php

namespace Database\Seeders;

use App\Services\InventarioService;
use Illuminate\Database\Seeder;

/**
 * 30 productos estilo catálogo Plaza Vea (Lácteos CAT-00001 y Aceites CAT-00008).
 * Precios venta referenciales Plaza Vea 2026; compra = costo bodega estimado.
 * Usa InventarioService::crearProducto para respetar IDs correlativos y Kardex.
 * Idempotente: omite productos cuyo nombre ya exista.
 */
class ProductosPlazaVeaSeeder extends Seeder
{
    public function run(): void
    {
        $svc = app(InventarioService::class);

        // [nombre, marca, categoria, descripcion, ubi, stock, min, max, detalles[unidad,factor,compra,venta], proveedores]
        $items = [
            // ---------- LÁCTEOS (CAT-00001) ----------
            ['Leche Gloria Evap 400g', 'Gloria', 'CAT-00001', 'Leche evaporada entera lata 400g', 'UBI-00001', 240, 20, 500, [['UND-00001', 1, 3.60, 4.00], ['UND-00015', 6, 21.00, 23.30], ['UND-00019', 24, 84.00, 88.00]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Leche Pura Vida 400g', 'Pura Vida', 'CAT-00001', 'Mezcla láctea evaporada lata 400g', 'UBI-00001', 180, 15, 400, [['UND-00001', 1, 3.20, 3.60], ['UND-00015', 6, 18.60, 20.50]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Leche Fresca Gloria 1L', 'Gloria', 'CAT-00001', 'Leche fresca UHT caja 1 litro', 'UBI-00001', 60, 10, 200, [['UND-00001', 1, 5.00, 5.50], ['UND-00026', 4, 19.50, 21.00]], ['PRV-00001', 'PRV-00004']],
            ['Yogurt Gloria Fresa 1L', 'Gloria', 'CAT-00001', 'Yogurt batido fresa botella 1L', 'UBI-00001', 80, 10, 250, [['UND-00001', 1, 5.20, 5.90], ['UND-00015', 6, 30.00, 33.50]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Yogurt Gloria Vainilla 1L', 'Gloria', 'CAT-00001', 'Yogurt batido vainilla botella 1L', 'UBI-00001', 75, 10, 250, [['UND-00001', 1, 5.20, 5.90], ['UND-00015', 6, 30.00, 33.50]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Yogurt Laive Fresa 1kg', 'Laive', 'CAT-00001', 'Yogurt batido fresa bolsa 1kg', 'UBI-00001', 50, 8, 200, [['UND-00001', 1, 6.00, 6.80], ['UND-00015', 6, 34.50, 38.50]], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Yogurt Griego Gloria 800g', 'Gloria', 'CAT-00001', 'Yogurt griego natural bolsa 800g', 'UBI-00001', 45, 8, 150, [['UND-00001', 1, 5.80, 6.50]], ['PRV-00001']],
            ['Yogurt Battimix Vainilla 146g', 'Gloria', 'CAT-00001', 'Yogurt vaso con arroz crujiente 146g', 'UBI-00002', 120, 12, 300, [['UND-00001', 1, 2.40, 2.70], ['UND-00022', 6, 13.80, 15.00]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Mantequilla Gloria 390g', 'Gloria', 'CAT-00001', 'Mantequilla con sal pote 390g', 'UBI-00001', 40, 6, 120, [['UND-00001', 1, 15.00, 16.50], ['UND-00026', 4, 58.00, 63.00]], ['PRV-00001', 'PRV-00004']],
            ['Mantequilla Laive 200g', 'Laive', 'CAT-00001', 'Mantequilla sin sal barra 200g', 'UBI-00001', 48, 6, 150, [['UND-00001', 1, 9.00, 10.00]], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Margarina Dorina 500g', 'Dorina', 'CAT-00001', 'Margarina familiar pote 500g', 'UBI-00002', 55, 8, 150, [['UND-00001', 1, 6.50, 7.20]], ['PRV-00004']],
            ['Queso Fresco Serrano 1kg', 'Serrano', 'CAT-00001', 'Queso fresco serrano por kilo', 'UBI-00001', 30, 5, 80, [['UND-00001', 1, 18.00, 20.00]], ['PRV-PYME-VECINA']],
            ['Queso Edam Laive 500g', 'Laive', 'CAT-00001', 'Queso edam en bloque 500g', 'UBI-00001', 25, 5, 80, [['UND-00001', 1, 14.00, 15.50]], ['PRV-00004']],
            ['Leche Condensada Gloria 397g', 'Gloria', 'CAT-00001', 'Leche condensada lata 397g', 'UBI-00002', 70, 8, 200, [['UND-00001', 1, 5.00, 5.60], ['UND-00024', 6, 29.00, 32.00]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Manjar Blanco Gloria 400g', 'Gloria', 'CAT-00001', 'Manjar blanco pote 400g', 'UBI-00002', 65, 8, 200, [['UND-00001', 1, 5.50, 6.20]], ['PRV-00001']],
            ['Crema Leche Gloria 250ml', 'Gloria', 'CAT-00001', 'Crema de leche caja 250ml', 'UBI-00002', 55, 8, 150, [['UND-00001', 1, 4.50, 5.00], ['UND-00024', 6, 26.00, 28.50]], ['PRV-00001']],
            ['Yogurt Bebible Fresa 500ml', 'Gloria', 'CAT-00001', 'Yogurt bebible fresa botella 500ml', 'UBI-00001', 90, 10, 250, [['UND-00001', 1, 3.00, 3.40], ['UND-00015', 6, 17.40, 19.20]], ['PRV-00001', 'PRV-PYME-VECINA']],
            ['Leche Chocolatada Gloria 1L', 'Gloria', 'CAT-00001', 'Leche chocolatada caja 1 litro', 'UBI-00001', 70, 10, 200, [['UND-00001', 1, 5.50, 6.00]], ['PRV-00001', 'PRV-00004']],
            // ---------- ACEITES (CAT-00008) ----------
            ['Aceite Primor Premium 900ml', 'Primor', 'CAT-00008', 'Aceite vegetal premium botella 900ml', 'UBI-00001', 150, 15, 400, [['UND-00001', 1, 8.50, 9.50], ['UND-00016', 12, 99.00, 108.00], ['UND-00015', 6, 49.50, 54.00]], ['PRV-00002', 'PRV-PYME-VECINA']],
            ['Aceite Primor Clasico 1.8L', 'Primor', 'CAT-00008', 'Aceite vegetal clasico botella 1.8L', 'UBI-00001', 80, 10, 200, [['UND-00001', 1, 15.50, 17.00], ['UND-00026', 4, 60.00, 65.00]], ['PRV-00002', 'PRV-00004']],
            ['Aceite Cocinero 900ml', 'Cocinero', 'CAT-00008', 'Aceite vegetal botella 900ml', 'UBI-00001', 130, 15, 400, [['UND-00001', 1, 6.50, 7.20], ['UND-00016', 12, 75.00, 82.00]], ['PRV-00002', 'PRV-PYME-VECINA']],
            ['Aceite Sao 900ml', 'Sao', 'CAT-00008', 'Aceite vegetal botella 900ml', 'UBI-00002', 110, 12, 300, [['UND-00001', 1, 6.00, 6.80], ['UND-00016', 12, 69.00, 77.00]], ['PRV-00002']],
            ['Aceite Cil 1L', 'Cil', 'CAT-00008', 'Aceite vegetal botella 1 litro', 'UBI-00002', 90, 10, 250, [['UND-00001', 1, 7.00, 7.80], ['UND-00016', 12, 81.00, 89.00]], ['PRV-00004', 'PRV-PYME-VECINA']],
            ['Aceite Tondero 900ml', 'Tondero', 'CAT-00008', 'Aceite vegetal botella 900ml', 'UBI-00002', 100, 10, 300, [['UND-00001', 1, 6.20, 7.00]], ['PRV-00004']],
            ['Aceite Oliva Extra Virgen 500ml', 'Carbonell', 'CAT-00008', 'Aceite de oliva extra virgen 500ml', 'UBI-00001', 25, 4, 60, [['UND-00001', 1, 22.00, 25.00]], ['PRV-00004']],
            ['Aceite Primor Bidon 5L', 'Primor', 'CAT-00008', 'Aceite vegetal bidon 5 litros', 'UBI-00001', 30, 5, 80, [['UND-00001', 1, 42.00, 46.00]], ['PRV-00002']],
            ['Manteca Vegetal Primor 500g', 'Primor', 'CAT-00008', 'Manteca vegetal paquete 500g', 'UBI-00002', 60, 8, 150, [['UND-00001', 1, 5.50, 6.20], ['UND-00016', 12, 63.00, 70.00]], ['PRV-00002', 'PRV-PYME-VECINA']],
            ['Aceite Cocinero 1.8L', 'Cocinero', 'CAT-00008', 'Aceite vegetal botella 1.8L', 'UBI-00001', 60, 8, 150, [['UND-00001', 1, 12.50, 14.00]], ['PRV-00002', 'PRV-00004']],
            ['Aceite de Coco 500ml', 'Tropical', 'CAT-00008', 'Aceite de coco prensado 500ml', 'UBI-00002', 20, 4, 60, [['UND-00001', 1, 12.00, 13.50]], ['PRV-PYME-VECINA']],
            ['Aceite Soya 1L', 'Soya', 'CAT-00008', 'Aceite de soya botella 1 litro', 'UBI-00002', 85, 10, 250, [['UND-00001', 1, 6.80, 7.50], ['UND-00016', 12, 78.00, 86.00]], ['PRV-00002']],
        ];

        $creados = 0;
        $omitidos = 0;
        foreach ($items as $it) {
            [$nombre, $marca, $cat, $desc, $ubi, $stock, $min, $max, $dets, $provs] = $it;

            if (\App\Models\Producto::where('ProductoNombre', $nombre)->exists()) {
                $omitidos++;
                continue;
            }

            $detalles = array_map(fn($d) => [
                'unidades_medidaId' => $d[0],
                'factor_conversion' => $d[1],
                'precio_compra' => $d[2],
                'precio_venta' => $d[3],
            ], $dets);

            $svc->crearProducto([
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => $cat,
                'ProductoMarca' => $marca,
                'producto_descripcion' => $desc,
                'Producto_producto_ubi_id' => $ubi,
                'ProductoStockMinimo' => $min,
                'ProductoStockMaximo' => $max,
                'stockInicial' => $stock,
            ], $detalles, $provs);

            $creados++;
        }

        $this->command->info("Productos Plaza Vea: {$creados} creados, {$omitidos} omitidos (ya existían).");
    }
}
