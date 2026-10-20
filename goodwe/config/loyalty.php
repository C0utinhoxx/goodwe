<?php

return [
    // Percentual do valor depositado convertido em pontos de fidelidade.
    'deposit_percent' => 5,
    'coupons' => [
        'bronze' => ['name' => 'Bronze', 'points' => '10.00', 'percent' => 5],
        'prata' => ['name' => 'Prata', 'points' => '25.00', 'percent' => 10],
        'ouro' => ['name' => 'Ouro', 'points' => '50.00', 'percent' => 15],
    ],
    'simulation' => ['cents_per_minute' => 65, 'energy_hundredths_per_minute' => 37, 'cents_per_kwh' => 190],
];
