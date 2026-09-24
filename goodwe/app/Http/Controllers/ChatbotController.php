<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChargingSimulator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function page(Request $request)
    {
        return view('manager.chatbot', [
            'history' => session('manager_chat_history', []),
        ]);
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $establishment = $user->establishment;
        $locale = app()->getLocale();

        if (! $establishment) {
            $message = match ($locale) {
                'en' => 'No establishment was found for this manager.',
                'es' => 'No se encontró ningún establecimiento para este gestor.',
                default => 'Nenhum estabelecimento foi encontrado para este gestor.',
            };

            return response()->json([
                'answer' => $message,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Perfil do gestor
        |--------------------------------------------------------------------------
        */

        $managerName = $user->name ?? 'Não informado';
        $managerEmail = $user->email ?? 'Não informado';
        $managerPhone = $user->phone ?? 'Não informado';
        $managerBalance = (float) ($user->balance ?? 0);
        $managerPoints = (int) ($user->points ?? 0);
        $managerType = $user->isGestor()
            ? 'Gestor'
            : 'Usuário';

        /*
        |--------------------------------------------------------------------------
        | Dados das sessões
        |--------------------------------------------------------------------------
        */

        $sessions = ChargingSession::where(
            'establishment_id',
            $establishment->id
        );

        $totalSessions = $sessions->clone()->count();

        $completedSessions = $sessions->clone()
            ->where(
                'status',
                ChargingSession::STATUS_COMPLETED
            )
            ->count();

        $pendingSessions = $sessions->clone()
            ->where(
                'status',
                ChargingSession::STATUS_PENDING
            )
            ->count();

        $cancelledSessions = $sessions->clone()
            ->where(
                'status',
                ChargingSession::STATUS_CANCELLED
            )
            ->count();

        $totalRevenue = (float) $sessions->clone()
            ->where(
                'status',
                ChargingSession::STATUS_COMPLETED
            )
            ->sum('amount_charged');

        $totalEnergy = (float) $sessions->clone()
            ->where(
                'status',
                ChargingSession::STATUS_COMPLETED
            )
            ->sum('energy_kwh');

        $averageRevenue = $completedSessions > 0
            ? $totalRevenue / $completedSessions
            : 0;

        $averageEnergy = $completedSessions > 0
            ? $totalEnergy / $completedSessions
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Sessões recentes
        |--------------------------------------------------------------------------
        */

        $recentSessions = ChargingSession::where(
            'establishment_id',
            $establishment->id
        )
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get();

        if ($recentSessions->isEmpty()) {
            $recentSessionsText =
                'Nenhuma sessão registrada.';
        } else {
            $recentSessionsText = '';

            foreach ($recentSessions as $session) {
                $sessionUserName =
                    $session->user?->name
                    ?? 'Usuário não identificado';

                $date = $session->created_at
                    ? $session->created_at->format(
                        'd/m/Y H:i'
                    )
                    : 'Data não disponível';

                $recentSessionsText .=
                    "- Data: {$date}\n" .
                    "  Usuário: {$sessionUserName}\n" .
                    "  Carregador: {$session->charger_name}\n" .
                    "  Energia: {$session->energy_kwh} kWh\n" .
                    "  Valor: R$ {$session->amount_charged}\n" .
                    "  Pagamento: {$session->payment_method}\n" .
                    "  Status: {$session->status}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Usuários cadastrados
        |--------------------------------------------------------------------------
        */

        $users = User::query()
            ->withCount([
                'chargingSessions',
                'transactions',
            ])
            ->orderBy('name')
            ->get();

        $totalUsers = $users->count();
        $totalManagers = $users
            ->where('is_admin', true)
            ->count();
        $totalRegularUsers = $totalUsers - $totalManagers;

        $usersText = '';

        if ($users->isEmpty()) {
            $usersText = 'Nenhum usuário cadastrado.';
        } else {
            foreach ($users as $registeredUser) {
                $accountType = $registeredUser->isGestor()
                    ? 'Gestor'
                    : 'Usuário';

                $phone = $registeredUser->phone
                    ?? 'Não informado';

                $balance = number_format(
                    (float) $registeredUser->balance,
                    2,
                    ',',
                    '.'
                );

                $usersText .=
                    "- ID: {$registeredUser->id}\n" .
                    "  Nome: {$registeredUser->name}\n" .
                    "  E-mail: {$registeredUser->email}\n" .
                    "  Telefone: {$phone}\n" .
                    "  Tipo de conta: {$accountType}\n" .
                    "  Saldo: R$ {$balance}\n" .
                    "  Pontos: {$registeredUser->points}\n" .
                    "  Total de recargas: {$registeredUser->charging_sessions_count}\n" .
                    "  Total de transações: {$registeredUser->transactions_count}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Transações recentes dos usuários
        |--------------------------------------------------------------------------
        */

        $recentTransactions = Transaction::query()
            ->with('user:id,name,email')
            ->latest()
            ->take(20)
            ->get();

        if ($recentTransactions->isEmpty()) {
            $transactionsText =
                'Nenhuma transação registrada.';
        } else {
            $transactionsText = '';

            foreach ($recentTransactions as $transaction) {
                $transactionUserName =
                    $transaction->user?->name
                    ?? 'Usuário não identificado';

                $transactionUserEmail =
                    $transaction->user?->email
                    ?? 'E-mail não disponível';

                $date = $transaction->created_at
                    ? $transaction->created_at->format(
                        'd/m/Y H:i'
                    )
                    : 'Data não disponível';

                $amount = number_format(
                    (float) $transaction->amount,
                    2,
                    ',',
                    '.'
                );

                $reference =
                    $transaction->reference
                    ?? 'Não informada';

                $transactionsText .=
                    "- Data: {$date}\n" .
                    "  Usuário: {$transactionUserName}\n" .
                    "  E-mail: {$transactionUserEmail}\n" .
                    "  Tipo: {$transaction->type}\n" .
                    "  Valor: R$ {$amount}\n" .
                    "  Descrição: {$transaction->description}\n" .
                    "  Referência: {$reference}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Recargas recentes de todos os usuários
        |--------------------------------------------------------------------------
        */

        $allRecentSessions = ChargingSession::query()
            ->with('user:id,name,email')
            ->latest()
            ->take(20)
            ->get();

        if ($allRecentSessions->isEmpty()) {
            $allRecentSessionsText =
                'Nenhuma recarga registrada.';
        } else {
            $allRecentSessionsText = '';

            foreach ($allRecentSessions as $session) {
                $sessionUserName =
                    $session->user?->name
                    ?? 'Usuário não identificado';

                $sessionUserEmail =
                    $session->user?->email
                    ?? 'E-mail não disponível';

                $date = $session->created_at
                    ? $session->created_at->format(
                        'd/m/Y H:i'
                    )
                    : 'Data não disponível';

                $allRecentSessionsText .=
                    "- Data: {$date}\n" .
                    "  Usuário: {$sessionUserName}\n" .
                    "  E-mail: {$sessionUserEmail}\n" .
                    "  Carregador: {$session->charger_name}\n" .
                    "  Energia: {$session->energy_kwh} kWh\n" .
                    "  Valor: R$ {$session->amount_charged}\n" .
                    "  Pagamento: {$session->payment_method}\n" .
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
                "- Código: {$charger->id}\n" .
                "  Nome: {$charger->name}\n" .
                "  Localização: {$charger->location}\n" .
                "  Potência: {$charger->powerKw} kW\n" .
                "  Conector: {$charger->connector}\n" .
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
                    "  Placa: {$vehiclePlate}\n" .
                    "  Disponível em: {$minutesText}\n";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Idioma
        |--------------------------------------------------------------------------
        */

        $languageInstruction = match ($locale) {
            'en' =>
                'Always respond in English, regardless of the language used by the user.',

            'es' =>
                'Responde siempre en español, independientemente del idioma utilizado por el usuario.',

            default =>
                'Responda sempre em português do Brasil, independentemente do idioma utilizado pelo usuário.',
        };

        /*
        |--------------------------------------------------------------------------
        | Contexto da IA
        |--------------------------------------------------------------------------
        */

        $context = "
Você é o Assistente GoodWe, o assistente operacional da plataforma GoodWe.

{$languageInstruction}

Responda de forma simples, objetiva e profissional.

Você auxilia o gestor a consultar informações do próprio perfil, do estabelecimento, dos usuários cadastrados, das transações, das recargas e dos carregadores.

==================================================
PERFIL DO GESTOR AUTENTICADO
==================================================

Nome: {$managerName}
E-mail: {$managerEmail}
Telefone: {$managerPhone}
Saldo da carteira: R$ " .
            number_format(
                $managerBalance,
                2,
                ',',
                '.'
            ) . "
Pontos: {$managerPoints}
Tipo de conta: {$managerType}

==================================================
ESTABELECIMENTO
==================================================

Nome: {$establishment->name}
Endereço: {$establishment->address}
Tipo: {$establishment->type}
Horário de funcionamento: {$establishment->operating_hours}
Telefone: " . ($establishment->phone ?? 'Não informado') . "
Custo por kWh: R$ {$establishment->cost_per_kwh}

==================================================
RESUMO DAS RECARGAS
==================================================

Total de sessões: {$totalSessions}
Sessões concluídas: {$completedSessions}
Sessões pendentes: {$pendingSessions}
Sessões canceladas: {$cancelledSessions}

Receita total das sessões concluídas: R$ " .
            number_format(
                $totalRevenue,
                2,
                ',',
                '.'
            ) . "

Energia total consumida nas sessões concluídas: " .
            number_format(
                $totalEnergy,
                2,
                ',',
                '.'
            ) . " kWh

Valor médio por sessão concluída: R$ " .
            number_format(
                $averageRevenue,
                2,
                ',',
                '.'
            ) . "

Energia média por sessão concluída: " .
            number_format(
                $averageEnergy,
                2,
                ',',
                '.'
            ) . " kWh

==================================================
ÚLTIMAS SESSÕES
==================================================

{$recentSessionsText}

==================================================
USUÁRIOS CADASTRADOS
==================================================

Total de contas cadastradas: {$totalUsers}
Usuários comuns: {$totalRegularUsers}
Gestores: {$totalManagers}

{$usersText}

==================================================
TRANSAÇÕES RECENTES DOS USUÁRIOS
==================================================

{$transactionsText}

==================================================
RECARGAS RECENTES DE TODOS OS USUÁRIOS
==================================================

{$allRecentSessionsText}

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
- Quando o gestor perguntar 'meu', 'minha', 'eu' ou 'me', considere que ele está falando do perfil do gestor autenticado.
- Você pode informar nome, e-mail, telefone, saldo, pontos e tipo de conta do gestor autenticado.
- Você pode consultar os usuários cadastrados apresentados neste contexto.
- Você pode informar nome, e-mail, telefone, saldo, pontos, tipo de conta, quantidade de recargas e quantidade de transações dos usuários apresentados no contexto.
- Você pode utilizar as transações recentes e as recargas recentes apresentadas no contexto para responder perguntas do gestor.
- Ao procurar um usuário pelo nome, compare com os nomes apresentados no contexto.
- Se houver mais de um usuário compatível com o nome informado, peça ao gestor para especificar qual deles.
- Nunca forneça senha, hash de senha, token, chave de API, código de recuperação, segredo de autenticação ou qualquer outra credencial.
- Nunca diga que não sabe o nome do gestor quando o nome estiver disponível no contexto.
- Nunca invente ou forneça senha, token, chave de API ou qualquer credencial de autenticação.
- Diferencie os dados do perfil pessoal dos dados do estabelecimento.
- Diferencie dados históricos das sessões dos dados atuais dos carregadores.
- Os carregadores pertencem à simulação atual do sistema.
- As sessões são registros armazenados no banco de dados.
- Receita e energia total consideram somente sessões concluídas.
- Considere o histórico para entender perguntas de continuação.
- Se perguntarem pelos carregadores disponíveis, informe somente os disponíveis.
- Se perguntarem pelos carregadores ocupados, informe somente os ocupados.
- Se perguntarem sobre sessões recentes, utilize a lista de últimas sessões.
- O histórico pode conter mensagens em idiomas diferentes.
- Entenda normalmente mensagens anteriores mesmo que estejam em outro idioma.
- Responda sempre no idioma atualmente selecionado no sistema.
";

        /*
        |--------------------------------------------------------------------------
        | Histórico
        |--------------------------------------------------------------------------
        */

        $history = session(
            'manager_chat_history',
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
                $errorMessage = match ($locale) {
                    'en' =>
                        'The assistant is unavailable right now.',

                    'es' =>
                        'No fue posible consultar el asistente en este momento.',

                    default =>
                        'Não foi possível consultar o assistente agora.',
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
                    fn ($item) =>
                        $item['content'] ?? []
                )
                ->firstWhere(
                    'type',
                    'output_text'
                );

            $fallbackMessage = match ($locale) {
                'en' =>
                    'I could not generate a response.',

                'es' =>
                    'No pude generar una respuesta.',

                default =>
                    'Não consegui gerar uma resposta.',
            };

            $answer =
                $answerItem['text']
                ?? $fallbackMessage;

        } catch (\Throwable $e) {
            $errorMessage = match ($locale) {
                'en' =>
                    'The assistant is unavailable right now.',

                'es' =>
                    'No fue posible consultar el asistente en este momento.',

                default =>
                    'Não foi possível consultar o assistente agora.',
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
            'manager_chat_history' => $history,
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
            'manager_chat_history'
        );

        return response()->json([
            'success' => true,
        ]);
    }
}