// Separate from the required address picker in the Add/Edit Resident form.
window.initResidentAddressFilter = function (table) {
    const root = document.getElementById('residentAddressFilters');
    const province = document.getElementById('filterAddressProvince');
    const city = document.getElementById('filterAddressCity');
    const barangay = document.getElementById('filterAddressBarangay');
    const status = document.getElementById('residentAddressFilterStatus');
    const retry = document.getElementById('retryResidentAddressFilter');
    let data;
    function fill(select, rows, hint) {
        select.replaceChildren(new Option(hint, ''));
        rows.slice().sort((a, b) => a.name.localeCompare(b.name)).forEach(row => select.add(new Option(row.name, row.code)));
        select.value = '';
        select.disabled = rows.length === 0;
    }
    function resetBarangays() {
        fill(barangay, city.value ? data.barangays.filter(row => row.parent === city.value) : [], city.value ? 'All barangays' : 'Select a city first');
    }
    function resetCities() {
        fill(city, province.value ? data.cities.filter(row => row.parent === province.value) : [], province.value ? 'All cities / municipalities' : 'Select a province first');
        resetBarangays();
    }
    function apply() {
        // Match the hierarchical suffix saved by resident_address(), so duplicate
        // barangay names in different cities cannot match the wrong location.
        const parts = [[barangay, data.barangays], [city, data.cities], [province, data.provinces]]
            .map(([select, rows]) => rows.find(row => row.code === select.value)?.name)
            .filter(Boolean);
        const escapeRegex = value => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const query = parts.length ? '(?:^|,\\s*)' + parts.map(escapeRegex).join(',\\s*') + '\\s*$' : '';
        table.column('address:name').search(query, true, false, true).draw();
    }
    province.addEventListener('change', () => { if (data) { resetCities(); apply(); } });
    city.addEventListener('change', () => { if (data) { resetBarangays(); apply(); } });
    barangay.addEventListener('change', () => { if (data) apply(); });
    document.getElementById('clearResidentAddressFilter').addEventListener('click', () => {
        province.value = '';
        if (data) resetCities();
        table.column('address:name').search('').draw();
        if (!province.disabled) province.focus();
    });
    async function load() {
        retry.hidden = true;
        status.textContent = 'Loading address options...';
        try {
            const rows = await Promise.all(['provinces', 'cities', 'barangays'].map(async name => {
                const response = await fetch(root.dataset.source + name + '.json');
                if (!response.ok) throw new Error('Address options unavailable');
                return response.json();
            }));
            data = { provinces: rows[0], cities: rows[1], barangays: rows[2] };
            fill(province, data.provinces, 'All provinces');
            resetCities();
            status.textContent = '';
        } catch (error) {
            status.textContent = 'Address options could not load. Please retry.';
            retry.hidden = false;
        }
    }
    retry.addEventListener('click', load);
    return load();
};
