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
    /* A tint the page background never uses, so the header row reads as
       part of the white table, not as the page showing through. */
    html:not(.dark) .fi-ta-header-cell {
        background: #EDEAE2;
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
</style>
