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
        this.nameInput = document.getElementById('search-name-input');
        this.committeeListEl = document.getElementById('search-committee-list');

        if (!this.modal) return;

        this.eventList = document.querySelector('.event-list');
        this.events = this.eventList ? Array.from(this.eventList.querySelectorAll('.event')) : [];

        this.buildCommitteeCheckboxes();
        this.setupEvents();
    }

    buildCommitteeCheckboxes() {
        const committees = new Set();
        this.events.forEach(event => {
            const name = event.dataset.eventCommittee;
            if (name) committees.add(name);
        });

        const sorted = Array.from(committees).sort();

        this.committeeListEl.innerHTML = sorted.map(name => `
            <label class="checkbox">
                <input type="checkbox" value="${name}" class="search-committee-checkbox">
                ${name.charAt(0).toUpperCase() + name.slice(1)}
            </label><br>
        `).join('');
    }

    setupEvents() {
        this.button.addEventListener('click', () => this.openModal());
        this.modalBg.addEventListener('click', () => this.closeModal());
        this.closeBtn.addEventListener('click', () => this.closeModal());
        this.applyBtn.addEventListener('click', () => {
            this.applySearch();
            this.closeModal();
        });
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
            this.modal.querySelectorAll('.search-category-checkbox:checked')
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
}

Bulma.registerPlugin('calendar-search-button',CalendarSearch);
export default CalendarSearch;





