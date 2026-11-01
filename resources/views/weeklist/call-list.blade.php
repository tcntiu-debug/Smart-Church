{{--
    resources/views/weeklist/call-list.blade.php

    The weekly call list as a page: every caller with the first timers they were
    handed, and one pre-filled WhatsApp button each. Linked from /weeklist
    ("Call list & WhatsApp") after the callers have been picked or reassigned in
    the Weekly Call List modal, and reachable on its own at /weeklist/call-list
    with the same filters in the query string.

    It used to be a print window the modal opened: window.open() plus
    document.write() built it from JSON captured when /weeklist loaded, so a
    phone blocked the popup, the print dialog covered the links, and the list
    could be stale. Here the links are real anchors, so tapping one opens
    WhatsApp with the caller's message already typed.

    WeekListController::weekListData() supplies everything; the grouping itself
    (round-robin, gender-matched, manual overrides) lives in buildCallerData().
--}}
@extends('layouts.app')

@section('content')
@php
    // Sorted by caller name, with whoever nobody was handed last - the order the
    // printed list has always used.
    $callListGroups = $groupedForPrint;
    $callListNames = array_keys($callListGroups);
    sort($callListNames);

    if (in_array('Unassigned', $callListNames, true)) {
        $callListNames = array_values(array_diff($callListNames, ['Unassigned']));
        $callListNames[] = 'Unassigned';
    }

    $callListTotal = 0;
    foreach ($callListGroups as $callListPersons) {
        $callListTotal += count($callListPersons);
    }
@endphp

<style>
    /* Paper, not a panel: this page is read on a phone (tap the green button)
       and printed from a desk, so it stays white with dark text in both display
       modes and keeps its colours when printed (docs/DISPLAY-MODE.md). */
    .call-list-sheet {
        max-width: 900px;
        margin: 0 auto;
        padding: 22px 26px;
        background-color: #ffffff;
        border: 1px solid #e3e6f3;
        border-radius: 12px;
        color: #2f3350;
    }

    .call-list-head {
        text-align: center;
        margin-bottom: 20px;
    }

    .call-list-head h4 {
        margin: 0 0 4px;
        color: #2f3350;
    }

    .call-list-head p {
        margin: 0;
        color: #6c757d;
    }

    section.call-group {
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 1px dashed #e3e6f3;
    }

    section.call-group:last-of-type {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .call-group-name {
        margin: 0 0 6px;
        font-weight: 700;
        color: #2f3350;
    }

    /* One tap = WhatsApp with the caller's own message already written. */
    a.caller-wa {
        display: inline-block;
        margin-left: 6px;
        padding: 2px 10px;
        border-radius: 999px;
        background-color: #25d366;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.7;
        text-decoration: none;
        vertical-align: middle;
        white-space: nowrap;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    a.caller-wa:hover,
    a.caller-wa:focus {
        background-color: #1da851;
        color: #ffffff;
    }

    ul.call-people {
        margin: 0;
        padding-left: 18px;
    }

    ul.call-people li {
        margin-bottom: 2px;
        color: #2f3350;
    }

    .call-person-meta {
        color: #6c757d;
    }

    .call-list-foot {
        margin-top: 22px;
        text-align: center;
        font-size: 12px;
        color: #8a90b5;
    }

    @media (max-width: 575.98px) {
        .call-list-sheet {
            padding: 16px 14px;
            border: 0;
            border-radius: 0;
        }

        ul.call-people {
            padding-left: 16px;
        }
    }

    /* Print: the sheet and nothing else - the navigation, the toolbar and the
       notification bell stay off the paper. */
    @media print {
        #ms-side-nav,
        .ms-aside-overlay,
        .ms-navbar,
        .d-print-none {
            display: none !important;
        }

        .ms-content-wrapper {
            padding: 0 !important;
        }

        .call-list-sheet {
            max-width: none;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
        }

        body {
            background: #ffffff !important;
        }
    }
</style>


<div class="call-list">

    <div class="d-print-none mb-3">
        <a href="{{ route('weeklist.index', request()->query()) }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to week list
        </a>

        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print();">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <div class="call-list-sheet">
        <div class="call-list-head">
            <h4>Weekly Call List</h4>
            <p>{{ $page_title }} | Caller team: {{ $selectedTouchpoints ? implode(', ', $selectedTouchpoints) : 'No touch point available' }}</p>
            <p class="small mt-1">
                {{ $callListTotal }} first timer{{ $callListTotal === 1 ? '' : 's' }}
                across {{ count($callListNames) }} caller{{ count($callListNames) === 1 ? '' : 's' }}.
                Tap a caller's WhatsApp button to send them their list.
            </p>
        </div>

        @forelse($callListNames as $callerName)
            @php
                $callListPersons = $callListGroups[$callerName];
                $callerLink = $callerWhatsApp[$callerName] ?? null;
            @endphp

            <section class="call-group" data-caller="{{ $callerName }}">
                <h6 class="call-group-name">
                    {{ $callerName }}:
                    @if($callerLink && !empty($callerLink['url']))
                        <a class="caller-wa" href="{{ $callerLink['url'] }}" target="_blank" rel="noopener"
                           title="Send this call list to {{ $callerName }} on WhatsApp">
                            <i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp
                        </a>
                    @endif
                </h6>

                <ul class="call-people">
                    @foreach($callListPersons as $person)
                        <li data-name="{{ $person['name'] ?? '' }}"
                            data-phone="{{ $person['phone'] ?? '' }}"
                            data-gender="{{ $person['gender'] ?? '' }}"
                            data-guest-type="{{ $person['guest_type'] ?? '' }}"
                            data-age="{{ $person['age'] ?? '' }}">
                            <span class="call-person-name">{{ $person['name'] ?? '' }}</span>
                            <span class="call-person-phone">({{ $person['phone'] ?? '' }})</span>
                            <span class="call-person-meta">
                                - {{ $person['gender'] ?? '' }} - {{ $person['guest_type'] ?? '' }} - {{ $person['age'] ?? '' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i>
                Nobody is in this window yet, so there is no call list to send.
                Change the filters on the week list and open this page again.
            </div>
        @endforelse

        <div class="call-list-foot">
            Printed on {{ now()->format('n/j/Y, g:i:s A') }}
        </div>
    </div>
</div>
@endsection
