import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

const manilaClock = document.querySelector('[data-manila-clock-time]');

if (manilaClock) {
    const date = document.querySelector('[data-manila-clock-date]');
    const timeFormatter = new Intl.DateTimeFormat('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    });
    const dateFormatter = new Intl.DateTimeFormat('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'Asia/Manila',
    });
    const renderClock = () => {
        const now = new Date();

        manilaClock.textContent = timeFormatter.format(now);
        manilaClock.dateTime = now.toISOString();
        date.textContent = dateFormatter.format(now);
    };

    renderClock();
    window.setInterval(renderClock, 1000);
}

document.querySelectorAll('[data-submit-once]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const confirmMessage = form.dataset.confirmMessage;

        if (confirmMessage && !window.confirm(confirmMessage)) {
            event.preventDefault();

            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.submittingText ?? 'Submitting…';
        }
    });
});

const monthFormatter = new Intl.DateTimeFormat('en-PH', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

document.querySelectorAll('[data-month-picker]').forEach((monthPicker) => {
    const input = monthPicker.querySelector('input[type="month"]');
    const display = monthPicker.querySelector('[data-month-display]');
    const accessibleDisplay = monthPicker.querySelector('[data-month-accessible-display]');
    const updateDisplay = () => {
        const [year, month] = input.value.split('-').map(Number);
        const formatted = year && month
            ? monthFormatter.format(new Date(Date.UTC(year, month - 1, 1)))
            : 'Choose month';

        display.textContent = formatted;
        accessibleDisplay.textContent = formatted;
    };

    input.addEventListener('change', updateDisplay);
});

document.querySelectorAll('[data-employee-picker]').forEach((employeePicker) => {
    const select = employeePicker.querySelector('.employee-picker-native');
    const combobox = employeePicker.querySelector('[data-employee-picker-combobox]');
    const input = employeePicker.querySelector('.employee-picker-input');
    const clearButton = employeePicker.querySelector('[data-employee-picker-clear]');
    const listbox = employeePicker.querySelector('[data-employee-picker-listbox]');
    const form = employeePicker.closest('form');
    const requiresSelection = select.required;
    const options = Array.from(select.options)
        .filter((option) => option.value)
        .map((option, index) => {
            const item = document.createElement('div');
            const name = document.createElement('span');
            const meta = document.createElement('span');
            const department = option.dataset.employeeDepartment;
            const optionId = `${listbox.id}-option-${index}`;

            item.className = 'employee-picker-option';
            item.id = optionId;
            item.dataset.value = option.value;
            item.dataset.search = `${option.dataset.employeeName} ${option.dataset.employeeNumber}`.toLocaleLowerCase();
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            name.className = 'employee-picker-option-name';
            name.textContent = option.dataset.employeeName;
            meta.className = 'employee-picker-option-meta';
            meta.textContent = `#${option.dataset.employeeNumber}${department ? ` · ${department}` : ''}`;
            item.append(name, meta);
            listbox.append(item);

            return { item, option };
        });
    const emptyState = document.createElement('div');
    let visibleOptions = options;
    let activeIndex = -1;

    emptyState.className = 'employee-picker-empty';
    emptyState.setAttribute('role', 'option');
    emptyState.setAttribute('aria-disabled', 'true');
    emptyState.textContent = 'No employees found';
    emptyState.hidden = true;
    listbox.append(emptyState);

    const displayLabel = (option) => option
        ? `${option.dataset.employeeName} · #${option.dataset.employeeNumber}`
        : '';
    const selectedOption = () => select.selectedOptions[0]?.value
        ? select.selectedOptions[0]
        : null;
    const closeListbox = () => {
        listbox.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        options.forEach(({ item }) => item.classList.remove('is-active'));
        activeIndex = -1;
    };
    const setActiveOption = (index) => {
        if (visibleOptions.length === 0) {
            return;
        }

        activeIndex = (index + visibleOptions.length) % visibleOptions.length;
        options.forEach(({ item }) => item.classList.remove('is-active'));
        const activeOption = visibleOptions[activeIndex].item;
        activeOption.classList.add('is-active');
        activeOption.scrollIntoView({ block: 'nearest' });
        input.setAttribute('aria-activedescendant', activeOption.id);
    };
    const filterOptions = () => {
        const currentLabel = displayLabel(selectedOption()).toLocaleLowerCase();
        const inputValue = input.value.trim().toLocaleLowerCase();
        const query = inputValue === currentLabel ? '' : inputValue;

        visibleOptions = options.filter(({ item }) => {
            const isMatch = item.dataset.search.includes(query);
            item.hidden = ! isMatch;

            return isMatch;
        });
        emptyState.hidden = visibleOptions.length !== 0;
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
    };
    const openListbox = () => {
        filterOptions();
        listbox.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };
    const chooseOption = ({ item, option }) => {
        select.value = option.value;
        input.value = displayLabel(option);
        input.setCustomValidity('');
        clearButton.hidden = false;
        options.forEach(({ item: optionItem, option: sourceOption }) => {
            optionItem.setAttribute('aria-selected', sourceOption === option ? 'true' : 'false');
        });

        if (employeePicker.dataset.employeePickerAction === 'true' && option.dataset.employeeUrl) {
            form.action = option.dataset.employeeUrl;
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
        closeListbox();
        input.focus();
    };
    const clearSelection = () => {
        select.value = '';
        input.value = '';
        input.setCustomValidity('');
        clearButton.hidden = true;
        options.forEach(({ item }) => item.setAttribute('aria-selected', 'false'));
        openListbox();
        input.focus();
    };
    const initialSelection = selectedOption();

    select.required = false;
    select.hidden = true;
    combobox.hidden = false;
    input.required = requiresSelection;
    input.value = displayLabel(initialSelection);
    clearButton.hidden = initialSelection === null;

    input.addEventListener('focus', openListbox);
    input.addEventListener('click', openListbox);
    input.addEventListener('input', () => {
        if (input.value !== displayLabel(selectedOption())) {
            select.value = '';
            options.forEach(({ item }) => item.setAttribute('aria-selected', 'false'));
        }

        input.setCustomValidity('');
        clearButton.hidden = input.value.length === 0;
        openListbox();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            openListbox();
            setActiveOption(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            openListbox();
            setActiveOption(activeIndex - 1);
        } else if (event.key === 'Enter' && ! listbox.hidden) {
            const choice = visibleOptions[activeIndex] ?? (visibleOptions.length === 1 ? visibleOptions[0] : null);

            if (choice) {
                event.preventDefault();
                chooseOption(choice);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            input.value = displayLabel(selectedOption());
            clearButton.hidden = selectedOption() === null;
            closeListbox();
        } else if (event.key === 'Tab') {
            closeListbox();
        }
    });
    clearButton.addEventListener('click', clearSelection);
    options.forEach((option) => {
        option.item.addEventListener('pointerdown', (event) => event.preventDefault());
        option.item.addEventListener('click', () => chooseOption(option));
    });
    document.addEventListener('pointerdown', (event) => {
        if (! employeePicker.contains(event.target)) {
            closeListbox();
        }
    });
    form.addEventListener('submit', (event) => {
        if (requiresSelection && ! select.value) {
            event.preventDefault();
            input.setCustomValidity('Select an employee from the list.');
            input.reportValidity();
            openListbox();
        }
    });
});

const modalToOpen = document.querySelector('[data-open-modal-on-load="true"]');

if (modalToOpen) {
    bootstrap.Modal.getOrCreateInstance(modalToOpen).show();
}

const teamAttendance = document.querySelector('[data-team-attendance]');

if (teamAttendance) {
    const activityFeed = teamAttendance.querySelector('[data-team-activity]');
    const refreshError = teamAttendance.querySelector('[data-refresh-error]');
    const initialPayload = JSON.parse(teamAttendance.querySelector('[data-team-initial-payload]').textContent);
    let activitySignature = JSON.stringify(initialPayload.activity);
    const renderActivity = (activity) => {
        const nextSignature = JSON.stringify(activity);

        if (nextSignature === activitySignature) {
            return;
        }

        const items = document.createDocumentFragment();
        let previousDate = null;

        if (activity.length === 0) {
            const emptyState = document.createElement('li');
            emptyState.className = 'feed-empty-state';
            emptyState.textContent = 'No active employees are assigned to this department.';
            items.append(emptyState);
        }

        activity.forEach((event) => {
            if (event.date_label !== previousDate) {
                const separator = document.createElement('li');
                const label = document.createElement('span');
                separator.className = 'feed-date-separator';
                separator.setAttribute('aria-label', event.date_label);
                label.textContent = event.date_label;
                separator.append(label);
                items.append(separator);
                previousDate = event.date_label;
            }

            const item = document.createElement('li');
            const avatar = document.createElement('span');
            const content = document.createElement('div');
            const main = document.createElement('div');
            const name = document.createElement('span');
            const action = document.createElement('span');
            const time = document.createElement('time');
            const meta = document.createElement('div');
            const arrangement = document.createElement('span');

            item.className = event.event === 'Not Clocked In'
                ? 'attendance-event attendance-event-muted'
                : 'attendance-event';
            avatar.className = 'avatar attendance-avatar';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.textContent = event.employee_initials;
            content.className = 'attendance-event-content';
            main.className = 'attendance-event-main';
            name.className = 'attendance-event-name';
            name.textContent = event.employee_name;
            main.append(name);

            if (event.is_hr_representative) {
                const hrBadge = document.createElement('span');
                hrBadge.className = 'hr-badge';
                hrBadge.setAttribute('aria-label', 'HR Representative');
                hrBadge.title = 'HR Representative';
                hrBadge.textContent = 'HR';
                main.append(hrBadge);
            }

            action.className = 'attendance-event-action';
            action.textContent = event.event;
            content.append(main, action);

            if (event.event_time) {
                time.dateTime = event.occurred_at;
                time.textContent = event.event_time;
                main.append(time);
            }

            meta.className = 'attendance-event-meta';
            arrangement.className = 'arrangement-chip';

            if (event.work_arrangement) {
                arrangement.textContent = event.work_arrangement;
                meta.append(arrangement);
            }

            if (event.net_hours && Number.parseFloat(event.net_hours) > 0) {
                const netHours = document.createElement('span');
                netHours.className = 'net-hours';
                netHours.textContent = `${event.net_hours} worked`;
                meta.append(netHours);
            }

            if (meta.childElementCount > 0) {
                content.append(meta);
            }
            item.append(avatar, content);
            items.append(item);
        });

        activityFeed.replaceChildren(items);
        activitySignature = nextSignature;
    };
    const refreshTeamAttendance = async () => {
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(teamAttendance.dataset.statusUrl, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Team Attendance refresh failed.');
            }

            const data = await response.json();
            Object.entries(data.summary).forEach(([key, value]) => {
                const summary = teamAttendance.querySelector(`[data-summary="${key}"]`);

                if (summary) {
                    summary.textContent = value;
                }
            });
            teamAttendance.querySelector('[data-last-updated]').textContent = data.last_updated;
            renderActivity(data.activity);
            refreshError.classList.add('d-none');
        } catch {
            refreshError.classList.remove('d-none');
        }
    };

    window.setInterval(refreshTeamAttendance, 20000);
}
