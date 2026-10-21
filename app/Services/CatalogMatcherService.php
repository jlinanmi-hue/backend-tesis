<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\DetalleProductoMedida;
use Illuminate\Support\Collection;

class CatalogMatcherService
{
    /**
     * Cache local de productos en memoria durante la ejecución del request
     */
    protected ?Collection $catalogoCache = null;

    /**
     * Emparejar un ítem detectado por la IA con el catálogo de productos de la base de datos.
     */
    public function matchItem(string $textoBuscado, float $cantidad = 1.0, ?string $presentacionTexto = null): array
    {
        $catalogo = $this->getCatalogo();
        $textoNormalizado = $this->normalizarTexto($textoBuscado);

        $mejorMatch = null;
        $mejorScore = 0.0;

        foreach ($catalogo as $prod) {
            $score = $this->calcularSimilitud($textoNormalizado, $prod['_nombre_normalizado'], $prod);
            if ($score > $mejorScore) {
                $mejorScore = $score;
                $mejorMatch = $prod;
            }
        }

        // Determinar nivel de confianza y badge visual
        $confianza = 'low';
        $badge = 'gris';
        if ($mejorScore >= 80.0) {
            $confianza = 'high';
            $badge = 'verde';
        } elseif ($mejorScore >= 50.0) {
            $confianza = 'medium';
            $badge = 'ambar';
        }

        // Si el score es muy bajo (< 35%), no forzar producto incorrecto
        $productoFinal = ($mejorScore >= 35.0) ? $mejorMatch : null;

        // Resolver la presentación del producto
        $presentaciones = [];
        $presentacionSeleccionada = null;

        if ($productoFinal) {
            $presentaciones = $this->obtenerPresentaciones($productoFinal['id']);
            $presentacionSeleccionada = $this->resolverPresentacion($presentaciones, $presentacionTexto);
        }

        return [
            'texto_detectado'          => $textoBuscado,
            'cantidad_detectada'       => max(1.0, $cantidad),
            'presentacion_detectada'   => $presentacionTexto,
            'match_score'              => round($mejorScore, 1),
            'match_nivel'              => $confianza,
            'match_badge'              => $badge, // verde, ambar, gris
            'producto_id'              => $productoFinal['id'] ?? null,
            'producto_nombre'          => $productoFinal['nombre'] ?? $textoBuscado,
            'producto_marca'           => $productoFinal['marca'] ?? null,
            'stock_actual'             => $productoFinal['stock_actual'] ?? 0,
            'presentacion_id'          => $presentacionSeleccionada['unidad_id'] ?? 'UND-00001',
            'presentacion_nombre'      => $presentacionSeleccionada['descripcion'] ?? 'Unidad',
            'presentacion_factor'      => $presentacionSeleccionada['factor'] ?? 1,
            'precio_unitario'          => $presentacionSeleccionada['precio_venta'] ?? 0.0,
            'presentacion_es_sugerida' => true,
            'presentaciones_posibles'  => $presentaciones,
        ];
    }

    /**
     * Procesar un conjunto de ítems extraídos por la IA
     */
    public function matchMultipleItems(array $itemsExtraidos): array
    {
        $resultados = [];
        foreach ($itemsExtraidos as $item) {
            $nombre = trim($item['nombre_buscado'] ?? $item['nombre'] ?? $item['producto'] ?? '');
            if (empty($nombre)) continue;

            $cant = (float) ($item['cantidad'] ?? 1);
            $pres = $item['presentacion'] ?? $item['unidad'] ?? null;

            $resultados[] = $this->matchItem($nombre, $cant, $pres);
        }
        return $resultados;
    }

    /**
     * Cargar el catálogo activo en memoria para comparaciones veloces
     */
    protected function getCatalogo(): Collection
    {
        if ($this->catalogoCache !== null) {
            return $this->catalogoCache;
        }

        $productos = Producto::where('ProductoEliminado', 'N')
            ->select(['ProductoId', 'ProductoNombre', 'ProductoMarca', 'ProductoStockActual'])
            ->get();

        $this->catalogoCache = $productos->map(function ($p) {
            return [
                'id'                 => $p->ProductoId,
                'nombre'             => $p->ProductoNombre,
                'marca'              => $p->ProductoMarca,
                'stock_actual'       => (float) $p->ProductoStockActual,
                '_nombre_normalizado'=> $this->normalizarTexto("{$p->ProductoNombre} {$p->ProductoMarca}"),
            ];
        });

        return $this->catalogoCache;
    }

