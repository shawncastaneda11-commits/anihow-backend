@php
    $percent = (float) $getState();
    $label = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.').'%';
@endphp

<div class="anihow-share">
    <span class="anihow-bar anihow-bar-thin" role="img" aria-label="{{ $label }} sold">
        <span class="anihow-bar-part anihow-tone-primary" style="width: {{ $percent }}%"></span>
    </span>
    <span class="anihow-share-label">{{ $label }}</span>
</div>
