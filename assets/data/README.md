# Address options

Local snapshot downloaded on 2026-10-18 from https://psgc.gitlab.io/api/provinces.json, https://psgc.gitlab.io/api/cities-municipalities.json, and https://psgc.gitlab.io/api/barangays.json.

Only codes, names, and parent links are retained. Cities without a province are grouped under Metro Manila or their region's independent cities. The downloaded source can lag administrative changes; review its coverage before refreshing. Keep all three files in sync: both browser filtering and PHP validation use them. No API request is made to the provider during signup.

Complete addresses are stored in the existing residents.address column as house/street details, barangay, city/municipality, province/group. No database migration is needed. Existing free-text addresses may be preserved unchanged when editing a resident.
