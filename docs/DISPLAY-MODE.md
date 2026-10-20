# Display Mode (Dark / Light)

The interface can be switched between light and dark. **Light is the default**:
nothing is stored until a user actually switches, so every new device starts
light and the operating system preference (`prefers-color-scheme`) is deliberately
ignored — a phone set to dark mode still gets the light interface.

The dark palette is the one
that already ships with the "Modern Admin" template: every dark rule in
`public/assets/css/style.css` is scoped to the `ms-dark-theme` class, for example
`body.ms-dark-theme, .ms-dark-theme a, ...`. The class therefore has to sit on
`<body>` (not on `<html>`).

Nothing about the display mode is stored in the database. In particular the legacy
`tiu_member_login.theme_settings` column is **not** used for this: that table is
login activity only (see `docs/Database-Architecture-Summary.md`).

## How it works

| Piece | Responsibility |
|-------|----------------|
| `tiu_theme` cookie | The single stored choice: `dark` or `light`. Written by JavaScript, one year, `SameSite=Lax` (+`Secure` on HTTPS). Absent until the user switches, because light is the default. |
| `app/Http/Middleware/EncryptCookies.php` | Whitelists `tiu_theme` (`$except`), because Laravel would otherwise discard the plain-text cookie written by JavaScript. |
| `resources/views/partials/theme-toggle.blade.php` | The switch markup. Variants: `navbar`, `sidebar`, `floating`, `button`. |
| `public/assets/js/theme.js` | Owns the `<body>` class: reads the cookie (light is the default), swaps on click, keeps `aria-pressed`/`title` in sync. Exposes `window.TiuTheme`. |
| `public/assets/css/theme-toggle.css` | Styles the switch and swaps the sun/moon icon and label. |

Resolution order in `theme.js`: stored cookie → light. There is no inline
anti-flash script any more: light needs no class, and a `dark` cookie is already
rendered into the `<body>` class by Blade, so no page can flash the wrong palette.
Server side the layouts simply render the class, e.g.
`<body class="ms-body ms-aside-left-open@if(request()->cookie('tiu_theme') === 'dark') ms-dark-theme@endif">`.

## Where the switch appears

- `resources/views/layouts/app.blade.php` — icon button in the top navbar (all authenticated pages).
- `resources/views/partials/nav.blade.php` — "Dark Mode" / "Light Mode" item above Log Out in the side navigation.
- `resources/views/settings/index.blade.php` — Display Mode card on the Settings page.

The logged-out screens (`auth/login`, `layouts/auth`) deliberately have **no**
switch: they always follow the default palette unless the browser carries a
`tiu_theme=dark` cookie from a previous signed-in session.

`layouts/auth` is also the shell the public registration page (`/mregister`,
`member/public-register.blade.php`) is served in. It is a link people forward to
non-members, so that shell carries no navigation, no welcome name and no
notification bell - the visitor sees the form and nothing else, in the palette
their cookie asks for (it ships the `.text-muted` dark twin its hints need).

## Adding the switch to another view

Any element carrying `data-theme-toggle` becomes a switch; no JavaScript wiring is
needed. Reuse the partial, or write your own and let the CSS swap the icon:

```blade
@include('partials.theme-toggle', ['variant' => 'button'])

{{-- or, custom markup --}}
<a href="#" class="ms-theme-toggle" data-theme-toggle aria-pressed="false">
    <i class="theme-icon-light fas fa-moon" aria-hidden="true"></i>
    <i class="theme-icon-dark fas fa-sun" aria-hidden="true"></i>
</a>
```

## Page-local colours

