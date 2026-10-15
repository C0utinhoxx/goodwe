function setupWebCharging() {
    const panel = document.querySelector('[data-web-charging]');
    if (!panel) return;
    const select = panel.querySelector('[data-charging-speed-select]');
    const feedback = panel.querySelector('[data-charging-feedback]');
    const money = value => Number(value).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
    let busy = false;
    let stopped = false;
    let speed = panel.dataset.chargingSpeed;
    const advance = async (newSpeed = null) => {
        if (busy || stopped || document.hidden) return;
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
            speed = String(session.simulation.speed);
            select.value = speed;
            document.querySelectorAll('[data-charging-energy]').forEach(node => { node.textContent = `${Number(session.measured_energy_kwh).toLocaleString('pt-BR', {minimumFractionDigits: 4})} kWh`; });
            document.querySelectorAll('[data-charging-amount]').forEach(node => { node.textContent = money(session.original_amount); });
            const seconds = Number(session.simulation.elapsed_seconds);
            panel.querySelector('[data-charging-elapsed]').textContent = [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60].map(n => String(n).padStart(2, '0')).join(':');
            feedback.textContent = `Teste em andamento em ${speed}×. Consumo e valor calculados pelo servidor; nenhum débito é feito antes da confirmação do pagamento.`;
            if (session.charging_state === 'ended') {
                stopped = true;
                window.location.reload();
            }
        } catch (error) {
            select.value = speed;
            feedback.textContent = error.message;
        } finally {
            busy = false;
            select.disabled = stopped;
        }
    };
    select.addEventListener('change', () => advance(select.value));
    panel.querySelector('[data-charging-speed-form]').addEventListener('submit', event => { event.preventDefault(); advance(select.value); });
    panel.querySelector('[data-charging-end]').addEventListener('submit', () => { stopped = true; });
    window.addEventListener('pagehide', () => { stopped = true; clearInterval(timer); });
    const timer = setInterval(() => advance(), 2000);
    advance();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupWebCharging);
else setupWebCharging();
