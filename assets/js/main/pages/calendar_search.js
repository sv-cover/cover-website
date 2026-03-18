import {Bulma} from 'cover-style-system/src/js';

class CalendarSearch {
    static getRootClass() {
        return 'calendar-search-button';
    }

    static parse(element) {
        new CalendarSearch({element});
    }

    constructor(options) {
        this.button = options.element;
        this.modal = document.getElementById('search-modal');
        this.modalBg = document.getElementById('search-modal-bg');
        this.closeBtn = document.getElementById('search-close-btn');
        this.applyBtn = document.getElementById('search-apply-btn');
        this.resetBtn = document.getElementById('search-reset-btn');
        this.nameInput = document.getElementById('search-name-input');
        this.committeeListEl = document.getElementById('search-committee-list');
        this.categoryListEl = document.getElementById('search-category-list');

        if (!this.modal) return;

        this.eventList = document.querySelector('.event-list');
        this.events = this.eventList ? Array.from(this.eventList.querySelectorAll('.event')) : [];

        this.buildCommitteeCheckboxes();
        this.setupEvents();
    }

    buildCommitteeCheckboxes() {
        const committees = new Map();
        this.events.forEach(event => {
            const key = event.dataset.eventCommittee;
            const display = event.dataset.eventCommitteeDisplay;
            if (key && !committees.has(key))
                committees.set(key, display);
        });

        const sorted = Array.from(committees.entries())
            .sort((a, b) => a[1].localeCompare(b[1]));

        this.committeeListEl.innerHTML = sorted.map(([key, display], i) => `
            <input type="checkbox" id="search-committee-${i}" value="${key}" class="chip search-committee-checkbox">
            <label for="search-committee-${i}">${display}</label>
        `).join('');
    }

    setupEvents() {
        this.button.addEventListener('click', () => this.openModal());
        this.modalBg.addEventListener('click', () => this.closeModal());
        this.closeBtn.addEventListener('click', () => this.closeModal());
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeModal();
        });
        this.applyBtn.addEventListener('click', () => {
            this.applySearch();
            this.closeModal();
        });
        this.resetBtn.addEventListener('click', () => this.resetSearch());
    }

    openModal() {
        this.modal.classList.add('is-active');
    }

    closeModal() {
        this.modal.classList.remove('is-active');
    }

    applySearch() {
        const nameQuery = this.nameInput.value.trim().toLowerCase();

        const selctedCommittees = Array.from(
            this.committeeListEl.querySelectorAll('.search-committee-checkbox:checked')
        ).map(cb => cb.value);

        const selectedCategories = Array.from(
            this.categoryListEl.querySelectorAll('.search-category-checkbox:checked')
        ).map(cb => cb.value);

        this.events.forEach(event => {
            let visible = true;

            if (nameQuery) {
                const eventName = event.dataset.eventName || '';
                if (!eventName.includes(nameQuery)) {
                    visible = false;
                }
            }

            if (selctedCommittees.length > 0) {
                const eventCommittee = event.dataset.eventCommittee || '';
                if (!selctedCommittees.includes(eventCommittee)) {
                    visible = false;
                }
            }

            if (selectedCategories.length > 0) {
                const eventCategory = (event.dataset.eventCategory || '').split(',');
                const hasMatch = selectedCategories.some(cat => eventCategory.includes(cat));
                if (!hasMatch) {
                    visible = false;
                }
            }

            event.style.display = visible ? '' : 'none';

            
        });

        // Hide month titles if no events under them
        if (this.eventList) {
            const monthTitles = this.eventList.querySelectorAll('.month-title');
            monthTitles.forEach(title => {
                let next = title.nextElementSibling;
                let hasVisile = false;
                while (next && !next.classList.contains('month-title')) {
                    if (next.classList.contains('event') && next.style.display !== 'none') {
                        hasVisile = true;
                        break;
                    }
                    next = next.nextElementSibling;
                }
                title.style.display = hasVisile ? '' : 'none';
            });
        }
    }

    resetSearch() {
        this.nameInput.value = '';
        this.committeeListEl.querySelectorAll('.search-committee-checkbox')
            .forEach(cb => cb.checked = false);
        this.categoryListEl.querySelectorAll('.search-category-checkbox')
            .forEach(cb => cb.checked = false);
        this.events.forEach(event => event.style.display = '');
        if (this.eventList) {
            this.eventList.querySelectorAll('.month-title')
                .forEach(title => title.style.display = '');
        }
        this.button.classList.remove('is-info');
    }
}

Bulma.registerPlugin('calendar-search-button',CalendarSearch);
export default CalendarSearch;





