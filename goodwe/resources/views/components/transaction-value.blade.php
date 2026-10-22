@props(['transaction'])
@php
    $confirmed = $transaction->status === 'approved';
    $pointsCredit = $transaction->type === 'cashback';
    $debit = in_array($transaction->type, ['charge_payment', 'reservation_fee'], true);
@endphp
<span class="{{ $confirmed && !$debit ? 'pt-positive' : '' }}">
    @if($pointsCredit)
        <x-points-value :transaction="$transaction" />
    @else
        {{ $confirmed ? ($debit ? '−' : '+') : '' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
    @endif
</span>
