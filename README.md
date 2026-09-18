# Dizzy Reservations Manager

Independent restaurant reservation management for WordPress.

## Reservation form

Add `[dizzy_reservation_form]` to any page. The form collects the guest's name, email, phone, date, time, party size, table and message.

## Table plan

Open **Reservations → Tables** to:

- select a floor-plan image from the WordPress Media Library;
- add, remove and drag tables;
- auto-align the recognised A0–F2 tables to the supplied Dizzy floor plan;
- snap a table magnetically to its detected position while dragging;
- set each table's code, label, capacity, shape, size and rotation;
- temporarily disable a table without deleting it.

Available tables are green, occupied tables are red, and the visitor's current selection is amber. A selection is held for ten minutes and availability is checked again when the reservation is saved.

The **Reservations** list displays the assigned table beside the party size.

## Concert-night reservations

When Dizzy Events Manager has a published paid event on the selected date, the reservation form automatically offers:

- **Dinner + Concert** (default): the guest keeps the table through the concert and explicitly chooses whether tickets were already purchased or should be bought;
- **Dinner only**: the table is reserved until one hour before the concert and concert admission is explicitly excluded.

The selected experience, linked event, ticket intent and calculated table duration are saved with the reservation and repeated in confirmation/status emails. Days without a paid event keep the standard two-hour reservation flow.

“I already have concert tickets” is never preselected. When chosen, the submitted reservation email must match enough valid tickets in Dizzy Ticket Manager for the same event occurrence and party size. Pending or unpaid ticket orders do not pass verification.
