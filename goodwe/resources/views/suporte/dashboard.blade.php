<x-user-layout>
    @php
        // Respeita a seleção feita pelo idioma da plataforma (/locale/{locale}).
        // O middleware global de idioma pode ser incorporado numa etapa futura.
        $idiomaSelecionado = session('locale', app()->getLocale());
        if (in_array($idiomaSelecionado, ['pt', 'en', 'es'], true)) {
            app()->setLocale($idiomaSelecionado);
        }

        // Textos de suporte carregados de lang/pt.json, lang/en.json e lang/es.json.
        $t = [];
        foreach ([
            'overview_title',
            'overview_description',
            'all_title',
            'all_description',
            'waiting_title',
            'waiting_description',
            'progress_title',
            'progress_description',
            'resolved_title',
            'resolved_description',
            'back_overview',
            'support_panel',
            'welcome',
            'welcome_description',
            'overall_total',
            'waiting',
            'in_progress',
            'progress_card',
            'resolved_card',
            'resolved_status',
            'view_tickets',
            'one_ticket',
            'many_tickets',
            'recent_title',
            'recent_description',
            'requester',
            'subject',
            'date',
            'action',
            'no_user',
            'account_missing',
            'manager',
            'user',
            'human_support',
            'view_initial',
            'view_conversation',
            'answer_ticket',
            'no_tickets_title',
            'no_tickets_description',
            'service',
            'close_conversation',
            'close',
            'loading',
            'your_reply',
            'reply_placeholder',
            'mark_resolved',
            'send_reply',
            'resolved_notice',
            'session_expired',
            'operation_error',
            'support_you',
            'load_error',
            'reply_sent',
            'confirm_resolve',
            'resolve_success',
            'nav_overview',
            'nav_all',
            'nav_waiting',
            'nav_in_progress',
            'nav_resolved',
            'sidebar_subtitle',
        ] as $chave) {
            $t[$chave] = __('support.' . $chave);
        }

        // O menu de Suporte já envia o status pela URL.
        // Aqui transformamos cada opção em uma tela visual diferente.
        $pagina = request()->query('status');

        $paginas = [
            'todos' => [
                'titulo' => $t['all_title'],
                'descricao' => $t['all_description'],
                'status' => null,
            ],
            'aguardando' => [
                'titulo' => $t['waiting_title'],
                'descricao' => $t['waiting_description'],
                'status' => 'aguardando',
            ],
            'em_atendimento' => [
                'titulo' => $t['progress_title'],
                'descricao' => $t['progress_description'],
                'status' => 'em_atendimento',
            ],
            'resolvido' => [
                'titulo' => $t['resolved_title'],
                'descricao' => $t['resolved_description'],
                'status' => 'resolvido',
            ],
        ];

        if (! array_key_exists($pagina ?? '', $paginas)) {
            $pagina = null; // Visão Geral
        }

        $ehVisaoGeral = $pagina === null;
        $configuracao = $ehVisaoGeral
            ? ['titulo' => $t['overview_title'],
               'descricao' => $t['overview_description'],
               'status' => null]
            : $paginas[$pagina];

        $totais = [
            'todos' => \App\Models\AtendimentoHumano::count(),
            'aguardando' => \App\Models\AtendimentoHumano::where('status', 'aguardando')->count(),
            'em_atendimento' => \App\Models\AtendimentoHumano::where('status', 'em_atendimento')->count(),
            'resolvido' => \App\Models\AtendimentoHumano::where('status', 'resolvido')->count(),
        ];

        $consulta = \Illuminate\Support\Facades\DB::table('atendimento_humanos')
            ->leftJoin('users', 'users.id', '=', 'atendimento_humanos.user_id')
            ->select(
                'atendimento_humanos.*',
                'users.name as usuario_nome',
                'users.is_admin as solicitante_gestor'
            );

        if ($configuracao['status'] !== null) {
            $consulta->where('atendimento_humanos.status', $configuracao['status']);
        }

        $tickets = $consulta
            ->orderByDesc('atendimento_humanos.created_at')
            ->paginate(10)
            ->withQueryString();

        $quantidadeAtual = $ehVisaoGeral
            ? $totais['todos']
            : $totais[$pagina];
    @endphp

    <!-- Título diferente para cada item do menu lateral -->
    <section class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ $configuracao['titulo'] }}
            </h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
                {{ $configuracao['descricao'] }}
            </p>
        </div>

        @if(! $ehVisaoGeral)
            <a href="{{ route('support.dashboard') }}"
               class="inline-flex w-fit items-center gap-2 rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 transition hover:border-[#e53935] hover:text-[#e53935] dark:border-[#343843] dark:text-[#a0a0a0] dark:hover:text-[#e53935]">
                &larr; {{ $t['back_overview'] }}
            </a>
        @else
            <span class="inline-flex w-fit items-center gap-2 rounded-lg border border-[#e53935]/30 bg-[#e53935]/10 px-4 py-2 text-sm font-medium text-[#e53935]">
                <span class="h-2 w-2 rounded-full bg-[#e53935]"></span>
                {{ $t['support_panel'] }}
            </span>
        @endif
    </section>

    <!-- Só a Visão Geral mostra os quatro indicadores -->
    @if($ehVisaoGeral)
        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $t['welcome'] }} {{ auth()->user()->name }}!
            </h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
                {{ $t['welcome_description'] }}
            </p>
        </section>

        <section class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['nome' => $t['overall_total'], 'quantidade' => $totais['todos'], 'cor' => '#e53935', 'filtro' => 'todos'],
                ['nome' => $t['waiting'], 'quantidade' => $totais['aguardando'], 'cor' => '#f59e0b', 'filtro' => 'aguardando'],
                ['nome' => $t['progress_title'], 'quantidade' => $totais['em_atendimento'], 'cor' => '#3b82f6', 'filtro' => 'em_atendimento'],
                ['nome' => $t['resolved_card'], 'quantidade' => $totais['resolvido'], 'cor' => '#10b981', 'filtro' => 'resolvido'],
            ] as $card)
                <a href="{{ route('support.dashboard', ['status' => $card['filtro']]) }}"
                   class="block rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-[#e53935]/50 dark:border-[#343843] dark:bg-[#1a1a1a]">
                    <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">
                        {{ $card['nome'] }}
                    </p>
                    <p class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $card['quantidade'] }}
                    </p>
                    <p class="mt-2 text-xs" style="color: {{ $card['cor'] }}">
                        {{ $t['view_tickets'] }} &rarr;
                    </p>
                </a>
            @endforeach
        </section>
    @else
        <!-- Nas outras telas aparece só o resumo da categoria selecionada -->
        <section class="mb-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
            <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ $configuracao['titulo'] }}</p>
            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                {{ $quantidadeAtual }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-[#a0a0a0]">
                {{ $quantidadeAtual === 1 ? $t['one_ticket'] : $t['many_tickets'] }}
            </p>
        </section>
    @endif

    <!-- Lista correspondente à tela escolhida -->
    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-[#343843] dark:bg-[#1a1a1a]">
        <div class="border-b border-gray-200 pb-4 dark:border-[#343843]">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                {{ $ehVisaoGeral ? $t['recent_title'] : $configuracao['titulo'] }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-[#a0a0a0]">
                {{ $ehVisaoGeral ? $t['recent_description'] : $configuracao['descricao'] }}
            </p>
        </div>

        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-[#343843] dark:text-[#a0a0a0]">
                    <tr>
                        <th class="px-3 py-3 font-medium">Ticket</th>
                        <th class="px-3 py-3 font-medium">{{ $t['requester'] }}</th>
                        <th class="px-3 py-3 font-medium">{{ $t['subject'] }}</th>
                        <th class="px-3 py-3 font-medium">{{ $t['date'] }}</th>
                        <th class="px-3 py-3 font-medium">Status</th>
                        <th class="px-3 py-3 font-medium text-right">{{ $t['action'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr class="border-b border-gray-200 dark:border-[#343843]">
                            <td class="px-3 py-4 font-semibold text-[#e53935]">
                                #{{ $ticket->id }}
                            </td>
                            <td class="px-3 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $ticket->usuario_nome ?? $t['no_user'] }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-[#a0a0a0]">
                                    {{ $ticket->usuario_nome === null ? $t['account_missing'] : ($ticket->solicitante_gestor ? $t['manager'] : $t['user']) }}
                                </p>
                            </td>
                            <td class="px-3 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $ticket->motivo ?: $t['human_support'] }}
                                </p>
                                <details class="mt-2">
                                    <summary class="cursor-pointer text-xs text-[#e53935] hover:underline">
                                        {{ $t['view_initial'] }}
                                    </summary>
                                    <p class="mt-2 max-w-md whitespace-pre-wrap break-words text-sm text-gray-500 dark:text-[#a0a0a0]">
                                        {{ $ticket->mensagem }}
                                    </p>
                                </details>
                            </td>
                            <td class="px-3 py-4 text-gray-500 dark:text-[#a0a0a0]">
                                {{ \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-3 py-4">
                                @if($ticket->status === 'aguardando')
                                    <span class="inline-flex rounded-lg bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-500">{{ $t['waiting'] }}</span>
                                @elseif($ticket->status === 'em_atendimento')
                                    <span class="inline-flex rounded-lg bg-blue-500/10 px-3 py-1 text-xs font-medium text-blue-500">{{ $t['in_progress'] }}</span>
                                @elseif($ticket->status === 'resolvido')
                                    <span class="inline-flex rounded-lg bg-emerald-500/10 px-3 py-1 text-xs font-medium text-emerald-500">{{ $t['resolved_status'] }}</span>
                                @else
                                    <span class="inline-flex rounded-lg bg-gray-500/10 px-3 py-1 text-xs font-medium text-gray-500">{{ $ticket->status }}</span>
                                @endif
                            </td>

                            <td class="px-3 py-4 text-right">
                                <button
                                    type="button"
                                    class="abrir-ticket inline-flex items-center justify-center rounded-lg border border-[#e53935]/40 bg-[#e53935]/10 px-3 py-2 text-xs font-semibold text-[#e53935] transition hover:bg-[#e53935]/20 focus:outline-none focus:ring-2 focus:ring-[#e53935]"
                                    data-numero="{{ $ticket->id }}"
                                    data-assunto="{{ $ticket->motivo ?: $t['human_support'] }}"
                                    data-solicitante="{{ $ticket->usuario_nome ?? $t['no_user'] }}"
                                    data-consultar="{{ route('tickets.show', ['atendimento' => $ticket->id]) }}"
                                    data-responder="{{ route('tickets.responder', ['atendimento' => $ticket->id]) }}"
                                    data-concluir="{{ route('tickets.concluir', ['atendimento' => $ticket->id]) }}"
                                >
                                    {{ $ticket->status === 'resolvido' ? $t['view_conversation'] : $t['answer_ticket'] }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-12 text-center">
                                <p class="font-medium text-gray-900 dark:text-white">{{ $t['no_tickets_title'] }}</p>
                                <p class="mt-2 text-sm text-gray-500 dark:text-[#a0a0a0]">
                                    {{ $t['no_tickets_description'] }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="mt-5 border-t border-gray-200 pt-5 dark:border-[#343843]">
                {{ $tickets->links() }}
            </div>
        @endif
    </section>

    <!-- Janela de atendimento: utiliza as rotas de tickets existentes. -->
    <div
        id="janelaTicket"
        class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/70 p-3 sm:p-6"
        role="dialog"
        aria-modal="true"
        aria-labelledby="tituloTicket"
        aria-hidden="true"
    >
        <div class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-[#343843] dark:bg-[#1a1a1a]">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 p-4 dark:border-[#343843] sm:p-5">
                <div class="min-w-0">
                    <h2 id="tituloTicket" class="text-xl font-semibold text-gray-900 dark:text-white">
                        {{ $t['service'] }}
                    </h2>
                    <p id="subtituloTicket" class="mt-1 break-words text-sm text-gray-500 dark:text-[#a0a0a0]"></p>
                    <p id="statusTicket" class="mt-2 text-xs font-semibold text-[#e53935]"></p>
                </div>
                <button
                    id="fecharTicket"
                    type="button"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-600 hover:text-[#e53935] dark:border-[#343843] dark:text-[#a0a0a0]"
                    aria-label="{{ $t['close_conversation'] }}"
                >{{ $t['close'] }}</button>
            </div>

            <div
                id="mensagensTicket"
                class="min-h-[180px] flex-1 space-y-4 overflow-y-auto bg-gray-50 p-4 dark:bg-[#0f1117] sm:p-5"
                aria-live="polite"
            >
                <p class="text-sm text-gray-500 dark:text-[#a0a0a0]">{{ $t['loading'] }}</p>
            </div>

            <div class="border-t border-gray-200 p-4 dark:border-[#343843] sm:p-5">
                <p id="avisoTicket" class="mb-3 hidden rounded-lg border px-3 py-2 text-sm" role="status"></p>
                <form id="formResponderTicket" class="space-y-3">
                    @csrf
                    <label for="respostaTicket" class="block text-sm font-medium text-gray-900 dark:text-white">
                        {{ $t['your_reply'] }}
                    </label>
                    <textarea
                        id="respostaTicket"
                        name="mensagem"
                        rows="3"
                        maxlength="10000"
                        required
                        placeholder="{{ $t['reply_placeholder'] }}"
                        class="w-full resize-y rounded-lg border border-gray-300 bg-white p-3 text-sm text-gray-900 outline-none focus:border-[#e53935] dark:border-[#343843] dark:bg-[#181b22] dark:text-white"
                    ></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button
                            id="concluirTicket"
                            type="button"
                            class="rounded-lg border border-emerald-500/50 px-4 py-2 text-sm font-medium text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400"
                        >{{ $t['mark_resolved'] }}</button>
                        <button
                            id="enviarRespostaTicket"
                            type="submit"
                            class="rounded-lg bg-[#e53935] px-5 py-2 text-sm font-semibold text-white hover:bg-[#c62828] disabled:cursor-not-allowed disabled:opacity-50"
                        >{{ $t['send_reply'] }}</button>
                    </div>
                </form>
                <p id="ticketResolvidoAviso" class="hidden text-sm text-gray-500 dark:text-[#a0a0a0]">
                    {{ $t['resolved_notice'] }}
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const texto = @json($t);
            const modal = document.getElementById('janelaTicket');
            const titulo = document.getElementById('tituloTicket');
            const subtitulo = document.getElementById('subtituloTicket');
            const status = document.getElementById('statusTicket');
            const areaMensagens = document.getElementById('mensagensTicket');
            const aviso = document.getElementById('avisoTicket');
            const formulario = document.getElementById('formResponderTicket');
            const campo = document.getElementById('respostaTicket');
            const botaoEnviar = document.getElementById('enviarRespostaTicket');
            const botaoConcluir = document.getElementById('concluirTicket');
            const avisoResolvido = document.getElementById('ticketResolvidoAviso');
            const csrf = formulario.querySelector('input[name="_token"]').value;
            const suporteId = @json(auth()->id());

            let ticketAtual = null;
            let houveAlteracao = false;
            let botaoAnterior = null;

            function mostrarAviso(texto, erro = false) {
                aviso.textContent = texto;
                aviso.classList.remove('hidden');
                aviso.classList.toggle('text-red-500', erro);
                aviso.classList.toggle('border-red-500/40', erro);
                aviso.classList.toggle('text-emerald-600', !erro);
                aviso.classList.toggle('border-emerald-500/40', !erro);
            }

            function limparAviso() {
                aviso.textContent = '';
                aviso.classList.add('hidden');
            }

            function linhaMensagem(nome, texto, minhaMensagem) {
                const caixa = document.createElement('div');
                caixa.className = 'flex ' + (minhaMensagem ? 'justify-end' : 'justify-start');

                const balao = document.createElement('div');
                balao.className = 'max-w-[90%] rounded-xl border p-3 text-sm ' +
                    (minhaMensagem
                        ? 'border-[#e53935]/30 bg-[#e53935]/10'
                        : 'border-gray-200 bg-white dark:border-[#343843] dark:bg-[#1a1a1a]');

                const remetente = document.createElement('p');
                remetente.className = 'mb-1 text-xs font-semibold text-[#e53935]';
                remetente.textContent = nome;

                const conteudo = document.createElement('p');
                conteudo.className = 'whitespace-pre-wrap break-words text-gray-900 dark:text-white';
                conteudo.textContent = texto;

                balao.append(remetente, conteudo);
                caixa.appendChild(balao);
                return caixa;
            }

            async function solicitar(url, options = {}) {
                const resposta = await fetch(url, {
                    ...options,
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...options.headers,
                    },
                });
                const dados = await resposta.json().catch(() => ({}));
                if (!resposta.ok || dados.sucesso === false) {
                    const mensagem = dados.message || dados.mensagem ||
                        (resposta.status === 419 ? texto.session_expired : texto.operation_error);
                    throw new Error(mensagem);
                }
                return dados;
            }

            async function carregarConversa() {
                if (!ticketAtual) return;
                const ticketId = ticketAtual.numero;
                const dados = await solicitar(ticketAtual.consultar);
                if (!ticketAtual || ticketAtual.numero !== ticketId) return;

                areaMensagens.replaceChildren();
                areaMensagens.appendChild(linhaMensagem(
                    ticketAtual.solicitante,
                    dados.mensagem_inicial || '',
                    false
                ));

                for (const mensagem of (dados.mensagens || [])) {
                    const minhaMensagem = Number(mensagem.remetente_id) === Number(suporteId);
                    areaMensagens.appendChild(linhaMensagem(
                        minhaMensagem ? texto.support_you : (mensagem.remetente_nome || ticketAtual.solicitante),
                        mensagem.mensagem || '',
                        minhaMensagem
                    ));
                }

                const resolvido = dados.ticket?.status === 'resolvido';
                status.textContent = resolvido ? texto.resolved_status :
                    (dados.ticket?.status === 'em_atendimento' ? texto.in_progress : texto.waiting);
                formulario.classList.toggle('hidden', resolvido);
                avisoResolvido.classList.toggle('hidden', !resolvido);
                botaoConcluir.disabled = resolvido;
                areaMensagens.scrollTop = areaMensagens.scrollHeight;
            }

            async function abrirTicket(botao) {
                botaoAnterior = botao;
                ticketAtual = {
                    numero: botao.dataset.numero,
                    assunto: botao.dataset.assunto,
                    solicitante: botao.dataset.solicitante,
                    consultar: botao.dataset.consultar,
                    responder: botao.dataset.responder,
                    concluir: botao.dataset.concluir,
                };
                limparAviso();
                titulo.textContent = `Ticket #${ticketAtual.numero}`;
                subtitulo.textContent = `${ticketAtual.assunto} · ${ticketAtual.solicitante}`;
                campo.value = '';
                areaMensagens.textContent = texto.loading;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
                try {
                    await carregarConversa();
                    if (formulario.classList.contains('hidden')) {
                        document.getElementById('fecharTicket').focus();
                    } else {
                        campo.focus();
                    }
                } catch (erro) {
                    areaMensagens.textContent = texto.load_error;
                    formulario.classList.add('hidden');
                    mostrarAviso(erro.message, true);
                }
            }

            function fecharTicket() {
                ticketAtual = null;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                botaoAnterior?.focus();
                if (houveAlteracao) window.location.reload();
            }

            document.querySelectorAll('.abrir-ticket').forEach(botao => {
                botao.addEventListener('click', () => abrirTicket(botao));
            });
            document.getElementById('fecharTicket').addEventListener('click', fecharTicket);
            modal.addEventListener('click', evento => {
                if (evento.target === modal) fecharTicket();
            });
            document.addEventListener('keydown', evento => {
                if (evento.key === 'Escape' && !modal.classList.contains('hidden')) fecharTicket();
            });

            formulario.addEventListener('submit', async evento => {
                evento.preventDefault();
                if (!ticketAtual || !campo.value.trim()) return;
                limparAviso();
                botaoEnviar.disabled = true;
                botaoConcluir.disabled = true;
                try {
                    await solicitar(ticketAtual.responder, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ mensagem: campo.value.trim() }),
                    });
                    houveAlteracao = true;
                    campo.value = '';
                    await carregarConversa();
                    mostrarAviso(texto.reply_sent);
                } catch (erro) {
                    mostrarAviso(erro.message, true);
                } finally {
                    botaoEnviar.disabled = false;
                    botaoConcluir.disabled = false;
                }
            });

            botaoConcluir.addEventListener('click', async () => {
                if (!ticketAtual || !confirm(texto.confirm_resolve)) return;
                limparAviso();
                botaoConcluir.disabled = true;
                botaoEnviar.disabled = true;
                try {
                    await solicitar(ticketAtual.concluir, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    });
                    houveAlteracao = true;
                    await carregarConversa();
                    mostrarAviso(texto.resolve_success);
                } catch (erro) {
                    mostrarAviso(erro.message, true);
                } finally {
                    botaoConcluir.disabled = false;
                    botaoEnviar.disabled = false;
                }
            });
        });
    </script>
</x-user-layout>
