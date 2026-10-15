@props(['manager' => false, 'history' => []])
@php
    $title = $manager ? 'Assistente do gestor' : 'Assistente PowerTag';
    $welcome = $manager ? 'Olá! Sou o assistente do gestor. Como posso ajudar com seu estabelecimento?' : 'Olá! 👋 Sou o assistente PowerTag. Como posso ajudar?';
    $chatRoute = $manager ? 'manager.chatbot' : 'user.chatbot';
@endphp
<div class="pt-chat" data-chat-widget data-chat-role="{{ $manager ? 'manager' : 'user' }}" data-welcome="{{ $welcome }}" @unless($manager) data-history-url="{{ route('user.chatbot.history') }}" @endunless data-chat-url="{{ route($chatRoute) }}" data-clear-url="{{ route($chatRoute.'.clear') }}">
    <section id="pt-chat-panel" class="pt-chat-panel" role="dialog" aria-modal="false" aria-labelledby="pt-chat-title" hidden>
        <div class="pt-chat-head"><span class="pt-chat-bot"><i data-lucide="bot"></i></span><div><strong id="pt-chat-title">{{ $title }}</strong><small>{{ $manager ? 'Sua loja, seus carregadores e suas recargas' : 'Ajuda com recargas, carteira e pontos' }}</small></div><button type="button" class="pt-chat-icon" data-chat-close aria-label="Fechar conversa"><i data-lucide="x"></i></button></div>
        <div class="pt-chat-messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions"><div class="pt-chat-message bot">{{ $welcome }}</div></div>
        <div class="pt-chat-foot"><form data-chat-form class="pt-chat-form"><label for="pt-chat-input" class="sr-only">Sua mensagem</label><input id="pt-chat-input" data-chat-input name="message" maxlength="1000" autocomplete="off" placeholder="Digite sua dúvida..." required><button type="submit" data-chat-send aria-label="Enviar mensagem"><i data-lucide="send"></i></button></form><button type="button" class="pt-chat-reset" data-chat-clear>Iniciar nova conversa</button><p class="pt-chat-disclaimer">Respostas geradas por IA podem conter imprecisões.</p></div>
    </section>
    @if($manager)<script type="application/json" data-chat-initial-history>@json($history)</script>@endif
    <button type="button" class="pt-chat-launcher" data-chat-open aria-label="{{ $manager ? 'Abrir assistente do gestor' : 'Abrir assistente PowerTag' }}" aria-controls="pt-chat-panel" aria-expanded="false"><i data-lucide="message-circle"></i><span>Precisa de ajuda?</span></button>
</div>
