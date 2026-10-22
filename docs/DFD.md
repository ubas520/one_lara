# OneLara Level 1 DFD

Open `onelara-dfd-level-1.drawio` in draw.io using **File > Open From > Device**. The diagram has one page, solid black directional arrows, editable shapes, three numbered processes and 13 data stores. Each store is one table, with its exact name from the current simplified ERD. The generator compares the table names against `onelara-erd.drawio` and fails if the sets differ.

This is a simplified Level 1 view of the same 13 main tables selected for the simplified ERD. It is not a complete model of all 28 database tables. Supporting registration/verification, uploaded files, audit logs, SMS, community features and financial-ledger side effects are outside this view.

| Process | Logical store | SQL tables |
| --- | --- | --- |
| 1.0 Manage accounts and residents | D1–D3, one store per table | roles, users, residents |
| 2.0 Manage documents, reservations and payments | D4–D8, one store per table | document_types, document_requests, facilities, facility_reservations, payments |
| 3.0 Manage appointments and health services | D9–D13, one store per table | appointments, consultations, health_records, medicines, medicine_dispensing |

Rectangles represent external actors, rounded rectangles represent processes, and parallel-line symbols represent data stores. Every arrow is labelled with the data it carries. Repeated Resident and Staff symbols refer to the same actors and keep the routes short. Each table store appears once; resident profiles flow from the residents store to processes 2 and 3. Process 2 handles both barangay document and facility workflows, including payment verification, within one process. Process 3 includes appointment handling and clinical/inventory work by authorized personnel. Unlike the ERD, the DFD shows table names and data flows, not individual fields or foreign-key relationships.

The table mapping comes from `onelara_database.sql`. Workflow checks used `dashboard_redirect.php`, `register.php`, the resident document/reservation/payment/appointment handlers, `admin/payments.php`, `admin/consultations.php`, `admin/inventory.php`, and `health/appointments.php`. This is based on project files, not a live database inspection. A schema alone does not define directional data flows, so application handlers were also consulted. No separate context DFD was supplied for formal balancing.

Regenerate with `node bin/generate_dfd_level1.cjs`. Save manual edits separately before regenerating.
