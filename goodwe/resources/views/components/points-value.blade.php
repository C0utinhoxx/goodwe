@props(['transaction'])
@php
    $hasRecordedPoints = preg_match('/(\d+)\s+pontos\b/ui', (string) $transaction->description, $recordedPoints) === 1;
@endphp
@if($hasRecordedPoints)
    {{ $transaction->status === 'approved' ? ($transaction->type === 'points_redeem' ? '−' : '+') : '' }} {{ number_format((int) $recordedPoints[1], 0, ',', '.') }} pts
@else
    Quantidade de pontos não registrada
@endif
