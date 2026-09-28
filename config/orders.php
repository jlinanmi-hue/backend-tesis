<?php

return [
    'timeout_hours' => (int) env('ORDER_TIMEOUT_HOURS', 12),
    'expiring_threshold_hours' => 2,
    'default_canal_id' => 'CNL-00001', // Tienda Presencial
];
