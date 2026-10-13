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
    /**
     * Status possíveis do campo `comando_web` do totem e sua apresentação.
     *
     * @var array<string, array{label: string, badge: string, dot: string}>
     */
    private const TOTEM_STATUS = [
        'iniciar' => [
            'label' => 'Carregando',
            'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/30',
            'dot' => 'bg-emerald-500 animate-pulse',
        ],
        'parar' => [
            'label' => 'Carga interrompida',
            'badge' => 'bg-red-50 text-red-700 ring-red-600/30',
            'dot' => 'bg-red-500',
        ],
        'espera' => [
            'label' => 'Aguardando comando',
            'badge' => 'bg-amber-50 text-amber-700 ring-amber-600/30',
            'dot' => 'bg-amber-500',
        ],
    ];

    private const DEFAULT_STATUS = [
        'label' => 'Sem conexão',
        'badge' => 'bg-gray-100 text-gray-600 ring-gray-500/20',
        'dot' => 'bg-gray-400',
    ];

    private const DEFAULT_TARIFF = 5.50;

    public function __construct(
        private readonly FirestoreService $firestore
    ) {}

    /**
     * Exibe a tela de recarga com os dados reais do usuário lidos do Firestore
     * (nome, id, saldo) e o status atual do totem (`comando_web`).
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

        $statusKey = (string) ($totem['comando_web'] ?? '');
        $status = self::TOTEM_STATUS[$statusKey] ?? self::DEFAULT_STATUS;

        return view('totens.carregar', [
            'totemId' => $totemId,
            'userName' => $user->name,
            'userId' => $user->id,
            'tagUid' => $tagUid,
            'saldo' => $saldo,
            'statusKey' => $statusKey,
            'statusLabel' => $status['label'],
            'statusBadge' => $status['badge'],
            'statusDot' => $status['dot'],
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
                    ->with('error', 'Saldo insuficiente para iniciar a recarga. Recarregue sua carteira.');
            }

            // Lê a tarifa do totem (fallback: 5.50)
            $totem = $this->firestore->getDocumentData('totems', $totemId) ?? [];
            $tarifaKwh = isset($totem['tarifa_kwh'])
                ? (float) $totem['tarifa_kwh']
                : self::DEFAULT_TARIFF;

            // Dispara o comando de início para o totem com a TAG dinâmica do usuário
            $this->firestore->setDocumentMerge('totems', $totemId, [
                'comando_web' => 'iniciar',
                'tag_uid' => $tagUid,
                'tarifa_kwh' => $tarifaKwh,
            ]);

            return redirect()->route('totens.carregar', $totemId)
                ->with('success', "Carga iniciada no totem {$totemId} para {$user->name}.");
        } catch (Throwable $e) {
            return redirect()->route('totens.carregar', $totemId)
                ->with('error', 'Erro ao iniciar carga: '.$e->getMessage());
        }
    }

    /**
     * Envia o comando `parar` para o totem, interrompendo a recarga remotamente.
     */
    public function parar(string $totemId): RedirectResponse
    {
        try {
            $this->firestore->setDocumentMerge('totems', $totemId, [
                'comando_web' => 'parar',
            ]);

            return redirect()->route('totens.carregar', $totemId)
                ->with('success', "Carga interrompida no totem {$totemId}.");
        } catch (Throwable $e) {
            return redirect()->route('totens.carregar', $totemId)
                ->with('error', 'Erro ao parar carga: '.$e->getMessage());
        }
    }

    /**
     * Identificador da TAG do usuário no Firestore (formato WEB_USER_{id}).
     */
    private function tagUid(User $user): string
    {
        return "WEB_USER_{$user->id}";
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
