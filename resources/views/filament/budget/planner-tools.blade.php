{{--
    Budget Planner client-side tools, injected panel-wide at BODY_END
    (AdminPanelProvider):

    1. Floating calculator — hidden until the Expenses tab's "Calculator"
       header action dispatches the `toggle-bp-calculator` Livewire event
       (which Livewire re-emits as a window event).
    2. Right-click colour marking — a custom context menu on expense month
       cells that sets/clears the "payment started" mark in a chosen colour
       via ExpensesRelationManager::setMonthMark().
--}}

<style>
    .bp-calc {
        position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 60;
        width: 15rem; padding: .75rem; border-radius: .75rem;
        background: #fff; color: #111827;
        box-shadow: 0 10px 25px rgba(0, 0, 0, .25);
        border: 1px solid rgba(0, 0, 0, .08);
        font-variant-numeric: tabular-nums;
    }
    .dark .bp-calc { background: #1f2937; color: #f9fafb; border-color: rgba(255, 255, 255, .10); }
    .bp-calc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .5rem; }
    .bp-calc-head span { font-size: .75rem; font-weight: 600; opacity: .7; }
    .bp-calc-close { border: 0; background: none; cursor: pointer; font-size: 1rem; line-height: 1; opacity: .6; color: inherit; }
    .bp-calc-close:hover { opacity: 1; }
    .bp-calc-display {
        width: 100%; text-align: right; font-size: .95rem; padding: .4rem .5rem;
        border-radius: .5rem; border: 1px solid rgba(0, 0, 0, .15);
        background: rgba(0, 0, 0, .03); color: inherit;
    }
    .dark .bp-calc-display { border-color: rgba(255, 255, 255, .15); background: rgba(255, 255, 255, .06); }
    .bp-calc-result { min-height: 1.4rem; text-align: right; font-size: 1.05rem; font-weight: 700; padding: .15rem .25rem .35rem; }
    .bp-calc-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: .3rem; }
    .bp-calc-grid button {
        padding: .45rem 0; border-radius: .5rem; border: 0; cursor: pointer;
        font-size: .85rem; font-weight: 600;
        background: rgba(0, 0, 0, .06); color: inherit;
    }
    .dark .bp-calc-grid button { background: rgba(255, 255, 255, .10); }
    .bp-calc-grid button:hover { background: rgba(0, 0, 0, .12); }
    .dark .bp-calc-grid button:hover { background: rgba(255, 255, 255, .18); }
    .bp-calc-grid button.bp-calc-op { background: rgba(245, 158, 11, .25); }
    .bp-calc-grid button.bp-calc-eq { background: rgba(34, 197, 94, .35); }
    [x-cloak] { display: none !important; }

    /* "Payment started" mark on an expense month cell (right-click menu).
       data-mark sits on the INNER input (the wrapper is wire:ignore.self, so
       its attributes never re-morph — marks set by other users would stay
       invisible until a full page reload). */
    .bp-month-input input[data-mark] {
        background-color: color-mix(in srgb, var(--bp-mark) 24%, transparent) !important;
        box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--bp-mark) 70%, transparent);
    }
    .bp-month-input input[data-mark='green'] { --bp-mark: #22c55e; }
    .bp-month-input input[data-mark='yellow'] { --bp-mark: #eab308; }
    .bp-month-input input[data-mark='red'] { --bp-mark: #ef4444; }
    .bp-month-input input[data-mark='blue'] { --bp-mark: #3b82f6; }
    .bp-month-input input[data-mark='purple'] { --bp-mark: #a855f7; }

    /* Right-click colour menu. */
    .bp-mark-menu {
        position: fixed; z-index: 70; display: flex; align-items: center; gap: .35rem;
        padding: .45rem .55rem; border-radius: .6rem;
        background: #fff; border: 1px solid rgba(0, 0, 0, .10);
        box-shadow: 0 8px 20px rgba(0, 0, 0, .25);
    }
    .dark .bp-mark-menu { background: #1f2937; border-color: rgba(255, 255, 255, .12); }
    .bp-mark-menu button {
        width: 1.5rem; height: 1.5rem; border-radius: 9999px; border: 0; cursor: pointer;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .15);
    }
    .bp-mark-menu button:hover { transform: scale(1.15); }
    .bp-mark-menu .bp-mark-clear {
        background: transparent; color: inherit; font-size: .85rem; line-height: 1;
        box-shadow: inset 0 0 0 1px rgba(128, 128, 128, .5);
    }

    /* ── Live presence (who is editing which row) ─────────────────────────
       Rows another user currently has focused get a tinted background, a
       coloured left edge and a name chip; a floating bar bottom-left lists
       everyone else working on the same budget version. */
    tr.bp-presence-row { position: relative; }
    tr.bp-presence-row > td {
        background-color: color-mix(in srgb, var(--bp-presence) 12%, transparent) !important;
    }
    /* Slim coloured strip pinned to the row's LEFT edge — identifies who's on
       the row (same colour as their top-bar avatar); hover it to see the name.
       It sits in the left padding gutter and is absolutely positioned, so it
       never covers cell content, never touches the action buttons on the right,
       and adding/removing it never changes row height (no twitch). */
    .bp-presence-edge {
        position: absolute; left: 0; top: 0; bottom: 0; width: 6px;
        background: var(--bp-presence); border-radius: 0 3px 3px 0;
        cursor: help; z-index: 4;
    }
    .bp-presence-edge:hover { width: 9px; }

    /* Instant tooltip for the left-edge strip (native title lags ~1s). */
    .bp-presence-tip {
        position: fixed; z-index: 80; pointer-events: none; display: none;
        padding: .25rem .55rem; border-radius: .4rem;
        font-size: .72rem; font-weight: 600; white-space: nowrap;
        background: #111827; color: #fff;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .35);
        border-inline-start: 3px solid var(--bp-presence, #6b7280);
    }
    .bp-presence-tip.bp-visible { display: block; }

    /* Brief pulse on the row you jumped to from the roster. */
    @keyframes bpPresenceFlash {
        0%, 100% { box-shadow: inset 0 0 0 0 var(--bp-presence); }
        30%      { box-shadow: inset 0 0 0 2px var(--bp-presence); }
    }
    tr.bp-presence-flash > td { animation: bpPresenceFlash 1.5s ease; }
    /* Presence sits CENTERED in the top bar, independent of (and away from)
       the user avatar on the right. Fixed + translateX(-50%) pins it to the
       horizontal centre and the 4rem topbar height centres it vertically; it's
       out of flow so it never pushes the avatar or logo. A group of
       overlapping coloured initials scales to many people; the full list
       (names + what each is doing) drops down on hover/focus. */
    .bp-presence-topbar {
        display: none; align-items: center;
        position: fixed; top: 0; left: 50%; transform: translateX(-50%);
        height: 4rem; z-index: 31; pointer-events: none;
    }
    .bp-presence-topbar.bp-has-people { display: inline-flex; }
    /* Only the avatars/list are interactive; the rest of the strip lets clicks
       through to whatever is under the (transparent) centre of the topbar. */
    .bp-presence-avatars, .bp-presence-list { pointer-events: auto; }
    .bp-presence-avatars { display: inline-flex; align-items: center; cursor: default; }
    .bp-presence-av {
        display: inline-flex; align-items: center; justify-content: center; flex: none;
        width: 1.6rem; height: 1.6rem; margin-inline-start: -0.4rem; border-radius: 9999px;
        font-size: .6rem; font-weight: 700; color: #fff; letter-spacing: .02em;
        background: var(--bp-presence, #6b7280);
        box-shadow: 0 0 0 2px var(--fi-topbar-bg, #fff);
    }
    .dark .bp-presence-av { box-shadow: 0 0 0 2px #18181b; }
    .bp-presence-av:first-child { margin-inline-start: 0; }
    .bp-presence-av.bp-presence-more { background: #6b7280; font-size: .58rem; }

    /* Hover/focus drop-down: the complete roster, always fully readable. */
    .bp-presence-list {
        display: none; position: absolute; top: calc(100% - .6rem); left: 50%; transform: translateX(-50%);
        z-index: 70; min-width: 12rem; max-width: 20rem; padding: .35rem;
        border-radius: .6rem; background: #fff; color: #111827;
        border: 1px solid rgba(0, 0, 0, .10); box-shadow: 0 10px 25px rgba(0, 0, 0, .25);
    }
    .dark .bp-presence-list { background: #1f2937; color: #f9fafb; border-color: rgba(255, 255, 255, .10); }
    .bp-presence-topbar:hover .bp-presence-list,
    .bp-presence-topbar:focus-within .bp-presence-list { display: block; }
    .bp-presence-list-row {
        display: flex; align-items: center; gap: .45rem;
        padding: .3rem .4rem; border-radius: .4rem; font-size: .75rem;
    }
    .bp-presence-list-row span {
        min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .bp-presence-list-row::before {
        content: ''; width: .55rem; height: .55rem; border-radius: 9999px; flex: none;
        background: var(--bp-presence);
    }
    /* Roster entries for someone on a specific row are buttons that jump to it. */
    button.bp-presence-list-row {
        width: 100%; text-align: start; font: inherit; color: inherit;
        background: none; border: 0; cursor: pointer;
    }
    button.bp-presence-list-row:hover,
    button.bp-presence-list-row:focus-visible {
        background: color-mix(in srgb, var(--bp-presence) 16%, transparent); outline: none;
    }
    .bp-presence-list-row .bp-presence-jump { margin-inline-start: auto; opacity: .6; font-size: .85em; flex-shrink: 0; }
    .bp-presence-list-head {
        padding: .2rem .4rem .35rem; font-size: .65rem; font-weight: 700; opacity: .6;
        text-transform: uppercase; letter-spacing: .04em;
    }
</style>

<script>
    window.bpCalculator = () => ({
        open: false,
        expr: '',
        result: '',

        press(key) { this.expr += key; },
        clearAll() { this.expr = ''; this.result = ''; },
        back() { this.expr = this.expr.slice(0, -1); },

        evaluate() {
            const safe = this.expr.replace(/,/g, '.');
            if (safe.trim() === '') return;
            if (! /^[0-9+\-*/(). ]*$/.test(safe)) { this.result = 'Err'; return; }
            try {
                const value = Function('"use strict"; return (' + safe + ')')();
                this.result = Number.isFinite(value)
                    ? String(Math.round(value * 100) / 100)
                    : 'Err';
            } catch (e) {
                this.result = 'Err';
            }
        },
    });

    // Right-click colour menu on expense month cells. The mark is applied to
    // the cell immediately (optimistic) so the highlight doesn't wait on the
    // Livewire round-trip that persists it.
    window.bpMarkMenu = () => ({
        open: false,
        x: 0,
        y: 0,
        cell: null,
        wireId: null,
        colors: { green: '#22c55e', yellow: '#eab308', red: '#ef4444', blue: '#3b82f6', purple: '#a855f7' },

        // Registered with .capture: Filament's text-input-column wrapper
        // stops event propagation, so the bubble phase never reaches window.
        onContextMenu(event) {
            // data-* attrs live on the inner input; locked versions disable
            // the input (pointer-events: none) so the event may land on the
            // wrapper instead — resolve the input from either entry point.
            const wrap = event.target.closest?.('.bp-month-input');
            const cell = wrap?.querySelector('input[data-expense-id]');
            if (! cell) { this.open = false; return; }

            const componentEl = cell.closest('[wire\\:id]');
            if (! componentEl || ! window.Livewire) return;

            event.preventDefault();
            event.stopPropagation();

            this.cell = cell;
            this.wireId = componentEl.getAttribute('wire:id');
            this.x = Math.min(event.clientX, window.innerWidth - 230);
            this.y = Math.min(event.clientY, window.innerHeight - 60);
            this.open = true;
        },

        pick(color) {
            if (this.cell && this.wireId) {
                if (color) {
                    this.cell.dataset.mark = color;
                } else {
                    delete this.cell.dataset.mark;
                }

                // Livewire 3: find() returns the $wire proxy — use $call().
                window.Livewire.find(this.wireId)?.$call(
                    'setMonthMark',
                    parseInt(this.cell.dataset.expenseId),
                    parseInt(this.cell.dataset.month),
                    color,
                );
            }

            this.close();
        },

        close() {
            this.open = false;
            this.cell = null;
            this.wireId = null;
        },
    });

    // ── Live presence + change-driven refresh on the Budget Planner grids ──
    //
    // There is deliberately NO Filament ->poll() on these tables. A table poll
    // re-renders the WHOLE relation-manager Livewire component every few
    // seconds, and each such request momentarily flips `disabled` on the header
    // buttons (Filament's wire:loading.attr) and morphs the filter dropdown —
    // so the funnel/filter and the action buttons become unclickable on a ~3s
    // cadence (cursor flickers pointer→arrow). That is inherent to wire:poll +
    // wire:loading; the only way to avoid it is to not re-render the component
    // on a timer.
    //
    // Instead everything rides ONE plain JSON heartbeat (POST /budget/presence/
    // {version}) every ~3s — a fetch(), never a Livewire call, so it touches no
    // buttons and causes no morph. Its response carries:
    //   • users       — everyone else on this version (drives the row tints +
    //                    top-bar roster, painted entirely client-side), and
    //   • fingerprint — an opaque token that changes iff a row was added/edited/
    //                    deleted. When it changes we do ONE Livewire $refresh of
    //                    the grid — but only while the user is momentarily idle
    //                    (no overlay open, no mouse/keyboard activity for a
    //                    beat), so the unavoidable button-disable of that single
    //                    refresh never lands under a click.
    //
    // Net: nothing changed ⇒ zero re-renders, buttons always live; someone else
    // edited ⇒ the table catches up within a couple seconds of you pausing;
    // presence is live the whole time regardless.
    (() => {
        const COLORS = ['#f59e0b', '#3b82f6', '#22c55e', '#a855f7', '#ec4899', '#14b8a6', '#ef4444', '#8b5cf6'];
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        // The budget version id from the edit URL (/admin/budget-versions/{id}/edit).
        const versionId = () => (location.pathname.match(/budget-versions\/([^/]+)\/edit/) || [])[1] ?? null;

        let focusedRow = null;   // { compId, key }
        let lastOthers = [];
        let debounceTimer = null;
        let barEl = null;
        let lastSig = null;      // roster signature — skip topbar rebuilds when unchanged
        let tipEl = null;        // shared instant tooltip element
        let lastActivity = 0;    // last mouse/keyboard interaction (ms)
        let lastFingerprint = null; // last seen data token
        let dataDirty = false;   // a change arrived; refresh once the user is idle

        // Is any Filament overlay (dropdown panel or modal) currently on screen?
        // They stay in the DOM and are shown/hidden via Alpine x-show
        // (display:none when closed), so a non-none computed display = open.
        const overlayOpen = () => {
            for (const el of document.querySelectorAll('.fi-dropdown-panel, .fi-modal-window, .fi-modal')) {
                if (getComputedStyle(el).display !== 'none' && el.getClientRects().length) return true;
            }
            return false;
        };

        // "Busy" = an overlay is open, or the mouse/keyboard was touched in the
        // last ~1.2s. We refresh the table only when NOT busy, so the single
        // refresh's momentary button-disable can never land under a click.
        // Mouse MOVEMENT counts as activity, so simply moving toward a button
        // holds off the refresh until you've settled.
        const busy = () => overlayOpen() || (Date.now() - lastActivity < 1200);

        // Refresh only the grid (relation-manager) components — found via their
        // .bp-compact rows — not every Livewire component on the page. The
        // page's footer widgets (summary stats + monthly charts) no longer
        // self-poll (their 5s default polling is disabled), so they ride the
        // same fingerprint-driven refresh here. Charts sit behind wire:ignore,
        // so a plain $refresh would not repaint the canvas — they need their
        // own updateChartData() call instead.
        const refreshTables = () => {
            const ids = new Set();
            document.querySelectorAll('tr.bp-compact').forEach((tr) => {
                const el = tr.closest('[wire\\:id]');
                if (el) ids.add(el.getAttribute('wire:id'));
            });
            document.querySelectorAll('.fi-wi-stats-overview').forEach((w) => {
                const el = w.closest('[wire\\:id]');
                if (el) ids.add(el.getAttribute('wire:id'));
            });
            ids.forEach((id) => window.Livewire?.find(id)?.$refresh?.());

            document.querySelectorAll('.fi-wi-chart').forEach((w) => {
                const el = w.closest('[wire\\:id]');
                if (! el) return;
                window.Livewire?.find(el.getAttribute('wire:id'))?.updateChartData?.();
            });
        };

        // Activity tracking (drives busy()).
        ['mousedown', 'keydown', 'touchstart', 'pointerdown', 'mousemove', 'pointermove', 'wheel', 'scroll'].forEach((evt) =>
            document.addEventListener(evt, () => { lastActivity = Date.now(); }, { capture: true, passive: true }));
        // Closing a modal is a good moment to catch up immediately.
        window.addEventListener('close-modal', () => { if (dataDirty) { dataDirty = false; refreshTables(); } });

        // Apply a pending refresh as soon as the user is idle. Cheap tick.
        setInterval(() => {
            if (dataDirty && ! busy()) {
                dataDirty = false;
                refreshTables();
            }
        }, 250);

        // Discover the mounted Investments/Expenses relation-manager components
        // straight from the DOM — NEVER by Livewire component name (Filament
        // registers them under their full backslashed class name, so any
        // kebab-case match silently fails). A budget grid is any Livewire
        // component whose rows carry .bp-compact; the expenses grid is the one
        // with the 12 month inputs (.bp-month-input), everything else is
        // investments. Keyed by the closest [wire:id] so we get the RM itself,
        // not the parent edit page.
        const rms = () => {
            const byId = new Map();
            document.querySelectorAll('tr.bp-compact').forEach((tr) => {
                const el = tr.closest('[wire\\:id]');
                if (! el) return;
                const id = el.getAttribute('wire:id');
                if (byId.has(id)) return;
                const wire = window.Livewire?.find(id);
                if (! wire) return;
                const tab = el.querySelector('.bp-month-input') ? 'expenseItems' : 'investmentItems';
                byId.set(id, { id, wire, tab });
            });
            return [...byId.values()];
        };

        const queueTick = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(tick, 300);
        };

        // Track which table row currently holds keyboard focus.
        document.addEventListener('focusin', (event) => {
            const tr = event.target.closest?.('tr.bp-compact');
            const compEl = tr?.closest?.('[wire\\:id]');
            const wireKey = tr?.getAttribute('wire:key');
            if (! tr || ! compEl || ! wireKey || ! wireKey.includes('.table.records.')) return;

            const next = {
                compId: compEl.getAttribute('wire:id'),
                key: wireKey.split('.table.records.')[1],
            };
            if (focusedRow?.key !== next.key || focusedRow?.compId !== next.compId) {
                focusedRow = next;
                queueTick();
            }
        });

        document.addEventListener('focusout', () => {
            setTimeout(() => {
                if (focusedRow && ! document.activeElement?.closest?.('tr.bp-compact')) {
                    focusedRow = null;
                    queueTick();
                }
            }, 150);
        });

        async function tick() {
            const comps = rms();
            if (! comps.length) return;

            const vid = versionId();
            if (! vid) return;

            const target = comps.find((c) => c.id === focusedRow?.compId) ?? comps[0];
            const row = focusedRow?.compId === target.id ? focusedRow.key : null;

            try {
                // Plain JSON round-trip — deliberately NOT a Livewire call, so
                // the heartbeat never re-renders the grid component. Returns
                // { users: [...others on this version], fingerprint: <token> }.
                const res = await fetch(`/budget/presence/${encodeURIComponent(vid)}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ tab: target.tab, row }),
                });
                if (! res.ok) return;
                const data = await res.json();
                lastOthers = data?.users ?? [];

                // Data change detection: when the fingerprint moves (someone
                // added/edited/deleted a row), flag a refresh — the idle loop
                // above applies it the moment the user isn't busy. Never refresh
                // on the FIRST reading (lastFingerprint === null) — that's just
                // us learning the current state, not a change.
                const fp = data?.fingerprint ?? null;
                if (lastFingerprint !== null && fp !== null && fp !== lastFingerprint) {
                    dataDirty = true;
                }
                lastFingerprint = fp;
            } catch (e) {
                return; // network hiccup / navigation
            }

            // Only rebuild the top-bar avatars when the roster actually changes
            // (who's here + which row) — rebuilding it every 3s made them blink.
            // Also rebuild if the container somehow ended up empty (e.g. after a
            // full page/topbar re-render) while we still have people.
            const sig = lastOthers.map((u) => u.id + ':' + (u.row || '')).join('|');
            const topbarEl = document.getElementById('bp-presence-topbar');
            const topbarStale = lastOthers.length && topbarEl && ! topbarEl.querySelector('.bp-presence-avatars');
            if (sig !== lastSig || topbarStale) {
                lastSig = sig;
                paintTopbar();
            }
            // Row decorations are re-applied atomically (in one synchronous
            // pass), so even an identical repaint produces no visible flicker.
            paintRows();
        }

        function ensureBar() {
            // Prefer the server-rendered top-bar container (next to the avatar);
            // fall back to a body element only if the hook markup isn't present.
            const topbar = document.getElementById('bp-presence-topbar');
            if (topbar) return topbar;
            if (! barEl || ! document.body.contains(barEl)) {
                barEl = document.createElement('div');
                barEl.className = 'bp-presence-topbar';
                document.body.appendChild(barEl);
            }
            return barEl;
        }

        const labelFor = (user) => {
            const tab = user.tab === 'expenseItems' ? 'Expenses' : 'Investments';
            return user.name + (user.row ? ` — editing (${tab})` : ` — viewing (${tab})`);
        };

        const initials = (name) => (name || '?').trim().split(/\s+/)
            .map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?';

        // Scroll to (and briefly pulse) the row a colleague is editing. Only
        // works when that row is in the DOM — i.e. the same tab is open and the
        // row is on the current page; otherwise it no-ops silently.
        function jumpTo(user) {
            if (! user.row) return;
            const comp = rms().find((c) => c.tab === user.tab);
            if (! comp) return;
            const tr = document.querySelector(
                `tr[wire\\:key="${comp.id}.table.records.${String(user.row).replaceAll('"', '\\"')}"]`,
            );
            if (! tr) return;
            tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            tr.classList.remove('bp-presence-flash');
            void tr.offsetWidth; // restart the animation if already flashed
            tr.classList.add('bp-presence-flash');
            setTimeout(() => tr.classList.remove('bp-presence-flash'), 1600);
        }

        // Instant tooltip (native title lags ~1s) — shown via event delegation
        // so it survives the edge strips being repainted on every poll.
        const showTip = (text, x, y, color) => {
            if (! tipEl) {
                tipEl = document.createElement('div');
                tipEl.className = 'bp-presence-tip';
                document.body.appendChild(tipEl);
            }
            tipEl.textContent = text;
            tipEl.style.setProperty('--bp-presence', color || '#6b7280');
            tipEl.style.left = x + 'px';
            tipEl.style.top = y + 'px';
            tipEl.classList.add('bp-visible');
        };
        const hideTip = () => tipEl && tipEl.classList.remove('bp-visible');

        // The centred top-bar avatars + hover roster. Lives outside the table
        // morph region, so it's rebuilt only when the roster changes (tick).
        function paintTopbar() {
            const bar = ensureBar();
            bar.innerHTML = '';
            bar.classList.toggle('bp-has-people', lastOthers.length > 0);
            if (! lastOthers.length) return;

            // Overlapping coloured initials, capped so any number of people
            // fits; the full roster opens on hover/focus below.
            const CAP = 5;
            const avatars = document.createElement('div');
            avatars.className = 'bp-presence-avatars';
            avatars.tabIndex = 0; // tap/keyboard opens the roster on touch devices

            const shown = lastOthers.length > CAP ? lastOthers.slice(0, CAP - 1) : lastOthers;
            shown.forEach((user) => {
                const av = document.createElement('span');
                av.className = 'bp-presence-av';
                av.style.setProperty('--bp-presence', COLORS[user.id % COLORS.length]);
                av.textContent = initials(user.name);
                av.title = labelFor(user);
                avatars.appendChild(av);
            });
            if (lastOthers.length > CAP) {
                const more = document.createElement('span');
                more.className = 'bp-presence-av bp-presence-more';
                more.textContent = '+' + (lastOthers.length - (CAP - 1));
                more.title = lastOthers.slice(CAP - 1).map(labelFor).join('\n');
                avatars.appendChild(more);
            }
            bar.appendChild(avatars);

            const list = document.createElement('div');
            list.className = 'bp-presence-list';
            const head = document.createElement('div');
            head.className = 'bp-presence-list-head';
            head.textContent = lastOthers.length + (lastOthers.length === 1 ? ' person here' : ' people here');
            list.appendChild(head);
            lastOthers.forEach((user) => {
                // If they're on a specific row, the entry is a button that
                // jumps to it; otherwise a plain (non-clickable) line.
                const clickable = !! user.row;
                const rowEl = document.createElement(clickable ? 'button' : 'div');
                rowEl.className = 'bp-presence-list-row';
                rowEl.style.setProperty('--bp-presence', COLORS[user.id % COLORS.length]);

                const text = document.createElement('span');
                text.textContent = labelFor(user);
                rowEl.appendChild(text);

                if (clickable) {
                    rowEl.type = 'button';
                    rowEl.title = 'Jump to this row';
                    const jump = document.createElement('span');
                    jump.className = 'bp-presence-jump';
                    jump.textContent = '↦';
                    rowEl.appendChild(jump);
                    rowEl.addEventListener('click', () => jumpTo(user));
                }
                list.appendChild(rowEl);
            });
            bar.appendChild(list);
        }

        // Tint each occupied grid row + pin a slim coloured strip to its left
        // edge (hover it for the name). Runs after every poll morph (which
        // wipes these) and whenever the roster changes. The whole pass is
        // synchronous, so the browser paints only the final state — no
        // wipe-then-restore flicker. The strip is CSS-absolute, so showing/
        // hiding it never changes row height (no up/down twitch), and it stays
        // in the left gutter so the row's action buttons are never covered.
        function paintRows() {
            document.querySelectorAll('.bp-presence-edge').forEach((el) => el.remove());
            document.querySelectorAll('tr.bp-presence-row').forEach((tr) => {
                tr.classList.remove('bp-presence-row');
                tr.style.removeProperty('--bp-presence');
            });

            if (! lastOthers.length) return;
            const comps = rms();

            lastOthers.forEach((user) => {
                if (! user.row) return;
                const comp = comps.find((c) => c.tab === user.tab);
                if (! comp) return;

                const tr = document.querySelector(
                    `tr[wire\\:key="${comp.id}.table.records.${String(user.row).replaceAll('"', '\\"')}"]`,
                );
                if (! tr) return;

                const color = COLORS[user.id % COLORS.length];
                tr.classList.add('bp-presence-row');
                tr.style.setProperty('--bp-presence', color);

                const td = tr.querySelector('td');
                if (td) {
                    const edge = document.createElement('span');
                    edge.className = 'bp-presence-edge';
                    edge.style.setProperty('--bp-presence', color);
                    // Read by the instant-tooltip delegation (not native title).
                    edge.dataset.label = labelFor(user);
                    td.appendChild(edge);
                }
            });
        }

        const start = () => {
            // The table's own `->poll('3s')` morph wipes the row decorations;
            // re-apply them SYNCHRONOUSLY in the same task as the morph so the
            // browser never paints the undecorated frame (that gap was the
            // visible flicker). Do NOT rebuild the top bar here — it lives
            // outside the table morph region and is untouched by it.
            try { window.Livewire.hook('morphed', () => paintRows()); } catch (e) { /* older Livewire */ }

            // Instant tooltip on the left-edge strips, via delegation so it keeps
            // working after the strips are repainted. Shows the moment you hover.
            document.addEventListener('mouseover', (e) => {
                const edge = e.target.closest?.('.bp-presence-edge');
                if (! edge) return;
                const r = edge.getBoundingClientRect();
                showTip(edge.dataset.label || '', r.right + 6, r.top - 2, edge.style.getPropertyValue('--bp-presence'));
            });
            document.addEventListener('mouseout', (e) => {
                if (e.target.closest?.('.bp-presence-edge')) hideTip();
            });

            // The heartbeat runs on its own fixed interval now — it's a cheap
            // fetch() that never re-renders anything, so there's nothing to
            // synchronise it with (and no click for it to interrupt).
            setInterval(tick, 3000);
            setTimeout(tick, 800);
        };

        window.Livewire?.hook ? start() : document.addEventListener('livewire:init', start);
    })();
</script>

<div
    x-data="bpMarkMenu()"
    x-show="open"
    x-cloak
    x-on:contextmenu.window.capture="onContextMenu($event)"
    x-on:click.outside="close()"
    x-on:keydown.escape.window="close()"
    x-bind:style="`left: ${x}px; top: ${y}px`"
    class="bp-mark-menu"
>
    <template x-for="(hex, name) in colors" :key="name">
        <button type="button" x-bind:style="`background: ${hex}`" x-bind:title="name" x-on:click="pick(name)"></button>
    </template>
    <button type="button" class="bp-mark-clear" title="Remove mark" x-on:click="pick(null)">✕</button>
</div>

<div
    x-data="bpCalculator()"
    x-show="open"
    x-cloak
    x-on:toggle-bp-calculator.window="open = ! open; if (open) $nextTick(() => $refs.display.focus())"
    class="bp-calc"
>
    <div class="bp-calc-head">
        <span>Calculator</span>
        <button type="button" class="bp-calc-close" x-on:click="open = false" title="Close">✕</button>
    </div>

    <input
        type="text"
        class="bp-calc-display"
        x-ref="display"
        x-model="expr"
        x-on:keydown.enter.prevent="evaluate()"
        x-on:keydown.escape="open = false"
        placeholder="0"
        autocomplete="off"
    />
    <div class="bp-calc-result" x-text="result"></div>

    <div class="bp-calc-grid">
        <button type="button" x-on:click="clearAll()">C</button>
        <button type="button" x-on:click="press('(')">(</button>
        <button type="button" x-on:click="press(')')">)</button>
        <button type="button" class="bp-calc-op" x-on:click="back()">⌫</button>

        <button type="button" x-on:click="press('7')">7</button>
        <button type="button" x-on:click="press('8')">8</button>
        <button type="button" x-on:click="press('9')">9</button>
        <button type="button" class="bp-calc-op" x-on:click="press('/')">÷</button>

        <button type="button" x-on:click="press('4')">4</button>
        <button type="button" x-on:click="press('5')">5</button>
        <button type="button" x-on:click="press('6')">6</button>
        <button type="button" class="bp-calc-op" x-on:click="press('*')">×</button>

        <button type="button" x-on:click="press('1')">1</button>
        <button type="button" x-on:click="press('2')">2</button>
        <button type="button" x-on:click="press('3')">3</button>
        <button type="button" class="bp-calc-op" x-on:click="press('-')">−</button>

        <button type="button" x-on:click="press('0')">0</button>
        <button type="button" x-on:click="press('.')">.</button>
        <button type="button" class="bp-calc-eq" x-on:click="evaluate()">=</button>
        <button type="button" class="bp-calc-op" x-on:click="press('+')">+</button>
    </div>
</div>