The template's dark rules are aggressive: `body.ms-dark-theme, .ms-dark-theme a`
sets **every** link to `#fff`, and `.ms-dark-theme span:not([class*="ms-text-"])`
does the same for every `<span>`. Any view that paints its own light palette
(white cards / rows) therefore turns into white text on a white surface in dark
mode. Such a view must ship a dark counterpart for each of its own rules, using
the template palette already reserved for that: surface `#252851`, deeper `#323a67`
for nested/inner surfaces, hover `#2a2e5b`, border `#242750`, accent `#ff8306`,
muted text `#b9bcd8`, plus the tinted twins `#1f3b2a` (success), `#3a2f10`
(warning) and `#3d2226` (danger) used for badges and status boxes.

The precedent in the repo is `public/assets/css/style2.css` (light rule followed
by its `.ms-dark-theme` twin). A worked example for a whole page is the dashboard
`resources/views/home.blade.php`, where the Quick Action tiles
(`.action-card`, an `<a>`), the Quick Links list (`.menu-row`, also an `<a>`) and
the page modals are re-coloured, and `layouts/app.blade.php` where the
notification bell colour was moved out of an inline `style` attribute into the
`.ms-navbar .ms-notif-bell` class so it could be overridden.

`resources/views/my-task/index.blade.php` (`/my-tasks`) shows the variant where an
existing Bootstrap utility fights the dark palette. `.ms-dark-theme .card` paints
the outer assignee card `#252851`, but the names/title use `.text-dark` and the
subtitle `.text-muted` - both `!important` - so they must be beaten with an
equally `!important` dark twin:

```css
.ms-dark-theme .assignee-card .text-dark { color: #fff !important; }
.ms-dark-theme .text-muted { color: #b9bcd8 !important; }
```

Its header card (`.list-header`, the `/my-tasks` twin of the task page's
`.profile-header`) and its count/meta pills (`.meta-chip`) need twins for the same
reason: the title carries the `!important` `.text-dark` utility and style.css'
`.ms-dark-theme span` rule would otherwise leave pale text on a pale card. Same
palette as the card, `.ms-dark-theme .list-header { background: #252851; }` and
`.ms-dark-theme .meta-chip { background: #323a67; color: #dfe3ff; }`.

Its panel header uses the same page-local twin for the two inline `style` attributes
that were moved into the `.tracking-panel-header` / `.tracking-panel-title`
classes: `<light rule>` then `.ms-dark-theme .tracking-panel-header { … }`.

`resources/views/admin/assignments/drag-drop.blade.php` (`/admin-assign-dragdrop`)
is the plain variant: its surfaces are page-local classes rather than Bootstrap
components, so style.css never repaints them and they stay white while the theme
turns their headings and paragraphs `#fff`. The twin block repeats every local
rule with a `ms-dark-theme` prefix (`.ms-dark-theme .panel`,
`.ms-dark-theme .guide-card .guide-head`, `.ms-dark-theme .first-timer-card`,
`.ms-dark-theme #unassigned-pool`, …) and also beats the `!important`
`.text-muted` utility:

```css
.ms-dark-theme .panel, .ms-dark-theme .first-timer-card { background: #252851; border-color: #242750; }
.ms-dark-theme .panel-head, .ms-dark-theme .guide-card .guide-head { background: #2d3160; }
.ms-dark-theme .text-muted { color: #b9bcd8 !important; }
```

One card partial (`admin/assignments/partials/first-timer-card.blade.php`) is
used by both columns - the guides' drop zones and the unassigned pool - so the
card rule exists once, for the light and the dark theme alike.

Because the light rules live in the page's own `<style>` (rendered after
`assets/css/style.css`) an equally specific twin wins the cascade by order.

### Rules learned while sweeping the remaining pages

**A twin must match the element, not only its class.** style.css matches elements
themselves (`.ms-dark-theme h5`, `p:not([class*="ms-text-"])`,
`span:not([class*="ms-text-"])`) and a directly matched declaration always beats an
inherited one. A two-class twin such as `.ms-dark-theme .badge-marked` therefore
*loses* to `.ms-dark-theme span:not([class*="ms-text-"])`; keep the element
(`span.badge-marked`, `h5.business-name`, `p.business-description`) or add a third
class (`.ms-dark-theme .child-card .child-details span`). Three classes beat the
template's two-class-plus-element rule, so a container twin is also enough for
plain `<div>`s (`.ms-dark-theme .attendance-counter .label`).

