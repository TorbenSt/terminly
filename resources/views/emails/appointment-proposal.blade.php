<x-mail::message>
# Terminvorschläge

Guten Tag,

@if($proposal->round > 1 && filled($negotiationFeedback ?? null))
vielen Dank für Ihre Rückmeldung. Auf Basis Ihres Wunsches „{{ $negotiationFeedback }}“ haben wir **3 neue Terminvorschläge** für Sie:
@else
für Ihren anstehenden Wartungstermin haben wir **3 Terminvorschläge** für Sie:
@endif

@foreach($proposal->options() as $number => $slot)
@if($proposal->round > 1 && $number === ($proposal->recommended_option ?? 1))
**Option {{ $number }} (Empfohlener Termin):** {{ $slot->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr
@else
**Option {{ $number }}:** {{ $slot->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr
@endif
@endforeach

<x-mail::button :url="$responseUrl">
Termin auswählen oder ablehnen
</x-mail::button>

Mit freundlichen Grüßen,<br>
{{ config('app.name') }}
</x-mail::message>
