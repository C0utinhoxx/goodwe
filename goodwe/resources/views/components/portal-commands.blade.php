<dialog class="pt-dialog gw-command-dialog" data-commands-dialog aria-labelledby="command-title">
    <div class="gw-command-heading"><div><p class="pt-eyebrow">{{ __('screen.acesso_rapido') }}</p><h2 id="command-title">{{ __('screen.onde_voce_quer_ir') }}</h2></div><button type="button" class="pt-dialog-close" data-commands-close aria-label="Fechar busca"><i data-lucide="x" aria-hidden="true"></i></button></div>
    <label class="gw-command-search"><i data-lucide="search" aria-hidden="true"></i><span class="sr-only">{{ __('screen.buscar_pagina_ou_acao') }}</span><input type="search" data-commands-search placeholder="Digite o nome de uma página ou ação…" autocomplete="off" aria-controls="command-results" autofocus></label>
    <p class="gw-command-status" data-commands-status role="status"></p>
    <nav id="command-results" class="gw-command-results" data-commands-results aria-label="Resultados da busca"></nav>
    <div class="gw-command-footer"><span>{{ __('screen.para_navegar_enter_para_abrir') }}</span><span>{{ __('screen.esc_para_fechar') }}</span></div>
</dialog>
