<?php

namespace App\Http\Controllers;

use App\Models\ChargingSession;
use App\Services\WebChargingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function page(Request $request)
    {
        return view('manager.chatbot', [
            'history' => session('manager_chat_history.scoped_v1.'.$request->user()->establishment->id, []),
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

        $currentTariff = $establishment->currentTariff();
        $managerName = $user->name ?? 'Não informado';
        $managerEmail = $user->email ?? 'Não informado';
        $managerPhone = $user->phone ?? 'Não informado';
        $managerType = $user->isGestor()
            ? 'Gestor'
            : 'Usuário';

        /*
        |--------------------------------------------------------------------------
        | Dados das sessões
        |--------------------------------------------------------------------------
        */

        $sessions = ChargingSession::where('is_demo', false)->where(
            'establishment_id',
            $establishment->id
        );

        if (! WebChargingService::available()) {
            $sessions->whereNull('simulation');
        }

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

        $recentSessions = (clone $sessions)
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
                    "- Data: {$date}\n".
                    "  Usuário: {$sessionUserName}\n".
                    "  Carregador: {$session->charger_name}\n".
                    '  Origem: '.($session->isWebSimulation() ? 'Recarga de teste' : 'Recarga')."\n".
                    "  Energia: {$session->energy_kwh} kWh\n".
                    "  Valor: R$ {$session->amount_charged}\n".
                    "  Pagamento: {$session->payment_method}\n".
                    "  Status: {$session->status}\n";
            }
        }

        $chargers = $establishment->chargers()->where('is_demo', false)->orderBy('name')->get();
        $totalChargers = $chargers->count();
        $availableChargers = $chargers->filter(fn ($charger) => $charger->isAvailable())->count();
        $occupiedChargers = $chargers->filter(fn ($charger) => $charger->status() === 'in_use')->count();
        $chargersText = $chargers->map(fn ($charger) => "- Código: {$charger->code}\n  Nome: {$charger->name}\n  Localização: {$charger->location}\n  Potência: {$charger->power_kw} kW\n  Conector: {$charger->connector}\n  Status: {$charger->status()}"
        )->implode("\n");

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

        $context = (WebChargingService::available() ? "Os indicadores deste ambiente incluem recargas de teste, sem operação física ou pagamento externo.\n" : '')."
Você é o Assistente GoodWe, o assistente operacional da plataforma GoodWe.

{$languageInstruction}

Responda de forma simples, objetiva e profissional.

Você auxilia o gestor a consultar informações do próprio perfil, do estabelecimento, das recargas e dos carregadores vinculados exclusivamente a esse estabelecimento.

==================================================
PERFIL DO GESTOR AUTENTICADO
==================================================

Nome: {$managerName}
E-mail: {$managerEmail}
Telefone: {$managerPhone}
Tipo de conta: {$managerType}

==================================================
ESTABELECIMENTO
==================================================

Nome: {$establishment->name}
Endereço: {$establishment->address}
Tipo: {$establishment->type}
Horário de funcionamento: {$establishment->operating_hours}
Telefone: ".($establishment->phone ?? 'Não informado')."
Tarifa-base por kWh: R$ {$establishment->cost_per_kwh}
Tarifa vigente por kWh: R$ {$currentTariff}

==================================================
RESUMO DAS RECARGAS
==================================================

Total de sessões: {$totalSessions}
Sessões concluídas: {$completedSessions}
Sessões pendentes: {$pendingSessions}
Sessões canceladas: {$cancelledSessions}

Receita total das sessões concluídas: R$ ".
            number_format(
                $totalRevenue,
                2,
                ',',
                '.'
            ).'

Energia total consumida nas sessões concluídas: '.
            number_format(
                $totalEnergy,
                2,
                ',',
                '.'
            ).' kWh

Valor médio por sessão concluída: R$ '.
            number_format(
                $averageRevenue,
                2,
                ',',
                '.'
            ).'

Energia média por sessão concluída: '.
            number_format(
                $averageEnergy,
                2,
                ',',
                '.'
            )." kWh

==================================================
ÚLTIMAS SESSÕES
==================================================

{$recentSessionsText}

==================================================
CARREGADORES DO ESTABELECIMENTO
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
- Você pode informar nome, e-mail, telefone e tipo de conta do gestor autenticado.
- As sessões fornecidas pertencem somente ao estabelecimento do gestor autenticado.
- Não forneça carteiras, pontos, depósitos ou informações de outros estabelecimentos.
- Se perguntarem por dados fora deste contexto, explique que não estão disponíveis neste ambiente.
- Nunca forneça senha, hash de senha, token, chave de API, código de recuperação, segredo de autenticação ou qualquer outra credencial.
- Nunca diga que não sabe o nome do gestor quando o nome estiver disponível no contexto.
- Nunca invente ou forneça senha, token, chave de API ou qualquer credencial de autenticação.
- Diferencie os dados do perfil pessoal dos dados do estabelecimento.
- Diferencie dados históricos das sessões dos dados atuais dos carregadores.
- Os carregadores são os equipamentos cadastrados para este estabelecimento; o status corresponde ao registro no backend.
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
            'manager_chat_history.scoped_v1.'.$request->user()->establishment->id,
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
            'manager_chat_history.scoped_v1.'.$request->user()->establishment->id => $history,
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
            'manager_chat_history.scoped_v1.'.$request->user()->establishment->id
        );

        return response()->json([
            'success' => true,
        ]);
    }
}
