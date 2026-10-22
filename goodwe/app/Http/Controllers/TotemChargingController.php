<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirestoreService;
use App\Services\TotemSessionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

/**
 * Controla a recarga remota de veículos elétricos via totem PowerTAG.
 *
 * Fluxo de integração com o Firebase Firestore:
 *  - Usuário autenticado no Laravel => documento "users/WEB_USER_{id}" no Firestore
 *  - Comando de carga               => documento "totems/{totemId}.comando_web"
 *  - O totem (IoT) escuta "comando_web" e inicia/para a recarga
 *
 * Otimizações do fluxo:
 *  - documentos da TAG e do totem são lidos em um único BatchGet;
 *  - canal do ESP e espelho do totem são gravados no mesmo commit;
 *  - o polling usa leitura sempre atual e não regrava a sessão a cada consulta;
 *  - iniciar/parar respondem JSON quando chamados por AJAX, sem recarregar a página.
 */
class TotemChargingController extends Controller
{
    /** Tarifa padrão usada quando o totem ainda não tem `tarifa_kwh`. */
    private const DEFAULT_TARIFF = 5.50;

    /** Estados em que o equipamento ainda está processando um comando. */
    private const FAST_POLL_STATES = ['iniciar', 'aguardando_pin', 'parar'];

    public function __construct(
        private readonly FirestoreService $firestore,
        private readonly TotemSessionService $sessions
    ) {}

    /**
     * Lista os totens de recarga disponíveis (config/totems.php) e dá acesso
     * à tela de controle remoto de cada um.
     *
     * Não consulta o Firestore: a lista é configurável e estática, o que
     * mantém a página rápida mesmo com o provedor externo indisponível.
     */
    public function index(): View
    {
        return view('totens.index', [
            'totems' => config('totems.available', []),
        ]);
    }

    /**
     * Exibe a tela de recarga com os dados reais do usuário lidos do Firestore
     * (nome, id, saldo) e o status atual da TAG (`comando_web`).
     *
     * O status é lido primeiro do documento da TAG (users/{TAG}) — que é o
     * canal que o ESP consome — e, na falta dele, do espelho em totems/{id}.
     * As duas leituras acontecem em um único BatchGet para a tela abrir mais
     * rápido.
     */
    public function show(string $totemId): View
    {
        $user = Auth::user();
        $tagUid = $this->tagUid($user);

        try {
            $documents = $this->firestore->getDocumentsData([
                "users/{$tagUid}",
                "totems/{$totemId}",
            ]);

            $firestoreUser = $documents["users/{$tagUid}"] ?? [];
            $totem = $documents["totems/{$totemId}"] ?? [];
        } catch (Throwable $e) {
            // Se o Firestore estiver indisponível, a tela ainda abre com os
            // dados locais do usuário para não bloquear o fluxo de recarga.
            report($e);

            $firestoreUser = [];
            $totem = [];
        }

        // Saldo real: prioriza o Firestore, com fallback para a carteira local.
        $saldo = isset($firestoreUser['saldo_pontos'])
            ? (float) $firestoreUser['saldo_pontos']
            : (float) $user->balance;

        $statusKey = (string) ($firestoreUser['comando_web'] ?? $totem['comando_web'] ?? '');

        return view('totens.carregar', [
            'totemId' => $totemId,
            'userName' => $user->name,
            'userId' => $user->id,
            'tagUid' => $tagUid,
            'saldo' => $saldo,
            'statusKey' => $statusKey,
            'tarifaKwh' => isset($totem['tarifa_kwh'])
                ? (float) $totem['tarifa_kwh']
                : self::DEFAULT_TARIFF,
            'pollingInterval' => max(250, (int) config('totems.polling_interval_ms', 1000)),
            'idlePollingInterval' => max(1000, (int) config('totems.idle_polling_interval_ms', 4000)),
            'fastPollStates' => self::FAST_POLL_STATES,
        ]);
    }

    /**
     * Envia o comando `iniciar` para o totem, desde que o usuário tenha
     * saldo maior que zero na coleção `users` do Firestore.
     *
     * O contrato do Firestore para `totems/{TOTEM_ID}` define:
     *  - comando_web: "iniciar"
     *  - tag_uid: ID virtual do usuário (ex: "WEB_USER_1")
     *  - pin_digitado: "" (resetado para nova sessão)
     *
     * O ESP32 processa o comando, gera um `pin_gerado` de 4 dígitos e altera
     * o `comando_web` para "aguardando_pin". O front-end deteta essa mudança
     * via polling e abre a modal para o utilizador digitar o PIN.
     */
    public function iniciar(Request $request, string $totemId): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $tagUid = $this->tagUid($user);

