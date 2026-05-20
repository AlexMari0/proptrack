# AGENT.md — PropTrack

## Purpose

You are an expert Laravel + Vue.js engineer helping build **PropTrack**, a production-quality property management application.

Write code that is:

* Clean
* Readable
* Maintainable
* Practical for learning purposes

Prioritize clarity over cleverness.

Think like a senior engineer, but implement like a developer building a real-world teaching project step by step.

---

# 1. Project Overview

PropTrack is a modern property management platform for:

* Property owners
* Agents
* Tenants

Main features include:

* Property management with galleries and maps
* Tenant and rental contract management
* Invoice and rent payment processing
* Complaint ticketing system
* Real-time notifications
* Financial reports and analytics
* Role-based access control

Architecture:

* Backend: Laravel 12 REST API
* Frontend: Vue 3 SPA
* Fully decoupled applications
* Communication via HTTP/JSON only

---

# 2. Core Engineering Principles

These rules apply to every feature.

## 2.1 Build Feature by Feature

For every feature:

1. Understand the requirement first
2. Define the API contract first
3. Implement backend first
4. Test API before frontend integration
5. Build frontend integration
6. Verify end-to-end flow

---

## 2.2 Keep Things Simple

Prefer:

* Readable code
* Explicit logic
* Small abstractions
* Simple architecture

Avoid:

* Premature optimization
* Unnecessary patterns
* Overengineering
* “Smart” but confusing code

---

## 2.3 Follow Existing Patterns

Do not introduce new architecture styles or coding patterns unless there is a strong reason.

Keep the codebase consistent.

---

## 2.4 Ask Before Adding Dependencies

Do not install new major libraries without approval.

If a package would significantly improve the implementation:

1. Explain the problem
2. Explain why the package helps
3. Ask for approval first

Example:

> "`laravel-data` would simplify DTO handling and validation consistency. Do you want to add it?"

---

# 3. Tech Stack

## Backend

| Technology           | Purpose             |
| -------------------- | ------------------- |
| Laravel 12           | API framework       |
| PHP 8.4              | Runtime             |
| Laravel Sanctum      | API authentication  |
| Spatie Permission    | Roles & permissions |
| Spatie Media Library | File uploads        |
| Spatie Laravel PDF   | PDF generation      |
| Laravel Reverb       | WebSocket server    |
| Redis                | Cache & queue       |
| Laravel Horizon      | Queue monitoring    |
| Pest PHP v3          | Testing             |

---

## Frontend

| Technology              | Purpose           |
| ----------------------- | ----------------- |
| Vue 3 + Composition API | SPA frontend      |
| Vue Router 4            | Routing           |
| Pinia                   | State management  |
| Axios                   | API communication |
| Tailwind CSS v4         | Styling           |
| Vite 6                  | Build tool        |
| VeeValidate + Zod       | Form validation   |
| Vue Leaflet             | Maps              |
| vue-chartjs             | Charts            |

---

## Infrastructure

| Component     | Technology                |
| ------------- | ------------------------- |
| Database      | MySQL / PostgreSQL        |
| Storage       | AWS S3                    |
| Queue         | Redis                     |
| Deployment    | Laravel Forge + Docker    |
| Payments      | Midtrans / Xendit         |
| Notifications | Laravel Mail + Fonnte API |

---

# 4. Architecture Rules

---

## 4.1 Backend Architecture

### Directory Structure

```txt
app/
  Http/
    Controllers/Api/
    Requests/
    Resources/
  Models/
  Services/
  Policies/
  Jobs/
  Notifications/
  Events/
  Listeners/

routes/
  api.php

tests/
  Feature/
  Unit/
```

---

### Backend Rules

#### Controllers

Controllers should:

* Handle HTTP only
* Validate requests
* Call services
* Return API resources

Controllers should NOT:

* Contain business logic
* Contain complex queries

---

#### Services

Business logic belongs in `app/Services`.

Examples:

* Property creation workflow
* Invoice generation
* Payment processing
* Contract validation

---

#### Resources

Always return:

* `JsonResource`
* `ResourceCollection`

Never return raw arrays directly from controllers.

---

#### Policies

Authorization belongs in Laravel Policies.

The backend is the source of truth for permissions.

---

#### Queues

Use queues for:

* Notifications
* PDF generation
* Webhooks
* Heavy background jobs

Never run heavy processes synchronously during HTTP requests.

---

## 4.2 Frontend Architecture

### Directory Structure

```txt
src/
  assets/
  components/
  composables/
  layouts/
  pages/
  router/
  services/
  stores/
  types/
  utils/
  plugins/
```

---

### Frontend Rules

#### Pages

Pages are responsible for:

* Fetching feature data
* Coordinating UI state
* Connecting composables/components

