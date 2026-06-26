<x-mail::message>
# Nachricht von {{ $company->name }}

{!! nl2br(e($bodyText)) !!}

---

<x-mail::button :url="$optOutUrl">
Keine weiteren Nachrichten erhalten
</x-mail::button>

Mit freundlichen Grüßen,<br>
{{ $company->name }}
@if($company->email)
<br>{{ $company->email }}
@endif
@if($company->phone)
<br>{{ $company->phone }}
@endif
</x-mail::message>
