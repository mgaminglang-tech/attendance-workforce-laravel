import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

const manilaClock = document.querySelector('[data-manila-clock]');

if (manilaClock) {
    const formatter = new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'full',
        timeStyle: 'medium',
        timeZone: 'Asia/Manila',
    });
    const renderClock = () => {
        manilaClock.textContent = formatter.format(new Date());
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

const teamAttendance = document.querySelector('[data-team-attendance]');

if (teamAttendance) {
    const membersBody = teamAttendance.querySelector('[data-team-members]');
    const refreshError = teamAttendance.querySelector('[data-refresh-error]');
    const statusBadgeClass = (status) => {
        if (status === 'Working') {
            return 'text-bg-success';
        }

        if (status === 'Completed') {
            return 'text-bg-primary';
        }

        return 'text-bg-secondary';
    };
    const appendCell = (row, value) => {
        const cell = document.createElement('td');
        cell.textContent = value ?? '—';
        row.append(cell);

        return cell;
    };
    const renderMembers = (members) => {
        const rows = document.createDocumentFragment();

        if (members.length === 0) {
            const row = document.createElement('tr');
            const cell = appendCell(row, 'No active employees are assigned to this department.');
            cell.colSpan = 6;
            cell.className = 'text-center text-body-secondary py-5';
            rows.append(row);
        }

        members.forEach((member) => {
            const row = document.createElement('tr');
            const employeeCell = document.createElement('td');
            const employeeName = document.createElement('span');
            const employeeNumber = document.createElement('span');
            const statusCell = document.createElement('td');
            const statusBadge = document.createElement('span');

            employeeName.className = 'fw-semibold';
            employeeName.textContent = member.employee_name;
            employeeNumber.className = 'small text-body-secondary';
            employeeNumber.textContent = member.employee_number;
            employeeCell.append(employeeName, document.createElement('br'), employeeNumber);
            row.append(employeeCell);

            statusBadge.className = `badge status-badge ${statusBadgeClass(member.status)}`;
            statusBadge.textContent = member.status;
            statusCell.append(statusBadge);
            row.append(statusCell);

            appendCell(row, member.work_arrangement);
            appendCell(row, member.work_date);
            appendCell(row, member.time_in);
            appendCell(row, member.time_out);
            rows.append(row);
        });

        membersBody.replaceChildren(rows);
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
            renderMembers(data.members);
            refreshError.classList.add('d-none');
        } catch {
            refreshError.classList.remove('d-none');
        }
    };

    window.setInterval(refreshTeamAttendance, 20000);
}
