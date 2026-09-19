<?php

declare(strict_types=1);

return [
    'admin_cache_ttl_seconds' => (int) env('ADMIN_DASHBOARD_CACHE_TTL_SECONDS', 300),
    'admin_kpi_cache_key' => 'dashboard.admin.kpi',
    'admin_completion_rate_cache_key' => 'dashboard.admin.completion-rate',
];
