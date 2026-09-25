<?php

namespace App\Http\Controllers;

use App\Services\ChargingSimulator;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserChatbotController extends Controller
{
    public function __construct(
        private WalletService $walletService,
    ) {}

    public function page(Request $request)
    {
        return view('user.chatbot', [
            'history' => session('user_chat_history', []),
        ]);
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $locale = app()->getLocale();

        /*
        |--------------------------------------------------------------------------
        | Perfil do usuário
        |--------------------------------------------------------------------------
        */

        $userName = $user->name ?? 'Não informado';
        $userEmail = $user->email ?? 'Não informado';
        $userPhone = $user->phone ?? 'Não informado';
        $userBalance = (float) ($user->balance ?? 0);
        $userPoints = (int) ($user->points ?? 0);

        /*
        |--------------------------------------------------------------------------
        | Histórico da carteira
        |--------------------------------------------------------------------------
        */

        $transactions = $this->walletService
            ->getHistory($user)
            ->take(10);

        if ($transactions->isEmpty()) {
            $transactionsText =
                'Nenhuma transação registrada.';
        } else {
            $transactionsText = '';

            foreach ($transactions as $transaction) {
                $date = $transaction->created_at
                    ? $transaction->created_at->format('d/m/Y H:i')
                    : 'Data não disponível';

                $transactionsText .=
                    "- Data: {$date}\n".
                    "  Tipo: {$transaction->type}\n".
                    "  Valor: R$ {$transaction->amount}\n".
                    "  Descrição: {$transaction->description}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Recargas recentes
        |--------------------------------------------------------------------------
        */

        $recentSessions =
            $this->walletService->getRecentSessions($user);

        if ($recentSessions->isEmpty()) {
            $sessionsText =
                'Nenhuma recarga registrada.';
        } else {
            $sessionsText = '';

            foreach ($recentSessions as $session) {
                $date = $session->created_at
                    ? $session->created_at->format('d/m/Y H:i')
                    : 'Data não disponível';

                $sessionsText .=
                    "- Data: {$date}\n".
                    "  Carregador: {$session->charger_name}\n".
                    "  Energia: {$session->energy_kwh} kWh\n".
                    "  Valor: R$ {$session->amount_charged}\n".
                    "  Pagamento: {$session->payment_method}\n".
                    "  Status: {$session->status}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Carregadores
        |--------------------------------------------------------------------------
        */

        $chargers = ChargingSimulator::all();

        $totalChargers = count($chargers);
        $availableChargers = 0;
        $occupiedChargers = 0;
        $chargersText = '';

        foreach ($chargers as $charger) {
            $status = $charger->status();

            if (
                $status ===
                $charger::STATUS_AVAILABLE
            ) {
                $availableChargers++;
            } else {
                $occupiedChargers++;
            }

            $chargersText .=
                "- Código: {$charger->id}\n".
                "  Nome: {$charger->name}\n".
                "  Localização: {$charger->location}\n".
                "  Potência: {$charger->powerKw} kW\n".
                "  Conector: {$charger->connector}\n".
                "  Status: {$status}\n";

            if (
                $status ===
                $charger::STATUS_OCCUPIED
            ) {
                $vehiclePlate =
                    $charger->vehiclePlate
                    ?? 'Não informada';

                $availableIn =
                    $charger->availableInMinutes();

                $minutesText =
                    $availableIn !== null
                    ? "{$availableIn} minutos"
                    : 'Não disponível';

                $chargersText .=
                    "  Placa: {$vehiclePlate}\n".
                    "  Disponível em: {$minutesText}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Idioma
        |--------------------------------------------------------------------------
        */

        $languageInstruction = match ($locale) {
            'en' => 'Always respond in English, regardless of the language used by the user.',

            'es' => 'Responde siempre en español, independientemente del idioma utilizado por el usuario.',

            default => 'Responda sempre em português do Brasil, independentemente do idioma utilizado pelo usuário.',
        };

        /*
        |--------------------------------------------------------------------------
        | Contexto da IA
        |--------------------------------------------------------------------------
        */

        $context = "
Você é o Assistente GoodWe, o assistente virtual da plataforma GoodWe.

{$languageInstruction}

Responda de forma simples, objetiva e profissional.

Você auxilia o usuário a consultar informações sobre o próprio perfil, carteira, pontos, histórico de recargas e carregadores.

==================================================
PERFIL DO USUÁRIO AUTENTICADO
==================================================

Nome: {$userName}
E-mail: {$userEmail}
Telefone: {$userPhone}
Saldo da carteira: R$ ".
            number_format(
                $userBalance,
                2,
                ',',
                '.'
            )."
Pontos: {$userPoints}

==================================================
TRANSAÇÕES RECENTES DO USUÁRIO
==================================================

{$transactionsText}

==================================================
RECARGAS RECENTES DO USUÁRIO
==================================================

{$sessionsText}

==================================================
CARREGADORES DA SIMULAÇÃO
==================================================

Total de carregadores: {$totalChargers}
Disponíveis agora: {$availableChargers}
Ocupados agora: {$occupiedChargers}

{$chargersText}

==================================================
REGRAS
==================================================

- Use somente os dados fornecidos neste contexto.
- Não invente números ou informações.
- Não altere os valores fornecidos pelo sistema.
- Se uma informação não estiver disponível, informe isso claramente.
- Quando o usuário disser 'meu', 'minha', 'eu' ou 'me', considere que ele está falando do usuário autenticado.
- Você pode informar nome, e-mail, telefone, saldo e pontos do próprio usuário.
- Você pode informar as transações e recargas do próprio usuário apresentadas neste contexto.
- Nunca invente ou forneça senha, token, chave de API ou credenciais de autenticação.
- Não forneça informações administrativas do gestor.
- Não forneça receita do estabelecimento.
- Não forneça informações pessoais de outros usuários.
- Os carregadores apresentados pertencem à simulação atual do sistema.
- O status dos carregadores representa o estado atual da simulação.
- Considere o histórico da conversa para entender perguntas de continuação.
- Se perguntarem quais carregadores estão disponíveis, informe somente os disponíveis.
- Se perguntarem quais carregadores estão ocupados, informe somente os ocupados.
- O histórico pode conter mensagens em idiomas diferentes.
- Entenda normalmente mensagens anteriores mesmo que estejam em outro idioma.
- Responda sempre no idioma atualmente selecionado no sistema.
";

        /*
        |--------------------------------------------------------------------------
        | Histórico da conversa
        |--------------------------------------------------------------------------
        */

        $history = session(
            'user_chat_history',
            []
        );

        $input = [];

        foreach ($history as $message) {
            $input[] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        $input[] = [
            'role' => 'user',
            'content' => $request->message,
        ];

        /*
        |--------------------------------------------------------------------------
        | OpenAI
        |--------------------------------------------------------------------------
        */

        try {
            $response = Http::withToken(
                config('services.openai.key')
            )
                ->timeout(60)
                ->post(
                    'https://api.openai.com/v1/responses',
                    [
                        'model' => 'gpt-5-mini',
                        'instructions' => $context,
                        'input' => $input,
                    ]
                );

            if ($response->failed()) {
                Log::error('User chatbot: OpenAI request failed.', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                    'has_key' => filled(config('services.openai.key')),
                ]);

                $errorMessage = match ($locale) {
                    'en' => 'The assistant is unavailable right now.',

                    'es' => 'No fue posible consultar el asistente en este momento.',

                    default => 'Não foi possível consultar o assistente agora.',
                };

                return response()->json([
                    'answer' => $errorMessage,
                ], 500);
            }

            $data = $response->json();

            $answerItem = collect(
                $data['output'] ?? []
            )
                ->flatMap(
                    fn ($item) => $item['content'] ?? []
                )
                ->firstWhere(
                    'type',
                    'output_text'
                );

            $fallbackMessage = match ($locale) {
                'en' => 'I could not generate a response.',

                'es' => 'No pude generar una respuesta.',

                default => 'Não consegui gerar uma resposta.',
            };

            $answer =
                $answerItem['text']
                ?? $fallbackMessage;

        } catch (\Throwable $e) {
            Log::error('User chatbot: unexpected error while calling OpenAI.', [
                'message' => $e->getMessage(),
            ]);

            $errorMessage = match ($locale) {
                'en' => 'The assistant is unavailable right now.',

                'es' => 'No fue posible consultar el asistente en este momento.',

                default => 'Não foi possível consultar o assistente agora.',
            };

            return response()->json([
                'answer' => $errorMessage,
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Salvar histórico
        |--------------------------------------------------------------------------
        */

        $history[] = [
            'role' => 'user',
            'content' => $request->message,
        ];

        $history[] = [
            'role' => 'assistant',
            'content' => $answer,
        ];

        session([
            'user_chat_history' => $history,
        ]);

        return response()->json([
            'answer' => $answer,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Nova conversa
    |--------------------------------------------------------------------------
    */

    public function clear(Request $request)
    {
        $request->session()->forget(
            'user_chat_history'
        );

        return response()->json([
            'success' => true,
        ]);
    }
}
