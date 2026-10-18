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
            'name' => 'Totem 01',
            'location' => 'Entrada principal',
        ],
        [
            'id' => 'totem_02',
            'name' => 'Totem 02',
            'location' => 'Estacionamento',
        ],
    ],
];
