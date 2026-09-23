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
    form.addEventListener('submit', () => {
        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = submitButton.dataset.submittingText ?? 'Submitting…';
        }
    });
});
