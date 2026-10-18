<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirestoreService;
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
 */
class TotemChargingController extends Controller
{
    /** Tarifa padrão usada quando o totem ainda não tem `tarifa_kwh`. */
    private const DEFAULT_TARIFF = 5.50;

    public function __construct(
        private readonly FirestoreService $firestore
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
     */
    public function show(string $totemId): View
    {
        $user = Auth::user();
        $tagUid = $this->tagUid($user);

        try {
            $firestoreUser = $this->firestore->getDocumentData('users', $tagUid) ?? [];
        } catch (Throwable $e) {
            // Se o Firestore estiver indisponível, a tela ainda abre com os
            // dados locais do usuário para não bloquear o fluxo de recarga.
            report($e);

            $firestoreUser = [];
        }

        try {
            $totem = $this->totemData($totemId);
        } catch (Throwable $e) {
            report($e);

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
    public function iniciar(string $totemId): RedirectResponse
    {
        $user = Auth::user();
        $tagUid = $this->tagUid($user);

        try {
            $saldo = $this->syncSaldo($user, $tagUid);

            if ($saldo <= 0) {
                return redirect()->route('totens.carregar', $totemId)
                    ->with('error', __('totens.no_balance'));
            }

            // Lê a tarifa do totem (fallback: 5.50)
            $totem = $this->firestore->getDocumentData('totems', $totemId) ?? [];
            $tarifaKwh = isset($totem['tarifa_kwh'])
                ? (float) $totem['tarifa_kwh']
                : self::DEFAULT_TARIFF;

            // 1) Comando para o ESP: canal que ele consome em tempo real
            $this->firestore->setDocumentMerge('users', $tagUid, [
                'comando_web' => 'iniciar',
            ]);

            // 2) Espelho no documento do totem (status na tela + TAG autenticada)
            //    Reseta o pin_digitado para garantir que o ESP32 gera um novo PIN
            $this->firestore->setDocumentMerge('totems', $totemId, [
                'comando_web' => 'iniciar',
                'tag_uid' => $tagUid,
                'tarifa_kwh' => $tarifaKwh,
                'pin_digitado' => '',
            ]);

            $this->forgetTotemData($totemId);

            return redirect()->route('totens.carregar', $totemId)
                ->with('success', __('totens.started', ['totem' => $totemId]));
        } catch (Throwable $e) {
            return redirect()->route('totens.carregar', $totemId)
                ->with('error', __('totens.start_failed', ['error' => $e->getMessage()]));
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

            return response()->json([
                'success' => true,
                'message' => __('totens.pin_sent'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('totens.pin_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Endpoint AJAX para consulta do estado atual do totem.
     *
     * Retorna os campos relevantes do documento `totems/{totemId}` para que
     * o front-end possa monitorizar o `comando_web` e reagir em tempo real
     * (ex: abrir a modal quando `comando_web === 'aguardando_pin'`).
     */
    public function status(string $totemId): JsonResponse
    {
        try {
            $totem = $this->firestore->getDocumentData('totems', $totemId) ?? [];

            return response()->json([
                'success' => true,
                'comando_web' => (string) ($totem['comando_web'] ?? ''),
                'pin_gerado' => (string) ($totem['pin_gerado'] ?? ''),
                'tag_uid' => (string) ($totem['tag_uid'] ?? ''),
                'tarifa_kwh' => isset($totem['tarifa_kwh'])
                    ? (float) $totem['tarifa_kwh']
                    : self::DEFAULT_TARIFF,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'comando_web' => '',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Envia o comando `parar` para o ESP (users/{TAG}.comando_web) e espelha
     * no totem, interrompendo a recarga remotamente.
     */
    public function parar(string $totemId): RedirectResponse
    {
        $tagUid = $this->tagUid(Auth::user());

        try {
            // 1) Comando para o ESP: canal que ele consome em tempo real
            $this->firestore->setDocumentMerge('users', $tagUid, [
                'comando_web' => 'parar',
            ]);

            // 2) Espelho no documento do totem (status na tela)
            $this->firestore->setDocumentMerge('totems', $totemId, [
                'comando_web' => 'parar',
            ]);

            $this->forgetTotemData($totemId);

            return redirect()->route('totens.carregar', $totemId)
                ->with('success', __('totens.stopped', ['totem' => $totemId]));
        } catch (Throwable $e) {
            return redirect()->route('totens.carregar', $totemId)
                ->with('error', __('totens.stop_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * Dados do totem (tarifa, localização e espelho de `comando_web`).
     *
     * A tarifa e os metadados do equipamento mudam raramente; um cache curto
     * evita uma ida à rede (Firestore via REST) a cada abertura da tela de
     * recarga. O cache é invalidado nas escritas (iniciar/parar/PIN).
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

    private function forgetTotemData(string $totemId): void
    {
        Cache::forget("totems:document:{$totemId}");
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

    /**
     * Garante que o documento do usuário exista na coleção `users` do Firestore
     * e devolve o saldo real (fonte de verdade para liberar a recarga).
     *
     * A carteira Laravel (`balance`) é a fonte de cobrança do sistema: quando
     * ela possui valor maior que o espelho do Firestore, o documento é
     * atualizado para não bloquear o usuário com saldo desatualizado.
     */
    private function syncSaldo(User $user, string $tagUid): float
    {
        $firestoreUser = $this->firestore->getDocumentData('users', $tagUid);
        $carteira = (float) $user->balance;

        if ($firestoreUser === null) {
            // Documento ainda não existe: cria com os dados do usuário Laravel
            $this->firestore->setDocumentMerge('users', $tagUid, [
                'nome' => $user->name,
                'saldo_pontos' => $carteira,
                'status_tag' => 'ativo',
                'comando_web' => 'espera',
            ]);

            return $carteira;
        }

        $saldo = (float) ($firestoreUser['saldo_pontos'] ?? 0);

        if ($carteira > $saldo) {
            $saldo = $carteira;
            $this->firestore->setDocumentMerge('users', $tagUid, [
                'saldo_pontos' => $saldo,
            ]);
        }

        return $saldo;
    }
}
