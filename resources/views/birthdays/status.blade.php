@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h6 class="mb-1"><i class="fas fa-birthday-cake"></i> Birthday reminder status</h6>
            <small class="text-muted">
                What today's digest would send &middot; {{ $today->format('D, d M Y') }} ({{ $timezone }})
            </small>
        </div>
        <div class="mt-2 mt-sm-0">
            <a href="{{ route('birthdays.status') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-sync-alt"></i> Refresh preview
            </a>
            <a href="{{ route('birthdays.index') }}" class="btn btn-sm btn-secondary">Birthdays</a>
        </div>
    </div>
    <div class="ms-panel-body">
        @if(count($warnings) > 0)
            <div class="alert alert-warning">
                <h6 class="mb-2"><i class="fas fa-exclamation-triangle"></i> Needs attention</h6>
                <ul class="mb-0 pl-3">
                    @foreach($warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                The scheduler logged a run today, mail has a real transport and the digest has recipients.
            </div>
        @endif

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-muted mb-2">Schedule</h6>
                    <p class="mb-1">
                        Daily at <strong>{{ config('birthday.send_at', '07:00') }}</strong> ({{ $timezone }})
                    </p>
                    <p class="mb-1">
                        Milestones: <strong>{{ implode(', ', $milestones) }}</strong> day(s) before
                        <span class="text-muted">(BIRTHDAY_DAYS)</span>
                    </p>
                    <p class="mb-0">
                        Channels: <strong>{{ implode(', ', $channels) }}</strong>
                    </p>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-muted mb-2">Log file</h6>
                    <p class="mb-1"><code>{{ $logPath }}</code></p>
                    @if($logExists)
                        <p class="mb-0">
                            Last written
                            <strong>{{ $logModified ? $logModified->format('D, d M Y g:i A') : 'unknown' }}</strong>
                            <span class="badge {{ $logModified && $logModified->isToday() ? 'badge-success' : 'badge-warning' }}">
                                {{ $logModified && $logModified->isToday() ? 'today' : 'not today' }}
                            </span>
                        </p>
                    @else
                        <p class="mb-0 text-danger">Not created yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <h6 class="mt-4">Tables</h6>
        <div class="mb-3">
            @foreach($tables as $table => $exists)
                <span class="badge {{ $exists ? 'badge-success' : 'badge-danger' }} mr-2 mb-1">
                    <i class="fas {{ $exists ? 'fa-check' : 'fa-times' }}"></i> {{ $table }}
                </span>
            @endforeach
        </div>

        <h6 class="mt-4">Preview (dry run)</h6>
        <p class="text-muted small mb-2">
            Produced by <code>php artisan birthdays:remind --dry-run</code>: it resolves today's birthdays,
            recipients and channels but sends nothing and writes no log row.
        </p>
        <pre class="bg-light border rounded p-3 mb-4" style="max-height:420px;overflow:auto;white-space:pre-wrap;">{{ $dryRun }}</pre>

        <h6 class="mt-4">Recipients ({{ count($recipients) }}, {{ $emailableRecipients }} with an e-mail)</h6>
        @if(count($recipients) > 0)
            <div class="table-responsive mb-4">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>E-mail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recipients as $recipient)
                            <tr>
                                <td>{{ $recipient['name'] }}</td>
                                <td>{{ $recipient['role'] }}</td>
                                <td>
                                    @if(filter_var($recipient['email'], FILTER_VALIDATE_EMAIL))
                                        {{ $recipient['email'] }}
                                    @else
                                        <span class="text-danger">missing / invalid</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-danger">
                Nobody matches <code>birthday.recipient_roles</code> in the configured department(s), so the digest has
                no audience. Check <code>config/birthday.php</code>.
            </div>
        @endif

        <h6 class="mt-4">Recent digests</h6>
        @if($recentLogs->count() > 0)
            <div class="table-responsive mb-4">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Sent on</th>
                            <th>Birthday</th>
                            <th>Milestone</th>
                            <th>Birthdays</th>
                            <th>Recipients</th>
                            <th>Channels</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentLogs as $log)
                            <tr>
                                <td>{{ $log->sent_on }}</td>
                                <td>{{ $log->reminder_date }}</td>
                                <td>{{ $log->days_before }} day(s)</td>
                                <td>{{ $log->birthday_count }}</td>
                                <td>{{ $log->recipient_count }}</td>
                                <td>{{ $log->channels }}</td>
                                <td>
                                    <span class="badge {{ $log->status === 'sent' ? 'badge-success' : 'badge-danger' }}">
                                        {{ $log->status }}
                                    </span>
                                </td>
                            </tr>
                            @if(!empty($log->message))
                                <tr>
                                    <td colspan="7" class="text-muted small">{{ $log->message }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">No digest has been recorded yet.</p>
        @endif

        <h6 class="mt-4">Log tail</h6>
        @if(count($logTail) > 0)
            <pre class="bg-light border rounded p-3 mb-4" style="max-height:320px;overflow:auto;white-space:pre-wrap;">{{ implode("\n", $logTail) }}</pre>
        @else
            <p class="text-muted">Nothing to show.</p>
        @endif

        <h6 class="mt-4">Configuration</h6>
        <div class="table-responsive mb-4">
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <th style="width:240px;">Digest mailer</th>
                        <td>
                            <code>{{ $digestMailer }}</code>
                            @if(trim((string) config('birthday.mailer', '')) !== '')
                                <span class="text-muted">(BIRTHDAY_MAIL_MAILER)</span>
                            @else
                                <span class="text-muted">(default - MAIL_MAILER)</span>
                            @endif
                            <br>
                            <span class="text-muted">
                                {{ $digestTransport['host'] ?? 'n/a' }}:{{ $digestTransport['port'] ?? 'n/a' }}
                                @if(!empty($digestTransport['encryption']))
                                    &middot; {{ $digestTransport['encryption'] }}
                                @endif
                                @if(!empty($digestTransport['transport']))
                                    &middot; {{ $digestTransport['transport'] }} transport
                                @endif
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>From</th>
                        <td>
                            {{ config('birthday.from.name') ?: config('mail.from.name') }}
                            &lt;{{ config('birthday.from.address') ?: 'not set' }}&gt;
                        </td>
                    </tr>
                    <tr>
                        <th>Recipient roles</th>
                        <td>{{ implode(', ', (array) config('birthday.recipient_roles', [])) }}</td>
                    </tr>
                    <tr>
                        <th>Department ids</th>
                        <td>{{ implode(', ', (array) config('birthday.department_ids', [])) }}</td>
                    </tr>
                    <tr>
                        <th>Extra e-mails</th>
                        <td>{{ implode(', ', (array) config('birthday.extra_emails', [])) ?: 'none' }}</td>
                    </tr>
                    <tr>
                        <th>Telegram</th>
                        <td>
                            @if(trim((string) config('birthday.telegram.bot_token', '')) !== '')
                                token set,
                                {{ count(array_filter((array) config('birthday.telegram.chat_ids', []))) }} chat id(s)
                            @else
                                not configured
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h6 class="mt-4">If today's run never happened</h6>
        <p class="text-muted small mb-2">
            The digest is scheduled by the framework, so the host must run Laravel's scheduler every minute
            (see <code>docs/CPANEL-DEPLOYMENT.md</code>):
        </p>
        <pre class="bg-light border rounded p-3 mb-3" style="white-space:pre-wrap;">* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</pre>
        <p class="text-muted small mb-2">Run it by hand once (safe to repeat - a milestone is only sent once):</p>
        <pre class="bg-light border rounded p-3 mb-0" style="white-space:pre-wrap;">cd {{ base_path() }}
php artisan birthdays:remind --dry-run   # what would go out
php artisan birthdays:remind             # send it now</pre>
    </div>
</div>
@endsection

