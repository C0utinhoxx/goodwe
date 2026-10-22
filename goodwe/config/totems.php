<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Totens de recarga disponíveis
    |--------------------------------------------------------------------------
    |
    | Lista exibida na página "Recarga" (menu principal) e usada para montar
    | os atalhos de controle remoto. O `id` é o id do documento na coleção
    | `totems` do Firestore — o mesmo valor que o ESP32 observa em
    | `comando_web` para iniciar/parar a recarga.
    |
    | Basta adicionar/remover itens para que a página reflita a rede real.
    |
    */

    'available' => [
        [
            'id' => 'totem_01',
            'name' => 'Lab Goodwe 01',
            'location' => 'Entrada principal',
        ],
        [
            'id' => 'totem_02',
            'name' => 'Lab Goodwe 02',
            'location' => 'Estacionamento',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache das leituras do Firestore
    |--------------------------------------------------------------------------
    |
    | O endpoint de status é consultado pelo polling da tela de recarga.
    | O padrão é zero (leitura sempre atual): o token OAuth agora é
    | compartilhado entre requisições, então cada consulta custa apenas um
    | round-trip curto ao Firestore. Aumente o valor se houver muitas abas
    | abertas no mesmo totem e for necessário reduzir o consumo de quota.
    |
    | Valores menores ou iguais a zero desativam o cache.
    |
    */

    'status_cache_seconds' => (int) env('TOTEMS_STATUS_CACHE_SECONDS', 0),

    /*
    |--------------------------------------------------------------------------
    | Intervalo do polling na tela de recarga
    |--------------------------------------------------------------------------
    |
    | `polling_interval_ms` é usado enquanto o equipamento ainda está
    | processando um comando (iniciar, aguardando PIN, parar). O intervalo
    | ocioso evita consultas desnecessárias durante uma recarga estável.
    |
    */

    'polling_interval_ms' => (int) env('TOTEMS_POLLING_INTERVAL_MS', 1000),

    'idle_polling_interval_ms' => (int) env('TOTEMS_IDLE_POLLING_INTERVAL_MS', 4000),
];
