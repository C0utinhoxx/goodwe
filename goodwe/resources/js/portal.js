function setupPortal() {
    const filters = document.querySelector('[data-charger-filters]');
    if (filters) {
        // Busca e filtros são resolvidos no servidor (a lista é paginada);
        // o JS só envia o formulário, com atraso na digitação da busca.
        const search = filters.querySelector('[data-charger-search]');
        const selects = [
            filters.querySelector('[data-charger-status]'),
            filters.querySelector('[data-charger-power]'),
        ];
        let searchTimer;
        search?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => filters.submit(), 400);
        });
        selects.forEach(select => select?.addEventListener('change', () => filters.submit()));
        filters.querySelector('[data-charger-reset]')?.addEventListener('click', () => {
            window.location.href = filters.action;
        });
    }

    const updateEstimate = dialog => {
        const estimate = dialog.querySelector('[data-estimate]');
        const duration = Number(dialog.querySelector('[data-duration]')?.value || 0);
        if (!estimate) return;
        const money = cents => (cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const original = duration * Number(estimate.dataset.minuteCents);
        const percent = Number(dialog.querySelector('[data-payment-coupon] option:checked')?.dataset.percent || 0);
        const discount = Math.min(original, Math.round(original * percent / 100));
        estimate.querySelector('[data-estimate-energy]').textContent = `${(duration * Number(estimate.dataset.energyHundredths) / 100).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} kWh`;
        estimate.querySelector('[data-estimate-price]').textContent = money(original);
        estimate.querySelector('[data-estimate-discount]').textContent = `− ${money(discount)} (${percent}% OFF)`;
        estimate.querySelector('[data-estimate-total]').textContent = money(original - discount);
    };
    document.querySelectorAll('[data-charger-open]').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.chargerOpen);
            dialog?.showModal();
            if (dialog) updateEstimate(dialog);
        });
    });
    document.querySelectorAll('[data-reward-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.rewardOpen)?.showModal()));
    document.querySelectorAll('[data-dialog-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog')?.close()));
    document.querySelectorAll('.pt-dialog').forEach(dialog => {
        dialog.querySelector('[data-payment-coupon]')?.addEventListener('change', () => updateEstimate(dialog));
        dialog.querySelector('[data-duration]')?.addEventListener('change', () => updateEstimate(dialog));
        dialog.querySelector('[data-vehicle-select]')?.addEventListener('change', event => {
            const plate = dialog.querySelector('[name="vehicle_plate"]');
            if (plate) plate.value = event.target.value;
        });
        dialog.addEventListener('click', event => {
            const box = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
        });
    });
    document.querySelectorAll('[data-set-amount]').forEach(button => button.addEventListener('click', () => {
        const amount = document.getElementById('deposit-amount');
        if (amount) { amount.value = button.dataset.setAmount; amount.focus(); }
    }));
    const amountInput = document.getElementById('deposit-amount');
    const mirrorAmount = document.querySelector('[data-amount-mirror]');
    const updatePresets = () => {
        document.querySelectorAll('[data-set-amount]').forEach(button => button.setAttribute('aria-pressed', String(Number(amountInput?.value) === Number(button.dataset.setAmount))));
        if (mirrorAmount && amountInput) mirrorAmount.value = amountInput.value;
    };
    amountInput?.addEventListener('input', updatePresets);
    document.querySelectorAll('[data-set-amount]').forEach(button => button.addEventListener('click', updatePresets));
    updatePresets();
    document.querySelector('[data-profile-theme]')?.addEventListener('click', () => document.getElementById('theme-toggle')?.click());
    const localTime = new Intl.DateTimeFormat('pt-BR', {dateStyle:'short', timeStyle:'medium'});
    document.querySelectorAll('time[data-local-time]').forEach(time => {
        const date = new Date(time.dateTime);
        if (!Number.isNaN(date.getTime())) time.textContent = localTime.format(date);
    });
    setupReservations();
    setupSessionPayments();
    setupChat();
}

