<style>
    .anihow-brand-panel,
    .anihow-brand-login {
        line-height: 1.1;
        text-align: center;
    }

    .anihow-brand-panel {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        padding: 0;
        background: transparent !important;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .anihow-brand-panel img.anihow-mark {
        display: block;
        height: 2.75rem;
        width: auto;
    }

    .fi-sidebar-header {
        height: 4.5rem !important;
        min-height: 4.5rem !important;
        align-items: center !important;
        overflow: visible !important;
    }

    .fi-logo {
        height: auto !important;
        overflow: visible !important;
    }

    .anihow-brand-login {
        display: inline-flex !important;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        background: #ffffff !important;
        border-radius: 1.5rem;
        padding: 1.35rem 2rem 1.15rem;
        border: 1.5px solid rgba(31, 90, 62, 0.14);
        box-shadow:
            0 12px 30px rgba(0, 0, 0, 0.16),
            0 4px 12px rgba(31, 90, 62, 0.10);
    }

    .anihow-brand-login img.anihow-mark {
        display: block;
        height: 6.5rem;
        width: auto;
    }

    .anihow-brand-login img.anihow-wordmark {
        display: block;
        height: 3.1rem;
        width: 17rem;
        object-fit: contain;
    }

    .anihow-brand-login .anihow-tagline {
        margin: 0.15rem 0 0;
        color: #8a5526;
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0.2em;
        line-height: 1.2;
        white-space: nowrap;
    }

    .anihow-topbar-theme {
        display: inline-flex;
        align-items: center;
        margin-inline-end: 0.5rem;
    }

    /* Dashboard analytics: payment split, harvest summary and harvest by crop. */
    .anihow-analytics {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .anihow-analytics-window,
    .anihow-analytics-title {
        margin: 0;
        font-weight: 600;
        color: var(--gray-950);
    }

    .anihow-analytics-window {
        font-size: 0.9375rem;
    }

    .anihow-analytics-title {
        font-size: 1.125rem;
        font-weight: 700;
    }

    .anihow-analytics-heading {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .anihow-muted {
        margin: 0;
        font-size: 0.8125rem;
        line-height: 1.45;
        color: var(--gray-600);
    }

    .anihow-split-grid,
    .anihow-harvest-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(0, 1fr);
    }

    @media (min-width: 768px) {
        .anihow-split-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .anihow-harvest-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1280px) {
        .anihow-harvest-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }
    }

    .anihow-split-card,
    .anihow-stat {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .anihow-stat {
        gap: 0.5rem;
    }

    .anihow-split-head {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.5rem 1rem;
    }

    .anihow-split-title,
    .anihow-stat-label {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-700);
    }

    .anihow-split-title {
        font-weight: 600;
    }

    .anihow-split-rows {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
    }

    .anihow-split-row {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1.4fr);
        align-items: center;
        gap: 0.75rem;
    }

    .anihow-split-label {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 0.875rem;
        color: var(--gray-800);
    }

    .anihow-split-amount {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-950);
    }

    .anihow-split-detail {
        text-align: end;
    }

    .anihow-stat-value {
        font-size: 1.875rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--gray-950);
    }

    .anihow-stat-none {
        color: var(--gray-400);
    }

    .anihow-stat-loss {
        color: var(--danger-600);
    }

    .anihow-swatch {
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 0.1875rem;
        flex-shrink: 0;
    }

    .anihow-bar {
        display: flex;
        height: 0.625rem;
        overflow: hidden;
        border-radius: 999px;
        background: var(--gray-200);
    }

    .anihow-bar-thin {
        height: 0.5rem;
    }

    .anihow-bar-part {
        height: 100%;
    }

    .anihow-bar-min:not([style*="width: 0%"]) {
        min-width: 0.375rem;
    }

    .anihow-tone-primary {
        background: var(--primary-600);
    }

    .anihow-tone-secondary {
        background: var(--info-400);
    }

    .anihow-share {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        min-width: 7.5rem;
    }

    .anihow-share .anihow-bar {
        flex: 1;
    }

    .anihow-share-label {
        width: 2.5rem;
        text-align: end;
        font-size: 0.875rem;
        color: var(--gray-700);
    }

    .anihow-tone-neutral {
        background: var(--gray-400);
    }

    .anihow-rank-list {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .anihow-rank-item {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .anihow-rank-head {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.875rem;
    }

    .anihow-rank-crop,
    .anihow-rank-sales {
        font-weight: 600;
        color: var(--gray-950);
    }

    .anihow-rank-sales {
        white-space: nowrap;
    }

    .anihow-rank-others {
        font-weight: 400;
        color: var(--gray-700);
    }

    .anihow-rank-share {
        width: 3rem;
    }

    .dark .anihow-rank-crop,
    .dark .anihow-rank-sales {
        color: #fff;
    }

    .dark .anihow-rank-others {
        color: var(--gray-300);
    }

    .dark .anihow-tone-neutral {
        background: var(--gray-500);
    }

    .dark .anihow-analytics-window,
    .dark .anihow-analytics-title,
    .dark .anihow-split-amount,
    .dark .anihow-stat-value {
        color: #fff;
    }

    .dark .anihow-muted {
        color: var(--gray-400);
    }

    .dark .anihow-split-title,
    .dark .anihow-stat-label,
    .dark .anihow-split-label,
    .dark .anihow-share-label {
        color: var(--gray-300);
    }

    .dark .anihow-stat-none {
        color: var(--gray-500);
    }

    .dark .anihow-stat-loss {
        color: var(--danger-400);
    }

    .dark .anihow-bar {
        background: var(--gray-800);
    }

    .dark .anihow-tone-secondary {
        background: var(--info-300);
    }

    /* ---- The AniHow look: warm paper background, white panels, green accents ---- */
    :root {
        --anihow-paper: #F6F4EE;
        --anihow-line: #E5E1D6;
        --anihow-line-soft: #EEEAE0;
        --anihow-ink: #1E2421;
        --anihow-ink-muted: #5F6863;
        --anihow-green: #1F5A3E;
        --anihow-green-tint: #E3EFE7;
        --anihow-warn: #B45309;
        --anihow-surface: #FFFFFF;
        --anihow-surface-soft: #FAF9F5;
    }

    .dark {
        --anihow-line: rgba(255, 255, 255, 0.10);
        --anihow-line-soft: rgba(255, 255, 255, 0.06);
        --anihow-ink: var(--gray-100);
        --anihow-ink-muted: var(--gray-400);
        --anihow-green: var(--primary-300);
        --anihow-green-tint: rgba(110, 190, 140, 0.14);
        --anihow-warn: #F2B35B;
        --anihow-surface: var(--gray-900);
        --anihow-surface-soft: rgba(255, 255, 255, 0.03);
    }

    html:not(.dark) body.fi-body,
    html:not(.dark) .fi-layout {
        background-color: var(--anihow-paper);
    }

    html:not(.dark) .fi-sidebar,
    html:not(.dark) .fi-topbar > nav,
    html:not(.dark) .fi-topbar {
        background-color: #FFFFFF;
    }

    html:not(.dark) .fi-sidebar {
        border-inline-end: 1px solid var(--anihow-line);
    }

    html:not(.dark) .fi-topbar {
        border-bottom: 1px solid var(--anihow-line);
        box-shadow: none;
    }

    .fi-sidebar-group-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    html:not(.dark) .fi-sidebar-item-active > .fi-sidebar-item-btn,
    html:not(.dark) .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background-color: var(--anihow-green-tint);
    }

    html:not(.dark) .fi-section,
    html:not(.dark) .fi-ta-ctn,
    html:not(.dark) .fi-wi-stats-overview-stat {
        border-radius: 14px;
        box-shadow: none;
        --tw-ring-color: var(--anihow-line);
    }

    /* ---- Home screens ---- */
    .anihow-home {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .anihow-home-block {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .anihow-home-heading {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--anihow-ink);
    }

    .anihow-home-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
        gap: 0.75rem;
    }

    .anihow-home-tile {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        padding: 1rem;
        border: 1px solid var(--anihow-line);
        border-radius: 14px;
        background: var(--anihow-surface);
        color: var(--anihow-ink);
        text-decoration: none;
    }

    .anihow-home-tile:hover {
        border-color: var(--anihow-green);
    }

    .anihow-home-tile:focus-visible,
    .anihow-home-link:focus-visible,
    .anihow-home-button:focus-visible {
        outline: 2px solid var(--anihow-green);
        outline-offset: 2px;
    }

    .anihow-home-tile-label {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--anihow-ink-muted);
    }

    .anihow-home-tile-count {
        font-size: 1.875rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .anihow-home-tile-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.15;
    }

    .anihow-home-tile-cta,
    .anihow-home-link {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--anihow-green);
        text-decoration: none;
    }

    .anihow-home-link:hover {
        text-decoration: underline;
    }

    .anihow-home-tile-note {
        font-size: 0.75rem;
        color: var(--anihow-ink-muted);
    }

    .anihow-home-warn,
    .anihow-home-tile-note.anihow-home-warn {
        color: var(--anihow-warn);
        font-weight: 600;
    }

    .anihow-home-columns {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: flex-start;
    }

    .anihow-home-wide {
        flex: 2 1 30rem;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .anihow-home-narrow {
        flex: 1 1 18rem;
        min-width: 0;
    }

    .anihow-home-table-wrap {
        overflow-x: auto;
        margin: -0.5rem;
    }

    .anihow-home-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .anihow-home-table th {
        text-align: start;
        font-weight: 600;
        color: var(--anihow-ink-muted);
        background: var(--anihow-surface-soft);
        padding: 0.625rem 0.75rem;
        white-space: nowrap;
    }

    .anihow-home-table td {
        padding: 0.6875rem 0.75rem;
        border-top: 1px solid var(--anihow-line-soft);
        color: var(--anihow-ink);
    }

    .anihow-home-strong {
        font-weight: 600;
        color: var(--anihow-ink);
    }

    a.anihow-home-strong {
        text-decoration: none;
    }

    a.anihow-home-strong:hover {
        color: var(--anihow-green);
        text-decoration: underline;
    }

    .anihow-home-note {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--anihow-warn);
    }

    .anihow-home-activity,
    .anihow-home-posts {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        font-size: 0.8125rem;
        color: var(--anihow-ink);
    }

    .anihow-home-activity li,
    .anihow-home-posts li {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--anihow-line-soft);
    }

    .anihow-home-kind {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--anihow-ink-muted);
    }

    .anihow-home-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .anihow-home-actions-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .anihow-home-button {
        display: inline-flex;
        align-items: center;
        min-height: 2.5rem;
        padding: 0 1rem;
        border-radius: 10px;
        background: var(--anihow-green);
        color: #FFFFFF;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
    }

    .dark .anihow-home-button {
        color: var(--gray-950);
    }

    .anihow-home-button-quiet {
        background: var(--anihow-surface);
        color: var(--anihow-ink);
        border: 1px solid var(--anihow-line);
    }

    .dark .anihow-home-button-quiet {
        color: var(--anihow-ink);
    }

    .anihow-home-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        min-height: 1.75rem;
        padding: 0 0.75rem;
        border-radius: 999px;
        background: var(--anihow-green-tint);
        color: var(--anihow-green);
        font-size: 0.75rem;
        font-weight: 600;
    }

    .anihow-home-pill::before {
        content: "";
        width: 0.5rem;
        height: 0.5rem;
        border-radius: 999px;
        background: currentColor;
    }

    .anihow-home-pill-warn {
        background: rgba(180, 83, 9, 0.12);
        color: var(--anihow-warn);
    }

    .anihow-home-strip {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1.75rem;
        padding: 0.875rem 1.125rem;
        border: 1px solid var(--anihow-line);
        border-radius: 14px;
        background: var(--anihow-surface);
    }

    .anihow-home-strip > div {
        display: flex;
        flex-direction: column;
        font-size: 0.75rem;
    }

    .anihow-home-strip-value {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--anihow-ink);
    }

    .anihow-home-push {
        margin-inline-start: auto;
    }

    .anihow-home-settings {
        margin: 0 0 0.75rem;
        display: flex;
        flex-direction: column;
    }

    .anihow-home-settings > div {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--anihow-line-soft);
    }

    .anihow-home-settings dt {
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--anihow-ink);
    }

    .anihow-home-settings dd {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--anihow-ink-muted);
    }

    /* ---- Sign-in: green panel on the left, the form on the right ---- */
    .fi-simple-layout:has(> .anihow-login-side) {
        flex-direction: row;
        align-items: stretch;
        background: var(--anihow-paper);
    }

    .dark .fi-simple-layout:has(> .anihow-login-side) {
        background: var(--gray-950);
    }

    .fi-simple-layout:has(> .anihow-login-side) > .fi-simple-main-ctn {
        flex: 1 1 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem;
    }

    .anihow-login-side {
        flex: 1 1 50%;
        box-sizing: border-box;
        min-height: 100vh;
        padding: 3.5rem;
        background: #1F5A3E;
        color: #FFFFFF;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 2rem;
    }

    .anihow-login-side-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .anihow-login-side-mark {
        width: 3.25rem;
        height: 3.25rem;
        border-radius: 14px;
        background: #FFFFFF;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .anihow-login-side-mark img {
        width: 2.5rem;
        height: 2.5rem;
        object-fit: contain;
    }

    .anihow-login-side-name {
        font-size: 1.375rem;
        font-weight: 700;
    }

    .anihow-login-side-copy {
        max-width: 28rem;
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .anihow-login-side-title {
        margin: 0;
        font-size: 2.125rem;
        line-height: 1.2;
        font-weight: 700;
    }

    .anihow-login-side-text {
        margin: 0;
        font-size: 1rem;
        line-height: 1.55;
        color: #D6E7DC;
    }

    .anihow-login-side-foot {
        margin: 0;
        font-size: 0.8125rem;
        color: #B9D3C3;
    }

    @media (min-width: 64rem) {
        .fi-simple-layout:has(> .anihow-login-side) .anihow-brand-login {
            display: none !important;
        }
    }

    @media (max-width: 63.99rem) {
        .fi-simple-layout:has(> .anihow-login-side) {
            flex-direction: column;
        }

        .anihow-login-side {
            display: none;
        }
    }

    /* ---- Shell, to match the mockups ---- */

    /* Brand block: leaf mark, "AniHow", and the office under it. */
    .anihow-brand-panel {
        gap: 0.625rem;
        text-align: start;
    }

    .anihow-brand-panel img.anihow-mark {
        height: 2.5rem;
    }

    .anihow-brand-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }

    .anihow-brand-name {
        font-size: 1rem;
        font-weight: 700;
        color: var(--anihow-ink);
    }

    .anihow-brand-office {
        font-size: 0.75rem;
        color: var(--anihow-ink-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 11.5rem;
    }

    /* Sidebar: white, flat group labels, compact items. */
    .fi-sidebar-nav-groups {
        gap: 1.125rem !important;
    }

    .fi-sidebar-group-btn {
        padding: 0 0.625rem 0.25rem !important;
        min-height: 0 !important;
    }

    .fi-sidebar-group-label {
        font-size: 0.6875rem !important;
        letter-spacing: 0.06em;
        color: var(--anihow-ink-muted) !important;
    }

    .fi-sidebar-group-items {
        gap: 0.125rem !important;
    }

    .fi-sidebar-item-btn {
        min-height: 2.25rem;
        padding: 0.375rem 0.625rem !important;
        border-radius: 8px !important;
        gap: 0.625rem !important;
    }

    .fi-sidebar-item-label {
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        color: var(--anihow-ink);
    }

    .fi-sidebar-item-icon {
        width: 1.125rem !important;
        height: 1.125rem !important;
        color: var(--anihow-ink-muted) !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon {
        color: var(--anihow-green) !important;
        font-weight: 600 !important;
    }

    .fi-sidebar-item-badge-ctn .fi-badge {
        background: #B45309 !important;
        color: #FFFFFF !important;
        --tw-ring-color: transparent !important;
    }

    /* Page header: a plain title with a description line. */
    .fi-header-heading {
        font-size: 1.5rem !important;
        line-height: 1.25 !important;
        font-weight: 700 !important;
        letter-spacing: 0 !important;
    }

    .fi-header-subheading {
        font-size: 0.875rem !important;
        color: var(--anihow-ink-muted) !important;
        margin-top: 0.25rem !important;
    }

    /* Tabs: left-aligned chips with counts. */
    .fi-tabs:not(.fi-vertical) {
        justify-content: flex-start !important;
        margin-inline: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        --tw-ring-color: transparent !important;
        padding: 0 !important;
        gap: 0.5rem !important;
        flex-wrap: wrap;
    }

    .fi-tabs:not(.fi-vertical) .fi-tabs-item {
        min-height: 2rem;
        padding: 0 0.75rem !important;
        border-radius: 999px !important;
        border: 1px solid #DDD8CC;
        background: var(--anihow-surface);
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active {
        background: var(--anihow-green) !important;
        border-color: var(--anihow-green);
    }

    .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active .fi-tabs-item-label {
        color: #FFFFFF !important;
    }

    .dark .fi-tabs:not(.fi-vertical) .fi-tabs-item {
        border-color: var(--anihow-line);
    }

    .dark .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active .fi-tabs-item-label {
        color: var(--gray-950) !important;
    }

    /* Status pills. */
    .fi-badge {
        border-radius: 999px !important;
        font-weight: 600 !important;
    }

    /* Tables: soft header row, clickable rows. */
    /* Header row: white like the table, with a line under it. */
    html:not(.dark) .fi-ta-header-cell {
        background: #FFFFFF;
        border-bottom: 1px solid #DDD8CC;
    }

    .fi-ta-header-cell {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--anihow-ink-muted);
    }

    html:not(.dark) .fi-ta-row.fi-clickable:hover,
    html:not(.dark) .fi-ta-row:hover {
        background: var(--anihow-surface-soft);
    }

    .fi-ta-row td {
        border-color: var(--anihow-line-soft);
    }

    /* Full-height sidebar: brand at the top, account menu at the foot. */
    html:not(.dark) .fi-sidebar,
    html:not(.dark) .fi-sidebar-header {
        background-color: #FFFFFF !important;
    }

    .fi-sidebar-header {
        border-bottom: 0 !important;
        box-shadow: none !important;
        padding-inline: 1.25rem !important;
    }

    .fi-sidebar-footer {
        border-top: 1px solid var(--anihow-line);
        padding: 0.75rem !important;
    }

    /* Side panels keep the list in view behind them. */
    .fi-modal-close-overlay {
        background-color: rgba(30, 36, 33, 0.18) !important;
    }

    /* Main buttons in the AniHow green, as in the mockups. */
    html:not(.dark) .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-btn-link) {
        background-color: var(--anihow-green) !important;
        color: #FFFFFF !important;
        --tw-ring-color: transparent;
    }

    html:not(.dark) .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-btn-link):hover {
        background-color: #163F2C !important;
    }

    html:not(.dark) .fi-btn.fi-color-primary:not(.fi-outlined):not(.fi-btn-link) .fi-icon {
        color: #FFFFFF !important;
    }

    /* A thin sidebar scrollbar that shows on hover only. */
    .fi-sidebar-nav {
        scrollbar-width: thin;
        scrollbar-color: transparent transparent;
    }

    .fi-sidebar-nav:hover {
        scrollbar-color: #CFCAC0 transparent;
    }

    /* Filters above a list: one quiet row, no "Filters" heading. */
    .fi-ta-filters-above-content-ctn .fi-ta-filters-header {
        display: none !important;
    }

    .fi-ta-filters-above-content-ctn {
        padding-block: 0.75rem !important;
    }

    .fi-ta-filters-above-content-ctn .fi-fo-field-label-content,
    .fi-ta-filters-above-content-ctn label {
        font-size: 0.8125rem;
        color: var(--anihow-ink-muted);
    }

    /* ---- Split screens: a list on the left, its panel open beside it ---- */
    /* A split page runs edge to edge beside the sidebar, with no page header. */
    .fi-main:has(.anihow-split-page) {
        padding: 0;
        max-width: none;
    }

    .anihow-split-page .fi-page-header-main-ctn {
        padding: 0;
        gap: 0;
    }

    .anihow-split {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        min-height: 100vh;
    }

    .anihow-split-list {
        flex: 1 1 18rem;
        max-width: 21.25rem;
        box-sizing: border-box;
        background: var(--anihow-surface-soft);
        border-inline-end: 1px solid var(--anihow-line);
        padding: 1.5rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .anihow-split-intro {
        padding: 0 0.25rem;
    }

    .anihow-split-intro p {
        margin: 0.25rem 0 0;
        font-size: 0.8125rem;
    }

    .anihow-split-heading {
        margin: 0;
        font-size: 1.375rem;
        font-weight: 700;
        color: var(--anihow-ink);
    }

    .anihow-split-panel {
        flex: 999 1 28rem;
        min-width: 0;
        box-sizing: border-box;
        padding: 1.75rem;
        display: flex;
        flex-direction: column;
        gap: 1.125rem;
    }

    @media (max-width: 47.99rem) {
        .anihow-split-list {
            max-width: none;
            border-inline-end: 0;
            border-bottom: 1px solid var(--anihow-line);
        }

        .anihow-split-panel {
            padding: 1.25rem 1rem;
        }
    }

    .anihow-split-tabs {
        display: flex;
        gap: 0.375rem;
        flex-wrap: wrap;
        padding: 0 0.25rem;
    }

    .anihow-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        min-height: 1.75rem;
        padding: 0 0.625rem;
        border-radius: 999px;
        border: 1px solid #DDD8CC;
        background: var(--anihow-surface);
        color: var(--anihow-ink);
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
    }

    .anihow-chip-on {
        background: var(--anihow-green);
        border-color: var(--anihow-green);
        color: #FFFFFF;
    }

    .anihow-chip-count {
        font-size: 0.6875rem;
        padding: 0 0.375rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.22);
    }

    .anihow-chip:not(.anihow-chip-on) .anihow-chip-count {
        background: #F1EFE9;
        color: var(--anihow-ink-muted);
    }

    .anihow-split-item {
        text-align: start;
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        padding: 0.75rem 0.875rem;
        border-radius: 12px;
        border: 1px solid var(--anihow-line);
        background: var(--anihow-surface);
        cursor: pointer;
    }

    .anihow-split-item:hover {
        border-color: var(--anihow-green);
    }

    .anihow-split-item-on {
        border: 2px solid var(--anihow-green);
        padding: calc(0.75rem - 1px) calc(0.875rem - 1px);
    }

    .anihow-split-item-title {
        font-weight: 600;
        color: var(--anihow-ink);
    }

    .anihow-split-item-meta {
        font-size: 0.75rem;
        color: var(--anihow-ink-muted);
    }

    .anihow-split-empty {
        padding: 0.5rem 0.25rem;
    }

    .anihow-split-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .anihow-split-name {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--anihow-ink);
    }

    .anihow-pill {
        display: inline-flex;
        align-items: center;
        min-height: 1.75rem;
        padding: 0 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .anihow-pill-wait { background: #FFF1D6; color: #7A4A00; }
    .anihow-pill-ok { background: #E3EFE7; color: #1F5A3E; }
    .anihow-pill-no { background: #FBEAE3; color: #9A3412; }

    .anihow-id-photos {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
        gap: 0.875rem;
    }

    .anihow-id-photo {
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .anihow-id-photo-frame {
        display: flex;
        align-items: center;
        justify-content: center;
        aspect-ratio: 1.58;
        border-radius: 14px;
        overflow: hidden;
        background: repeating-linear-gradient(135deg, #ECE8DE 0 12px, #E4DFD3 12px 24px);
    }

    .anihow-id-photo-frame img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .anihow-id-photo figcaption {
        font-size: 0.8125rem;
    }

    .anihow-id-photo-missing {
        color: var(--anihow-ink-muted);
        font-weight: 600;
        font-size: 0.875rem;
    }

    .anihow-id-facts {
        margin: 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
        gap: 0.75rem;
        padding: 1rem 1.125rem;
        border: 1px solid var(--anihow-line);
        border-radius: 14px;
        background: var(--anihow-surface);
    }

    .anihow-id-facts dt {
        font-size: 0.75rem;
        color: var(--anihow-ink-muted);
    }

    .anihow-id-facts dd {
        margin: 0;
        font-weight: 600;
        color: var(--anihow-ink);
    }

    .anihow-id-actions {
        display: flex;
        gap: 0.625rem;
        flex-wrap: wrap;
    }

    .anihow-id-actions-end {
        justify-content: flex-end;
    }

    .anihow-btn {
        min-height: 2.875rem;
        padding: 0 1.125rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.875rem;
        cursor: pointer;
        border: 0;
    }

    .anihow-btn-primary {
        flex: 2 1 15rem;
        background: var(--anihow-green);
        color: #FFFFFF;
    }

    .anihow-btn-primary:hover { background: #163F2C; }

    .anihow-btn-quiet {
        background: var(--anihow-surface);
        color: var(--anihow-ink);
        border: 1px solid #DDD8CC;
    }

    .anihow-btn-reject {
        flex: 1 1 10rem;
        color: #9A3412;
    }

    .anihow-btn-danger {
        background: #9A3412;
        color: #FFFFFF;
    }

    .anihow-id-reject {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem 1.125rem;
        border: 1px solid var(--anihow-line);
        border-radius: 14px;
        background: var(--anihow-surface);
    }

    .anihow-field-label {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--anihow-ink);
    }

    .anihow-input {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid #DDD8CC;
        border-radius: 10px;
        padding: 0.625rem 0.75rem;
        font: inherit;
        background: var(--anihow-surface);
        color: var(--anihow-ink);
    }

    .anihow-error {
        margin: 0;
        font-size: 0.8125rem;
        color: #9A3412;
    }

    .anihow-split button:focus-visible,
    .anihow-split a:focus-visible,
    .anihow-split select:focus-visible,
    .anihow-split textarea:focus-visible {
        outline: 2px solid var(--anihow-green);
        outline-offset: 2px;
    }
    /* ---- Docked panel: the list, with the open record's panel at the right edge ---- */
    .anihow-dock {
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        min-height: 100vh;
    }

    .anihow-dock-main {
        flex: 999 1 32rem;
        min-width: 0;
        box-sizing: border-box;
        padding: 1.75rem;
        display: flex;
        flex-direction: column;
        gap: 1.125rem;
    }

    .anihow-dock-main .fi-page-content,
    .anihow-dock-main > .fi-sc {
        gap: 1rem;
    }

    .anihow-dock-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .anihow-dock-title {
        font-size: 1.5rem;
    }

    .anihow-dock-head .anihow-muted {
        margin-top: 0.25rem;
        font-size: 0.875rem;
    }

    html:not(.dark) .fi-ta-row.anihow-row-on,
    html:not(.dark) .fi-ta-row.anihow-row-on:hover {
        background: #F2F8F4;
    }

    .fi-ta-row.anihow-row-on td:first-child {
        box-shadow: inset 3px 0 0 var(--anihow-green);
    }

    /* Stays in view while the list scrolls, with Save always on screen. */
    .anihow-dock-panel {
        flex: 1 1 28rem;
        max-width: 30rem;
        min-width: 0;
        box-sizing: border-box;
        align-self: flex-start;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        background: var(--anihow-surface);
        border-inline-start: 1px solid var(--anihow-line);
        padding: 1.75rem;
        display: flex;
        flex-direction: column;
        gap: 1.125rem;
    }

    .anihow-dock-panel .fi-fo-field-label-content {
        font-size: 0.9375rem;
    }

    .anihow-dock-foot {
        position: sticky;
        bottom: 0;
        padding-top: 0.75rem;
        background: var(--anihow-surface);
    }

    .anihow-dock-panel-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .anihow-dock-who {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        min-width: 0;
    }

    .anihow-dock-who .anihow-split-name {
        font-size: 1.125rem;
    }

    .anihow-dock-who .anihow-muted {
        margin-top: 0.125rem;
    }

    .anihow-avatar {
        flex-shrink: 0;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 999px;
        background: #DCEBE1;
        color: var(--anihow-green);
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .anihow-dock-close {
        flex-shrink: 0;
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
        border: 0;
        background: #F1EFE9;
        color: var(--anihow-ink);
        font-size: 1rem;
        cursor: pointer;
    }

    .anihow-dock-note {
        margin: 0;
        padding: 0.5rem 0.75rem;
        border-radius: 10px;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .anihow-dock-note-no { background: #FBEAE3; color: #9A3412; }
    .anihow-dock-note-wait { background: #FFF1D6; color: #7A4A00; }

    .anihow-dock-form {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .anihow-dock-rows {
        border: 1px solid var(--anihow-line-soft);
        border-radius: 12px;
        display: flex;
        flex-direction: column;
    }

    .anihow-dock-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 0.875rem;
    }

    .anihow-dock-row + .anihow-dock-row {
        border-top: 1px solid var(--anihow-line-soft);
    }

    .anihow-dock-row > div {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .anihow-dock-row-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--anihow-ink);
    }

    .anihow-dock-row-hint {
        font-size: 0.8125rem;
        color: var(--anihow-ink-muted);
    }

    .anihow-dock-row .fi-link {
        font-size: 0.8125rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .anihow-dock-more {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--anihow-green);
    }

    .anihow-dock-foot {
        margin-top: auto;
        display: flex;
        gap: 0.5rem;
    }

    .anihow-dock-foot .anihow-btn {
        flex: 1 1 0;
        min-height: 2.5rem;
    }

    .anihow-dock-tools {
        display: flex;
        gap: 0.625rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .anihow-search {
        flex: 1 1 15rem;
        max-width: 21.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        height: 2.375rem;
        padding: 0 0.75rem;
        border: 1px solid #DDD8CC;
        border-radius: 10px;
        background: var(--anihow-surface);
        color: var(--anihow-ink-muted);
    }

    .anihow-search input {
        flex: 1;
        min-width: 0;
        border: 0;
        padding: 0;
        background: transparent;
        font: inherit;
        font-size: 0.875rem;
        color: var(--anihow-ink);
        box-shadow: none;
        outline: none;
    }

    .anihow-search:focus-within {
        outline: 2px solid var(--anihow-green);
        outline-offset: 2px;
    }

    .anihow-dock-tools .anihow-chip {
        min-height: 2rem;
        padding: 0 0.75rem;
        font-size: 0.8125rem;
    }

    .anihow-sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .anihow-dock-farm {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        gap: 0.375rem;
        font-size: 0.8125rem;
        color: var(--anihow-ink-muted);
    }

    .anihow-dock-farm .anihow-input {
        width: auto;
        max-width: 15rem;
        height: 2.125rem;
        padding: 0 2rem 0 0.5rem;
        border-radius: 8px;
        font-size: 0.8125rem;
    }


    /* A compact list, as in the design. */
    .anihow-accounts-page .fi-ta-ctn,
    .anihow-listings-page .fi-ta-ctn,
    .anihow-sales-page .fi-ta-ctn {
        border-radius: 14px;
    }
    .anihow-accounts-page .fi-ta-header-cell,
    .anihow-listings-page .fi-ta-header-cell,
    .anihow-sales-page .fi-ta-header-cell {
        padding-top: 0.625rem;
        padding-bottom: 0.625rem;
        font-size: 0.8125rem;
    }
    html:not(.dark) .anihow-accounts-page .fi-ta-header-cell,
    html:not(.dark) .anihow-listings-page .fi-ta-header-cell,
    html:not(.dark) .anihow-sales-page .fi-ta-header-cell,
    html:not(.dark) .anihow-accounts-page .fi-ta-table thead tr,
    html:not(.dark) .anihow-listings-page .fi-ta-table thead tr,
    html:not(.dark) .anihow-sales-page .fi-ta-table thead tr {
        background: var(--anihow-surface-soft);
    }
    .anihow-accounts-page .fi-ta-cell,
    .anihow-listings-page .fi-ta-cell,
    .anihow-sales-page .fi-ta-cell,
    .anihow-accounts-page .fi-ta-record-content .fi-ta-col,
    .anihow-listings-page .fi-ta-record-content .fi-ta-col,
    .anihow-sales-page .fi-ta-record-content .fi-ta-col,
    .anihow-accounts-page .fi-ta-text,
    .anihow-listings-page .fi-ta-text,
    .anihow-sales-page .fi-ta-text {
        font-size: 0.8125rem;
    }
    .anihow-accounts-page .fi-ta-text,
    .anihow-listings-page .fi-ta-text,
    .anihow-sales-page .fi-ta-text {
        padding-top: 0.625rem;
        padding-bottom: 0.625rem;
    }
    .anihow-accounts-page .fi-ta-text.fi-ta-text-has-descriptions,
    .anihow-listings-page .fi-ta-text.fi-ta-text-has-descriptions,
    .anihow-sales-page .fi-ta-text.fi-ta-text-has-descriptions {
        gap: 0;
    }
    .anihow-accounts-page .fi-ta-cell.fi-ta-selection-cell,
    .anihow-listings-page .fi-ta-cell.fi-ta-selection-cell,
    .anihow-sales-page .fi-ta-cell.fi-ta-selection-cell,
    .anihow-accounts-page .fi-ta-cell:has(.fi-ta-actions),
    .anihow-listings-page .fi-ta-cell:has(.fi-ta-actions),
    .anihow-sales-page .fi-ta-cell:has(.fi-ta-actions),
    .anihow-accounts-page .fi-ta-cell:has(.fi-ta-record-checkbox),
    .anihow-listings-page .fi-ta-cell:has(.fi-ta-record-checkbox),
    .anihow-sales-page .fi-ta-cell:has(.fi-ta-record-checkbox) {
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    .anihow-accounts-page .fi-ta-text-description,
    .anihow-listings-page .fi-ta-text-description,
    .anihow-sales-page .fi-ta-text-description {
        font-size: 0.75rem;
    }

    /* ---- Seller requests ---- */
    .anihow-card {
        background: var(--anihow-surface);
        border: 1px solid var(--anihow-line);
        border-radius: 14px;
        padding: 1.125rem 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .anihow-card-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
    }

    .anihow-card-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--anihow-ink);
    }

    .anihow-dock-intro {
        margin-top: 0.25rem;
        font-size: 0.875rem;
    }

    .anihow-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 0;
        border-top: 1px solid var(--anihow-line-soft);
    }

    .anihow-card-head + .anihow-line {
        border-top: 0;
    }

    .anihow-line-main {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        min-width: 0;
    }

    .anihow-line-title {
        font-weight: 600;
        color: var(--anihow-ink);
    }

    .anihow-line-meta {
        font-size: 0.8125rem;
        color: var(--anihow-ink-muted);
    }

    .anihow-line-reason {
        font-size: 0.8125rem;
        color: #9A3412;
    }

    .anihow-line-empty {
        padding: 0.5rem 0;
    }

    .anihow-facts-stack {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .anihow-facts-wide {
        grid-column: 1 / -1;
    }

    .anihow-facts-plain {
        font-weight: 400 !important;
    }

    .anihow-checks {
        margin: 0;
        padding: 0;
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .anihow-check {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 0.875rem;
        color: var(--anihow-ink);
    }

    .anihow-check-dot {
        flex-shrink: 0;
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 999px;
        background: var(--anihow-green);
    }

    .anihow-check-no {
        color: #9A3412;
        font-weight: 600;
    }

    .anihow-check-no .anihow-check-dot {
        background: #9A3412;
    }

    .anihow-created {
        gap: 0.75rem;
        padding: 1.5rem;
    }

    .anihow-password {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        border-radius: 12px;
        background: #F2F8F4;
        border: 1px dashed #A9CDB6;
    }

    .anihow-password code {
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: var(--anihow-ink);
        word-break: break-all;
    }

    .anihow-segment {
        display: flex;
        padding: 0.25rem;
        gap: 0.25rem;
        border-radius: 10px;
        background: #F1EFE9;
    }

    .anihow-segment button {
        flex: 1 1 0;
        min-height: 2.25rem;
        border: 0;
        border-radius: 8px;
        background: transparent;
        font: inherit;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--anihow-ink-muted);
        cursor: pointer;
    }

    .anihow-segment button.anihow-segment-on {
        background: var(--anihow-surface);
        color: var(--anihow-ink);
        box-shadow: 0 1px 2px rgba(30, 36, 33, 0.08);
    }

    .anihow-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .anihow-preview {
        border: 1px solid var(--anihow-line-soft);
        border-radius: 12px;
        padding: 0.75rem 0.875rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        font-size: 0.8125rem;
    }

    .anihow-preview-title {
        font-weight: 600;
        color: var(--anihow-ink);
        margin-bottom: 0.125rem;
    }

    .anihow-preview-line {
        color: var(--anihow-green);
    }

    .anihow-preview-skip {
        color: #9A3412;
    }

    .anihow-dock-note-ok { background: #E3EFE7; color: #1F5A3E; }

    .anihow-dock-foot-stack {
        flex-direction: column;
    }

    .anihow-dock-foot-stack .anihow-btn {
        flex: 0 0 auto;
        min-height: 2.875rem;
    }

    .anihow-dock-panel .anihow-input {
        font-size: 0.875rem;
    }

    .anihow-split-panel .anihow-card {
        max-width: 40rem;
    }

    /* Fields in a row line up along the bottom even when a label wraps. */
    .anihow-dock-panel .fi-grid {
        align-items: end;
    }

    .anihow-dock-foot {
        border-top: 1px solid var(--anihow-line-soft);
        box-shadow: 0 -8px 12px -10px rgba(30, 36, 33, 0.18);
    }

    .anihow-dock-foot .anihow-btn-primary {
        flex: 2 1 0;
    }

    /* ---- Orders and reservations ---- */
    .anihow-segment-inline {
        align-self: flex-start;
    }

    .anihow-segment-inline a {
        display: inline-flex;
        align-items: center;
        min-height: 2.25rem;
        padding: 0 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--anihow-ink-muted);
        text-decoration: none;
    }

    .anihow-segment-inline a.anihow-segment-on {
        background: var(--anihow-surface);
        color: var(--anihow-ink);
        box-shadow: 0 1px 2px rgba(30, 36, 33, 0.08);
    }

    .anihow-pill-self {
        align-self: flex-start;
    }

    .anihow-lines {
        gap: 0.5rem;
    }

    .anihow-lines-row {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: var(--anihow-ink);
    }

    .anihow-lines-note {
        color: var(--anihow-green);
    }

    .anihow-lines-total {
        padding-top: 0.5rem;
        border-top: 1px solid var(--anihow-line-soft);
        font-weight: 700;
    }

    /* ---- Listings ---- */
    .anihow-thumb {
        flex-shrink: 0;
        width: 2.75rem;
        height: 2.75rem;
        border-radius: 10px;
        object-fit: cover;
    }

    .anihow-thumb-letters {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #DCEBE1;
        color: var(--anihow-green);
        font-weight: 700;
        font-size: 0.875rem;
    }

    .anihow-listings-page .fi-ta-image img {
        border-radius: 10px;
    }

    /* Underlined tabs in the panel, all on one line with no scroll strip. */
    .anihow-listing-panel .fi-tabs:not(.fi-vertical) {
        flex-wrap: nowrap;
        overflow: visible !important;
        gap: 0 !important;
        border-bottom: 1px solid var(--anihow-line);
        border-radius: 0;
    }

    .anihow-listing-panel .fi-tabs:not(.fi-vertical) .fi-tabs-item {
        flex: 0 1 auto;
        min-height: 2.5rem;
        padding: 0 0.75rem !important;
        margin-bottom: -1px;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0 !important;
        background: transparent !important;
    }

    .anihow-listing-panel .fi-tabs:not(.fi-vertical) .fi-tabs-item .fi-tabs-item-label {
        color: var(--anihow-ink-muted) !important;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .anihow-listing-panel .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active {
        border-bottom-color: var(--anihow-green);
        background: transparent !important;
    }

    .anihow-listing-panel .fi-tabs:not(.fi-vertical) .fi-tabs-item.fi-active .fi-tabs-item-label {
        color: var(--anihow-green) !important;
    }

    .anihow-listing-panel .fi-sc-tabs-tab {
        padding-top: 1rem;
    }

    @media (max-width: 47.99rem) {
        .anihow-dock-main {
            padding: 1.25rem 1rem;
        }

        .anihow-dock-panel {
            max-width: none;
            position: static;
            height: auto;
            border-inline-start: 0;
            border-top: 1px solid var(--anihow-line);
        }
    }
</style>
