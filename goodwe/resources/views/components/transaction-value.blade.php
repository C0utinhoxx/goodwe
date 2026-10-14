@props(['transaction'])
@php
    $confirmed = $transaction->status === 'approved';
    $pointsCredit = $transaction->type === 'cashback';
    $debit = $transaction->type === 'charge_payment';
@endphp
<span class="{{ $confirmed && !$debit ? 'pt-positive' : '' }}">
    @if($pointsCredit)
        <x-points-value :transaction="$transaction" />
    @else
        {{ $confirmed ? ($debit ? '−' : '+') : '' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
    @endif
</span>
