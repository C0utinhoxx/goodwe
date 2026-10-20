<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Experição de reservas no caminho de requisição
    |--------------------------------------------------------------------------
    |
    | O portal dispara a expiração de reservas vencidas ao abrir a home e a
    | lista de carregadores, porque o agendador (charging:expire-reservations)
    | nem sempre roda em produção. Este valor limita a frequência da varredura:
    | uma visita expira as vencidas e as próximas, dentro do intervalo, são
    | ignoradas.
    |
    | Valores menores ou iguais a zero desativam o limite (varredura a cada
    | visita, como era antes).
    |
    */

    'expire_guard_seconds' => (int) env('RESERVATIONS_EXPIRE_GUARD_SECONDS', 30),
];