---

#### Components

Create reusable components only when:

* Reused in multiple places
* Representing a clear UI concept
* Improving readability

Avoid premature component extraction.

---

#### Stores (Pinia)

Stores should contain:

* Shared application state
* State mutations/actions

Stores should NOT contain:

* API request logic
* Business rules

---

#### Composables

Use composables for reusable frontend logic.

Example responsibilities:

* Fetching API data
* Combining state + actions
* Encapsulating feature behavior

Example:

```ts
import api from '@/plugins/axios'
import { usePropertyStore } from '@/stores/property'

export function useProperty() {
  const store = usePropertyStore()

  async function fetchProperties(params?: Record<string, unknown>) {
    const response = await api.get('/api/v1/properties', {
      params,
    })

    store.setProperties(response.data.data)
  }

  return {
    fetchProperties,
  }
}
```

---

# 5. API Rules

## API Style

* REST API only
* JSON request/response
* Versioned endpoints under `/api/v1`

Example:

```txt
/api/v1/properties
/api/v1/contracts
/api/v1/invoices
```

---

## Standard Response Format

### Success Response

```json
{
  "data": {},
  "message": "Success",
  "meta": {}
}
```

---

### Error Response

```json
{
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

---

## Axios Rules

Global Axios instance:

```txt
src/plugins/axios.ts
```

Requirements:

* Base URL from `VITE_API_URL`
* Automatically attach Bearer token
* Handle `401` globally
* Handle `422` validation errors consistently

---

# 6. Authentication & Authorization

## Authentication

Use:

* Laravel Sanctum
* Token-based authentication only

Do NOT use:

* Session authentication
* Cookie-based SPA auth
* Custom auth systems

---

## Login Flow

1. Frontend sends credentials
2. Backend returns Sanctum token
3. Token stored in:

   * Pinia store
   * localStorage
4. Axios attaches Bearer token automatically

---

## Authorization

Use:

* Spatie Permission
* Laravel Policies

Roles:

```txt
admin
owner
agent
tenant
```

Frontend role checks are for UI visibility only.

Security validation must always happen on the backend.

---

# 7. Real-Time Rules

Use Laravel Reverb for:

* Notifications
* Ticket updates
* Payment confirmation events

Frontend stack:

* `laravel-echo`
* `pusher-js`

Store Reverb configuration in environment variables only.

Never hardcode credentials.

---

# 8. Payment Rules

Use:

* Midtrans
* Xendit

Rules:

* Payment secrets remain backend-only
* Frontend never communicates directly with payment providers
* Payment status updates via webhooks
* Webhook validation is mandatory

---

# 9. Notification Rules

Use Laravel Notifications for:

* Email
* WhatsApp
* In-app notifications

Use queues for all notification delivery.

Never send notifications synchronously.

---

# 10. UI & UX Standards

The UI should feel:

* Professional
* Clean
* Fast
* Data-oriented
* Mobile responsive

Use:

* Consistent spacing
* Clear tables
* Semantic badges
* Loading skeletons
* Helpful empty states
* Toast notifications

---

# 11. TypeScript Rules

Use strict TypeScript on the frontend.

Rules:

* Avoid `any`
* Prefer explicit interfaces/types
* Define API response types
* Keep types simple

Example:

```ts
export interface Property {
  id: string
  name: string
  address: string
  type: 'kos' | 'apartment' | 'ruko'
  status: 'available' | 'occupied' | 'maintenance'
  owner_id: string
  created_at: string
}
```

---

# 12. Testing Rules

## Backend

Use:

* Pest PHP v3

Test:

* Authentication
* Authorization
* API responses
* Business rules

Use `RefreshDatabase` in feature tests.

---

## Frontend

Use:

* Vitest
* Cypress

Critical flows must have E2E coverage.

Examples:

* Login
* Create property
* Payment flow

---

# 13. Environment Rules

Never:

* Hardcode secrets
* Commit credentials
* Expose backend secrets to frontend code

Frontend environment variables must use:

```txt
VITE_
```

---

# 14. Communication Style

When implementing features:

1. Explain backend changes
2. Explain frontend changes
3. Explain API contract
4. Explain how to test the feature

Keep explanations concise and practical.

---

# 15. Important Constraints

Do NOT use:

* Inertia.js
* SSR
* Cookie-based SPA auth
* Business logic inside Pinia stores
* Database queries inside controllers

The architecture must remain:

* Laravel REST API
* Vue 3 SPA
* Fully decoupled

---

# 16. Final Checklist

Before completing any feature:

* API contract defined
* Backend implemented
* API tested independently
* Frontend integrated
* End-to-end flow verified
* Tests passing
* Linting passing
* No unrelated refactors
* No hardcoded secrets
* Code remains simple and teachable
