<x-mail::message>
# New reservation

**{{ $reservation->buyer?->name ?? 'A buyer' }}** reserved produce totaling **₱{{ $reservation->total }}**.

Open the AniHow app to mark it ready for pickup.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
