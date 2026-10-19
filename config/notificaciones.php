<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Motivos predefinidos para el envío manual de notificaciones
    |--------------------------------------------------------------------------
    |
    | Cada motivo precarga un título y una plantilla de mensaje en el
    | compositor del frontend. El backend también los usa como respaldo
    | cuando el título o el mensaje llegan vacíos.
    |
    */
    'motivos' => [
        'VENTA_BAJA' => [
            'label' => 'Venta baja',
            'titulo' => 'Alerta de ventas',
            'plantilla' => 'No has registrado ventas hoy. Por favor, reporta tu actividad.',
        ],
        'APERTURA_TIENDA' => [
            'label' => 'Apertura de tienda',
            'titulo' => 'Apertura de tienda',
            'plantilla' => 'La tienda abre a las 8:00. Preséntate puntual.',
        ],
        'CIERRE_CAJA' => [
            'label' => 'Cierre de caja',
            'titulo' => 'Recordatorio de cierre',
            'plantilla' => 'Recuerda cerrar caja antes de las 21:00.',
        ],
        'STOCK_CRITICO' => [
            'label' => 'Stock crítico',
            'titulo' => 'Revisión de inventario',
            'plantilla' => 'Revisa el inventario asignado y reporta faltantes.',
        ],
        'GENERAL' => [
            'label' => 'Mensaje general',
            'titulo' => '',
            'plantilla' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prioridades permitidas
    |--------------------------------------------------------------------------
    */
    'prioridades' => ['NORMAL', 'ALTA', 'URGENTE'],

    /*
    |--------------------------------------------------------------------------
    | Roles autorizados para enviar notificaciones manuales
    |--------------------------------------------------------------------------
    |
    | El proyecto aún no tiene un usuario con rol JEFE_TIENDA asignado, por
    | eso se habilita también a ADMINISTRADOR. Ajustar aquí cuando se cree
    | el primer Jefe de Tienda.
    |
    */
    'roles_autorizados' => ['ADMINISTRADOR', 'JEFE_TIENDA'],

];
