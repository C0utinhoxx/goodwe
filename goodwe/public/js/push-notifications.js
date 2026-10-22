(function () {
    'use strict';

    const button = document.getElementById('goodwe-push-button');
    const status = document.getElementById('goodwe-push-status');

    if (!button || !status) {
        return;
    }

    const subscribeUrl = '/gestor/notificacoes/inscrever';
    const publicKeyUrl = '/gestor/notificacoes/chave-publica';

    function setStatus(message) {
        status.textContent = message;
    }

    function base64ToUint8Array(base64) {
        const padding = '='.repeat((4 - base64.length % 4) % 4);
        const normalized = (base64 + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const binary = atob(normalized);

        return Uint8Array.from(
            binary,
            character => character.charCodeAt(0)
        );
    }

    function getCsrfToken() {
        return document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
    }

    async function saveSubscription(subscription) {
        const response = await fetch(subscribeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken() || '',
            },
            body: JSON.stringify(subscription.toJSON()),
        });

        if (!response.ok) {
            throw new Error('Não foi possível salvar a inscrição.');
        }
    }

    async function activateNotifications() {
        button.disabled = true;

        try {
            if (
                !window.isSecureContext ||
                !('serviceWorker' in navigator) ||
                !('PushManager' in window) ||
                !('Notification' in window)
            ) {
                setStatus('Este navegador não oferece suporte às notificações push.');
                return;
            }

            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setStatus('Permissão para notificações não concedida.');
                return;
            }

            const registration = await navigator.serviceWorker.ready;

            const keyResponse = await fetch(publicKeyUrl, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });

            if (!keyResponse.ok) {
                throw new Error('Não foi possível obter a chave pública.');
            }

            const data = await keyResponse.json();

            if (!data.publicKey) {
                throw new Error('A chave pública não está configurada.');
            }

            let subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64ToUint8Array(data.publicKey),
                });
            }

            await saveSubscription(subscription);

            button.textContent = 'Notificações ativadas';
            setStatus('Este dispositivo está cadastrado para receber alertas.');
        } catch (error) {
            console.error('[GoodWe Push]', error);
            setStatus('Erro ao ativar notificações. Verifique o navegador.');
        } finally {
            button.disabled = false;
        }
    }

    button.addEventListener('click', activateNotifications);
})();
