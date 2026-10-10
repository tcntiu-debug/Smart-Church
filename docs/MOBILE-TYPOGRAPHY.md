# Phone Typography

The app is used mostly on phones, so pages are checked at **360 px** width.

## The trap: the template's desktop heading scale

`public/assets/css/style.css` declares the bare-element scale once, near the top of the
file, and nothing else in the template ever reduced it:

| Tag  | Desktop | Phone (`<=767.98px`) |
|------|---------|----------------------|
| `h1` | 60px    | 28px                 |
| `h2` | 48px    | 24px                 |
| `h3` | 30px    | 20px                 |
| `h4` | 24px    | 18px                 |
| `h5` | 21px    | 16px                 |
| `h6` | 16px    | 14px                 |

Because those are element selectors they also beat Bootstrap's own `h1`-`h6` sizes, so a
bare `<h4>`/`<h5>` used as a card title (very common in the views) stayed 24px/21px wide
on a phone and any bold text built on it looked oversized.

The phone column is a `@media (max-width: 767.98px)` block at the **end** of
`style.css`. It has the same specificity as the desktop rules, so it must stay last to
win on source order. Only the bare elements are rescaled: the class scale
(`.display-1..4`, `.welcome-title`, `.ms-feature h3`, ...) is deliberately untouched,
those are hero sizes picked per page.

## Page-local tuning

A `<style>` block inside a view is injected in the `<body>`, i.e. **after**
`style.css`, so same-specificity rules there win at every width. Use that to finish a
page whose own components pin `rem`/`px` sizes.

Precedent: the "Phone tuning" block at the end of the `<style>` in
`resources/views/my-task/index.blade.php`, and its twin in `tasks.blade.php` for the
header the two pages share.

Rules of thumb:

- Shrink the *container* before the text: `p-4` (1.5rem) card gutters eat a third of a
  360px screen. Prefer Bootstrap's own responsive spacing utilities in the markup
  (`class="card-body p-3 p-lg-4"`) over `!important` overrides.
- Keep fields at **16px** on phones (e.g. `.form-select-minimal`, `.address-textarea`):
  iOS Safari zooms the whole page in when a focused field is smaller than that.
- Don't shrink chrome that is already small: `.small-label` (0.65rem), `.index-tag`
  (0.7rem), breadcrumbs (0.75-0.82rem), `.ms-directions` (12px).
- The dark palette needs no typography twin - colour rules live in the
  `ms-dark-theme` blocks (see `docs/DISPLAY-MODE.md`).
- Check `body` (14px) is the ceiling for body copy, not 16px: Bootstrap's `.btn` is
  `1rem`, so buttons read slightly larger than the text around them by design.
