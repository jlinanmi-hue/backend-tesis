<?php

namespace Database\Seeders;

use App\Models\ZonaDelivery;
use Illuminate\Database\Seeder;

class ZonaDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Fuente: GeoJSON guia precisa del usuario (WGS84/EPSG:4326).
     * 12 distritos, nodos compartidos identicos para evitar huecos/solapes
     * en la validacion topologica. Tarifas propias del negocio.
     */
    public function run(): void
    {
        $zonas = [
            [
                'id' => 'ZD-00001',
                'nombre' => 'Trujillo Centro',
                'tarifa' => 5.00,
                'ubigeo' => '130101', 'area' => 39.36, 'pob' => 362852,
                'ring' => [
                    [-79.055, -8.125], [-79.050, -8.110], [-79.035, -8.090], [-79.010, -8.085],
                    [-79.000, -8.095], [-79.005, -8.115], [-79.025, -8.130], [-79.055, -8.125],
                ],
            ],
            [
                'id' => 'ZD-00002',
                'nombre' => 'Florencia de Mora',
                'tarifa' => 5.00,
                'ubigeo' => '130103', 'area' => 1.99, 'pob' => 45000,
                'ring' => [
                    [-79.030, -8.075], [-79.015, -8.070], [-79.010, -8.085], [-79.035, -8.090],
                    [-79.030, -8.075],
                ],
            ],
            [
                'id' => 'ZD-00003',
                'nombre' => 'El Porvenir',
                'tarifa' => 7.00,
                'ubigeo' => '130102', 'area' => 36.70, 'pob' => 157228,
                'ring' => [
                    [-79.010, -8.085], [-79.000, -8.060], [-78.980, -8.065], [-78.975, -8.085],
                    [-79.000, -8.095], [-79.010, -8.085],
                ],
            ],
            [
                'id' => 'ZD-00004',
                'nombre' => 'La Esperanza',
                'tarifa' => 7.00,
                'ubigeo' => '130105', 'area' => 15.55, 'pob' => 237359,
                'ring' => [
                    [-79.065, -8.080], [-79.045, -8.065], [-79.030, -8.075], [-79.035, -8.090],
                    [-79.050, -8.110], [-79.065, -8.080],
                ],
            ],
            [
                'id' => 'ZD-00005',
                'nombre' => 'Víctor Larco Herrera',
                'tarifa' => 8.00,
                'ubigeo' => '130111', 'area' => 18.02, 'pob' => 75000,
                'ring' => [
                    [-79.070, -8.140], [-79.055, -8.125], [-79.025, -8.130], [-79.040, -8.155],
                    [-79.070, -8.140],
                ],
            ],
            [
                'id' => 'ZD-00006',
                'nombre' => 'Moche',
                'tarifa' => 10.00,
                'ubigeo' => '130107', 'area' => 59.20, 'pob' => 42000,
                'ring' => [
                    [-79.040, -8.155], [-79.025, -8.130], [-79.005, -8.115], [-78.970, -8.140],
                    [-78.990, -8.180], [-79.040, -8.155],
                ],
            ],
            [
                'id' => 'ZD-00007',
                'nombre' => 'Huanchaco',
                'tarifa' => 12.00,
                'ubigeo' => '130104', 'area' => 333.90, 'pob' => 80000,
                'ring' => [
                    [-79.120, -8.070], [-79.080, -8.010], [-78.990, -8.030], [-79.000, -8.060],
                    [-79.030, -8.075], [-79.045, -8.065], [-79.065, -8.080], [-79.050, -8.110],
                    [-79.055, -8.125], [-79.120, -8.070],
                ],
            ],
            [
                'id' => 'ZD-00008',
                'nombre' => 'Laredo',
                'tarifa' => 12.00,
                'ubigeo' => '130106', 'area' => 335.44, 'pob' => 40000,
                'ring' => [
                    [-79.005, -8.115], [-79.000, -8.095], [-78.975, -8.085], [-78.960, -8.040],
                    [-78.910, -8.030], [-78.880, -8.100], [-78.970, -8.140], [-79.005, -8.115],
                ],
            ],
            [
                'id' => 'ZD-00009',
                'nombre' => 'Salaverry',
                'tarifa' => 15.00,
                'ubigeo' => '130109', 'area' => 295.88, 'pob' => 21000,
                'ring' => [
                    [-78.990, -8.180], [-78.970, -8.140], [-78.920, -8.170], [-78.950, -8.240],
                    [-78.990, -8.180],
                ],
            ],
            [
                'id' => 'ZD-00010',
                'nombre' => 'Poroto',
                'tarifa' => 18.00,
                'ubigeo' => '130108', 'area' => 276.01, 'pob' => 6000,
                'ring' => [
                    [-78.880, -8.100], [-78.910, -8.030], [-78.800, -8.060], [-78.770, -8.120],
                    [-78.880, -8.100],
                ],
            ],
            [
                'id' => 'ZD-00011',
                'nombre' => 'Simbal',
                'tarifa' => 18.00,
                'ubigeo' => '130110', 'area' => 390.55, 'pob' => 5000,
                'ring' => [
                    [-78.910, -8.030], [-78.850, -7.960], [-78.750, -7.990], [-78.800, -8.060],
                    [-78.910, -8.030],
                ],
            ],
            [
                'id' => 'ZD-00012',
                'nombre' => 'Alto Trujillo',
                'tarifa' => 7.00,
                'ubigeo' => '130112', 'area' => 42.00, 'pob' => 86000,
                'ring' => [
                    [-79.000, -8.060], [-78.990, -8.030], [-78.960, -8.040], [-78.975, -8.085],
                    [-78.980, -8.065], [-79.000, -8.060],
                ],
            ],
        ];

        foreach ($zonas as $z) {
            $feature = [
                'type' => 'Feature',
                'properties' => [
                    'name' => $z['nombre'],
                    'tarifa' => $z['tarifa'],
                    'ubigeo' => $z['ubigeo'],
                    'area_km2' => $z['area'],
                    'pob_2026' => $z['pob'],
                ],
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$z['ring']],
                ],
            ];

            ZonaDelivery::updateOrCreate(
                ['Zona_DeliveryId' => $z['id']],
                [
                    'Zona_DeliveryNombre' => $z['nombre'],
                    'Zona_DeliveryTarifa' => $z['tarifa'],
                    'Zona_DeliveryPoligonoGeoJSON' => json_encode($feature, JSON_UNESCAPED_UNICODE),
                    'Zona_DeliveryEstado' => 'S',
                    'Zona_DeliveryUsuarioCreacion' => 'SYSTEM',
                    'Zona_DeliveryHostCreacion' => '127.0.0.1',
                    'Zona_DeliveryFechaCreacion' => now(),
                ]
            );
        }
    }
}
