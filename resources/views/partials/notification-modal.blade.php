{{-- resources/views/partials/notification-modal.blade.php --}}
{{--
    The notification centre, in a panel: the bell in layouts/app.blade.php opens
    this instead of sending the member to /notifications, so they never lose the
    page they were working on. `.notif-dialog` docks the dialog to the right edge
    (Bootstrap 4.1 has no offcanvas component - see the CSS in layouts/app).

    The rows are filled by public/assets/js/notifications.js from
    /notifications/feed (the same JSON the layout polls), so opening the panel
    always shows the current list. Marking an alert read clears it, therefore the
    list only ever holds what still needs attention; the page at
    /notifications is the no-JS fallback and the place where browser alerts are
    switched on. See docs/NOTIFICATIONS.md.
--}}
<div class="modal fade" id="notificationsModal" tabindex="-1" role="dialog" aria-labelledby="notificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog notif-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header notif-modal-header">
                <h5 class="modal-title" id="notificationsModalLabel">
                    <i class="fas fa-bell"></i> Notifications
                    <span class="notif-panel-count" id="notifModalCount" hidden></span>
                </h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body notif-modal-body">
                <div class="notif-loading" id="notifModalLoading">
                    <i class="fas fa-spinner fa-spin"></i> Loading your alerts&hellip;
                </div>

                {{-- Shown by the script once the list is empty. --}}
                <div class="notif-empty" id="notifModalEmpty" hidden>
                    <i class="fas fa-check-circle"></i>
                    <p class="notif-empty-title">You are all caught up.</p>
                    <p class="notif-empty-note">New caller and guide assignments appear here.</p>
                </div>

                <ul class="notif-list" id="notifModalList"></ul>
            </div>

            <div class="modal-footer notif-modal-footer">
                {{-- Written by notifications.js, and only when there is something to
                     report that the buttons cannot say themselves (alerts blocked,
                     a browser without the API, already on, a retry). It takes a row
                     of its own ABOVE the buttons - see the `span.notif-modal-state`
                     rule in layouts/app - because it used to sit between them in
                     Bootstrap's single nowrap footer row, where it was squeezed and
                     painted over "Mark all as read" and "Close" on a phone. --}}
                <span class="notif-modal-state" id="notifPanelBrowserAlertsState" hidden></span>

                <button type="button" class="btn btn-sm btn-outline-primary" id="notifMarkAll"
                        title="Removes every alert from this list">
                    <i class="fas fa-check-double"></i> Mark all as read
                </button>

                <div class="notif-modal-links">
                    {{-- One click, right where the member already is. This used to
                         be a link to /notifications that then asked them to find a
                         second button there. Hidden by notifications.js whenever a
                         click would achieve nothing (already on, blocked, or a
                         browser without the Notification API). --}}
                    <button type="button" class="btn btn-sm btn-outline-primary" id="notifPanelBrowserAlerts" hidden>
                        <i class="fas fa-mobile-alt"></i> Turn on phone alerts
                    </button>

                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // The same binder the notification page and the dashboard use, so the panel
    // can switch phone alerts on without sending anybody to another page. It renders
    // after window.tiuNotifications and notifications.js - see the scripts stack at
    // the end of layouts/app.blade.php. (Write that stack name WITHOUT an @ in here:
    // Blade compiles directives even inside a // comment, and this one made the
    // panel print itself as page text.)
    (function () {
        'use strict';

        var config = window.tiuNotifications;

        if (!config || typeof config.bindEnableButton !== 'function') {
            return;
        }

        config.bindEnableButton(
            document.getElementById('notifPanelBrowserAlerts'),
            document.getElementById('notifPanelBrowserAlertsState')
        );
    })();
</script>
@endpush
