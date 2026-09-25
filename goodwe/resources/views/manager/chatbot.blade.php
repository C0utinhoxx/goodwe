<x-user-layout>
    <x-slot name="header">
        <div>
            <h1 class="goodwe-page-title">
                {{ __('chatbot.title') }}
            </h1>

            <p class="goodwe-page-subtitle">
                {{ __('chatbot.subtitle') }}
            </p>
        </div>
    </x-slot>

    <style>
        .goodwe-page-title {
            margin: 0;
            font-size: 26px;
            line-height: 1.2;
            font-weight: 700;
            color: #18181b;
        }

        .goodwe-page-subtitle {
            margin: 6px 0 0;
            font-size: 14px;
            color: #71717a;
        }

        .goodwe-chat-actions {
            width: 100%;
            max-width: 900px;
            margin: 0 auto 12px;
            display: flex;
            justify-content: flex-end;
        }

        .goodwe-new-chat {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            border: 1px solid #e4e4e7;
            border-radius: 12px;
            background: #fff;
            color: #3f3f46;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: .18s ease;
        }

        .goodwe-new-chat:hover {
            border-color: rgba(229, 57, 53, .55);
            color: #e53935;
            background: #fafafa;
        }

        .goodwe-new-chat svg {
            width: 17px;
            height: 17px;
        }

        .goodwe-chat {
            position: relative;
            width: 100%;
            max-width: 900px;
            height: calc(100vh - 215px);
            min-height: 600px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }

        .goodwe-messages {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 30px 24px 155px;
            scrollbar-width: thin;
        }

        .goodwe-welcome {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .goodwe-welcome-content {
            width: 100%;
            max-width: 680px;
            text-align: center;
        }

        .goodwe-logo {
            width: 52px;
            height: 52px;
            margin: 0 auto 20px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e53935;
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            box-shadow: 0 8px 25px rgba(229, 57, 53, .22);
        }

        .goodwe-welcome-title {
            margin: 0;
            font-size: 28px;
            line-height: 1.3;
            font-weight: 650;
            color: #18181b;
        }

        .goodwe-welcome-text {
            max-width: 560px;
            margin: 10px auto 0;
            font-size: 14px;
            line-height: 1.6;
            color: #71717a;
        }

        .goodwe-suggestions {
            margin-top: 32px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .goodwe-suggestion {
            min-height: 92px;
            padding: 16px 18px;
            border: 1px solid #e4e4e7;
            border-radius: 18px;
            background: #fff;
            color: #18181b;
            text-align: left;
            cursor: pointer;
            transition: .18s ease;
        }

        .goodwe-suggestion:hover {
            border-color: rgba(229, 57, 53, .65);
            background: #fafafa;
            transform: translateY(-1px);
        }

        .goodwe-suggestion-title {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 600;
            color: #18181b;
        }

        .goodwe-suggestion-description {
            display: block;
            font-size: 12px;
            line-height: 1.5;
            color: #71717a;
        }

        .goodwe-conversation {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
        }

        .goodwe-message-row {
            width: 100%;
            margin-bottom: 30px;
            display: flex;
        }

        .goodwe-message-row.user {
            justify-content: flex-end;
        }

        .goodwe-message-row.assistant {
            justify-content: flex-start;
            align-items: flex-start;
            gap: 13px;
        }

        .goodwe-user-message {
            max-width: 75%;
            padding: 11px 17px;
            border-radius: 21px;
            background: #eeeeef;
            color: #18181b;
            font-size: 15px;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .goodwe-avatar {
            width: 32px;
            height: 32px;
            flex: 0 0 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #e53935;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
        }

        .goodwe-assistant-message {
            flex: 1;
            min-width: 0;
            padding-top: 4px;
            color: #27272a;
            font-size: 15px;
            line-height: 1.75;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .goodwe-composer-area {
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            padding: 50px 20px 12px;
            background: linear-gradient(
                to bottom,
                rgba(253, 253, 252, 0),
                #fdfdfc 38%,
                #fdfdfc 100%
            );
        }

        .goodwe-form {
            width: 100%;
            max-width: 760px;
            margin: 0 auto;
        }

        .goodwe-composer {
            position: relative;
            display: flex;
            align-items: flex-end;
            min-height: 62px;
            padding: 8px 58px 8px 8px;
            border: 1px solid #d4d4d8;
            border-radius: 28px;
            background: #fff;
            box-shadow:
                0 2px 10px rgba(0, 0, 0, .05),
                0 8px 30px rgba(0, 0, 0, .04);
        }

        .goodwe-input {
            display: block;
            width: 100%;
            min-height: 44px;
            max-height: 150px;
            margin: 0;
            padding: 11px 12px;
            overflow-y: auto;
            resize: none;
            border: 0 !important;
            outline: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            color: #18181b !important;
            font-family: inherit;
            font-size: 15px;
            line-height: 22px;
        }

        .goodwe-input::placeholder {
            color: #a1a1aa !important;
            opacity: 1;
        }

        .goodwe-send {
            position: absolute;
            right: 10px;
            bottom: 10px;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e53935;
            color: #fff;
            cursor: pointer;
            transition: .18s ease;
        }

        .goodwe-send:hover:not(:disabled) {
            background: #c62828;
            transform: scale(1.03);
        }

        .goodwe-send:disabled {
            background: #d4d4d8;
            color: #71717a;
            cursor: default;
        }

        .goodwe-send svg {
            width: 20px;
            height: 20px;
        }

        .goodwe-disclaimer {
            margin: 8px 0 0;
            text-align: center;
            color: #a1a1aa;
            font-size: 11px;
        }

        .goodwe-loading {
            display: flex;
            gap: 5px;
            padding-top: 10px;
        }

        .goodwe-loading span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #a1a1aa;
            animation: goodwe-bounce 1s infinite alternate;
        }

        .goodwe-loading span:nth-child(2) {
            animation-delay: .15s;
        }

        .goodwe-loading span:nth-child(3) {
            animation-delay: .3s;
        }

        @keyframes goodwe-bounce {
            from {
                transform: translateY(0);
                opacity: .45;
            }

            to {
                transform: translateY(-5px);
                opacity: 1;
            }
        }

        html.dark .goodwe-new-chat {
            border-color: #34363d;
            background: #181a20;
            color: #d4d4d8;
        }

        html.dark .goodwe-new-chat:hover {
            border-color: rgba(229, 57, 53, .7);
            background: #202228;
            color: #ef5350;
        }

        html.dark .goodwe-page-title {
            color: #f7f7f8 !important;
        }

        html.dark .goodwe-page-subtitle {
            color: #a1a1aa !important;
        }

        html.dark .goodwe-welcome-title {
            color: #f7f7f8 !important;
        }

        html.dark .goodwe-welcome-text {
            color: #a1a1aa !important;
        }

        html.dark .goodwe-suggestion {
            border-color: #34363d;
            background: #181a20;
            color: #f7f7f8;
        }

        html.dark .goodwe-suggestion:hover {
            border-color: rgba(229, 57, 53, .7);
            background: #202228;
        }

        html.dark .goodwe-suggestion-title {
            color: #f7f7f8 !important;
        }

        html.dark .goodwe-suggestion-description {
            color: #a1a1aa !important;
        }

        html.dark .goodwe-user-message {
            background: #2a2c32;
            color: #f7f7f8 !important;
        }

        html.dark .goodwe-assistant-message {
            color: #ececef !important;
        }

        html.dark .goodwe-composer-area {
            background: linear-gradient(
                to bottom,
                rgba(15, 17, 23, 0),
                #0f1117 38%,
                #0f1117 100%
            );
        }

        html.dark .goodwe-composer {
            border-color: #3a3c43;
            background: #24262c;
            box-shadow:
                0 2px 12px rgba(0, 0, 0, .2),
                0 12px 35px rgba(0, 0, 0, .25);
        }

        html.dark .goodwe-input {
            background: transparent !important;
            color: #f7f7f8 !important;
            -webkit-text-fill-color: #f7f7f8 !important;
        }

        html.dark .goodwe-input::placeholder {
            color: #8b8d94 !important;
            -webkit-text-fill-color: #8b8d94 !important;
        }

        html.dark .goodwe-send:disabled {
            background: #41434a;
            color: #85878e;
        }

        html.dark .goodwe-disclaimer {
            color: #777981 !important;
        }

        @media (max-width: 700px) {
            .goodwe-chat {
                min-height: 500px;
                height: calc(100vh - 200px);
            }

            .goodwe-messages {
                padding-right: 8px;
                padding-left: 8px;
            }

            .goodwe-suggestions {
                grid-template-columns: 1fr;
            }

            .goodwe-user-message {
                max-width: 88%;
            }

            .goodwe-composer-area {
                padding-right: 4px;
                padding-left: 4px;
            }
        }
    </style>

    <div class="goodwe-chat-actions">
        <button
            id="newConversationButton"
            type="button"
            class="goodwe-new-chat"
        >
            <svg
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                stroke-width="1.8"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 4.5v15m7.5-7.5h-15"
                />
            </svg>

            <span>
                @switch(app()->getLocale())
                    @case('en')
                        New conversation
                        @break

                    @case('es')
                        Nueva conversación
                        @break

                    @default
                        Nova conversa
                @endswitch
            </span>
        </button>
    </div>

    <div class="goodwe-chat">

        <div id="chatMessages" class="goodwe-messages">

            <div
                id="welcomeScreen"
                class="goodwe-welcome"
                @if(!empty($history)) style="display: none;" @endif
            >
                <div class="goodwe-welcome-content">

                    <div class="goodwe-logo">
                        W
                    </div>

                    <h2 class="goodwe-welcome-title">
                        {{ __('chatbot.welcome') }}
                    </h2>

                    <p class="goodwe-welcome-text">
                        {{ __('chatbot.description') }}
                    </p>

                    <div class="goodwe-suggestions">

                        <button
                            type="button"
                            class="goodwe-suggestion"
                            data-question="{{ __('chatbot.suggestion_business_question') }}"
                        >
                            <span class="goodwe-suggestion-title">
                                📊 {{ __('chatbot.suggestion_business') }}
                            </span>

                            <span class="goodwe-suggestion-description">
                                {{ __('chatbot.suggestion_business_desc') }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="goodwe-suggestion"
                            data-question="{{ __('chatbot.suggestion_sessions_question') }}"
                        >
                            <span class="goodwe-suggestion-title">
                                ⚡ {{ __('chatbot.suggestion_sessions') }}
                            </span>

                            <span class="goodwe-suggestion-description">
                                {{ __('chatbot.suggestion_sessions_desc') }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="goodwe-suggestion"
                            data-question="{{ __('chatbot.suggestion_revenue_question') }}"
                        >
                            <span class="goodwe-suggestion-title">
                                💰 {{ __('chatbot.suggestion_revenue') }}
                            </span>

                            <span class="goodwe-suggestion-description">
                                {{ __('chatbot.suggestion_revenue_desc') }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="goodwe-suggestion"
                            data-question="{{ __('chatbot.suggestion_energy_question') }}"
                        >
                            <span class="goodwe-suggestion-title">
                                🔋 {{ __('chatbot.suggestion_energy') }}
                            </span>

                            <span class="goodwe-suggestion-description">
                                {{ __('chatbot.suggestion_energy_desc') }}
                            </span>
                        </button>

                    </div>
                </div>
            </div>

            <div
                id="conversation"
                class="goodwe-conversation"
                @if(empty($history)) style="display: none;" @endif
            >

                @foreach($history as $message)

                    @if($message['role'] === 'user')

                        <div class="goodwe-message-row user">
                            <div class="goodwe-user-message">
                                {{ $message['content'] }}
                            </div>
                        </div>

                    @elseif($message['role'] === 'assistant')

                        <div class="goodwe-message-row assistant">

                            <div class="goodwe-avatar">
                                W
                            </div>

                            <div class="goodwe-assistant-message">
                                {{ $message['content'] }}
                            </div>

                        </div>

                    @endif

                @endforeach

            </div>

        </div>

        <div class="goodwe-composer-area">

            <form id="chatForm" class="goodwe-form">

                @csrf

                <div class="goodwe-composer">

                    <textarea
                        id="chatInput"
                        class="goodwe-input"
                        rows="1"
                        maxlength="1000"
                        autocomplete="off"
                        placeholder="{{ __('chatbot.placeholder') }}"
                    ></textarea>

                    <button
                        id="sendButton"
                        class="goodwe-send"
                        type="submit"
                        disabled
                        aria-label="{{ __('chatbot.send') }}"
                        title="{{ __('chatbot.send') }}"
                    >
                        <svg
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            stroke-width="2.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 19V5m0 0-5 5m5-5 5 5"
                            />
                        </svg>
                    </button>

                </div>

                <p class="goodwe-disclaimer">
                    {{ __('chatbot.warning') }}
                </p>

            </form>

        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('chatForm');
            const input = document.getElementById('chatInput');
            const messages = document.getElementById('chatMessages');
            const conversation = document.getElementById('conversation');
            const welcome = document.getElementById('welcomeScreen');
            const sendButton = document.getElementById('sendButton');
            const suggestions = document.querySelectorAll('.goodwe-suggestion');
            const newConversationButton = document.getElementById('newConversationButton');

            let started = @json(!empty($history));

            function startConversation() {
                if (started) {
                    return;
                }

                started = true;
                welcome.style.display = 'none';
                conversation.style.display = 'block';
            }

            function updateButton() {
                sendButton.disabled = input.value.trim() === '';
            }

            function resizeInput() {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 150) + 'px';
            }

            function scrollBottom(animate = true) {
                requestAnimationFrame(function () {
                    messages.scrollTo({
                        top: messages.scrollHeight,
                        behavior: animate ? 'smooth' : 'auto'
                    });
                });
            }

            function addUserMessage(text) {
                const row = document.createElement('div');
                row.className = 'goodwe-message-row user';

                const bubble = document.createElement('div');
                bubble.className = 'goodwe-user-message';
                bubble.textContent = text;

                row.appendChild(bubble);
                conversation.appendChild(row);
            }

            function addAssistantMessage(text) {
                const row = document.createElement('div');
                row.className = 'goodwe-message-row assistant';

                const avatar = document.createElement('div');
                avatar.className = 'goodwe-avatar';
                avatar.textContent = 'W';

                const content = document.createElement('div');
                content.className = 'goodwe-assistant-message';
                content.textContent = text;

                row.appendChild(avatar);
                row.appendChild(content);

                conversation.appendChild(row);
            }

            function addLoading() {
                const row = document.createElement('div');
                row.id = 'goodweLoading';
                row.className = 'goodwe-message-row assistant';

                const avatar = document.createElement('div');
                avatar.className = 'goodwe-avatar';
                avatar.textContent = 'W';

                const loading = document.createElement('div');
                loading.className = 'goodwe-loading';
                loading.innerHTML = '<span></span><span></span><span></span>';

                row.appendChild(avatar);
                row.appendChild(loading);

                conversation.appendChild(row);
            }

            async function sendMessage(message) {
                const text = message.trim();

                if (!text || input.disabled) {
                    return;
                }

                startConversation();
                addUserMessage(text);

                input.value = '';
                resizeInput();
                updateButton();

                input.disabled = true;
                sendButton.disabled = true;

                addLoading();
                scrollBottom();

                try {
                    const response = await fetch(
                        '{{ route('manager.chatbot') }}',
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN':
                                    document.querySelector(
                                        '#chatForm input[name="_token"]'
                                    ).value
                            },
                            body: JSON.stringify({
                                message: text
                            })
                        }
                    );

                    const data = await response.json();

                    document
                        .getElementById('goodweLoading')
                        ?.remove();

                    if (response.ok) {
                        addAssistantMessage(
                            data.answer ||
                            @json(__('chatbot.no_answer'))
                        );
                    } else {
                        addAssistantMessage(
                            data.answer ||
                            @json(__('chatbot.error'))
                        );
                    }

                } catch (error) {
                    document
                        .getElementById('goodweLoading')
                        ?.remove();

                    addAssistantMessage(
                        @json(__('chatbot.connection_error'))
                    );
                }

                input.disabled = false;
                input.focus();

                updateButton();
                scrollBottom();
            }

            async function startNewConversation() {
                if (input.disabled) {
                    return;
                }

                newConversationButton.disabled = true;

                try {
                    const response = await fetch(
                        '{{ route('manager.chatbot.clear') }}',
                        {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN':
                                    document.querySelector(
                                        '#chatForm input[name="_token"]'
                                    ).value
                            }
                        }
                    );

                    if (!response.ok) {
                        throw new Error('Unable to clear conversation');
                    }

                    conversation.innerHTML = '';
                    conversation.style.display = 'none';
                    welcome.style.display = 'flex';

                    started = false;

                    input.value = '';
                    input.disabled = false;

                    resizeInput();
                    updateButton();

                    messages.scrollTo({
                        top: 0,
                        behavior: 'auto'
                    });

                    input.focus();

                } catch (error) {
                    alert(@json(
                        app()->getLocale() === 'en'
                            ? 'Could not start a new conversation.'
                            : (app()->getLocale() === 'es'
                                ? 'No fue posible iniciar una nueva conversación.'
                                : 'Não foi possível iniciar uma nova conversa.')
                    ));
                }

                newConversationButton.disabled = false;
            }

            newConversationButton.addEventListener('click', function () {
                startNewConversation();
            });

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                sendMessage(input.value);
            });

            input.addEventListener('input', function () {
                resizeInput();
                updateButton();
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage(input.value);
                }
            });

            suggestions.forEach(function (button) {
                button.addEventListener('click', function () {
                    sendMessage(button.dataset.question);
                });
            });

            resizeInput();
            updateButton();

            if (started) {
                scrollBottom(false);
            }
        });
    </script>

</x-user-layout>