<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FirestoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
            $totem = $this->firestore->getDocumentData('totems', $totemId) ?? [];
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
        ]);
    }

    /**
     * Envia o comando `iniciar` para o totem, desde que o usuário tenha
     * saldo maior que zero na coleção `users` do Firestore.
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
            $this->firestore->setDocumentMerge('totems', $totemId, [
                'comando_web' => 'iniciar',
                'tag_uid' => $tagUid,
                'tarifa_kwh' => $tarifaKwh,
            ]);

            return redirect()->route('totens.carregar', $totemId)
                ->with('success', __('totens.started', ['totem' => $totemId]));
        } catch (Throwable $e) {
            return redirect()->route('totens.carregar', $totemId)
                ->with('error', __('totens.start_failed', ['error' => $e->getMessage()]));
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

            return redirect()->route('totens.carregar', $totemId)
                ->with('success', __('totens.stopped', ['totem' => $totemId]));
        } catch (Throwable $e) {
            return redirect()->route('totens.carregar', $totemId)
                ->with('error', __('totens.stop_failed', ['error' => $e->getMessage()]));
        }
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
