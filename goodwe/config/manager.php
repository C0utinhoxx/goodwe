<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache do painel do gestor
    |--------------------------------------------------------------------------
    |
    | Duração (em segundos) do cache dos indicadores agregados do dashboard
    | (totais, uso diário e distribuições), que varrem todas as sessões do
    | estabelecimento a cada abertura da página.
    |
    | Valores menores ou iguais a zero desativam o cache.
    |
    */

    'dashboard_cache_seconds' => (int) env('MANAGER_DASHBOARD_CACHE_SECONDS', 60),
];
