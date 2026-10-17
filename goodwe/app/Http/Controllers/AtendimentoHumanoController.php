<?php

namespace App\Http\Controllers;

use App\Models\AtendimentoHumano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AtendimentoHumanoController extends Controller
{
    /**
     * Verifica se o usuário autenticado é suporte.
     */
    private function ehSuporte(Request $request): bool
    {
        return (bool) ($request->user()->is_support ?? false);
    }

    /**
     * Garante que apenas o dono do ticket
     * ou o suporte possa visualizá-lo.
     */
    private function autorizarAcesso(
        Request $request,
        AtendimentoHumano $atendimento
    ): void {
        $dono = (int) $atendimento->user_id ===
                (int) $request->user()->id;

        abort_unless(
            $dono || $this->ehSuporte($request),
            403,
            'Você não tem permissão para acessar este ticket.'
        );
    }

    /**
     * Lista os tickets.
     *
     * Suporte: visualiza todos.
     * Usuário/Gestor: visualiza apenas os próprios.
     */
    public function index(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'aguardando',
                    'em_atendimento',
                    'resolvido',
                ]),
            ],
        ]);

        $query = AtendimentoHumano::query();

        if (! $this->ehSuporte($request)) {
            $query->where(
                'user_id',
                $request->user()->id
            );
        }

        if (! empty($dados['status'])) {
            $query->where('status', $dados['status']);
        }

        $tickets = $query
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json([
            'sucesso' => true,
            'tickets' => $tickets,
        ]);
    }

    /**
     * Impede a abertura direta de tickets por requisições HTTP.
     *
     * A criação de atendimentos é realizada exclusivamente pelos
     * controladores dos chatbots, no servidor, após a triagem da IA.
     *
     * Não aceitar parâmetros enviados pelo navegador como prova
     * de que a IA autorizou a abertura de um chamado.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'sucesso' => false,
            'mensagem' => 'Para abrir um ticket, explique seu problema ao Assistente GoodWe. Um chamado será criado somente quando o assunto estiver dentro do escopo da plataforma e o chatbot identificar necessidade de atendimento humano.',
        ], 403);
    }

    /**
     * Exibe um ticket e suas mensagens.
     */
    public function show(
        Request $request,
        AtendimentoHumano $atendimento
    ): JsonResponse {
        $this->autorizarAcesso($request, $atendimento);

        $mensagens = DB::table('atendimento_mensagens')
            ->leftJoin(
                'users',
                'users.id',
                '=',
                'atendimento_mensagens.remetente_id'
            )
            ->where(
                'atendimento_mensagens.atendimento_humano_id',
                $atendimento->id
            )
            ->select(
                'atendimento_mensagens.id',
                'atendimento_mensagens.remetente_id',
                'users.name as remetente_nome',
                'atendimento_mensagens.mensagem',
                'atendimento_mensagens.created_at'
            )
            ->orderBy('atendimento_mensagens.id')
            ->get();

        return response()->json([
            'sucesso' => true,
            'ticket' => $atendimento,
            'mensagem_inicial' => $atendimento->mensagem,
            'mensagens' => $mensagens,
        ]);
    }

    /**
     * Envia uma mensagem dentro do ticket.
     *
     * Permite que o usuário e o suporte
     * conversem no mesmo atendimento.
     */
    public function responder(
        Request $request,
        AtendimentoHumano $atendimento
    ): JsonResponse {
        $this->autorizarAcesso($request, $atendimento);

        $dados = $request->validate([
            'mensagem' => 'required|string|max:10000',
        ]);

        $ehSuporte = $this->ehSuporte($request);

        $resultado = DB::transaction(function () use (
            $request,
            $atendimento,
            $dados,
            $ehSuporte
        ) {
            $ticket = AtendimentoHumano::query()
                ->whereKey($atendimento->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($ticket->status === 'resolvido') {
                return false;
            }

            DB::table('atendimento_mensagens')->insert([
                'atendimento_humano_id' => $ticket->id,
                'remetente_id' => $request->user()->id,
                'mensagem' => $dados['mensagem'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $ticket->update([
                'status' => $ehSuporte
                    ? 'em_atendimento'
                    : 'aguardando',
            ]);

            return true;
        });

        if (! $resultado) {
            return response()->json([
                'sucesso' => false,
                'mensagem' => 'Este ticket já foi resolvido.',
            ], 422);
        }

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Mensagem enviada com sucesso.',
        ]);
    }

    /**
     * Encerra um ticket.
     * Apenas o suporte pode encerrar.
     */
    public function concluir(
        Request $request,
        AtendimentoHumano $atendimento
    ): JsonResponse {
        abort_unless(
            $this->ehSuporte($request),
            403,
            'Apenas o suporte pode encerrar tickets.'
        );

        if ($atendimento->status === 'resolvido') {
            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Este ticket já está resolvido.',
            ]);
        }

        $atendimento->update([
            'status' => 'resolvido',
        ]);

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Ticket resolvido com sucesso.',
            'ticket' => $atendimento->fresh(),
        ]);
    }
}
