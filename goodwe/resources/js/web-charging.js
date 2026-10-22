function setupWebCharging() {
    const panel = document.querySelector('[data-web-charging]');
    if (!panel) return;
    const select = panel.querySelector('[data-charging-speed-select]');
    const feedback = panel.querySelector('[data-charging-feedback]');
    const elapsedNode = panel.querySelector('[data-charging-elapsed]');
    const money = value => Number(value).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});

    // Consultas ao servidor: em 1x o consumo muda devagar; em 300x cada
    // segundo real vale cinco minutos de recarga. O relógio local preenche
    // o intervalo entre consultas, então a tela não fica "congelada".
    const POLL_MS = {1: 5000, 60: 2000, 300: 1000};
    const RETRY_MS = 6000;
    const CLOCK_MS = 250;

    let state = {
        elapsed: Number(panel.dataset.chargingElapsed) || 0,
        clockAt: Date.parse(panel.dataset.chargingClockAt) || Date.now(),
        speed: Number(panel.dataset.chargingSpeed) || 1,
    };
    let busy = false;
    let stopped = false;
    let timer = null;

    const interval = () => POLL_MS[state.speed] ?? 2000;
    const elapsedNow = () => Math.min(86400, state.elapsed + Math.max(0, Date.now() - state.clockAt) / 1000 * state.speed);

    const paintElapsed = () => {
        const seconds = Math.floor(elapsedNow());
        elapsedNode.textContent = [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60]
            .map(n => String(n).padStart(2, '0')).join(':');
    };

    const apply = session => {
        state = {
            elapsed: Number(session.simulation.elapsed_seconds),
            clockAt: Date.parse(session.simulation.clock_at) || Date.now(),
            speed: Number(session.simulation.speed) || 1,
        };
        select.value = String(state.speed);
        document.querySelectorAll('[data-charging-energy]').forEach(node => { node.textContent = `${Number(session.measured_energy_kwh).toLocaleString('pt-BR', {minimumFractionDigits: 4})} kWh`; });
        document.querySelectorAll('[data-charging-amount]').forEach(node => { node.textContent = money(session.original_amount); });
        paintElapsed();
    };

    const schedule = delay => {
        clearTimeout(timer);
        if (!stopped) timer = setTimeout(tick, delay);
    };

    const advance = async (newSpeed = null) => {
        if (busy || stopped) return false;
        busy = true;
        select.disabled = true;
        try {
            const response = await fetch(panel.dataset.advanceUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(newSpeed === null ? {} : {speed: Number(newSpeed)})
            });
            if (!response.ok) throw new Error('Não foi possível atualizar a recarga. Confira sua conexão e tente novamente.');
            const {session} = await response.json();
            apply(session);
            if (session.charging_state === 'ended') {
                stopped = true;
                clearTimeout(timer);
                window.location.reload();
                return true;
            }
            feedback.textContent = `Teste em andamento em ${state.speed}×. Consumo e valor calculados pelo servidor; nenhum débito é feito antes da confirmação do pagamento.`;
            return true;
        } catch (error) {
            select.value = String(state.speed);
            feedback.textContent = error.message;
            return false;
        } finally {
            busy = false;
            select.disabled = stopped;
        }
    };

    const tick = async () => {
        if (stopped || busy) return;
        if (document.hidden) return; // retoma no visibilitychange
        const ok = await advance();
        schedule(ok ? interval() : RETRY_MS);
    };

    select.addEventListener('change', async () => {
        const ok = await advance(select.value);
        schedule(ok ? interval() : RETRY_MS);
    });
    panel.querySelector('[data-charging-speed-form]').addEventListener('submit', event => { event.preventDefault(); tick(); });
    panel.querySelector('[data-charging-end]').addEventListener('submit', () => { stopped = true; clearTimeout(timer); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    const clockTimer = setInterval(paintElapsed, CLOCK_MS);
    window.addEventListener('pagehide', () => {
        stopped = true;
        clearTimeout(timer);
        clearInterval(clockTimer);
    });

    paintElapsed();
    schedule(400);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupWebCharging);
else setupWebCharging();
