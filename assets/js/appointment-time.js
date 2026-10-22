(() => {
    const form = document.getElementById('appointment-form');
    if (!form) return;
    const date = document.getElementById('appointment-date');
    const slots = document.getElementById('appointment-slot');
    const note = document.getElementById('appointment-time-note');
    const serverNow = Number(form.dataset.serverNow);
    const loadedAt = Date.now();
    const now = () => serverNow + Date.now() - loadedAt;

    function updateSlots() {
        let available = 0;
        let cleared = false;
        slots.disabled = !date.value;
        for (const option of slots.options) {
            if (!option.value) continue;
            const start = Date.parse(`${date.value}T${option.value.slice(0, 5)}:00+08:00`);
            const expired = !!date.value && (!Number.isFinite(start) || start <= now());
            if (expired && option.selected) cleared = true;
            option.disabled = expired;
            option.textContent = option.value + (expired ? ' (Unavailable)' : '');
            if (!expired) available++;
        }
        if (cleared || !date.value) slots.value = '';
        note.textContent = !date.value ? 'Choose a date to see available times. Times are in Philippine time.'
            : !available ? 'No times remain on this date. Please choose another date.'
            : cleared ? 'Your selected time has already started. Please choose a later time.'
            : 'Times are in Philippine time. Slots that have already started are unavailable.';
    }
    date.addEventListener('change', updateSlots);
    window.addEventListener('focus', updateSlots);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) updateSlots(); });
    form.addEventListener('submit', (event) => {
        updateSlots();
        if (!date.value || !slots.value || !form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
    updateSlots();
    setInterval(updateSlots, 1000);
})();
