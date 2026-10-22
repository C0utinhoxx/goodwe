<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function sendToUser(
        User $user,
        string $title,
        string $message,
        string $url = '/gestor/demanda'
    ): array {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'removed' => 0,
        ];

        if (! $user->isGestor()) {
            return $result;
        }

        $vapid = config('webpush.vapid');

        if (
            empty($vapid['subject']) ||
            empty($vapid['public_key']) ||
            empty($vapid['private_key'])
        ) {
            Log::warning('GoodWe: chaves VAPID não configuradas.');

            return $result;
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $url = '/gestor/demanda';
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $user->id)
            ->get();

        if ($subscriptions->isEmpty()) {
            return $result;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapid['subject'],
                'publicKey' => $vapid['public_key'],
                'privateKey' => $vapid['private_key'],
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'url' => $url,
            'tag' => 'goodwe-demand-alert',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        foreach ($subscriptions as $savedSubscription) {
            try {
                $subscription = Subscription::create(
                    $savedSubscription->getWebPushSubscription()
                );

                $report = $webPush->sendOneNotification(
                    $subscription,
                    $payload,
                    [
                        'TTL' => 3600,
                        'urgency' => 'high',
                    ]
                );

                if ($report->isSuccess()) {
                    $savedSubscription->update([
                        'last_used_at' => now(),
                    ]);

                    $result['sent']++;

                    continue;
                }

                $result['failed']++;

                if ($report->isSubscriptionExpired()) {
                    $savedSubscription->delete();
                    $result['removed']++;
                }

                Log::warning('GoodWe: falha no envio de Web Push.', [
                    'subscription_id' => $savedSubscription->id,
                    'reason' => $report->getReason(),
                ]);
            } catch (Throwable $exception) {
                $result['failed']++;

                Log::error('GoodWe: erro ao preparar ou enviar Web Push.', [
                    'subscription_id' => $savedSubscription->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $result;
    }
}
