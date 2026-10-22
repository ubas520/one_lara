(() => {
    const picker = document.querySelector('.address-picker');
    if (!picker) return;
    const province = picker.querySelector('[name="address_province"]');
    const city = picker.querySelector('[name="address_city"]');
    const barangay = picker.querySelector('[name="address_barangay"]');
    const street = document.getElementById('address');
    const status = picker.querySelector('.address-status');
    const retry = picker.querySelector('.address-retry');
    let data, pendingAddress, legacyAddress = null;
    const fill = (select, rows, hint, selected = '') => {
        select.replaceChildren(new Option(hint, ''));
        rows.slice().sort((a, b) => a.name.localeCompare(b.name)).forEach(row => {
            select.add(new Option(row.name, row.code));
        });
        select.value = selected;
        select.disabled = rows.length === 0;
    };
    const requireSelection = () => {
        const legacy = legacyAddress !== null && street.value === legacyAddress && !province.value;
        [province, city, barangay].forEach(select => { select.required = !legacy; });
    };
    const cities = (selected = '') => {
        fill(city, data.cities.filter(row => row.parent === province.value), 'Select city / municipality', selected);
        fill(barangay, [], 'Select a city / municipality first');
    };
    const barangays = (selected = '') => fill(barangay,
        data.barangays.filter(row => row.parent === city.value), 'Select barangay', selected);
    province.addEventListener('change', () => { cities(); requireSelection(); });
    city.addEventListener('change', () => barangays());
    street.addEventListener('input', requireSelection);
    window.setResidentAddress = (address = '') => {
        pendingAddress = address;
        if (!data) return;
        legacyAddress = address || null;
        street.value = address;
        province.value = '';
        cities();
        // Resolve only the exact format written by the shared PHP validator.
        const p = data.provinces.find(row => address.endsWith(', ' + row.name));
        const c = p && data.cities.find(row => row.parent === p.code && address.endsWith(', ' + row.name + ', ' + p.name));
        const b = c && data.barangays.find(row => row.parent === c.code && address.endsWith(', ' + row.name + ', ' + c.name + ', ' + p.name));
        if (b) {
            province.value = p.code;
            cities(c.code);
            barangays(b.code);
            street.value = address.slice(0, -(', ' + b.name + ', ' + c.name + ', ' + p.name).length);
            legacyAddress = null;
        }
        status.textContent = legacyAddress ? 'Existing address kept. To change it, select its province, city/municipality, and barangay, then enter the house/street details.' : '';
        requireSelection();
    };
    picker.closest('form').addEventListener('submit', event => {
        requireSelection();
        if (!data || (!(legacyAddress !== null && street.value === legacyAddress && !province.value)
            && (!province.value || !city.value || !barangay.value))) {
            event.preventDefault();
            status.textContent = data ? 'Complete all three address selections.' : 'Wait for addresses to load, or use Retry loading addresses.';
        }
    });
    async function load() {
        retry.hidden = true;
        status.textContent = 'Loading address options...';
        try {
            const rows = await Promise.all(['provinces', 'cities', 'barangays'].map(async name => {
                const response = await fetch(picker.dataset.source + name + '.json');
                if (!response.ok) throw new Error('Address data unavailable');
                return response.json();
            }));
            data = {provinces: rows[0], cities: rows[1], barangays: rows[2]};
            const selection = JSON.parse(picker.dataset.selection);
            fill(province, data.provinces, 'Select province / Metro Manila', selection.province);
            cities(selection.city);
            barangays(selection.barangay);
            status.textContent = '';
            if (pendingAddress !== undefined) window.setResidentAddress(pendingAddress);
        } catch (error) {
            status.textContent = 'Address options could not load. Please retry.';
            retry.hidden = false;
        }
    }
    retry.addEventListener('click', load);
    load();
})();
