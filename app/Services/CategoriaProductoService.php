<?php

namespace App\Services;

use App\Models\CategoriaProducto;
use App\Repositories\Contracts\CategoriaProductoRepositoryInterface;
use App\Support\AuditHelper;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoriaProductoService
{
    public function __construct(
        protected CategoriaProductoRepositoryInterface $categoriaRepository
    ) {}

    public function listarCategorias(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        return $this->categoriaRepository->getAll($filters, $perPage);
    }

    public function obtenerCategoriaPorId(string $id, bool $includeDeleted = false): CategoriaProducto
    {
        $categoria = $this->categoriaRepository->findById($id, $includeDeleted);

        if (!$categoria) {
            throw new Exception("Categoría con ID '{$id}' no encontrada.");
        }

        return $categoria;
    }

    public function crearCategoria(array $datos): CategoriaProducto
    {
        return DB::transaction(function () use ($datos) {
            if ($this->categoriaRepository->findByDescripcion($datos['Categoria_ProductoDescripcion_categoria'])) {
                throw ValidationException::withMessages([
                    'Categoria_ProductoDescripcion_categoria' => 'La descripción de categoría ingresada ya existe.',
                ]);
            }

            $categoriaId = $this->categoriaRepository->getNextId();
            $audit = AuditHelper::getCreationAudit('Categoria_Producto');

            $payload = array_merge($datos, [
                'Categoria_ProductoId' => $categoriaId,
                'Categoria_ProductoEstado' => $datos['Categoria_ProductoEstado'] ?? 'A',
                'Categoria_ProductoEliminado' => 'N',
            ], $audit);

            return $this->categoriaRepository->create($payload);
        });
    }

    public function actualizarCategoria(string $id, array $datos): CategoriaProducto
    {
        return DB::transaction(function () use ($id, $datos) {
            $categoria = $this->obtenerCategoriaPorId($id);

            if (isset($datos['Categoria_ProductoDescripcion_categoria']) && $datos['Categoria_ProductoDescripcion_categoria'] !== $categoria->Categoria_ProductoDescripcion_categoria) {
                $existente = $this->categoriaRepository->findByDescripcion($datos['Categoria_ProductoDescripcion_categoria']);
                if ($existente && $existente->Categoria_ProductoId !== $id) {
                    throw ValidationException::withMessages([
                        'Categoria_ProductoDescripcion_categoria' => 'La descripción de categoría ingresada ya pertenece a otra categoría.',
                    ]);
                }
            }

            $audit = AuditHelper::getModificationAudit('Categoria_Producto');
            $payload = array_merge($datos, $audit);

            return $this->categoriaRepository->update($id, $payload);
        });
    }

    public function cambiarEstadoCategoria(string $id, string $nuevoEstado): CategoriaProducto
    {
        return DB::transaction(function () use ($id, $nuevoEstado) {
            $categoria = $this->obtenerCategoriaPorId($id);

            if (!in_array($nuevoEstado, ['A', 'I'], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'El estado debe ser A (Activo) o I (Inactivo).',
                ]);
            }

            $audit = AuditHelper::getModificationAudit('Categoria_Producto');
            $payload = array_merge([
                'Categoria_ProductoEstado' => $nuevoEstado,
            ], $audit);

            return $this->categoriaRepository->update($id, $payload);
        });
    }

    public function eliminarCategoria(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerCategoriaPorId($id);

            $audit = AuditHelper::getDeletionAudit('Categoria_Producto');

            return $this->categoriaRepository->deleteLogico($id, $audit);
        });
    }

    public function restaurarCategoria(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $this->obtenerCategoriaPorId($id, true);

            $audit = AuditHelper::getModificationAudit('Categoria_Producto');

            return $this->categoriaRepository->restore($id, $audit);
        });
    }
}
