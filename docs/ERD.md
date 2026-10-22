# OneLara editable ERD

## Current version: simplified, one page

`onelara-erd.drawio` now shows **13 main tables, all 131 of their fields, and 22 relationships on one page**. It focuses on accounts, residents, documents, facility reservations, payments, appointments, consultations, health records, and medicine inventory/dispensing. Connectors are solid black, with no dashed lines, jump arcs, or field question marks. All objects remain editable.

This is a selected view, not the complete database: community features and supporting registration, verification, file, logging, and SMS tables are omitted. The complete two-part version is preserved in `onelara-erd-complete.drawio`.

Regenerate the current version with `node bin/generate_core_erd.cjs`. Save manual diagram edits separately first.

## Previous complete version: two parts

Open `onelara-erd.drawio` in draw.io using **File > Open From > Device**. Use the bottom tabs to switch between **Part 1 — Accounts and community** and **Part 2 — Services and payments**. Together they contain all 28 tables, all 254 fields, and 40 relationships. Users and residents appear in both parts for context; they represent the same entities. Every field displays its name and data type, with PK/FK/UK/REF markers where applicable. Question marks are omitted; nullability, enum values, and full SQL definitions remain available on hover. All connectors are solid black with no line-jump arcs. All objects are editable. Connector routes are checked for table intersections and shared segments; crossing lines do not represent junctions.

Regenerate this version with `node bin/generate_twopart_erd.cjs`. Save manual edits separately before regenerating. The older generators below produce superseded layouts.

Sources are the project's three SQL schema files and application code, not an inspection of live MySQL. The original detailed diagram remains in `onelara-erd-detailed.drawio`.

## Earlier multi-page versions (reference only)

The current `onelara-erd.drawio` is the simplified version: all pages show primary keys and relationship fields, without data types or connector labels. Hover over tables for full column definitions and over connectors for relationship details. Explicit connector bend points route around table interiors; the generator checks every segment for intersections. Moving tables manually may require adjusting their connector bend points.

The original full-attribute version is preserved as `onelara-erd-detailed.drawio`. The detailed-page descriptions below refer to that version. Both files retain all 28 tables and all 40 relationships.

Regenerate the simplified version with `node bin/generate_simple_erd.cjs`.

Connectors now use separate attachment points and spaced routing lanes. The generator verifies that no connector enters a table and no two connectors share a line segment. Perpendicular crossings can still occur; they do not represent junctions. Extra canvas space keeps the routes separate.

Open `onelara-erd.drawio` in draw.io / diagrams.net using **File > Open From > Device**. Tables, attribute rows, labels, and connectors are native editable diagram objects. Use the page tabs along the bottom to switch views.

- **01 — Complete overview:** all 28 tables, showing primary keys and relationship columns.
- **02 — Accounts and registration:** roles, accounts, residents, officials, review, proofs, and email verification.
- **03 — Documents and payments:** documents, files, facilities, reservations, and payments.
- **04 — Health services:** appointments, consultations, health records, and medicine inventory/movements.
- **05 — Community and administration:** announcements, incidents, events, financial records, logs, and messages.
- **06 — SMS and devices:** queue, delivery logs, and IoT devices.

Detailed pages collectively include all 254 columns. Shared entities are repeated for context; editing a copy does not update other pages automatically. Hover over a column for its complete SQL definition, including enum values and defaults.

PK = primary key; FK = declared database foreign key; UK = single-column unique key; REF = application-managed reference; ? = nullable. Solid connectors represent the 33 SQL foreign keys. Dashed connectors represent seven application references without SQL foreign-key constraints. Connectors run from parent to child; cardinalities follow nullability and uniqueness in the supplied schema. Child collections may contain zero rows.

The two dashed payment links are conditional alternatives selected by `payable_type`, not two required parents. Generic audit and SMS reference IDs are not drawn as foreign keys because the schema does not define a fixed target. Application references do not imply that database referential integrity is enforced.

Sources: `onelara_database.sql`, `bin/registration_email_schema.sql`, `bin/email_otp_schema.sql`, and application code for logical references. This describes the checked-in schema, not a live database inspection. The email verification tables require the separate setup described in `DATABASE.md`.

Regenerate from the project root with `node bin/generate_erd.cjs`. Regeneration overwrites the diagram, so save manual edits as a separate file first.
