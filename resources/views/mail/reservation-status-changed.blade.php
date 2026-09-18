<x-mail::message>
# Reservation update

Your reservation is now **{{ $reservation->status?->label() ?? $reservation->status?->value }}**.

@if ($reservation->total)
Total: **₱{{ $reservation->total }}**
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
