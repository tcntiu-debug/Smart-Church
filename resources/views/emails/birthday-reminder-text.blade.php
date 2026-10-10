BIRTHDAY REMINDER — {{ $campusName }} Campus
Welcome Center Department
=========================================

@if ($daysBefore <= 0)
{{ count($birthdays) }} {{ count($birthdays) === 1 ? 'member is' : 'members are' }} celebrating {{ count($birthdays) === 1 ? 'a birthday' : 'birthdays' }} TODAY ({{ date('l, d M Y', strtotime($birthdayDate)) }}).
@elseif ($daysBefore === 1)
{{ count($birthdays) }} {{ count($birthdays) === 1 ? 'member has' : 'members have' }} a birthday TOMORROW ({{ date('l, d M Y', strtotime($birthdayDate)) }}).
@else
{{ count($birthdays) }} {{ count($birthdays) === 1 ? 'member has' : 'members have' }} a birthday in {{ $daysBefore }} DAYS ({{ date('l, d M Y', strtotime($birthdayDate)) }}).
@endif

@foreach ($birthdays as $index => $birthday)
{{ $index + 1 }}. {{ $birthday['name'] }}
   Department : {{ !empty($birthday['departments']) ? implode(', ', $birthday['departments']) : '—' }}
   Phone      : {{ $birthday['phone'] ?: '—' }}
   Date       : {{ $birthday['birthday_label'] }}
@if (!empty($birthday['whatsapp_url']))
   Wish link  : {{ $birthday['whatsapp_url'] }}
@endif

@endforeach
Every birthday is listed on the Birthdays page: {{ url('/birthdays') }}

--
Sent automatically by Smart-Church to the Welcome Center admins
({{ ucfirst(config('birthday.timezone', 'Africa/Lagos')) }} schedule).
