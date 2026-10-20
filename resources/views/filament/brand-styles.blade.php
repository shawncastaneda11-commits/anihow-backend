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
</style>
