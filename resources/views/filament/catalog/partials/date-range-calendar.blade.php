{{-- CALENDARUL intervalului liber — UN SINGUR calendar, într-un popover.
     Cele două câmpuri de dată de dinainte cereau utilizatorului să tasteze/aleagă separat fiecare
     capăt, fără să vadă niciodată perioada ca întreg. Aici selecția se face DIRECT pe casetele
     zilelor: primul clic alege ziua (deja o selecție validă, aplicată imediat), al doilea o extinde
     la interval și pliază calendarul. Nicio a doua componentă, niciun buton de confirmare.

     Textele (luni, zile, „azi") vin TRADUSE DE PE SERVER — componenta din browser n-are niciun
     cuvânt în ea, deci merge identic în RO/RU/EN. --}}
@php($calendarLocale = $this->timeCalendarLocale())

<div
    x-data="cxDateRange({
        start: @js($this->timeFrom),
        end: @js($this->timeUntil),
        today: @js($calendarLocale['today']),
        months: @js($calendarLocale['months']),
        weekdays: @js($calendarLocale['weekdays']),
    })"
    x-on:keydown.escape.window="close()"
    class="relative max-sm:w-full"
>
    {{-- DECLANȘATORUL: arată perioada aleasă (eticheta vine de pe server, deci e aceeași frază ca
         în restul barei) sau invitația de a alege. --}}
    <button
        type="button"
        x-on:click="toggle()"
        x-bind:aria-expanded="open"
        aria-haspopup="dialog"
        @class([
            'fi-btn fi-size-sm inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium ring-1 transition max-sm:min-h-11 max-sm:w-full',
            'bg-primary-50 text-primary-700 ring-primary-600/30 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30' => ! $this->timeCustomIsEmpty(),
            'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10' => $this->timeCustomIsEmpty(),
        ])
    >
        <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4 shrink-0" />
        <span class="truncate">
            {{ $this->timeCustomIsEmpty() ? __('panel.homework_time.custom_pick') : $this->timePeriodLabel() }}
        </span>
        <x-filament::icon
            icon="heroicon-m-chevron-down"
            class="h-4 w-4 shrink-0 transition"
            x-bind:class="open && 'rotate-180'"
        />
    </button>

    {{-- Fundal de pe telefon: calendarul devine o foaie de jos, iar restul paginii se estompează
         (fără el, atingerea „pe lângă" ar nimeri în tabelul de dedesubt). --}}
    <div
        x-show="open"
        x-transition.opacity
        x-on:click="close()"
        class="fixed inset-0 z-30 bg-gray-950/50 sm:hidden"
        style="display: none"
        aria-hidden="true"
    ></div>

    {{-- PANOUL. Desktop: ancorat sub declanșator. Telefon: foaie de jos, lată cât ecranul. --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1 max-sm:translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1 max-sm:translate-y-4"
        x-on:click.outside="close()"
        role="dialog"
        :aria-label="@js(__('panel.homework_time.custom_pick'))"
        class="absolute start-0 top-full z-40 mt-2 w-72 rounded-xl bg-white p-3 shadow-lg ring-1 ring-gray-950/10 max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:top-auto max-sm:mt-0 max-sm:w-auto max-sm:rounded-b-none max-sm:p-4 max-sm:pb-6 dark:bg-gray-900 dark:ring-white/20"
        style="display: none"
    >
        {{-- Antet: ‹ [Luna Anul] › — eticheta deschide vederea de luni/ani (extindere în ACELAȘI
             panou, fără salt de layout). --}}
        <div class="flex items-center justify-between gap-1">
            <button
                type="button"
                x-on:click="view === 'days' ? prevMonth() : prevYear()"
                class="fi-icon-btn inline-flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 max-sm:size-11 dark:text-gray-400 dark:hover:bg-white/5"
                :aria-label="@js(__('panel.homework_time.prev'))"
            >
                <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
            </button>

            <button
                type="button"
                x-on:click="view = view === 'days' ? 'months' : 'days'"
                class="flex-1 rounded-lg px-2 py-1.5 text-sm font-semibold text-gray-950 hover:bg-gray-50 max-sm:min-h-11 dark:text-white dark:hover:bg-white/5"
                x-text="view === 'days' ? title : year"
            ></button>

            <button
                type="button"
                x-on:click="view === 'days' ? nextMonth() : nextYear()"
                class="fi-icon-btn inline-flex size-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 max-sm:size-11 dark:text-gray-400 dark:hover:bg-white/5"
                :aria-label="@js(__('panel.homework_time.next'))"
            >
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" />
            </button>
        </div>

        {{-- ZILELE --}}
        <div x-show="view === 'days'" class="mt-2">
            <div class="grid grid-cols-7 gap-y-1">
                <template x-for="wd in weekdays" :key="wd">
                    <div class="py-1 text-center text-xs font-medium text-gray-400 dark:text-gray-500" x-text="wd"></div>
                </template>
            </div>

            <div class="grid grid-cols-7" x-on:mouseleave="hover = null">
                <template x-for="cell in cells" :key="cell.key">
                    <div
                        class="p-px"
                        :class="{
                            'bg-primary-50 dark:bg-primary-400/10': cell.date && inRange(cell.date),
                            'rounded-s-lg': cell.date && isFrom(cell.date),
                            'rounded-e-lg': cell.date && isTo(cell.date),
                        }"
                    >
                        <template x-if="! cell.date">
                            <div class="aspect-square"></div>
                        </template>

                        <template x-if="cell.date">
                            <button
                                type="button"
                                x-on:click="select(cell.date)"
                                x-on:mouseenter="hover = cell.date"
                                class="flex aspect-square w-full items-center justify-center rounded-lg text-sm transition max-sm:min-h-11"
                                :class="{
                                    'bg-primary-600 font-semibold text-white hover:bg-primary-500': isEdge(cell.date),
                                    'text-primary-700 dark:text-primary-300': ! isEdge(cell.date) && inRange(cell.date),
                                    'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10': ! isEdge(cell.date) && ! inRange(cell.date),
                                    'ring-1 ring-inset ring-primary-500': cell.date === today && ! isEdge(cell.date),
                                }"
                                :aria-current="cell.date === today ? 'date' : null"
                                x-text="cell.day"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- LUNILE (extinderea): aceeași lățime, doar conținutul se schimbă. --}}
        <div x-show="view === 'months'" class="mt-2 grid grid-cols-3 gap-1" style="display: none">
            <template x-for="(name, index) in months" :key="name">
                <button
                    type="button"
                    x-on:click="month = index; view = 'days'"
                    class="rounded-lg px-2 py-2 text-sm transition max-sm:min-h-11"
                    :class="index === month
                        ? 'bg-primary-600 font-semibold text-white'
                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10'"
                    x-text="name"
                ></button>
            </template>
        </div>

        {{-- Subsolul: ce urmează (îndrumarea ține locul hover-ului pe touch) + ieșirile. --}}
        <div class="mt-3 border-t border-gray-950/5 pt-3 dark:border-white/10">
            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="hint"></p>

            <div class="mt-2 flex items-center justify-between gap-2">
                <button
                    type="button"
                    x-on:click="clear()"
                    class="rounded-lg px-2 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-50 max-sm:min-h-11 dark:text-gray-400 dark:hover:bg-white/5"
                >
                    {{ __('panel.homework_time.clear_range') }}
                </button>

                <button
                    type="button"
                    x-on:click="close()"
                    class="rounded-lg bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 max-sm:min-h-11 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10"
                >
                    {{ __('panel.homework_time.done') }}
                </button>
            </div>
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('cxDateRange', (config) => ({
        open: false,
        view: 'days',
        year: 2026,
        month: 0,
        start: null,
        end: null,
        hover: null,
        // „extindere" = s-a ales o zi și următorul clic o transformă în interval. Tot atunci
        // funcționează previzualizarea la trecerea cursorului.
        extending: false,

        init() {
            this.start = config.start || null;
            this.end = config.end || null;
            this.moveTo(this.start || config.today);
        },

        get today() {
            return config.today;
        },

        get months() {
            return config.months;
        },

        get weekdays() {
            return config.weekdays;
        },

        get title() {
            return config.months[this.month] + ' ' + this.year;
        },

        get hint() {
            if (this.extending) {
                return @js(__('panel.homework_time.hint_extend'));
            }

            return this.start
                ? @js(__('panel.homework_time.hint_restart'))
                : @js(__('panel.homework_time.hint_start'));
        },

        /** Casetele lunii afișate: 0-6 goale pentru aliniere + zilele reale (săptămâna începe luni). */
        get cells() {
            const first = new Date(this.year, this.month, 1);
            const lead = (first.getDay() + 6) % 7;
            const total = new Date(this.year, this.month + 1, 0).getDate();
            const cells = [];

            for (let i = 0; i < lead; i++) {
                cells.push({ key: 'blank-' + i, date: null, day: null });
            }

            for (let day = 1; day <= total; day++) {
                const date = this.iso(this.year, this.month, day);
                cells.push({ key: date, date, day });
            }

            return cells;
        },

        iso(year, month, day) {
            return year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        },

        moveTo(date) {
            const [year, month] = (date || config.today).split('-');
            this.year = Number(year);
            this.month = Number(month) - 1;
        },

        // Capetele PREVIZUALIZATE: în timpul extinderii, ziua de sub cursor ține locul capătului
        // care încă nu a fost ales — utilizatorul vede intervalul înainte să-l confirme.
        get previewFrom() {
            if (this.extending && this.hover && this.start) {
                return this.hover < this.start ? this.hover : this.start;
            }

            return this.start;
        },

        get previewTo() {
            if (this.extending && this.hover && this.start) {
                return this.hover < this.start ? this.start : this.hover;
            }

            return this.end;
        },

        isFrom(date) {
            return date === this.previewFrom;
        },

        isTo(date) {
            return date === this.previewTo;
        },

        isEdge(date) {
            return this.isFrom(date) || this.isTo(date);
        },

        inRange(date) {
            const from = this.previewFrom;
            const to = this.previewTo;

            return !! from && !! to && date >= from && date <= to;
        },

        /**
         * Primul clic = ZIUA aleasă (selecție validă, aplicată pe loc — cine caută o singură zi a
         * terminat). Al doilea clic o extinde la interval și pliază calendarul. Un clic după un
         * interval complet reîncepe selecția.
         */
        select(date) {
            if (! this.extending) {
                this.start = date;
                this.end = date;
                this.extending = true;
                this.hover = null;
                this.apply();

                return;
            }

            if (date < this.start) {
                this.end = this.start;
                this.start = date;
            } else {
                this.end = date;
            }

            this.extending = false;
            this.hover = null;
            this.apply();
            this.close();
        },

        apply() {
            this.$wire.setCustomRange(this.start, this.end);
        },

        clear() {
            this.start = null;
            this.end = null;
            this.hover = null;
            this.extending = false;
            this.$wire.clearCustomRange();
        },

        toggle() {
            this.open ? this.close() : this.openPanel();
        },

        openPanel() {
            this.view = 'days';
            this.extending = false;
            this.hover = null;
            this.moveTo(this.start);
            this.open = true;
        },

        close() {
            this.open = false;
            this.extending = false;
            this.hover = null;
        },

        prevMonth() {
            this.month === 0 ? (this.month = 11, this.year--) : this.month--;
        },

        nextMonth() {
            this.month === 11 ? (this.month = 0, this.year++) : this.month++;
        },

        prevYear() {
            this.year--;
        },

        nextYear() {
            this.year++;
        },
    }));
</script>
@endscript
