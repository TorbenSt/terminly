<x-mail::message>
# Terminvorschläge

Guten Tag,

für Ihren anstehenden Wartungstermin haben wir **3 Terminvorschläge** für Sie:

@foreach($proposal->options() as $number => $slot)
**Option {{ $number }}:** {{ $slot->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr
@endforeach

<x-mail::button :url="$responseUrl">
Termin auswählen oder ablehnen
</x-mail::button>

Mit freundlichen Grüßen,<br>
{{ config('app.name') }}
</x-mail::message>
