<?php

namespace App\Services;

use App\Models\ZonaDelivery;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    /**
     * Generar el siguiente ID con formato ZD-XXXXX (ej. ZD-00012)
     */
    public function getNextId(): string
    {
        $lastId = ZonaDelivery::select('Zona_DeliveryId')
            ->orderBy('Zona_DeliveryId', 'desc')
            ->first();

        if (!$lastId || !$lastId->Zona_DeliveryId) {
            return 'ZD-00001';
        }

        $lastNumber = (int) preg_replace('/[^0-9]/', '', $lastId->Zona_DeliveryId);
        $nextNumber = $lastNumber + 1;

        return 'ZD-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Listar zonas de delivery con filtros opcionales.
     */
    public function listarZonas(array $filters = [], int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = ZonaDelivery::query();

        if (!empty($filters['solo_activas']) || (isset($filters['activas']) && filter_var($filters['activas'], FILTER_VALIDATE_BOOLEAN))) {
            $query->activas();
        }

        if (!empty($filters['estado'])) {
            $query->where('Zona_DeliveryEstado', strtoupper(trim($filters['estado'])));
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where('Zona_DeliveryNombre', 'LIKE', "%{$search}%");
        }

        $query->orderBy('Zona_DeliveryNombre', 'asc');

        if ($perPage <= 0) {
            return $query->get();
        }

        return $query->paginate($perPage);
    }

    /**
     * Obtener una zona por su ID.
     */
    public function obtenerZonaPorId(string $id): ZonaDelivery
    {
        $zona = ZonaDelivery::where('Zona_DeliveryId', $id)->first();

        if (!$zona) {
            throw new Exception("Zona de delivery con ID '{$id}' no encontrada.");
        }

        return $zona;
    }

    /**
     * Crear una nueva zona de delivery.
     */
    public function crearZona(array $datos): ZonaDelivery
    {
        return DB::transaction(function () use ($datos) {
            $nombre = trim($datos['Zona_DeliveryNombre'] ?? $datos['nombre'] ?? '');
            if (empty($nombre)) {
                throw ValidationException::withMessages([
                    'Zona_DeliveryNombre' => 'El nombre de la zona de delivery es requerido.',
                ]);
            }

            $existe = ZonaDelivery::where('Zona_DeliveryNombre', $nombre)->exists();
            if ($existe) {
                throw ValidationException::withMessages([
                    'Zona_DeliveryNombre' => "Ya existe una zona de delivery registrada con el nombre '{$nombre}'.",
                ]);
            }

            $tarifa = isset($datos['Zona_DeliveryTarifa']) 
                ? (float) $datos['Zona_DeliveryTarifa'] 
                : (isset($datos['tarifa']) ? (float) $datos['tarifa'] : 0.00);

            if ($tarifa < 0) {
                throw ValidationException::withMessages([
                    'Zona_DeliveryTarifa' => 'La tarifa de delivery no puede ser un valor negativo.',
                ]);
            }

            $poligono = $datos['Zona_DeliveryPoligonoGeoJSON'] ?? $datos['poligono_geojson'] ?? $datos['poligono'] ?? null;
            if (is_array($poligono)) {
                $poligono = json_encode($poligono, JSON_UNESCAPED_UNICODE);
            }
            if (empty($poligono)) {
                throw ValidationException::withMessages([
                    'Zona_DeliveryPoligonoGeoJSON' => 'El polígono GeoJSON de la zona es obligatorio.',
                ]);
            }

            $estado = strtoupper(trim($datos['Zona_DeliveryEstado'] ?? $datos['estado'] ?? 'S'));
            if (!in_array($estado, ['S', 'N'])) {
                $estado = 'S';
            }

            $id = $this->getNextId();
            $audit = AuditHelper::getCreationAudit('Zona_Delivery');

            return ZonaDelivery::create(array_merge([
                'Zona_DeliveryId' => $id,
                'Zona_DeliveryNombre' => $nombre,
                'Zona_DeliveryTarifa' => $tarifa,
                'Zona_DeliveryPoligonoGeoJSON' => $poligono,
                'Zona_DeliveryEstado' => $estado,
            ], $audit));
        });
    }

    /**
     * Actualizar una zona existente.
     */
    public function actualizarZona(string $id, array $datos): ZonaDelivery
    {
        return DB::transaction(function () use ($id, $datos) {
            $zona = $this->obtenerZonaPorId($id);

            $camposActualizar = [];

            if (isset($datos['Zona_DeliveryNombre']) || isset($datos['nombre'])) {
                $nuevoNombre = trim($datos['Zona_DeliveryNombre'] ?? $datos['nombre']);
                if (empty($nuevoNombre)) {
                    throw ValidationException::withMessages([
                        'Zona_DeliveryNombre' => 'El nombre de la zona no puede estar vacío.',
                    ]);
                }

                $existeOtro = ZonaDelivery::where('Zona_DeliveryNombre', $nuevoNombre)
                    ->where('Zona_DeliveryId', '!=', $id)
                    ->exists();

                if ($existeOtro) {
                    throw ValidationException::withMessages([
                        'Zona_DeliveryNombre' => "Ya existe otra zona con el nombre '{$nuevoNombre}'.",
                    ]);
                }

                $camposActualizar['Zona_DeliveryNombre'] = $nuevoNombre;
            }

            if (isset($datos['Zona_DeliveryTarifa']) || isset($datos['tarifa'])) {
                $tarifa = (float) ($datos['Zona_DeliveryTarifa'] ?? $datos['tarifa']);
                if ($tarifa < 0) {
                    throw ValidationException::withMessages([
                        'Zona_DeliveryTarifa' => 'La tarifa no puede ser negativa.',
                    ]);
                }
                $camposActualizar['Zona_DeliveryTarifa'] = $tarifa;
            }

            if (isset($datos['Zona_DeliveryPoligonoGeoJSON']) || isset($datos['poligono_geojson']) || isset($datos['poligono'])) {
                $poligono = $datos['Zona_DeliveryPoligonoGeoJSON'] ?? $datos['poligono_geojson'] ?? $datos['poligono'];
                if (is_array($poligono)) {
                    $poligono = json_encode($poligono, JSON_UNESCAPED_UNICODE);
                }
                if (!empty($poligono)) {
                    $camposActualizar['Zona_DeliveryPoligonoGeoJSON'] = $poligono;
                }
            }

            if (isset($datos['Zona_DeliveryEstado']) || isset($datos['estado'])) {
                $estado = strtoupper(trim($datos['Zona_DeliveryEstado'] ?? $datos['estado']));
                if (in_array($estado, ['S', 'N'])) {
                    $camposActualizar['Zona_DeliveryEstado'] = $estado;
                }
            }

            $audit = AuditHelper::getModificationAudit('Zona_Delivery');
            $zona->update(array_merge($camposActualizar, $audit));

            return $zona->fresh();
        });
    }

    /**
     * Cambiar estado lógico ('S' <-> 'N'). Principio 5: NUNCA eliminación física.
     */
    public function cambiarEstado(string $id, ?string $nuevoEstado = null): ZonaDelivery
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $zona = $this->obtenerZonaPorId($id);

            if ($nuevoEstado === null) {
                // Alternar estado
                $nuevoEstado = ($zona->Zona_DeliveryEstado === 'S') ? 'N' : 'S';
            } else {
                $nuevoEstado = strtoupper(trim($nuevoEstado));
                if (!in_array($nuevoEstado, ['S', 'N'])) {
                    throw ValidationException::withMessages([
                        'estado' => 'El estado debe ser S (Activa) o N (Inactiva).',
                    ]);
                }
            }

            $audit = AuditHelper::getModificationAudit('Zona_Delivery');
            $zona->update(array_merge([
                'Zona_DeliveryEstado' => $nuevoEstado,
            ], $audit));

            return $zona->fresh();
        });
    }

    /**
     * Obtener y verificar la tarifa de una zona activa.
     */
    public function obtenerTarifa(string $id): float
    {
        $zona = $this->obtenerZonaPorId($id);

        if (!$zona->esActiva()) {
            throw ValidationException::withMessages([
                'zona_delivery' => "La zona '{$zona->Zona_DeliveryNombre}' no se encuentra habilitada para delivery.",
            ]);
        }

        return (float) $zona->Zona_DeliveryTarifa;
    }
}
