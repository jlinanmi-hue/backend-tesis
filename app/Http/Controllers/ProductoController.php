<?php

namespace App\Http\Controllers;

use App\Services\InventarioService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    public function __construct(
        protected InventarioService $inventarioService
    ) {}

    /**
     * List products with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'categoriaId', 'marca', 'estado', 'stockBajo', 'eliminados', 'todos']);
            $perPage = (int) $request->input('per_page', 15);

            $productos = $this->inventarioService->listarProductos($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $productos,
                'message' => 'Lista de productos obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener lista de productos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created product and its required details.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ProductoNombre' => ['required', 'string', 'max:45'],
                'Producto_Categoria_ProductoId' => ['required', 'string', 'exists:Categoria_Producto,Categoria_ProductoId'],
                'ProductoMarca' => ['nullable', 'string', 'max:45'],
                'producto_descripcion' => ['nullable', 'string', 'max:50'],
                'producto_imagen' => ['nullable', 'string', 'max:255'],
                'Producto_producto_ubi_id' => ['nullable', 'string', 'exists:producto_ubi,producto_ubi_id'],
                'ProductoStockMinimo' => ['nullable', 'numeric'],
                'ProductoStockMaximo' => ['nullable', 'numeric'],
                'stockInicial' => ['nullable', 'numeric', 'min:0'],
                'detalles' => ['required', 'array', 'min:1'],
                'detalles.*.unidades_medidaId' => ['required', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'detalles.*.factor_conversion' => ['required', 'integer', 'min:1'],
                'detalles.*.precio_compra' => ['required', 'numeric', 'min:0'],
                'detalles.*.precio_venta' => ['required', 'numeric', 'min:0'],
                'proveedorIds' => ['nullable', 'array'],
                'proveedorIds.*' => ['string', 'exists:Proveedor,ProveedorId'],
            ]);

            $producto = $this->inventarioService->crearProducto(
                $validated,
                $validated['detalles'],
                $validated['proveedorIds'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => 'Producto y unidades de medida registrados exitosamente.',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en los datos del producto.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al registrar el producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Registro exprés de nuevo producto durante la recepción física de mercancía.
     * POST /api/inventario/productos/express
     */
    public function storeExpress(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ProductoNombre' => ['sometimes', 'string', 'max:45'],
                'nombre' => ['sometimes', 'string', 'max:45'],
                'Producto_Categoria_ProductoId' => ['sometimes', 'string', 'exists:Categoria_Producto,Categoria_ProductoId'],
                'categoria_id' => ['sometimes', 'string', 'exists:Categoria_Producto,Categoria_ProductoId'],
                'unidades_medidaId' => ['nullable', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'unidad_medida_id' => ['nullable', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'precio_compra' => ['nullable', 'numeric', 'min:0'],
                'precio_venta' => ['nullable', 'numeric', 'min:0'],
                'proveedor_id' => ['nullable', 'string'],
            ]);

            $nombre = $validated['ProductoNombre'] ?? $validated['nombre'] ?? null;
            $categoriaId = $validated['Producto_Categoria_ProductoId'] ?? $validated['categoria_id'] ?? null;

            if (empty($nombre)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El nombre del producto es obligatorio.',
                ], 422);
            }

            $datos = [
                'ProductoNombre' => $nombre,
                'Producto_Categoria_ProductoId' => $categoriaId,
                'unidades_medidaId' => $validated['unidades_medidaId'] ?? $validated['unidad_medida_id'] ?? 'UND-00001',
                'precio_compra' => $validated['precio_compra'] ?? 0,
                'precio_venta' => $validated['precio_venta'] ?? 0,
                'proveedor_id' => $validated['proveedor_id'] ?? null,
            ];

            $producto = $this->inventarioService->crearProductoExpress($datos);

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => "Producto '{$producto->ProductoNombre}' creado en modo exprés correctamente.",
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al crear producto exprés.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al crear producto exprés: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified product with conversion details and suppliers.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $producto = $this->inventarioService->obtenerProductoPorId($id);

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => 'Detalle del producto obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified product and its presentation details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ProductoNombre' => ['sometimes', 'required', 'string', 'max:45'],
                'Producto_Categoria_ProductoId' => ['sometimes', 'required', 'string', 'exists:Categoria_Producto,Categoria_ProductoId'],
                'ProductoMarca' => ['nullable', 'string', 'max:45'],
                'producto_descripcion' => ['nullable', 'string', 'max:50'],
                'producto_imagen' => ['nullable', 'string', 'max:255'],
                'Producto_producto_ubi_id' => ['nullable', 'string', 'exists:producto_ubi,producto_ubi_id'],
                'ProductoStockMinimo' => ['nullable', 'numeric'],
                'ProductoStockMaximo' => ['nullable', 'numeric'],
                'detalles' => ['nullable', 'array', 'min:1'],
                'detalles.*.unidades_medidaId' => ['required_with:detalles', 'string', 'exists:unidades_medida,unidades_medidaId'],
                'detalles.*.factor_conversion' => ['required_with:detalles', 'integer', 'min:1'],
                'detalles.*.precio_compra' => ['required_with:detalles', 'numeric', 'min:0'],
                'detalles.*.precio_venta' => ['required_with:detalles', 'numeric', 'min:0'],
                'proveedorIds' => ['nullable', 'array'],
                'proveedorIds.*' => ['string', 'exists:Proveedor,ProveedorId'],
            ]);

            $producto = $this->inventarioService->actualizarProducto(
                $id,
                $validated,
                $validated['detalles'] ?? null,
                $validated['proveedorIds'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => 'Producto actualizado correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación al actualizar el producto.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar el producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified product logically (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->inventarioService->eliminarProductoLogico($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Producto y sus presentaciones eliminados lógicamente de forma exitosa.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al eliminar producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fast update for product status (Active/Inactive).
     */
    public function updateEstado(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'estado' => ['required', 'string', 'in:A,I'],
            ]);

            $producto = $this->inventarioService->cambiarEstadoProducto($id, $request->input('estado'));

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => 'Estado del producto actualizado correctamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación en el estado.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al actualizar estado del producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Catalog lists.
     */
    public function categorias(): JsonResponse
    {
        try {
            $categorias = $this->inventarioService->obtenerCategoriasActivas();

            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => 'Lista de categorías obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener categorías: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function unidades(): JsonResponse
    {
        try {
            $unidades = $this->inventarioService->obtenerUnidadesMedidaActivas();

            return response()->json([
                'success' => true,
                'data' => $unidades,
                'message' => 'Lista de unidades de medida obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener unidades de medida: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function proveedores(): JsonResponse
    {
        try {
            $proveedores = $this->inventarioService->obtenerProveedoresActivos();

            return response()->json([
                'success' => true,
                'data' => $proveedores,
                'message' => 'Lista de proveedores obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener proveedores: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function ubicaciones(): JsonResponse
    {
        try {
            $ubicaciones = $this->inventarioService->obtenerUbicacionesActivas();

            return response()->json([
                'success' => true,
                'data' => $ubicaciones,
                'message' => 'Lista de ubicaciones obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener ubicaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Quick stock stats and summary.
     */
    public function resumenStock(): JsonResponse
    {
        try {
            $resumen = $this->inventarioService->resumenStock();

            return response()->json([
                'success' => true,
                'data' => $resumen,
                'message' => 'Resumen general de inventario obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener resumen de stock: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stockMinimo(): JsonResponse
    {
        try {
            $productos = $this->inventarioService->productosStockMinimo();

            return response()->json([
                'success' => true,
                'data' => $productos,
                'message' => 'Lista de productos con stock mínimo obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener productos con stock mínimo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Productos con stock bajo / crítico para reposición de órdenes de compra.
     * GET /api/productos/stock-bajo
     */
    public function stockBajo(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 50);
            $productos = $this->inventarioService->obtenerProductosStockBajo($limit);

            return response()->json([
                'success' => true,
                'total' => count($productos),
                'data' => $productos,
                'message' => 'Productos con stock bajo obtenidos correctamente para órdenes de compra.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error al obtener productos con stock bajo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Búsqueda rápida de productos para autocompletado en órdenes de compra.
     * GET /api/productos/buscar?q=...
     */
    public function buscar(Request $request): JsonResponse
    {
        try {
            $q = $request->input('q', $request->input('search', ''));
            $limit = (int) $request->input('limit', 15);

            $productos = $this->inventarioService->buscarProductosRapido($q, $limit);

            return response()->json([
                'success' => true,
                'total' => count($productos),
                'data' => $productos,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Error en la búsqueda rápida de productos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el último precio pactado y costos de un producto.
     * GET /api/productos/{id}/ultimo-precio
     */
    public function ultimoPrecio(string $id): JsonResponse
    {
        try {
            $datos = $this->inventarioService->obtenerUltimoPrecioProducto($id);

            return response()->json([
                'success' => true,
                'data' => $datos,
                'message' => 'Último precio y cotizaciones del producto obtenidos correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function stockProductoPorUnidades(string $productoId): JsonResponse
    {
        try {
            $desglose = $this->inventarioService->consultarStockProductoPorUnidades($productoId);

            return response()->json([
                'success' => true,
                'data' => $desglose,
                'message' => 'Desglose de stock por unidades de medida obtenido correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Lightweight products list for select dropdowns.
     */
    public function productosParaSelect(): JsonResponse
    {
        try {
            $productos = \App\Models\Producto::where('ProductoEliminado', 'N')
                ->where('ProductoEstado', 'A')
                ->with(['categoria', 'detalleProductoMedidas' => function ($q) {
                    $q->where('Detalle_Producto_medidaEliminado', 'N')->with('unidadMedida');
                }])
                ->orderBy('ProductoNombre', 'asc')
                ->get()
                ->map(function ($p) {
                    $unidades = $p->detalleProductoMedidas->map(function ($det) {
                        $costo = (float) $det->Detalle_Producto_medida_precio_compra;
                        $venta = (float) $det->Detalle_Producto_medida_precio_venta;
                        $margen = $venta > 0 ? round((($venta - $costo) / $venta) * 100, 1) : 0;
                        $esBase = (int) $det->Detalle_Producto_medida_factor_conversion === 1 || ($det->unidadMedida && (int) $det->unidadMedida->unidades_medidaEsBase === 1);

                        return [
                            'unidades_medidaId' => $det->Detalle_Producto_medida_unidades_medidaId,
                            'descripcion' => $det->unidadMedida->unidades_medidaDescripcionUnidades ?? 'Unidad',
                            'abreviatura' => $det->unidadMedida->unidades_medidaAbreviatura ?? 'UND',
                            'es_base' => $esBase,
                            'factor_conversion' => (int) $det->Detalle_Producto_medida_factor_conversion,
                            'precio_compra' => $costo,
                            'precio_venta' => $venta,
                            'precio_compra_formateado' => 'S/ ' . number_format($costo, 2),
                            'precio_venta_formateado' => 'S/ ' . number_format($venta, 2),
                            'margen_porcentaje' => $margen . '%',
                        ];
                    })->values();

                    $unidadBase = $unidades->first(fn ($u) => !empty($u['es_base']) || $u['factor_conversion'] === 1) ?? $unidades->first();
                    $abrevBase = $unidadBase['abreviatura'] ?? 'UND';
                    $nombreBase = $unidadBase['descripcion'] ?? 'Unidad';

                    return [
                        'ProductoId' => $p->ProductoId,
                        'ProductoNombre' => $p->ProductoNombre,
                        'ProductoMarca' => $p->ProductoMarca ?? 'Genérico',
                        'categoria_nombre' => $p->categoria->Categoria_ProductoDescripcion_categoria ?? 'Sin Categoría',
                        'ProductoStockActual' => (float) $p->ProductoStockActual,
                        'stock_total_vendible' => (float) $p->ProductoStockActual,
                        'unidad_base' => $abrevBase,
                        'unidad_base_nombre' => $nombreBase,
                        'stock_actual_texto' => number_format((float) $p->ProductoStockActual, 0) . ' ' . $abrevBase,
                        'unidades' => $unidades,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $productos,
                'message' => 'Lista para select de productos obtenida correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener productos para select: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted product.
     */
    public function restaurar(string $id): JsonResponse
    {
        try {
            $this->inventarioService->restaurarProducto($id);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => "Producto '{$id}' restaurado exitosamente.",
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al restaurar producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir imagen para un producto.
     */
    public function uploadImagen(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'imagen' => ['required', 'file', 'image', 'max:5120'],
            ]);

            $producto = $this->inventarioService->subirImagenProducto($id, $request->file('imagen'));

            return response()->json([
                'success' => true,
                'data' => $producto,
                'message' => 'Imagen del producto subida exitosamente.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error de validación de la imagen.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al subir la imagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alertas de stock bajo en formato de tarjetas UI para el panel de notificaciones.
     */
    public function alertasStockBajo(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->input('limit', 50);
            $productos = $this->inventarioService->obtenerProductosStockBajo($limit);

            $alertas = array_map(function ($p) {
                $stockActual = (float) $p['stock_actual'];
                $stockMinimo = (float) $p['stock_minimo'];

                if ($stockActual <= 0) {
                    $nivel = 'CRÍTICO';
                    $badgeColor = '#DC2626'; // Rojo
                    $badgeTipo = 'danger';
                } elseif ($stockActual <= ($stockMinimo / 2)) {
                    $nivel = 'ALTO';
                    $badgeColor = '#EA580C'; // Naranja intenso
                    $badgeTipo = 'warning';
                } else {
                    $nivel = 'MEDIO';
                    $badgeColor = '#D97706'; // Ámbar
                    $badgeTipo = 'warning';
                }

                return [
                    'alerta_id' => 'ALT-' . $p['producto_id'],
                    'producto_id' => $p['producto_id'],
                    'producto_nombre' => $p['nombre'],
                    'marca' => $p['marca'],
                    'categoria' => $p['categoria'],
                    'nivel_criticidad' => $nivel,
                    'stock_actual' => $stockActual,
                    'stock_minimo' => $stockMinimo,
                    'deficit' => $p['deficit'],
                    'sugerido_reposicion' => $p['sugerido_reposicion'],
                    'unidad_base' => $p['unidad_base'],
                    'precio_compra_estimado' => $p['precio_compra_estimado'],
                    'impacto_soles' => $p['impacto_reposicion_soles'],
                    'proveedores' => $p['proveedores'],
                    'tarjeta_ui' => [
                        'badge' => [
                            'texto' => "Stock {$nivel}",
                            'tipo' => $badgeTipo,
                            'color' => $badgeColor,
                            'icono' => 'alert_circle',
                        ],
                        'entidad' => 'COMERCIAL VALENCIA',
                        'referencia_codigo' => $p['producto_id'],
                        'flujo' => [
                            'origen' => "Stock: {$stockActual} {$p['unidad_base']}",
                            'destino' => "Mínimo: {$stockMinimo} {$p['unidad_base']}",
                            'indicador' => "Pedir: +{$p['sugerido_reposicion']} {$p['unidad_base']}",
                        ],
                        'impacto_financiero' => [
                            'costo_reposicion_estimado' => $p['impacto_reposicion_soles'],
                            'moneda' => 'S/',
                        ],
                        'accion' => [
                            'texto' => 'Generar Orden de Compra',
                            'url' => '/api/ordenes-compra',
                        ],
                    ],
                ];
            }, $productos);

            return response()->json([
                'success' => true,
                'total' => count($alertas),
                'data' => $alertas,
                'message' => 'Alertas de stock bajo obtenidas correctamente.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Error al obtener alertas de stock bajo: ' . $e->getMessage(),
            ], 500);
        }
    }
}
