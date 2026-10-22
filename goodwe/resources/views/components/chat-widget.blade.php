
@props(['manager' => false, 'history' => []])

@php
    $title = $manager
        ? __('chatui.manager_title')
        : __('chatui.user_title');

    $welcome = $manager
        ? __('chatui.manager_welcome')
        : __('chatui.user_welcome');

    $description = $manager
        ? __('chatui.manager_description')
        : __('chatui.user_description');

    $chatRoute = $manager ? 'manager.chatbot' : 'user.chatbot';
@endphp

<div
    class="pt-chat"
    data-chat-widget
    data-chat-role="{{ $manager ? 'manager' : 'user' }}"
    data-welcome="{{ $welcome }}"
    @unless($manager)
        data-history-url="{{ route('user.chatbot.history') }}"
    @endunless
    data-chat-url="{{ route($chatRoute) }}"
    data-clear-url="{{ route($chatRoute.'.clear') }}"
    data-tickets-url="{{ route('tickets.index') }}"
    data-ticket-translations="{{ e(json_encode([
        'tabs_aria' => __('chatui.ticket_tabs_aria'),
        'tickets_tab' => __('chatui.ticket_tickets_tab'),
        'tickets_open_hint' => __('chatui.ticket_tickets_open_hint'),
        'tickets_back' => __('chatui.ticket_tickets_back'),
        'tickets_reply_label' => __('chatui.ticket_tickets_reply_label'),
        'tickets_reply_placeholder' => __('chatui.ticket_tickets_reply_placeholder'),
        'tickets_send' => __('chatui.ticket_tickets_send'),
        'status_pending' => __('chatui.ticket_status_pending'),
        'status_progress' => __('chatui.ticket_status_progress'),
        'status_resolved' => __('chatui.ticket_status_resolved'),
        'status_unknown' => __('chatui.ticket_status_unknown'),
        'ticket_heading' => __('chatui.ticket_ticket_heading'),
        'initial_message' => __('chatui.ticket_initial_message'),
        'participant' => __('chatui.ticket_participant'),
        'resolved_notice' => __('chatui.ticket_resolved_notice'),
        'open_error' => __('chatui.ticket_open_error'),
        'loading' => __('chatui.ticket_loading'),
        'empty' => __('chatui.ticket_empty'),
        'fallback_subject' => __('chatui.ticket_fallback_subject'),
        'pagination' => __('chatui.ticket_pagination'),
        'load_error' => __('chatui.ticket_load_error'),
        'send_error' => __('chatui.ticket_send_error'),
    ], JSON_UNESCAPED_UNICODE)) }}"
