{{--
    Display mode switch (dark / light).

    Four variants, picked through the $variant variable:
      navbar   - icon only button in the top navbar of layouts/app (default)
      sidebar  - menu item in the side navigation (partials/nav)
      floating - fixed pill on the logged-out screens (auth/login, layouts/auth)
      button   - inline Bootstrap button (settings/index)

    Behaviour: public/assets/js/theme.js   Styling: public/assets/css/theme-toggle.css
--}}
@php
    $variant = $variant ?? 'navbar';
@endphp

@if($variant === 'sidebar')
    <li class="menu-item">
        <a href="#" class="ms-theme-toggle" data-theme-toggle aria-pressed="false"
           aria-label="Switch between dark and light mode" title="Switch to dark mode">
            <span>
                <i class="theme-icon-light fas fa-moon fs-16" aria-hidden="true"></i>
                <i class="theme-icon-dark fas fa-sun fs-16" aria-hidden="true"></i>
                <span class="theme-label-light">Dark Mode</span>
                <span class="theme-label-dark">Light Mode</span>
            </span>
        </a>
    </li>
@elseif($variant === 'button')
    <button type="button" class="btn btn-primary ms-theme-toggle ms-theme-toggle-button"
            data-theme-toggle aria-pressed="false" title="Switch to dark mode">
        <i class="theme-icon-light fas fa-moon" aria-hidden="true"></i>
        <i class="theme-icon-dark fas fa-sun" aria-hidden="true"></i>
        <span class="theme-label-light">Switch to dark mode</span>
        <span class="theme-label-dark">Switch to light mode</span>
    </button>
@else
    <a href="#" class="ms-theme-toggle{{ $variant === 'floating' ? ' ms-theme-toggle-floating' : '' }}"
       data-theme-toggle aria-pressed="false"
       aria-label="Switch between dark and light mode" title="Switch to dark mode">
        <i class="theme-icon-light fas fa-moon" aria-hidden="true"></i>
        <i class="theme-icon-dark fas fa-sun" aria-hidden="true"></i>
        @if($variant === 'floating')
            <span class="theme-label-light">Dark Mode</span>
            <span class="theme-label-dark">Light Mode</span>
        @endif
    </a>
@endif
