# PropTrack

Property management for owners and their agents: properties are let to tenants under contracts, tenants are billed monthly, and maintenance is tracked through tickets.

## People

**Owner**:
A person who owns Properties. An Owner sees and manages only their own Portfolio.
_Avoid_: Landlord, pemilik (in code)

**Portfolio**:
The Properties one Owner owns, together with their Contracts, Invoices and Tickets.

**Agent**:
Staff who handle Tickets and property listings for every Owner. Agents do not see billing (Contracts or Invoices).

**Admin**:
Staff who see and manage everything, across all Portfolios.

**Tenant**:
A person who rents a Property under a Contract: the one who is billed and who reports maintenance issues.
_Avoid_: Renter, resident, penyewa (in code)

**Tenant account**:
The login a Tenant uses to see their own Contracts, Invoices and Tickets. A Tenant has at most one and may have none (for example, a walk-in tenant managed only by the owner).
_Avoid_: User (when you mean the Tenant)

## Billing

**Invoice**:
A monthly request for rent payment issued to a Tenant under one Contract, for one billing month.
_Avoid_: Bill, tagihan (in code)

**Billing month**:
The calendar month an Invoice charges rent for. A Contract has at most one Invoice per billing month.

**Due date**:
The last day an Invoice can be paid without being Overdue, set from the Contract's billing day within the billing month.

**Outstanding**:
An Invoice that has been issued and not yet paid or cancelled: money still owed. Every Outstanding Invoice is either Unpaid or Overdue.
_Avoid_: Pending, open

**Unpaid**:
An Outstanding Invoice whose due date has not yet passed.

**Overdue**:
An Outstanding Invoice whose due date has passed, with days counted in Jakarta time. Overdue is a consequence of time, not a separate recorded state, and no one marks an Invoice overdue.
_Avoid_: Late, past-due status

**Paid**:
An Invoice whose payment has been settled by the payment gateway. Paid is final.

**Payable**:
An Invoice a Tenant can pay right now. Today every Outstanding Invoice is Payable.