>
    <section
        id="pt-chat-panel"
        class="pt-chat-panel"
        role="dialog"
        aria-modal="false"
        aria-labelledby="pt-chat-title"
        hidden
    >
        <div class="pt-chat-head">
            <span class="pt-chat-bot">
                <i data-lucide="bot"></i>
            </span>

            <div>
                <strong id="pt-chat-title">{{ $title }}</strong>
                <small>{{ $description }}</small>
            </div>

            <button
                type="button"
                class="pt-chat-icon"
                data-chat-close
                aria-label="{{ __('chatui.close') }}"
            >
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="gw-chat-tabs" role="tablist" aria-label="{{ __('chatui.ticket_tabs_aria') }}">
            <button type="button" role="tab" id="gw-chat-tab-ia" aria-controls="gw-chat-view-ia" aria-selected="true" data-chat-tab="ia">Chatbot</button>
            <button type="button" role="tab" id="gw-chat-tab-tickets" aria-controls="gw-chat-view-tickets" aria-selected="false" data-chat-tab="tickets">{{ __('chatui.ticket_tickets_tab') }}</button>
        </div>
        <div id="gw-chat-view-ia" data-chat-ia-view role="tabpanel" aria-labelledby="gw-chat-tab-ia">
        <div
            class="pt-chat-messages"
            data-chat-messages
            role="log"
            aria-live="polite"
            aria-relevant="additions"
        >
            <div class="pt-chat-message bot">{{ $welcome }}</div>
        </div>

        <div class="pt-chat-foot">
            <form data-chat-form class="pt-chat-form">
                <label for="pt-chat-input" class="sr-only">
                    {{ __('chatui.your_message') }}
                </label>

                <input
                    id="pt-chat-input"
                    data-chat-input
                    name="message"
                    maxlength="1000"
                    autocomplete="off"
                    placeholder="{{ __('chatui.placeholder') }}"
                    required
                >

                <button
                    type="submit"
                    data-chat-send
                    aria-label="{{ __('chatui.send') }}"
                >
                    <i data-lucide="send"></i>
                </button>
            </form>

            <button type="button" class="pt-chat-reset" data-chat-clear>
                {{ __('chatui.new_conversation') }}
            </button>

            <p class="pt-chat-disclaimer">
                {{ __('chatui.disclaimer') }}
            </p>
        </div>
        </div>
        <div id="gw-chat-view-tickets" data-chat-tickets-view role="tabpanel" aria-labelledby="gw-chat-tab-tickets" hidden>
            <div class="gw-chat-tickets-list" data-chat-tickets-list>
                <p data-chat-tickets-notice role="status">{{ __('chatui.ticket_tickets_open_hint') }}</p>
                <div data-chat-tickets-items></div>
            </div>
            <div class="gw-chat-ticket-detail" data-chat-ticket-detail hidden>
                <button type="button" data-chat-tickets-back class="gw-chat-ticket-back">← {{ __('chatui.ticket_tickets_back') }}</button>
                <strong data-chat-ticket-heading></strong>
                <small data-chat-ticket-status></small>
                <div class="gw-chat-ticket-messages" data-chat-ticket-messages role="log" aria-live="polite"></div>
                <form data-chat-ticket-reply-form>
                    <label for="gw-chat-ticket-reply">{{ __('chatui.ticket_tickets_reply_label') }}</label>
                    <textarea id="gw-chat-ticket-reply" data-chat-ticket-reply rows="2" maxlength="10000" required placeholder="{{ __('chatui.ticket_tickets_reply_placeholder') }}"></textarea>
                    <button type="submit" data-chat-ticket-send>{{ __('chatui.ticket_tickets_send') }}</button>
                </form>
                <p data-chat-ticket-notice role="status"></p>
            </div>
        </div>
    </section>

    @if($manager)
        <script type="application/json" data-chat-initial-history>
            @json($history)
        </script>
    @endif

    <button
        type="button"
        class="pt-chat-launcher"
        data-chat-open
        aria-label="{{ $manager ? __('chatui.open_manager') : __('chatui.open_user') }}"
        aria-controls="pt-chat-panel"
        aria-expanded="false"
    >
        <i data-lucide="message-circle"></i>
        <span>{{ __('chatui.need_help') }}</span>
    </button>
</div>

<style>
    .gw-chat-tabs { display:flex; gap:4px; padding:8px; border-bottom:1px solid #8885; }
    .gw-chat-tabs button { flex:1; border-radius:8px; padding:8px 4px; font-size:12px; font-weight:600; cursor:pointer; }
    .gw-chat-tabs button[aria-selected="true"] { background:#e53935; color:#fff; }
    #gw-chat-view-ia[hidden], #gw-chat-view-tickets[hidden], .gw-chat-ticket-detail[hidden], .gw-chat-tickets-list[hidden] { display:none!important; }
    #gw-chat-view-ia { min-height:0; display:flex; flex:1; flex-direction:column; }
    #gw-chat-view-tickets { padding:12px; overflow-y:auto; flex:1; min-height:0; }
    .gw-chat-tickets-list { display:grid; gap:8px; }
    [data-chat-tickets-items] { display:grid; gap:8px; }
    .gw-chat-ticket-item { display:grid; gap:5px; text-align:left; border:1px solid #8885; border-radius:9px; padding:10px; width:100%; cursor:pointer; }
    .gw-chat-ticket-item:hover { border-color:#e53935; }
    .gw-chat-ticket-item small, .gw-chat-ticket-detail small { opacity:.7; }
    .gw-chat-ticket-detail { display:grid; gap:10px; }
    .gw-chat-ticket-back { color:#e53935; text-align:left; font-size:13px; }
    .gw-chat-ticket-messages { display:grid; gap:8px; max-height:230px; overflow-y:auto; }
    .gw-chat-ticket-message { border-radius:10px; background:#8882; padding:10px; white-space:pre-wrap; overflow-wrap:anywhere; }
    .gw-chat-ticket-message small { display:block; margin-bottom:4px; }
    [data-chat-ticket-reply-form] { display:grid; gap:6px; }
    [data-chat-ticket-reply-form][hidden] { display:none!important; }
    [data-chat-ticket-reply] { width:100%; border:1px solid #8886; border-radius:8px; padding:8px; color:inherit; background:transparent; }
    [data-chat-ticket-send] { background:#e53935; color:#fff; border-radius:8px; padding:8px; }
    [data-chat-tickets-notice], [data-chat-ticket-notice] { font-size:12px; }
</style>
