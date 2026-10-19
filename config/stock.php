<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Parámetros del Modelo de Alertas y Lote Económico de Compra (EOQ)
    |--------------------------------------------------------------------------
    |
    | Define las constantes para la detección de zonas de alerta de inventario
    | y el cálculo óptimo de reposición según el modelo Wilson / EOQ.
    |
    */

    // Factor multiplicador sobre el stock mínimo para entrar en zona de ALERTA
    // Ejemplo: stock_minimo = 10 -> Alerta si stock <= 10 * 1.3 = 13 unidades
    'factor_alerta' => (float) env('STOCK_FACTOR_ALERTA', 1.3),

    // Costo fijo estimado de emisión por orden de compra (S en fórmula EOQ)
    'costo_orden' => (float) env('STOCK_COSTO_ORDEN', 50.0),

    // Tasa anual de costo de almacenamiento sobre el precio de compra (H = PrecioCompra * tasa)
    // 10% por defecto anual
    'tasa_almacenamiento' => (float) env('STOCK_TASA_ALMACENAMIENTO', 0.10),

    // Ventana de días históricos para analizar la demanda de salidas (Kárdex tipo 'S')
    'dias_analisis_demanda' => (int) env('STOCK_DIAS_ANALISIS_DEMANDA', 90),

    // Factor para anualizar la demanda: 90 días * 4 = 360 días operativos
    'factor_anualizacion' => (int) env('STOCK_FACTOR_ANUALIZACION', 4),

    // Período de enfriamiento anti-spam (horas) para no repetir alertas del mismo producto
    'cooldown_horas' => (int) env('STOCK_COOLDOWN_HORAS', 24),
];
