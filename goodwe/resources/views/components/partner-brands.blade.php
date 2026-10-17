@props(['compact' => false])

<span {{ $attributes->class(['partner-brands', 'partner-brands-compact' => $compact]) }}>
    <img src="{{ asset('imagens/powertag-logo-preto.png') }}" alt="PowerTag" width="1254" height="1254" class="partner-brands-powertag partner-brands-light">
    <img src="{{ asset('imagens/powertag-logo-branco.png') }}" alt="PowerTag" width="1254" height="1254" class="partner-brands-powertag partner-brands-dark">
    <span class="partner-brands-divider" aria-hidden="true"></span>
    <img src="{{ asset('imagens/goodwe-logo.png') }}" alt="GoodWe" width="280" height="42" class="partner-brands-goodwe">
</span>
