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
    | `status_cache_seconds` cobre o endpoint consultado pelo polling da tela
    | de recarga (a cada 2s): um valor baixo colapsa chamadas repetidas do
    | mesmo totem sem atrasar a troca de estado percebida pelo usuário.
    |
    | `user_cache_seconds` cobre o documento da TAG lido na abertura da tela
    | (saldo/status inicial), invalidado a cada escrita.
    |
    | Valores menores ou iguais a zero desativam o cache.
    |
    */

    'status_cache_seconds' => (int) env('TOTEMS_STATUS_CACHE_SECONDS', 1),

    'user_cache_seconds' => (int) env('TOTEMS_USER_CACHE_SECONDS', 10),
];
