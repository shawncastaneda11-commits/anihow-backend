<x-mail::message>
# Low stock

**{{ $listing->name }}** is down to **{{ $listing->quantity_available }} {{ $listing->unit?->value }}**.

Update the listing in the AniHow app when you have more produce.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
