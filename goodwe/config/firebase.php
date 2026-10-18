<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase / Firestore
    |--------------------------------------------------------------------------
    |
    | Os valores ficam aqui (e não em env() dentro do código) para continuarem
    | disponíveis quando a configuração está em cache — `config:cache` não
    | carrega o .env em runtime, então env() fora de arquivos de config
    | retornaria null.
    |
    */

    'projects' => [
        'app' => [
            'project_id' => env('FIREBASE_PROJECT_ID', 'powertag--contas'),

            // Caminho para o arquivo de credenciais (Service Account).
            'credentials' => env('FIREBASE_CREDENTIALS')
                ?? env('GOOGLE_APPLICATION_CREDENTIALS'),

            // Conteúdo do JSON (ou base64 dele) usado para reconstruir o
            // arquivo de credenciais em produção, onde ele não é versionado.
            'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
        ],
    ],

];