function setupReservations() {
    document.querySelectorAll('[data-reservation-card]').forEach(card => {
        if (card.dataset.reservationStatus !== 'active') return;
        const countdown = card.querySelector('[data-reservation-countdown]');
        if (!countdown) return;
        const expires = Date.parse(card.dataset.expiresAt);
        const serverTime = Date.parse(card.dataset.serverTime);
        const began = performance.now();
        let checking = false;
        const tick = async () => {
            const seconds = Math.max(0, Math.ceil((expires - serverTime - (performance.now() - began)) / 1000));
            countdown.textContent = `${Math.floor(seconds / 60)}min ${String(seconds % 60).padStart(2, '0')}s`;
            if (seconds > 0 || checking) return;
            checking = true;
            clearInterval(timer);
            countdown.textContent = 'Prazo encerrado. Conferindo estado…';
            card.querySelectorAll('[data-reservation-cancel] button, [data-reservation-start] button').forEach(button => button.setAttribute('disabled', ''));
            try {
                const response = await fetch(card.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('state');
                const data = await response.json();
                card.dataset.reservationStatus = data.reservation.status;
                card.querySelector('[data-reservation-state]').textContent = ({ expired: 'Expirada por ausência', cancelled: 'Cancelada', started: 'Recarga iniciada', active: 'Reserva ativa' })[data.reservation.status] || 'Estado atualizado';
                countdown.textContent = data.reservation.status === 'expired' ? `Ausência registrada · ${Number(data.reservation.penalty_applied_points).toLocaleString('pt-BR', {minimumFractionDigits:2})} pontos descontados` : 'Estado atualizado. Recarregue a página para consultar os detalhes.';
            } catch { countdown.textContent = 'Prazo encerrado. Atualize a página para consultar o estado no servidor.'; }
        };
        const timer = setInterval(tick, 1000);
        tick();
    });
}

function setupSessionPayments() {
    document.querySelectorAll('[data-session-payment]').forEach(form => {
        const money = cents => (cents / 100).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
        const update = () => {
            const original = Number(form.dataset.originalCents);
            const percent = Number(form.querySelector('[data-payment-coupon] option:checked')?.dataset.percent || 0);
            const discount = Math.min(original, Math.round(original * percent / 100));
            form.querySelector('[data-session-discount]').textContent = `− ${money(discount)} (${percent}% OFF)`;
            form.querySelector('[data-session-total]').textContent = money(original - discount);
        };
        form.querySelector('[data-payment-coupon]')?.addEventListener('change', update);
        update();
    });
}

function setupChat() {
    const widget = document.querySelector('[data-chat-widget]');
    if (!widget) return;
    const panel = widget.querySelector('#pt-chat-panel');
    const launcher = widget.querySelector('[data-chat-open]');
    const messages = widget.querySelector('[data-chat-messages]');
    const form = widget.querySelector('[data-chat-form]');
    const input = widget.querySelector('[data-chat-input]');
    const send = widget.querySelector('[data-chat-send]');
    const clear = widget.querySelector('[data-chat-clear]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let loaded = false;
    let busy = false;
    const setBusy = active => {
        busy = active;
        send.disabled = active;
        clear.disabled = active;
        input.disabled = active;
        messages.setAttribute('aria-busy', String(active));
    };
    const addMessage = (text, role, error = false) => {
        const element = document.createElement('div');
        element.className = `pt-chat-message ${role}${error ? ' error' : ''}`;
        element.textContent = text;
        messages.append(element);
        messages.scrollTop = messages.scrollHeight;
        return element;
    };
    const request = async (url, options = {}) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 70000);
        try {
            const response = await fetch(url, { ...options, signal: controller.signal, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...options.headers } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw Error(data.answer || data.message || 'Serviço indisponível. Tente novamente.');
            return data;
        } finally { clearTimeout(timer); }
    };
    const toggle = async open => {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        if (!open) { launcher.focus(); return; }
        if (loaded || busy) { input.focus(); return; }
        setBusy(true);
        try {
            const initialHistory = widget.querySelector('[data-chat-initial-history]');
            const data = initialHistory ? { history: JSON.parse(initialHistory.textContent) } : await request(widget.dataset.historyUrl);
            (data.history || []).forEach(message => addMessage(String(message.content ?? ''), message.role === 'user' ? 'user' : 'bot'));
            loaded = true;
        } catch (_) { addMessage('Não foi possível recuperar o histórico. Tente abrir novamente ou inicie uma nova conversa.', 'bot', true); }
        finally { setBusy(false); if (!panel.hidden) input.focus(); }
    };
    launcher.addEventListener('click', () => toggle(panel.hidden));
    document.querySelector('[data-open-assistant]')?.addEventListener('click', () => toggle(true));
    widget.querySelector('[data-chat-close]')?.addEventListener('click', () => toggle(false));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) toggle(false); });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy) return;
        setBusy(true);
        input.value = '';
        addMessage(message, 'user');
        const loading = addMessage('Digitando...', 'bot');
        try {
            const data = await request(widget.dataset.chatUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ message }) });
            loading.textContent = data.answer || 'Não foi possível gerar uma resposta.';
        } catch (error) {
            loading.textContent = error.name === 'AbortError' ? 'O assistente demorou para responder. Tente novamente.' : error.message;
            loading.classList.add('error');
            input.value = message;
        } finally {
            setBusy(false);
            messages.scrollTop = messages.scrollHeight;
            if (!panel.hidden) input.focus();
        }
    });
    clear.addEventListener('click', async () => {
        if (busy) return;
        setBusy(true);
        try {
            await request(widget.dataset.clearUrl, { method: 'DELETE' });
            messages.replaceChildren();
            loaded = true;
            addMessage(widget.dataset.welcome, 'bot');
        } catch (_) { addMessage('Não foi possível reiniciar a conversa.', 'bot', true); }
        finally { setBusy(false); input.focus(); }
    });

    // Acompanhamento dos tickets na mesma janela, sem alterar a triagem da IA.
    const iaView = widget.querySelector('[data-chat-ia-view]');
    const ticketsView = widget.querySelector('[data-chat-tickets-view]');
    const ticketsList = widget.querySelector('[data-chat-tickets-list]');
    const ticketsItems = widget.querySelector('[data-chat-tickets-items]');
    const ticketsNotice = widget.querySelector('[data-chat-tickets-notice]');
    const ticketDetail = widget.querySelector('[data-chat-ticket-detail]');
    const ticketHeading = widget.querySelector('[data-chat-ticket-heading]');
    const ticketStatus = widget.querySelector('[data-chat-ticket-status]');
    const ticketMessages = widget.querySelector('[data-chat-ticket-messages]');
    const ticketReplyForm = widget.querySelector('[data-chat-ticket-reply-form]');
    const ticketReply = widget.querySelector('[data-chat-ticket-reply]');
    const ticketSend = widget.querySelector('[data-chat-ticket-send]');
    const ticketNotice = widget.querySelector('[data-chat-ticket-notice]');
    const ticketText = JSON.parse(widget.dataset.ticketTranslations || '{}');
    const translateTicket = key => ticketText[key] || key;
    const ticketLocale = document.documentElement.lang || 'pt-BR';
    let selectedTicketId = null;
    let ticketsBusy = false;

    const ticketStatusLabel = status => ({
        aguardando: translateTicket('status_pending'),
        em_atendimento: translateTicket('status_progress'),
        resolvido: translateTicket('status_resolved'),
    })[status] || status || translateTicket('status_unknown');

    const setTicketsBusy = value => {
        ticketsBusy = value;
        ticketSend.disabled = value;
        ticketReply.disabled = value;
        ticketsView.setAttribute('aria-busy', String(value));
    };

    const ticketsBase = widget.dataset.ticketsUrl.replace(/\/$/, '');
    const ticketUrl = id => `${ticketsBase}/${encodeURIComponent(id)}`;

    const openTicket = async id => {
        if (ticketsBusy) return;
        setTicketsBusy(true);
        ticketsNotice.textContent = '';
        ticketNotice.textContent = '';
        try {
            const data = await request(ticketUrl(id));
            selectedTicketId = id;
            const ticket = data.ticket || {};
            ticketHeading.textContent = translateTicket('ticket_heading').replace(':id', ticket.id);
            ticketStatus.textContent = ticketStatusLabel(ticket.status);
            ticketMessages.replaceChildren();
            const addTicketMessage = (author, content) => {
                const block = document.createElement('div');
                block.className = 'gw-chat-ticket-message';
                const label = document.createElement('small');
                label.textContent = author;
                const body = document.createElement('div');
                body.textContent = content || '';
                block.append(label, body);
                ticketMessages.append(block);
            };
            addTicketMessage(translateTicket('initial_message'), data.mensagem_inicial ?? ticket.mensagem);
            (data.mensagens || []).forEach(msg => addTicketMessage(msg.remetente_nome || translateTicket('participant'), msg.mensagem));
            ticketsList.hidden = true;
            ticketDetail.hidden = false;
            ticketReplyForm.hidden = ticket.status === 'resolvido';
            if (ticket.status === 'resolvido') ticketNotice.textContent = translateTicket('resolved_notice');
        } catch (error) {
            ticketsNotice.textContent = error.message || translateTicket('open_error');
        } finally {
            setTicketsBusy(false);
        }
    };

    const loadTickets = async () => {
        if (ticketsBusy) return;
        setTicketsBusy(true);
        ticketDetail.hidden = true;
        ticketsList.hidden = false;
        selectedTicketId = null;
        ticketsItems.replaceChildren();
        ticketsNotice.textContent = translateTicket('loading');
        try {
            const data = await request(ticketsBase);
            const tickets = data.tickets?.data || [];
            ticketsNotice.textContent = tickets.length
                ? ''
                : translateTicket('empty');
            tickets.forEach(ticket => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'gw-chat-ticket-item';
                const title = document.createElement('strong');
                title.textContent = `Ticket #${ticket.id} · ${ticketStatusLabel(ticket.status)}`;
                const summary = document.createElement('span');
                summary.textContent = ticket.motivo || ticket.mensagem || translateTicket('fallback_subject');
                const date = document.createElement('small');
                date.textContent = ticket.created_at
                    ? new Date(ticket.created_at).toLocaleString(ticketLocale)
                    : '';
                button.append(title, summary, date);
                button.addEventListener('click', () => openTicket(ticket.id));
                ticketsItems.append(button);
            });
            if (data.tickets?.next_page_url) {
                ticketsNotice.textContent = translateTicket('pagination');
            }
        } catch (error) {
            ticketsNotice.textContent = error.message || translateTicket('load_error');
        } finally {
            setTicketsBusy(false);
        }
    };

    widget.querySelectorAll('[data-chat-tab]').forEach(tab => {
        tab.addEventListener('click', () => {
            const showTickets = tab.dataset.chatTab === 'tickets';
            iaView.hidden = showTickets;
            ticketsView.hidden = !showTickets;
            widget.querySelectorAll('[data-chat-tab]').forEach(button => {
                button.setAttribute('aria-selected', String(button === tab));
            });
            if (showTickets) loadTickets();
            else input.focus();
        });
    });

    widget.querySelector('[data-chat-tickets-back]')?.addEventListener('click', loadTickets);

    ticketReplyForm?.addEventListener('submit', async event => {
        event.preventDefault();
        if (ticketsBusy || !selectedTicketId) return;
        const text = ticketReply.value.trim();
        if (!text) return;
        setTicketsBusy(true);
        ticketNotice.textContent = '';
        try {
            await request(`${ticketUrl(selectedTicketId)}/responder`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mensagem: text }),
            });
            ticketReply.value = '';
            const id = selectedTicketId;
            setTicketsBusy(false);
            await openTicket(id);
        } catch (error) {
            ticketNotice.textContent = error.message || translateTicket('send_error');
        } finally {
            setTicketsBusy(false);
        }
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupPortal);
else setupPortal();
