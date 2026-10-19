<?php

namespace App\Services;

/**
 * ⚠️ REGLA DEL PROYECTO:
 * Este servicio NO crea tablas nuevas ni relaciones Eloquent.
 * NO define belongsTo / hasMany / belongsToMany.
 * NO agrega foreign keys.
 * Accede a datos de otras tablas EXCLUSIVAMENTE mediante DB::table()->join()
 * o DB::select() con JOINs explícitos sobre el esquema existente.
 */

use App\Support\AuditHelper;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DespachoService
{
    public function __construct(
        protected RoturaStockService  $roturaStockService,
        protected InventarioService   $inventarioService
    ) {}

    // =========================================================================
    // 1. BANDEJA DE DESPACHO
    // =========================================================================

    /**
     * Lista pedidos disponibles para despacho.
     * JOIN manual: Pedido + Cliente + Canal_Pedido.
     * NO crea relaciones. Solo SELECT con JOINs.
     */
    public function listarPedidosDespacho(array $filtros = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = DB::table('Pedido as p')
            ->join('Cliente as c', 'p.Pedido_ClienteId', '=', 'c.ClienteId')
            ->leftJoin('Canal_pedido as cp', 'p.Pedido_canal_pedidoId', '=', 'cp.Canal_pedidoId')
            ->where('p.PedidoEliminado', 'N')
            ->whereIn('p.PedidoEstado_pedido', ['P', 'C', 'A'])
            // Excluir cotizaciones
            ->where(function ($q) {
                $q->whereNull('p.PedidoAcuerdo_Comercial')
                  ->orWhere('p.PedidoAcuerdo_Comercial', 'not like', '%Cotizaci%');
            })
            ->select(
                'p.PedidoId',
                'p.PedidoEstado_pedido as PedidoEstado',
                DB::raw("
                    COALESCE(p.\"PedidoEstadoDespacho\",
                        CASE 
                            WHEN p.\"PedidoEstado_pedido\" = 'C' THEN 'ENTREGADO_COMPLETO'
                            WHEN p.\"PedidoEstado_pedido\" = 'A' THEN 'RECHAZADO'
                            ELSE 'PENDIENTE_DESPACHO'
                        END
                    ) as \"PedidoEstadoDespacho\"
                "),
                'p.PedidoTieneFaltante',
                'p.PedidoTotal',
                'p.PedidoTotalDespachado',
                'p.PedidoFecha_pedido as PedidoFecha',
                'p.PedidoFechaCreacion',
                'p.PedidoFechaInicioDespacho',
                'p.PedidoFechaFinDespacho',
                'p.PedidoDespachadoPor',
                'p.PedidoObservacionDespacho',
                'p.PedidoEsDelivery',
                'p.PedidoDireccionEntrega',
                'c.ClienteId',
                'c.ClienteNombre',
                'c.ClienteNumero as ClienteTelefono',
                'cp.Canal_pedidoDescripcion as canal'
            );

        // Filtro por estado_despacho
        if (!empty($filtros['estado_despacho'])) {
            if ($filtros['estado_despacho'] === 'PENDIENTE_DESPACHO' || $filtros['estado_despacho'] === 'PENDIENTE') {
                $query->where(function ($q) {
                    $q->whereIn('p.PedidoEstadoDespacho', ['PENDIENTE', 'PENDIENTE_DESPACHO'])
                      ->orWhere(function ($sub) {
                          $sub->whereNull('p.PedidoEstadoDespacho')
                              ->where('p.PedidoEstado_pedido', 'P');
                      });
                });
            } else {
                $query->where('p.PedidoEstadoDespacho', $filtros['estado_despacho']);
            }
        }
        if (!empty($filtros['canal_id'])) {
            $query->where('p.Pedido_canal_pedidoId', $filtros['canal_id']);
        }
        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('p.PedidoFecha_pedido', '>=', $filtros['fecha_desde']);
        }
        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('p.PedidoFecha_pedido', '<=', $filtros['fecha_hasta']);
        }
        if (!empty($filtros['search'])) {
            $search = '%' . $filtros['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('p.PedidoId', 'like', $search)
                  ->orWhere('c.ClienteNombre', 'like', $search);
            });
        }

        return $query->orderByRaw("
            CASE 
                WHEN p.\"PedidoEstadoDespacho\" = 'EN_PREPARACION' THEN 1
                WHEN p.\"PedidoEstadoDespacho\" IN ('PENDIENTE', 'PENDIENTE_DESPACHO') THEN 2
                ELSE 3
            END, p.\"PedidoFecha_pedido\" DESC
        ")->paginate($perPage);
    }

    // =========================================================================
    // 2. DETALLE PARA DESPACHO
    // =========================================================================

    /**
     * Carga pedido + ítems + stock actual.
     * JOIN manual entre Pedido, Detalle_Pedido_Productos, Producto, Unidades_Medida.
     * NO se definen relaciones Eloquent nuevas.
     * Solo SELECT con JOINs sobre el esquema existente.
     */
    public function obtenerDetalleParaDespacho(string $pedidoId): array
    {
        // Cabecera del pedido con datos del cliente y canal (JOIN manual)
        $pedido = DB::table('Pedido as p')
            ->join('Cliente as c', 'p.Pedido_ClienteId', '=', 'c.ClienteId')
            ->leftJoin('Canal_pedido as cp', 'p.Pedido_canal_pedidoId', '=', 'cp.Canal_pedidoId')
            ->where('p.PedidoId', $pedidoId)
            ->select(
                'p.PedidoId',
                'p.PedidoEstado_pedido as PedidoEstado',
                'p.PedidoEstadoDespacho',
                'p.PedidoTieneFaltante',
                'p.PedidoTotal',
                'p.PedidoTotalDespachado',
                'p.PedidoFecha_pedido as PedidoFecha',
                'p.PedidoFechaCreacion',
                'p.PedidoFechaInicioDespacho',
                'p.PedidoFechaFinDespacho',
                'p.PedidoDespachadoPor',
                'p.PedidoCerradoPor',
                'p.PedidoObservacionDespacho',
                'p.PedidoEsDelivery',
                'p.PedidoDireccionEntrega',
                'c.ClienteId',
                'c.ClienteNombre',
                'c.ClienteNumero as ClienteTelefono',
                'c.ClienteDireccion',
                'cp.Canal_pedidoDescripcion as canal'
            )
            ->first();

        if (!$pedido) {
            throw new Exception("Pedido {$pedidoId} no encontrado.");
        }

        // Ítems del pedido con stock actual (JOIN manual: Detalle + Producto + Unidades_Medida)
        $items = DB::table('Detalle_Pedido_Productos as dpp')
            ->join('Producto as prod', 'dpp.Detalle_Pedido_Productos_ProductoId', '=', 'prod.ProductoId')
            ->leftJoin('unidades_medida as um', 'dpp.Detalle_Pedido_Productos_unidades_medidaId', '=', 'um.unidades_medidaId')
            ->where('dpp.Detalle_Pedido_Productos_PedidoId', $pedidoId)
            ->where('dpp.Detalle_Pedido_ProductosEliminado', 'N')
            ->select(
                'dpp.Detalle_Pedido_ProductosId',
                'dpp.Detalle_Pedido_Productos_ProductoId as producto_id',
                'prod.ProductoNombre as producto_nombre',
                'dpp.Detalle_Pedido_Productos_unidades_medidaId as unidad_medida_id',
                'um.unidades_medidaAbreviatura as unidad_medida',
                'dpp.Detalle_Pedido_Productos_cantidad as cantidad_solicitada',
                'dpp.Detalle_Pedido_Productos_cantidad_despachada as cantidad_despachada',
                'dpp.Detalle_Pedido_Productos_estado_item_despacho as estado_item',
                'dpp.Detalle_Pedido_Productos_observacion_item as observacion_item',
                'dpp.Detalle_Pedido_Productos_precio_unitario_venta as precio_unitario',
                'dpp.Detalle_Pedido_Productos_subtotal as subtotal',
                'dpp.Detalle_Pedido_ProductosTuvoRotura as tuvo_rotura',
                'dpp.Detalle_Pedido_ProductosCantidadFaltanteRotura as cantidad_faltante_rotura',
                'prod.ProductoStockActual as stock_actual'
            )
            ->get();

        return [
            'pedido' => $pedido,
            'items'  => $items,
        ];
    }

    // =========================================================================
    // 3. INICIAR PREPARACIÓN
    // =========================================================================

    public function iniciarPreparacion(string $pedidoId): object
    {
        $pedido = DB::table('Pedido')->where('PedidoId', $pedidoId)->first();

        if (!$pedido) {
            throw new Exception("Pedido {$pedidoId} no encontrado.");
        }

        $estadosFinales = ['ENTREGADO_COMPLETO', 'ENTREGADO_CON_FALTANTE', 'RECHAZADO_ROTURA', 'ENTREGADO_PARCIAL', 'RECHAZADO'];
        if (in_array($pedido->PedidoEstadoDespacho, $estadosFinales)) {
            throw new Exception("El pedido ya fue procesado (estado: {$pedido->PedidoEstadoDespacho}). No se puede modificar.");
        }

        $usuario = AuditHelper::getCurrentUser();

        DB::table('Pedido')->where('PedidoId', $pedidoId)->update([
            'PedidoEstadoDespacho'      => 'EN_PREPARACION',
            'PedidoFechaInicioDespacho' => now(),
            'PedidoDespachadoPor'       => $usuario,
        ]);

        return DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
    }

    // =========================================================================
    // 4. CANCELAR PREPARACIÓN (Vuelve a PENDIENTE)
    // =========================================================================

    public function cancelarPreparacion(string $pedidoId, string $motivo = ''): object
    {
        $pedido = DB::table('Pedido')->where('PedidoId', $pedidoId)->first();

        if (!$pedido) {
            throw new Exception("Pedido {$pedidoId} no encontrado.");
        }

        if ($pedido->PedidoEstadoDespacho !== 'EN_PREPARACION') {
            throw new Exception("Solo se puede cancelar la preparación desde el estado EN_PREPARACION.");
        }

        $usuario = AuditHelper::getCurrentUser();

        DB::table('Pedido')->where('PedidoId', $pedidoId)->update([
            'PedidoEstadoDespacho'      => 'PENDIENTE_DESPACHO',
            'PedidoFechaInicioDespacho' => null,
            'PedidoDespachadoPor'       => null,
            'PedidoObservacionDespacho' => $motivo ?: "Preparación cancelada por {$usuario}",
        ]);

        return DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
    }

    // =========================================================================
    // 5. CONFIRMAR DESPACHO COMPLETO (PODE=1, PRS=0)
    // =========================================================================

    public function confirmarDespachoCompleto(string $pedidoId, array $datos = []): object
    {
        $pedido = DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
        if (!$pedido) throw new Exception("Pedido {$pedidoId} no encontrado.");

        $estadosFinales = ['ENTREGADO_COMPLETO', 'ENTREGADO_CON_FALTANTE', 'RECHAZADO_ROTURA', 'ENTREGADO_PARCIAL', 'RECHAZADO'];
        if (in_array($pedido->PedidoEstadoDespacho, $estadosFinales)) {
            throw new \RuntimeException(
                "IDEMPOTENCIA_409: El pedido ya fue procesado (estado: {$pedido->PedidoEstadoDespacho})."
            );
        }

        $usuario = AuditHelper::getCurrentUser();
        $observacion = $datos['observacion'] ?? null;

        DB::transaction(function () use ($pedidoId, $pedido, $usuario, $observacion) {

            // Marcar todos los ítems como verificados OK
            $items = DB::table('Detalle_Pedido_Productos')
                ->where('Detalle_Pedido_Productos_PedidoId', $pedidoId)
                ->where('Detalle_Pedido_ProductosEliminado', 'N')
                ->get();

            foreach ($items as $item) {
                DB::table('Detalle_Pedido_Productos')
                    ->where('Detalle_Pedido_ProductosId', $item->Detalle_Pedido_ProductosId)
                    ->update([
                        'Detalle_Pedido_Productos_cantidad_despachada'  => $item->Detalle_Pedido_Productos_cantidad,
                        'Detalle_Pedido_Productos_estado_item_despacho' => 'VERIFICADO_OK',
                        'Detalle_Pedido_ProductosTuvoRotura'            => 'N',
                        'Detalle_Pedido_ProductosCantidadFaltanteRotura' => 0,
                    ]);
            }

            // Cerrar pedido
            DB::table('Pedido')->where('PedidoId', $pedidoId)->update([
                'PedidoEstadoDespacho'      => 'ENTREGADO_COMPLETO',
                'PedidoEstado_pedido'       => 'C',
                'PedidoTieneFaltante'       => 'N',
                'PedidoTotalDespachado'     => $pedido->PedidoTotal,
                'PedidoFechaFinDespacho'    => now(),
                'PedidoFechaDespacho'       => now(),
                'PedidoCerradoPor'          => $usuario,
                'PedidoObservacionDespacho' => $observacion,
            ]);

            Log::info("DespachoService: Despacho COMPLETO — Pedido {$pedidoId} por {$usuario}");
        });

        return DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
    }

    // =========================================================================
    // 6. CONFIRMAR DESPACHO CON FALTANTE (Cliente acepta: PODE=1, PRS=1)
    // =========================================================================

    public function confirmarDespachoConFaltante(string $pedidoId, array $payload): object
    {
        $pedido = DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
        if (!$pedido) throw new Exception("Pedido {$pedidoId} no encontrado.");

        $estadosFinales = ['ENTREGADO_COMPLETO', 'ENTREGADO_CON_FALTANTE', 'RECHAZADO_ROTURA', 'ENTREGADO_PARCIAL', 'RECHAZADO'];
        if (in_array($pedido->PedidoEstadoDespacho, $estadosFinales)) {
            throw new \RuntimeException(
                "IDEMPOTENCIA_409: El pedido ya fue procesado (estado: {$pedido->PedidoEstadoDespacho})."
            );
        }

        $itemsPayload = $payload['items'] ?? [];
        $observacionGeneral = $payload['observacion'] ?? 'Despacho con faltante aceptado por el cliente';
        $usuario = AuditHelper::getCurrentUser();

        DB::transaction(function () use ($pedidoId, $pedido, $itemsPayload, $observacionGeneral, $usuario) {

            $totalDespachado = 0.0;
            $hayFaltante = false;

            foreach ($itemsPayload as $itemData) {
                $detalleId        = $itemData['detalle_id'];
                $cantDespachada   = (float) ($itemData['cantidad_despachada'] ?? 0);
                $obsItem          = $itemData['observacion'] ?? null;

                $itemOriginal = DB::table('Detalle_Pedido_Productos as dpp')
                    ->join('Producto as prod', 'dpp.Detalle_Pedido_Productos_ProductoId', '=', 'prod.ProductoId')
                    ->where('dpp.Detalle_Pedido_ProductosId', $detalleId)
                    ->select(
                        'dpp.Detalle_Pedido_ProductosId',
                        'dpp.Detalle_Pedido_Productos_ProductoId as producto_id',
                        'dpp.Detalle_Pedido_Productos_cantidad as cantidad_solicitada',
                        'dpp.Detalle_Pedido_Productos_precio_unitario_venta as precio_unitario',
                        'dpp.Detalle_Pedido_Productos_factor_conversion as factor_conversion',
                        'prod.ProductoStockActual as stock_actual'
                    )
                    ->first();

                if (!$itemOriginal) continue;

                $cantSolicitada = (float) $itemOriginal->cantidad_solicitada;

                // Validación estricta: No se puede despachar más de lo solicitado en el pedido
                if ($cantDespachada > $cantSolicitada) {
                    throw new Exception("La cantidad a despachar ({$cantDespachada}) no puede ser mayor a la cantidad solicitada ({$cantSolicitada}) para el producto '{$itemOriginal->producto_id}'.");
                }
                if ($cantDespachada < 0) {
                    throw new Exception("La cantidad a despachar no puede ser negativa.");
                }

                $cantFaltante   = max(0.0, $cantSolicitada - $cantDespachada);
                $tieneFaltante  = $cantFaltante > 0;
                $factor         = (float) ($itemOriginal->factor_conversion ?: 1.0);

                // Estado del ítem
                $estadoItem = 'VERIFICADO_OK';
                if ($cantDespachada <= 0) {
                    $estadoItem = 'NO_DISPONIBLE';
                } elseif ($tieneFaltante) {
                    $estadoItem = 'CON_FALTANTE';
                }

                // Actualizar detalle del pedido
                DB::table('Detalle_Pedido_Productos')
                    ->where('Detalle_Pedido_ProductosId', $detalleId)
                    ->update([
                        'Detalle_Pedido_Productos_cantidad_despachada'  => $cantDespachada,
                        'Detalle_Pedido_Productos_estado_item_despacho' => $estadoItem,
                        'Detalle_Pedido_Productos_observacion_item'     => $obsItem,
                        'Detalle_Pedido_ProductosTuvoRotura'            => $tieneFaltante ? 'S' : 'N',
                        'Detalle_Pedido_ProductosCantidadFaltanteRotura' => $cantFaltante,
                    ]);

                // Si hay faltante: registrar rotura en detalle_rotura_stock
                if ($tieneFaltante) {
                    $hayFaltante = true;
                    $cantFaltanteBase = round($cantFaltante * $factor, 2);
                    $cantSolicitadaBase = round($cantSolicitada * $factor, 2);
                    $cantDespachadaBase = round($cantDespachada * $factor, 2);

                    $this->roturaStockService->confirmarRotura([
                        'pedido_id'                   => $pedidoId,
                        'detalle_pedido_id'           => $detalleId,
                        'producto_id'                 => $itemOriginal->producto_id,
                        'cantidad_solicitada'         => $cantSolicitadaBase,
                        'cantidad_disponible_momento' => $cantDespachadaBase,
                        'cantidad_faltante'           => $cantFaltanteBase,
                        'usuario_id'                  => $usuario,
                        'observaciones'               => "Despacho físico: faltaron {$cantFaltante} unidades ({$cantFaltanteBase} base). {$obsItem}",
                    ]);

                    // Reintegrar unidades no despachadas al stock físico
                    if ($cantFaltanteBase > 0 && $itemOriginal->producto_id) {
                        DB::table('Producto')
                            ->where('ProductoId', $itemOriginal->producto_id)
                            ->increment('ProductoStockActual', $cantFaltanteBase);
                    }
                }

                $totalDespachado += $cantDespachada * (float) $itemOriginal->precio_unitario;
            }

            // Cerrar pedido: PODE = 1 (cliente aceptó), PRS = 1
            DB::table('Pedido')->where('PedidoId', $pedidoId)->update([
                'PedidoEstadoDespacho'      => 'ENTREGADO_CON_FALTANTE',
                'PedidoEstado_pedido'       => 'C',   // Completado = PODE 1
                'PedidoTieneFaltante'       => $hayFaltante ? 'S' : 'N',
                'PedidoTotalDespachado'     => round($totalDespachado, 2),
                'PedidoFechaFinDespacho'    => now(),
                'PedidoFechaDespacho'       => now(),
                'PedidoCerradoPor'          => $usuario,
                'PedidoObservacionDespacho' => $observacionGeneral,
            ]);

            Log::info("DespachoService: Despacho CON_FALTANTE — Pedido {$pedidoId} por {$usuario}. Total despachado: {$totalDespachado}");
        });

        return DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
    }

    // =========================================================================
    // 7. RECHAZAR DESPACHO POR ROTURA (Cliente desiste: PODE=0, PRS=1)
    // =========================================================================

    public function rechazarDespachoPorRotura(string $pedidoId, array $payload): object
    {
        $pedido = DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
        if (!$pedido) throw new Exception("Pedido {$pedidoId} no encontrado.");

        $estadosFinales = ['ENTREGADO_COMPLETO', 'ENTREGADO_CON_FALTANTE', 'RECHAZADO_ROTURA', 'ENTREGADO_PARCIAL', 'RECHAZADO'];
        if (in_array($pedido->PedidoEstadoDespacho, $estadosFinales)) {
            throw new \RuntimeException(
                "IDEMPOTENCIA_409: El pedido ya fue procesado (estado: {$pedido->PedidoEstadoDespacho})."
            );
        }

        $motivo         = $payload['motivo'] ?? 'Cliente desistió por falta de stock';
        $itemsConRotura = $payload['items_con_rotura'] ?? [];
        $usuario        = AuditHelper::getCurrentUser();

        DB::transaction(function () use ($pedidoId, $motivo, $itemsConRotura, $usuario) {

            $todosLosItems = DB::table('Detalle_Pedido_Productos as dpp')
                ->where('dpp.Detalle_Pedido_Productos_PedidoId', $pedidoId)
                ->where('dpp.Detalle_Pedido_ProductosEliminado', 'N')
                ->select(
                    'dpp.Detalle_Pedido_ProductosId',
                    'dpp.Detalle_Pedido_Productos_ProductoId as producto_id',
                    'dpp.Detalle_Pedido_Productos_cantidad as cantidad_solicitada',
                    'dpp.Detalle_Pedido_Productos_factor_conversion as factor_conversion'
                )
                ->get();

            $roturaMap = collect($itemsConRotura)->keyBy('detalle_id');

            foreach ($todosLosItems as $item) {
                $cantSolicitada = (float) $item->cantidad_solicitada;
                $factor         = (float) ($item->factor_conversion ?: 1.0);
                $cantBase       = round($cantSolicitada * $factor, 2);
                $infoRotura     = $roturaMap->get($item->Detalle_Pedido_ProductosId);
                $cantFaltante   = $infoRotura ? (float) ($infoRotura['cantidad_faltante'] ?? 0) : 0;
                $cantFaltanteBase = round($cantFaltante * $factor, 2);

                // Reintegrar todo lo que se descontó originalmente al stock
                if ($cantBase > 0 && $item->producto_id) {
                    DB::table('Producto')
                        ->where('ProductoId', $item->producto_id)
                        ->increment('ProductoStockActual', $cantBase);
                }

                // Actualizar detalle
                DB::table('Detalle_Pedido_Productos')
                    ->where('Detalle_Pedido_ProductosId', $item->Detalle_Pedido_ProductosId)
                    ->update([
                        'Detalle_Pedido_Productos_cantidad_despachada'  => 0,
                        'Detalle_Pedido_Productos_estado_item_despacho' => $cantFaltante > 0 ? 'NO_DISPONIBLE' : 'VERIFICADO_OK',
                        'Detalle_Pedido_ProductosTuvoRotura'            => $cantFaltante > 0 ? 'S' : 'N',
                        'Detalle_Pedido_ProductosCantidadFaltanteRotura' => $cantFaltante,
                    ]);

                // Registrar rotura para los ítems faltantes
                if ($cantFaltante > 0) {
                    $this->roturaStockService->confirmarRotura([
                        'pedido_id'                   => $pedidoId,
                        'detalle_pedido_id'           => $item->Detalle_Pedido_ProductosId,
                        'producto_id'                 => $item->producto_id,
                        'cantidad_solicitada'         => $cantBase,
                        'cantidad_disponible_momento' => max(0, $cantBase - $cantFaltanteBase),
                        'cantidad_faltante'           => $cantFaltanteBase,
                        'usuario_id'                  => $usuario,
                        'observaciones'               => "Cliente desistió del pedido. Motivo: {$motivo}",
                    ]);
                }
            }

            // Anular pedido: PODE = 0, PRS = 1
            DB::table('Pedido')->where('PedidoId', $pedidoId)->update([
                'PedidoEstadoDespacho'      => 'RECHAZADO_ROTURA',
                'PedidoEstado_pedido'       => 'A',   // Anulado = PODE 0
                'PedidoMotivoAnulacion'     => 'FALTA_STOCK',
                'PedidoTieneFaltante'       => 'S',
                'PedidoTotalDespachado'     => 0,
                'PedidoFechaFinDespacho'    => now(),
                'PedidoCerradoPor'          => $usuario,
                'PedidoObservacionDespacho' => $motivo,
            ]);

            Log::info("DespachoService: Despacho RECHAZADO — Pedido {$pedidoId} por {$usuario}. Motivo: {$motivo}");
        });

        return DB::table('Pedido')->where('PedidoId', $pedidoId)->first();
    }

    // =========================================================================
    // 8. KPIs DE LA BANDEJA
    // =========================================================================

    public function obtenerKpisBandeja(): array
    {
        $hoy = now()->toDateString();

        $contadores = DB::table('Pedido')
            ->where('PedidoEliminado', 'N')
            ->where(function ($q) {
                $q->whereNull('PedidoAcuerdo_Comercial')
                  ->orWhere('PedidoAcuerdo_Comercial', 'not like', '%Cotizaci%');
            })
            ->selectRaw("
                COUNT(CASE WHEN (\"PedidoEstadoDespacho\" IN ('PENDIENTE', 'PENDIENTE_DESPACHO') OR (\"PedidoEstadoDespacho\" IS NULL AND \"PedidoEstado_pedido\" = 'P')) THEN 1 END) as pendientes,
                COUNT(CASE WHEN \"PedidoEstadoDespacho\" = 'EN_PREPARACION' THEN 1 END) as en_preparacion,
                COUNT(CASE WHEN \"PedidoEstadoDespacho\" IN ('ENTREGADO_COMPLETO', 'ENTREGADO_CON_FALTANTE')
                           AND CAST(\"PedidoFechaFinDespacho\" AS DATE) = ? THEN 1 END) as despachados_hoy,
                COUNT(CASE WHEN \"PedidoEstadoDespacho\" = 'ENTREGADO_CON_FALTANTE'
                           AND CAST(\"PedidoFechaFinDespacho\" AS DATE) = ? THEN 1 END) as con_faltante_hoy
            ", [$hoy, $hoy])
            ->first();

        return [
            'pendientes'       => (int) ($contadores->pendientes ?? 0),
            'en_preparacion'   => (int) ($contadores->en_preparacion ?? 0),
            'despachados_hoy'  => (int) ($contadores->despachados_hoy ?? 0),
            'con_faltante_hoy' => (int) ($contadores->con_faltante_hoy ?? 0),
        ];
    }
}
