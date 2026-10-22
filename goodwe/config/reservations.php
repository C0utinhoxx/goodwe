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

    /*
    |--------------------------------------------------------------------------
    | Taxa de reserva
    |--------------------------------------------------------------------------
    |
    | A taxa é cobrada do saldo no ato da confirmação e nunca é devolvida.
    | Valor-base = tarifa vigente (R$/kWh) × duração média das últimas
    | recargas do estabelecimento (horas) × potência do carregador (kW) —
    | ou seja, quanto custaria manter o carro carregando pelo tempo médio.
    |
    | Sobre a base aplica-se o percentual do dia: de segunda a quinta
    | (`fee_percent_weekday`), de sexta a domingo (`fee_percent_weekend`,
    | dias `fee_weekend_days`, onde 0 = domingo e 5 = sexta).
    |
    | `fee_default_average_minutes` é usado quando o estabelecimento ainda
    | não possui recargas medidas; `fee_sample_size` limita quantas sessões
    | entram na média e `fee_average_cache_seconds` por quanto tempo ela
    | fica em cache (zero desativa o cache).
    |
    */

    'fee_enabled' => (bool) env('RESERVATION_FEE_ENABLED', true),
    'fee_percent_weekday' => (int) env('RESERVATION_FEE_WEEKDAY_PERCENT', 10),
    'fee_percent_weekend' => (int) env('RESERVATION_FEE_WEEKEND_PERCENT', 20),
    'fee_weekend_days' => [5, 6, 0],
    'fee_default_average_minutes' => (int) env('RESERVATION_FEE_DEFAULT_AVERAGE_MINUTES', 60),
    'fee_sample_size' => (int) env('RESERVATION_FEE_SAMPLE_SIZE', 50),
    'fee_average_cache_seconds' => (int) env('RESERVATION_FEE_AVERAGE_CACHE_SECONDS', 300),
];