    /**
     * Calcula similitud ponderada entre el texto detectado y el nombre del producto
     */
    protected function calcularSimilitud(string $busqueda, string $candidato, array $prod): float
    {
        // 1. Coincidencia exacta de ID o texto
        if ($busqueda === $candidato || str_contains($candidato, $busqueda)) {
            return 98.0;
        }

        // 2. Token Matching (Intersección ponderada de palabras)
        $tokensBusqueda = array_filter(explode(' ', $busqueda));
        $tokensCandidato = array_filter(explode(' ', $candidato));

        if (empty($tokensBusqueda) || empty($tokensCandidato)) {
            return 0.0;
        }

        $coincidencias = 0;
        $puntosBonus = 0.0;

        foreach ($tokensBusqueda as $t) {
            // Si el token mide más de 1 caracter y está contenido en el candidato
            foreach ($tokensCandidato as $tc) {
                if ($t === $tc) {
                    $coincidencias += 1.0;
                    break;
                } elseif (strlen($t) >= 4 && (str_contains($tc, $t) || str_contains($t, $tc))) {
                    $coincidencias += 0.8;
                    break;
                }
            }

            // Bonus especial para tamaños y volúmenes (ej. "3l", "500ml", "1kg", "zero", "light")
            if (preg_match('/^(\d+(?:l|ml|kg|g)|zero|light|azul|roja)$/i', $t) && str_contains($candidato, $t)) {
                $puntosBonus += 15.0;
            }
        }

        $tokenScore = ($coincidencias / count($tokensBusqueda)) * 80.0;

        // 3. Similitud de cadenas tipo similar_text
        similar_text($busqueda, $candidato, $similPct);
        $similarScore = $similPct * 0.20;

        $finalScore = min(100.0, $tokenScore + $similarScore + $puntosBonus);

        return $finalScore;
    }

    /**
     * Obtener presentaciones activas de un producto
     */
    protected function obtenerPresentaciones(string $productoId): array
    {
        $detalles = DetalleProductoMedida::with('unidadMedida')
            ->where('Detalle_Producto_medida_ProductoId', $productoId)
            ->where('Detalle_Producto_medidaEliminado', 'N')
            ->orderBy('Detalle_Producto_medida_factor_conversion', 'asc')
            ->get();

        $lista = [];
        foreach ($detalles as $d) {
            $um = $d->unidadMedida;
            $lista[] = [
                'unidad_id'     => $d->Detalle_Producto_medida_unidades_medidaId,
                'descripcion'   => $um?->unidades_medidaDescripcionUnidades ?? 'Unidad',
                'abreviatura'   => $um?->unidades_medidaAbreviatura ?? 'UND',
                'factor'        => (int) ($d->Detalle_Producto_medida_factor_conversion ?? 1),
                'precio_venta'  => (float) ($d->Detalle_Producto_medida_precio_venta ?? 0.0),
                'precio_compra' => (float) ($d->Detalle_Producto_medida_precio_compra ?? 0.0),
                'es_base'       => ((int) $d->Detalle_Producto_medida_factor_conversion === 1),
            ];
        }

        if (empty($lista)) {
            $lista[] = [
                'unidad_id'     => 'UND-00001',
                'descripcion'   => 'Unidad',
                'abreviatura'   => 'UND',
                'factor'        => 1,
                'precio_venta'  => 0.0,
                'precio_compra' => 0.0,
                'es_base'       => true,
            ];
        }

        return $lista;
    }

    /**
     * Resolver la presentación: si se detectó "caja"/"paquete" intenta buscarla;
     * de lo contrario, asigna silenciosamente la unidad base (factor 1).
     */
    protected function resolverPresentacion(array $presentaciones, ?string $presentacionTexto): array
    {
        if (empty($presentaciones)) {
            return [
                'unidad_id'    => 'UND-00001',
                'descripcion'  => 'Unidad',
                'factor'       => 1,
                'precio_venta' => 0.0,
            ];
        }

        // Si se especificó una presentación en el texto (ej: "paquete", "caja", "sixpack")
        if (!empty($presentacionTexto)) {
            $pNorm = $this->normalizarTexto($presentacionTexto);
            foreach ($presentaciones as $p) {
                $descNorm = $this->normalizarTexto($p['descripcion'] . ' ' . $p['abreviatura']);
                if (str_contains($descNorm, $pNorm) || str_contains($pNorm, $descNorm)) {
                    return $p;
                }
            }
        }

        // Fallback por defecto: unidad base (factor 1)
        foreach ($presentaciones as $p) {
            if ($p['es_base'] || $p['factor'] === 1) {
                return $p;
            }
        }

        return $presentaciones[0];
    }

    /**
     * Normalizar cadenas de texto (minúsculas, sin tildes, sin signos raros)
     */
    protected function normalizarTexto(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $str = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $str
        );
        $str = preg_replace('/[^a-z0-9\s]/', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }
}
