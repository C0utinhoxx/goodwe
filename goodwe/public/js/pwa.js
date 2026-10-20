/*
 * GoodWe PWA - registro do Service Worker + modo fullscreen app-like.
 *
 * O Service Worker so funciona em contexto seguro (HTTPS ou localhost).
 * Quando o PWA esta instalado e rodando em standalone, adicionamos a classe
 * `pt-fullscreen` no <html> para ocupar toda a tela sem bordas.
 */

(function () {
    'use strict';

    var SW_URL = '/sw.js';
    var SCOPE = '/';

    var state = {
        supported: false,
        registered: false,
        secureContext: false,
        standalone: false,
        error: null,
    };

    window.GoodWePWA = state;

    function isLocalDev() {
        var host = window.location.hostname;
        return host === 'localhost' || host === '127.0.0.1' || host === '[::1]' || host === '';
    }

    /* Detecta se o PWA esta rodando em modo standalone (instalado). */
    function detectStandalone() {
        if (window.matchMedia('(display-mode: standalone)').matches) return true;
        if (window.navigator.standalone === true) return true; // iOS Safari
        return false;
    }

    /* Ativa o modo fullscreen sem bordas quando standalone. */
    function applyFullscreenMode() {
        if (detectStandalone()) {
            state.standalone = true;
            document.documentElement.classList.add('pt-fullscreen');
            console.log('[GoodWe PWA] Modo fullscreen standalone ativado.');
        }
    }

    // Aplica o modo fullscreen imediatamente e tambem em mudancas de display
    applyFullscreenMode();
    window.matchMedia('(display-mode: standalone)').addEventListener('change', applyFullscreenMode);

    if (!('serviceWorker' in navigator)) {
        return;
    }

    if (!window.isSecureContext && !isLocalDev()) {
        return;
    }

    state.supported = true;
    state.secureContext = window.isSecureContext;

    function register() {
        navigator.serviceWorker.register(SW_URL, { scope: SCOPE })
            .then(function (registration) {
                state.registered = true;
                state.scope = registration.scope;
                console.log('[GoodWe PWA] Service Worker registrado em', registration.scope);

                if (registration.waiting) {
                    console.log('[GoodWe PWA] Nova versao disponivel, sera aplicada no proximo carregamento.');
                }

                registration.addEventListener('updatefound', function () {
                    var installing = registration.installing;
                    if (!installing) return;
                    installing.addEventListener('statechange', function () {
                        if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[GoodWe PWA] Atualizacao instalada e aguardando a proxima visita.');
                        }
                    });
                });
            })
            .catch(function (error) {
                state.error = String(error);
                console.warn('[GoodWe PWA] Falha ao registrar o Service Worker:', error);
            });
    }

    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register);
    }

    var reloading = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (reloading) return;
        reloading = true;
        console.log('[GoodWe PWA] Service Worker assumiu o controle.');
    });
})();
