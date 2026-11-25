# Overdue is derived from the due date, not stored

An Invoice's recorded status is only `unpaid`, `paid` or `cancelled`. "Overdue" is computed whenever it is read: an unpaid Invoice whose due date is before today, with "today" taken in the billing timezone (Asia/Jakarta). Previously a daily cron bulk-updated rows to `overdue`. That made the status up to a day stale, skipped the audit log, and made tests depend on the calendar. We accepted losing a "became overdue" audit entry, because becoming overdue is a consequence of time, not an action anyone takes.

## Consequences

- Do not add `overdue` back to the status column or reintroduce a mark-overdue job. Anything that needs to react to an Invoice becoming overdue (for example, a reminder) should query for Invoices that fell due yesterday.
- The API still returns `status: "overdue"` as the effective status, so clients never compute it themselves.