**Inline `style=""` is unreachable**, so tinted surfaces were moved into classes the
twin can repaint: `.summary-card-neutral/-success/-warning/-danger` and
`.panel-heading-info/-success/-warning` in
`resources/views/analytics/attendance-analysis.blade.php`.

**Some light surfaces are intentional.** Bootstrap's `.bg-light` has no `!important`
in this build, so `.badge.bg-light` pills and `.bg-light` panels stay light (as do
form controls app-wide: `.form-control`, `<select>`). Paint such a surface with its
own twin rather than recolouring the text on it, e.g. on
`resources/views/my-task/tasks.blade.php`:
`.ms-dark-theme .bg-light { background-color: #323a67; }` and
`.ms-dark-theme .badge.bg-light.border { border-color: #242750 !important; }`.

**`.bg-primary` turns white in the dark theme** (`.ms-dark-theme .bg-primary`), which
hides a `.text-white` header; the community map keeps its table head solid with
`.ms-dark-theme thead.bg-primary { background-color: #ff8306; }`
(`resources/views/maps/community.blade.php`).

**A page may also paint `<body>`** (the check-in page sets
`body { background-color: var(--bg-light) }`), whose twin is
`body.ms-dark-theme { background-color: #292e5a; }`.

**A shared layout can own the offending rule.** `layouts/app.blade.php` paints
every `.btn-link` `#0062cc !important` (plus the `.accordion .card-header`
variants) in its own `<head>` `<style>`, because the template's `.btn-link` used
to be unreadable. A page-local twin *without* `!important` loses to that - which
is how the Drag & Drop guide names stayed blue after that page had been patched.
The dark twins therefore live next to the light rules in the layout, the same
light-rule-then-twin shape as the `.ms-navbar .ms-notif-bell` pair in that block.

Checklist when a page still looks broken in dark mode:

1. Surfaces you set to white need a `.ms-dark-theme .my-class { background:#252851 }`.
2. Text you set to a dark colour inside such a surface needs to become light.
3. Prefer classes over inline `style=""` - inline declarations cannot be
   overridden by the dark palette.
4. Keep the element in the twin when the template matches that element
   (`span.`, `h5.`, `p.`) or add one more class.
5. Utilities with `!important` (`.text-dark`, `.text-muted`, `.border-top`) need an
   equally `!important` twin.
6. Verify with `tiu_theme=dark` set in the browser (the toggle, see above).

## Tests

`tests/Feature/ThemeToggleTest.php` (no `RefreshDatabase`) asserts that the light
default holds (no cookie → no `ms-dark-theme` class, the logged-out pages ship no
switch and no toggle stylesheet), that the switch and its assets are rendered on
the signed-in pages, and that a `tiu_theme=dark` cookie produces the
`ms-dark-theme` body class. It also guards the default directly: the theme script
must not call `matchMedia` (it can no longer follow `prefers-color-scheme`).
One HTTP test per swept page then asserts that the
dark counterparts (and the original light rules) reach the browser: the dashboard
(`/home`), My Tasks (`/my-tasks`), the Drag & Drop page
(`/admin-assign-dragdrop`), the check-in page (`/children-church`), attendance
analysis (`/attendance-analysis`), the marketplace (`/purchase`), the community
map (`/map-view`), the gallery (`/gallery`), the first timer form (`/ft-register`)
and the task timeline (`/my-tasks/{id}/tasks`). The last two page groups are
driven by the `lightSurfacePages()` data provider, so a new page only has to be
listed there. The layout-level `!important` twins have their own test,
`test_layout_button_link_rules_ship_dark_counterparts()`.

```bash
vendor/bin/phpunit --filter ThemeToggleTest
```
