{{-- Componenta Alpine a calendarului de interval (bara temporală, modul Personalizat),
     înregistrată GLOBAL, pe `alpine:init` — adică garantat înainte ca Alpine să inițializeze
     orice element. A stat într-un @script lângă markup și s-a dovedit nesigur: calendarul
     apare prin morph abia când utilizatorul intră pe „Personalizat", iar elementul lui era
     procesat de Alpine ÎNAINTE ca @script-ul să ruleze — componentă nedefinită, panou mort
     (raportat pe planificatorul cantinei, 2026-08-01). Textele traduse NU stau aici: vin prin
     config (`hints`), din blade-ul care randează markup-ul. --}}
<script>
    document.addEventListener('alpine:init', () => {

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
                return config.hints.extend;
            }

            return this.start
                ? config.hints.restart
                : config.hints.start;
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
    });
</script>
