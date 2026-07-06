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

    /* "Payment started" mark on an expense month cell (right-click menu). */
    .bp-month-input[data-mark] input {
        background-color: color-mix(in srgb, var(--bp-mark) 24%, transparent) !important;
        box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--bp-mark) 70%, transparent);
    }
    .bp-month-input[data-mark='green'] { --bp-mark: #22c55e; }
    .bp-month-input[data-mark='yellow'] { --bp-mark: #eab308; }
    .bp-month-input[data-mark='red'] { --bp-mark: #ef4444; }
    .bp-month-input[data-mark='blue'] { --bp-mark: #3b82f6; }
    .bp-month-input[data-mark='purple'] { --bp-mark: #a855f7; }

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
            const cell = event.target.closest?.('.bp-month-input[data-expense-id]');
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
