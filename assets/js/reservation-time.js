(() => {
    const form = document.getElementById('reservation-form');
    if (!form) return;
    const date = document.getElementById('reservation-date');
    const start = document.getElementById('reservation-start');
    const end = document.getElementById('reservation-end');
    const note = document.getElementById('reservation-time-note');
    const busyNote = document.getElementById('reservation-busy-times');
    const facility = form.querySelector('[name="facility_id"]');
    const submit = form.querySelector('[type="submit"]');
    const bookings = JSON.parse(form.dataset.bookings || '[]');
    const serverNow = Number(form.dataset.serverNow);
    const loadedAt = Date.now();
    const stamp = (time) => Date.parse(`${date.value}T${time}:00+08:00`);

    function updateTimes() {
        const now = serverNow + Date.now() - loadedAt;
        // Shift to Philippine time before formatting; do not use the device timezone.
        const local = new Date(now + 8 * 3600000).toISOString();
        const today = local.slice(0, 10);
        date.min = today;
        const nextMinute = new Date((Math.floor(now / 60000) + 1) * 60000 + 8 * 3600000).toISOString();
        const noTimeLeft = date.value === today && (nextMinute.slice(0, 10) !== today || nextMinute.slice(11, 16) >= '23:59');
        start.min = date.value === today ? nextMinute.slice(11, 16) : '00:00';
        start.max = '23:58';
        start.disabled = end.disabled = !date.value || noTimeLeft;
        let startError = '';
        let endError = '';
        if (start.value && date.value && stamp(start.value) <= now) {
            startError = 'That start time has already passed. Choose a later time or another date.';
        }
        if (start.value === '23:59') startError = 'Choose an earlier start or another date so the reservation can end on the same day.';
        end.min = date.value && start.value && start.value < '23:59' && Number.isFinite(stamp(start.value))
            ? new Date(stamp(start.value) + 60000 + 8 * 3600000).toISOString().slice(11, 16) : '00:00';
        if (end.value && start.value && end.value <= start.value) endError = 'End time must be after start time.';
        const busy = bookings.filter(slot => String(slot.facility_id) === facility.value && slot.reserve_date === date.value);
        const conflict = !!(start.value && end.value && end.value > start.value && busy.some(slot =>
            start.value < slot.end_time.slice(0, 5) && end.value > slot.start_time.slice(0, 5)));
        if (conflict && !startError && !endError) {
            startError = 'Not available: this time overlaps a pending or approved reservation. Choose another time.';
        }
        submit.disabled = conflict || noTimeLeft;
        busyNote.textContent = busy.length ? 'Not available on this date: ' + busy.map(slot =>
            slot.start_time.slice(0, 5) + '–' + slot.end_time.slice(0, 5)).join(', ') + ' (Philippine time).' : '';
        date.setCustomValidity(noTimeLeft ? 'No reservation times remain today. Choose another date.' : '');
        start.setCustomValidity(startError);
        end.setCustomValidity(endError);
        start.setAttribute('aria-invalid', String(!!startError));
        end.setAttribute('aria-invalid', String(!!endError));
        note.textContent = noTimeLeft ? 'No reservation times remain today. Choose another date.'
            : startError || endError || (!date.value ? 'Choose a date first. Times are in Philippine time.'
            : 'Times are in Philippine time. Your start time must be in the future.');
    }
    [date, start, end, facility].forEach(input => {
        input.addEventListener('input', updateTimes);
        input.addEventListener('change', updateTimes);
    });
    window.addEventListener('focus', updateTimes);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) updateTimes(); });
    form.addEventListener('submit', event => {
        updateTimes();
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); }
    });
    updateTimes();
    setInterval(updateTimes, 1000);
})();