        try {
            // Uma única ida ao Firestore devolve a TAG (saldo/comando) e o
            // totem (tarifa). Antes eram duas leituras sequenciais.
            $documents = $this->firestore->getDocumentsData([
                "users/{$tagUid}",
                "totems/{$totemId}",
            ]);

            $firestoreUser = $documents["users/{$tagUid}"];
            $totem = $documents["totems/{$totemId}"] ?? [];

            // A carteira Laravel é a fonte de cobrança. O espelho do Firestore
            // só é elevado quando está atrás, preservando o comportamento antigo.
            $carteira = (float) $user->balance;
            $saldo = $firestoreUser === null
                ? $carteira
                : (float) ($firestoreUser['saldo_pontos'] ?? 0);

            if ($carteira > $saldo) {
                $saldo = $carteira;
            }

            if ($saldo <= 0) {
                // Documento ainda inexistente: cria com os dados locais antes
                // de bloquear, como fazia a sincronização de saldo anterior.
                if ($firestoreUser === null) {
                    $this->firestore->setDocumentMerge('users', $tagUid, [
                        'nome' => $user->name,
                        'saldo_pontos' => $carteira,
                        'status_tag' => 'ativo',
                        'comando_web' => 'espera',
                    ]);
                }

                return $this->commandResponse(
                    $request,
                    $totemId,
                    false,
                    __('totens.no_balance'),
                    422
                );
            }

            // Lê a tarifa do totem (fallback: 5.50)
            $tarifaKwh = isset($totem['tarifa_kwh'])
                ? (float) $totem['tarifa_kwh']
                : self::DEFAULT_TARIFF;

            $userPayload = ['comando_web' => 'iniciar'];

            if ($firestoreUser === null) {
                $userPayload += [
                    'nome' => $user->name,
                    'saldo_pontos' => $carteira,
                    'status_tag' => 'ativo',
                ];
            } elseif ($carteira > (float) ($firestoreUser['saldo_pontos'] ?? 0)) {
                $userPayload['saldo_pontos'] = $carteira;
            }

            // 1) Comando para o ESP (canal que ele consome em tempo real) e
            //    2) espelho no totem (status na tela + TAG autenticada), no
            //    mesmo commit. `tarifa_kwh` não é reescrito aqui: uma edição
            //    concorrente no gestor poderia ser sobrescrita com o valor antigo.
            $this->firestore->setDocumentsMerge([
                "users/{$tagUid}" => $userPayload,
                "totems/{$totemId}" => [
                    'comando_web' => 'iniciar',
                    'tag_uid' => $tagUid,
                    'pin_digitado' => '',
                ],
            ]);

            $this->forgetTotemData($totemId);

            // Espelha a recarga no banco: é o registro que compõe o dashboard
            // do gestor (o comando no Firestore é só o canal do equipamento).
            $this->sessions->start($user, $totemId, $tarifaKwh);

            return $this->commandResponse(
                $request,
                $totemId,
                true,
                __('totens.started', ['totem' => $totemId])
            );
        } catch (Throwable $e) {
            report($e);

            return $this->commandResponse(
                $request,
                $totemId,
                false,
                __('totens.start_failed', ['error' => $e->getMessage()]),
                500
            );
        }
    }

    /**
     * Recebe o PIN digitado pelo utilizador na modal e atualiza apenas o campo
     * `pin_digitado` no documento `totems/{totemId}` do Firestore.
     *
     * O ESP32 compara o `pin_digitado` com o `pin_gerado` e, se coincidirem,
     * inicia a recarga e altera o `comando_web` para "carregando".
     */
    public function enviarPin(Request $request, string $totemId): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ]);

        $pin = (string) $request->input('pin');

        try {
            // Atualiza apenas o campo pin_digitado no documento do totem
            $this->firestore->setDocumentMerge('totems', $totemId, [
                'pin_digitado' => $pin,
            ]);

            $this->forgetTotemData($totemId);

            return response()
                ->json([
                    'success' => true,
                    'message' => __('totens.pin_sent'),
                ])
                ->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            report($e);

            return response()
                ->json([
                    'success' => false,
                    'message' => __('totens.pin_failed', ['error' => $e->getMessage()]),
                ], 500)
                ->header('Cache-Control', 'no-store');
        }
    }

    /**
     * Endpoint AJAX para consulta do estado atual do totem.
     *
     * Retorna os campos relevantes do documento `totems/{totemId}` para que
     * o front-end possa monitorizar o `comando_web` e reagir em tempo real
     * (ex: abrir a modal quando `comando_web === 'aguardando_pin'`).
     *
     * A resposta não é cacheada: o polling precisa ver a mudança do ESP o
     * quanto antes — é ela que libera o código/PIN para o usuário.
     */
    public function status(string $totemId): JsonResponse
    {
        try {
            $totem = $this->totemStatus($totemId);
            $comando = (string) ($totem['comando_web'] ?? '');

            // O avanço do fluxo é observado no polling: quando o ESP confirma o
            // PIN e passa a "carregando", a sessão espelhada sai de
            // `pending_pin` para que o painel do gestor mostre a recarga ativa.
            $this->sessions->syncState(Auth::user(), $totemId, $comando);

            return response()
                ->json([
                    'success' => true,
                    'comando_web' => $comando,
                    'pin_gerado' => (string) ($totem['pin_gerado'] ?? ''),
                    'tag_uid' => (string) ($totem['tag_uid'] ?? ''),
                    'tarifa_kwh' => isset($totem['tarifa_kwh'])
                        ? (float) $totem['tarifa_kwh']
                        : self::DEFAULT_TARIFF,
                ])
                ->header('Cache-Control', 'no-store');
        } catch (Throwable $e) {
            return response()
                ->json([
                    'success' => false,
                    'comando_web' => '',
                    'message' => $e->getMessage(),
                ], 500)
                ->header('Cache-Control', 'no-store');
        }
    }

    /**
     * Envia o comando `parar` para o ESP (users/{TAG}.comando_web) e espelha
     * no totem, interrompendo a recarga remotamente.
     */
    public function parar(Request $request, string $totemId): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $tagUid = $this->tagUid($user);

        try {
            // 1) Comando para o ESP e 2) espelho no totem em um só commit.
            $this->firestore->setDocumentsMerge([
                "users/{$tagUid}" => ['comando_web' => 'parar'],
                "totems/{$totemId}" => ['comando_web' => 'parar'],
            ]);

            $this->forgetTotemData($totemId);

            // 3) Encerra a sessão no banco com a medição do totem, para que o
            //    consumo e o valor da recarga cheguem ao painel do gestor.
            $this->sessions->finish($user, $totemId, $this->totemData($totemId));

            return $this->commandResponse(
                $request,
                $totemId,
                true,
                __('totens.stopped', ['totem' => $totemId])
            );
        } catch (Throwable $e) {
            report($e);

            return $this->commandResponse(
                $request,
                $totemId,
                false,
                __('totens.stop_failed', ['error' => $e->getMessage()]),
                500
            );
        }
    }

    /**
     * Resposta de iniciar/parar.
     *
     * Chamadas AJAX recebem JSON (a tela não recarrega e já começa a
     * consultar o status); formulários sem JavaScript continuam usando o
     * redirect com mensagem na sessão.
     */
    private function commandResponse(
        Request $request,
        string $totemId,
        bool $success,
        string $message,
        int $status = 200
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()
                ->json(['success' => $success, 'message' => $message], $status)
                ->header('Cache-Control', 'no-store');
        }

        $redirect = redirect()->route('totens.carregar', $totemId);

        return $success
            ? $redirect->with('success', $message)
            : $redirect->with('error', $message);
    }

    /**
     * Dados do totem (tarifa, localização e espelho de `comando_web`).
     *
     * Usado no encerramento da recarga, quando a medição final precisa ser
     * lida uma única vez. O cache é invalidado nas escritas
     * (iniciar/parar/PIN e alterações de tarifa do gestor).
     *
     * @return array<string, mixed>
     */
    private function totemData(string $totemId): array
    {
        return Cache::remember(
            "totems:document:{$totemId}",
            now()->addSeconds(60),
            fn (): array => $this->firestore->getDocumentData('totems', $totemId) ?? []
        );
    }

    /**
     * Dados do totem usados pelo polling do front-end.
     *
     * O padrão é sem cache (`status_cache_seconds = 0`): o polling precisa
     * enxergar a transição para "aguardando_pin" assim que o ESP a gravar.
     * Quem tiver muitas abas no mesmo totem pode elevar o TTL para reduzir
     * chamadas ao Firestore.
     *
     * @return array<string, mixed>
     */
    private function totemStatus(string $totemId): array
    {
        return $this->rememberLocally(
            "totems:status:{$totemId}",
            (int) config('totems.status_cache_seconds', 0),
            fn (): array => $this->firestore->getDocumentData('totems', $totemId) ?? []
        );
    }

    /**
     * Lê com cache opcional: TTL menor ou igual a zero desliga o cache.
     *
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    private function rememberLocally(string $key, int $seconds, Closure $read): mixed
    {
        return $seconds > 0 ? Cache::remember($key, $seconds, $read) : $read();
    }

    private function forgetTotemData(string $totemId): void
    {
        Cache::forget("totems:document:{$totemId}");
        Cache::forget("totems:status:{$totemId}");
    }

    /**
     * ID do documento da TAG do usuário no Firestore.
     *
     * Usa a TAG física cadastrada no cadastro (coluna `tag_uid`, quando
     * preenchida) e, na falta dela, o identificador WEB_USER_{id} usado pelo
     * web — que é o documento criado no registro em UserController::store.
     * É para esse documento que o ESP escuta `comando_web`.
     */
    private function tagUid(User $user): string
    {
        $tagFisica = filled($user->tag_uid) ? strtoupper((string) $user->tag_uid) : '';

        return $tagFisica !== '' ? $tagFisica : "WEB_USER_{$user->id}";
    }
}
