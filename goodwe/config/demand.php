<?php

return [
    // Set only after implementing a detector that validates the team's agreed criteria.
    'detector' => null,

    /*
    |--------------------------------------------------------------------------
    | Controle de demanda elétrica
    |--------------------------------------------------------------------------
    |
    | Janela (em minutos) considerada para que uma leitura reportada por um
    | totem ainda seja tratada como consumo ativo. Leituras mais antigas que a
    | janela são ignoradas na agregação da demanda.
    |
    | Tensão nominal (V) e número de fases usados para estimar a corrente ativa
    | (A) de um carregador a partir da sua potência nominal (kW), quando o
    | equipamento não reporta a corrente diretamente.
    |
    */

    'readings_window_minutes' => 2,
    'voltage_v' => 220,
    'phases' => 3,

    /*
    |--------------------------------------------------------------------------
    | Simulação de distribuição de potência
    |--------------------------------------------------------------------------
    |
    | Menor potência considerada viável para um carregador no balanceamento.
    | Quando a capacidade da instalação não permite manter todos os veículos
    | acima desse valor, a simulação sinaliza que novas recargas devem ser
    | bloqueadas. O controle físico é uma integração futura.
    |
    */

    'minimum_allocation_kw' => 1.4,
];
