# Minerva Digital Platform — Frontend Tasks

**Document version:** 1.0  
**Source:** Software Requirements Specification (SRS) v1.1  
**Proposed frontend:** React + TypeScript  
**Audience:** Frontend developers, UI/UX designers, QA engineers, and project leads

---

## 1. Purpose and Scope

This document breaks the approved Minerva Digital Platform SRS into frontend implementation tasks. It covers the public website, authenticated participant, publisher, and administrator experiences. Backend services, database schema, deployment configuration, and API implementation are out of scope except where their contracts affect frontend integration.

## 2. Frontend Principles

- Build a responsive, accessible web application for desktop, tablet, and mobile.
- Use TypeScript throughout; avoid untyped API responses and role checks.
- Enforce access controls in the user interface for clarity, while treating backend authorization as the source of truth.
- Use clear loading, empty, success, and error states for every server-backed screen.
- Never expose restricted results, controls, or private analytics in the browser before authorization has been confirmed.
- Design reusable components for common navigation, data display, forms, status indicators, and moderation actions.

## 3. Suggested Application Structure

```text
src/
  api/             # Typed API client and endpoint modules
  app/             # Routing, providers, application configuration
  components/      # Reusable UI components
  features/        # Feature modules by domain
  hooks/           # Shared React hooks
  layouts/         # Public, participant, publisher, admin layouts
  pages/           # Route-level pages
  types/           # Domain and API types
  utils/           # Formatting, validation, access-control helpers
```

Recommended feature modules: `auth`, `public-content`, `articles`, `voting`, `comments`, `participant`, `publisher`, `admin`, `workshops`, `magazine`, and `analytics`.

## 4. Shared Foundation Tasks

| ID | Task | Deliverable | Acceptance criteria |
|---|---|---|---|
| FE-01 | Initialize application | React + TypeScript project with linting, formatting, test setup, environment configuration, and build scripts | App builds successfully; type checking, linting, and tests run in CI. |
| FE-02 | Establish design foundations | Color, typography, spacing, breakpoints, elevation, status colors, and responsive layout tokens | Shared tokens are used across new screens. |
| FE-03 | Build application shell | Header, footer, mobile navigation, page container, breadcrumbs where needed, and public layout | Navigation is usable at 320 px through desktop widths. |
| FE-04 | Configure routing | Public, authenticated, participant, publisher, and administrator route groups | Direct navigation preserves intended location after sign-in; unauthorized routes are safely handled. |
| FE-05 | Implement typed API layer | Fetch client, auth token handling, typed request/response models, error normalization | Feature code does not make untyped ad hoc requests. |
| FE-06 | Implement session and permission state | Current-user profile, roles, permissions, loading/expired session handling, permission helpers | UI updates correctly after login/logout and does not assume publisher and participant are mutually exclusive. |
| FE-07 | Build shared UI states | Skeletons, inline validation, empty states, error panels, confirmation dialogs, toasts | Each async feature presents meaningful pending, error, and empty feedback. |
| FE-08 | Accessibility baseline | Semantic landmarks, keyboard navigation, visible focus, label/description associations, contrast, screen-reader announcements | Critical workflows can be completed with keyboard only. |

## 5. Public Website Tasks

| ID | Task | Key requirements | SRS reference |
|---|---|---|---|
| FE-10 | Homepage | Hero, initiative introduction, featured workshops/events/articles, sponsor preview, calls to register and browse articles | FR-01 |
| FE-11 | Initiative page | Present objectives, activities, initiative structure, competition summary, and related links | FR-02, FR-04 |
| FE-12 | Workshops pages | Workshop list and detail views with title, description, date, speakers, and supplied resources | FR-03 |
| FE-13 | Competition information page | Rules, eligibility, important dates, article browsing entry point, sign-in prompt for voting | FR-04 |
| FE-14 | Timeline page | Chronological, responsive event timeline; clear event dates and statuses | FR-05 |
| FE-15 | Sponsors page | Sponsor names, logos, optional links, and accessible logo alt text | FR-06 |
| FE-16 | Organizing committee page | Committee members, teams/positions, and supplied profile information | FR-07 |
| FE-17 | Magazine pages | Magazine overview, E-Magazine metadata, and accessible link/download/open action when published | FR-08, FR-09 |
| FE-18 | Public article catalogue | Paginated/searchable/filterable published article list, categories, featured state, and article cards | FR-25, FR-26 |
| FE-19 | Public article detail | Article content, author, date, category, permitted attachments, comments section, and contextual sign-in prompts | FR-25, FR-26, FR-42 |

### Public-content acceptance criteria

- Unauthenticated users can browse only published public content.
- Public visitors see no vote controls, registered-user-only vote results, dashboards, moderation controls, or private analytics.
- Pages remain readable and operable on mobile screens.
- External links, downloads, and attachments are visibly identified and safely opened.

## 6. Authentication and Account Tasks

| ID | Task | Key requirements | SRS reference |
|---|---|---|---|
| FE-20 | Registration page | Required-field validation, password guidance, duplicate-account error display, terms/consent placeholder if required | FR-11 |
| FE-21 | Login page | Email/username and password form, validation, secure error messages, redirect to intended destination | FR-12, FR-14 |
| FE-22 | Logout control | Clear session state and return user to a safe public route | FR-12 |
| FE-23 | Password recovery flow | Request reset, reset confirmation, new-password validation, expired/invalid-link states | FR-13 |
| FE-24 | Account profile | Display/edit only permitted account fields; handle saved, validation, and API error states | FR-55 |
| FE-25 | Access-denied and not-found pages | Friendly 403/404 pages with safe next actions | FR-14, FR-15 |

## 7. Participant Experience Tasks

| ID | Task | Key requirements | SRS reference |
|---|---|---|---|
| FE-30 | Participant dashboard | Account summary, voting activity, commenting activity, relevant alerts, and article browsing links | FR-55 |
| FE-31 | Vote control | Show only to authenticated eligible participants on published articles during voting period | FR-28–FR-31, FR-40–FR-41 |
| FE-32 | Final-vote confirmation | Explain that a submitted vote is final; require a clear confirmation before submission | FR-32–FR-33 |
| FE-33 | Vote-result state | After successful vote, replace action with a final-vote state and show updated data when permitted | FR-36–FR-37 |
| FE-34 | Live voting results | Present current result counts/charts only to authenticated users authorized by policy; subscribe to real-time updates with polling fallback | FR-37–FR-38 |
| FE-35 | Voting-period messaging | Display not-open, open, and closed states using server-provided timestamps; never rely only on client time | FR-40–FR-41 |
| FE-36 | Comment submission | Authenticated participant form with client-side validation, submit state, and successful-comment rendering | FR-42–FR-44 |
| FE-37 | My activity view | Show the participant’s own voting and comment activity according to API-provided data | FR-55 |

### Voting UI rules

- The page must not imply a vote may be edited, withdrawn, or resubmitted.
- Disable and explain unavailable actions, but submit handling must gracefully recover from server-side rejection (duplicate vote, closed period, expired session, or ineligible account).
- Do not calculate authoritative vote totals in the client; render server-supplied results.
- Use an accessible live region for vote-confirmation and incoming real-time result updates without making screen readers overly noisy.

## 8. Publisher Experience Tasks

| ID | Task | Key requirements | SRS reference |
|---|---|---|---|
| FE-40 | Publisher dashboard | Owned article list, status summary, create-article action, review feedback, and own-article engagement overview | FR-56 |
| FE-41 | Article editor | Title, abstract/introduction, body editor, category selector, attachment/image upload, draft save, and validation | FR-20, FR-21, FR-26 |
| FE-42 | Article workflow controls | Render permitted actions based on article status and publisher permissions: save draft, submit review, or direct publish | FR-19–FR-24 |
| FE-43 | Article status/history UI | Clearly display Draft, Pending Review, Approved, Rejected, Published, and Unpublished; show any available review feedback/history | FR-22, FR-27 |
| FE-44 | File upload UX | File type/size guidance, upload progress, failures/retry, attachment list, and remove action only where permitted | FR-20, FR-26 |
| FE-45 | Own-article analytics | Present votes, comment count, publication status, and available engagement statistics for owned articles | FR-50–FR-52 |
| FE-46 | Publisher authorization states | Show an informative unavailable state when a user lacks publisher access or direct publishing permission | FR-18–FR-19 |

### Publisher UI rules

- A participant who is also a publisher must be able to access both sets of permitted features.
- Direct publishing controls must only render if the API confirms the specific direct-publishing permission.
- Analytics pages must use an article selector restricted to API-returned owned articles; handle a server-side authorization rejection without revealing data.

## 9. Administrator Experience Tasks

| ID | Task | Key requirements | SRS reference |
|---|---|---|---|
| FE-50 | Administrator dashboard | Overview cards and navigation for users, content, articles, voting, comments, analytics, and audit activity | FR-57 |
| FE-51 | User management | Search/list users, inspect roles, grant/revoke publisher permission, configure direct-publish setting, confirm high-impact changes | FR-18–FR-19 |
| FE-52 | Article review queue | Filterable pending list; article preview; approve/reject; optional feedback; publish/unpublish/feature controls | FR-23–FR-24 |
| FE-53 | Content management screens | CRUD interfaces for workshops, resources, timeline, sponsors, committee, magazine, and E-Magazine content | FR-10, FR-03, FR-05–FR-09 |
| FE-54 | Voting management | Configure and display voting start/end times; show current open/closed status; use explicit confirmation for changes | FR-39–FR-41 |
| FE-55 | Comment moderation queue | Filter comments; view author/article/timestamp; remove inappropriate comments with mandatory confirmation and reason where API supports it | FR-45–FR-47 |
| FE-56 | Platform analytics | Platform-wide article, vote, and engagement views with useful filters and export action only if provided by API | FR-39, FR-53 |
| FE-57 | Audit-log viewer | Search/filter relevant platform activity and administrative action history | Administrator permissions, NFR Security |

## 10. Shared Components

- `AppHeader`, `PublicNavigation`, `DashboardNavigation`, `Footer`
- `ProtectedRoute`, `PermissionGate`, `RoleBadge`
- `PageHeader`, `Breadcrumbs`, `EmptyState`, `ErrorState`, `LoadingSkeleton`
- `DataTable`, `Pagination`, `SearchField`, `FilterBar`, `StatusBadge`
- `ConfirmDialog`, `Toast`, `InlineAlert`, `FormField`, `FileUploader`
- `ArticleCard`, `ArticleStatus`, `ArticleEditor`, `AttachmentList`
- `VotePanel`, `VotingResults`, `CommentList`, `CommentForm`
- `AnalyticsSummaryCard`, `DateRangeFilter`, `ActivityTimeline`

## 11. Data, API, and Real-Time Integration Tasks

| ID | Task | Acceptance criteria |
|---|---|---|
| FE-60 | Define TypeScript domain models | Models cover user, permissions, content, article, vote, comment, analytics, and paginated responses. |
| FE-61 | Implement API query/mutation hooks | Each endpoint has loading, success, error, and invalidation/refresh behavior. |
| FE-62 | Centralize authorization handling | 401 triggers session recovery/sign-in; 403 presents access denied; UI never treats a hidden button as authorization. |
| FE-63 | Implement real-time client | Connect authenticated users to permitted vote-result updates using Laravel Reverb/WebSockets; reconnect safely. |
| FE-64 | Add real-time fallback | Refresh data through a bounded polling strategy if the socket is unavailable. |
| FE-65 | Handle file delivery | Render API-provided safe download/view URLs; do not construct storage URLs client-side. |
| FE-66 | Secure environment configuration | Only public, non-secret configuration is exposed in the frontend build; no API keys, storage credentials, or admin secrets in client code. |

## 12. Quality Assurance Tasks

| ID | Task | Minimum coverage |
|---|---|---|
| FE-70 | Unit tests | Permission helpers, formatters, form validation, vote-state logic, and status mappings. |
| FE-71 | Component tests | Forms, dialogs, route guards, article status controls, vote panel, comment form, and error states. |
| FE-72 | End-to-end tests | Register/login, vote once per article, comment, publisher submission, admin approval, moderation, and route authorization. |
| FE-73 | Responsive testing | Core public and dashboard routes at mobile, tablet, and desktop viewport widths. |
| FE-74 | Accessibility audit | Automated checks plus keyboard checks for authentication, voting, article editing, moderation, and navigation. |
| FE-75 | Browser support verification | Verify the agreed target browsers and record any known limitations. |
| FE-76 | Performance review | Lazy-load noncritical routes, optimize images, avoid unnecessary re-renders, and check public page performance. |

## 13. Delivery Sequence

1. Foundation: FE-01 through FE-08.
2. Public website and article browsing: FE-10 through FE-19.
3. Authentication and session handling: FE-20 through FE-25.
4. Participant voting and commenting: FE-30 through FE-37.
5. Publisher workflow and analytics: FE-40 through FE-46.
6. Administrator management interfaces: FE-50 through FE-57.
7. API/real-time hardening and full QA: FE-60 through FE-76.

## 14. Frontend Definition of Done

A frontend task is complete when:

- The implementation is typed, linted, formatted, reviewed, and covered by appropriate tests.
- It has responsive, loading, empty, success, and error states.
- It meets the accessibility baseline and uses reusable shared components where appropriate.
- Role/permission-dependent UI is based on authenticated API data.
- The associated backend endpoint has been integrated and failure conditions have been verified.
- The relevant SRS acceptance requirement is demonstrably satisfied.

## 15. Dependencies and Open Inputs

The following approved inputs are still needed before final visual/content completion:

- Brand guidelines, logo, color palette, typography, and imagery.
- Final workshop titles, dates, speakers, and resources.
- Competition rules, eligibility, submission format, and voting dates.
- Sponsor logos/details and committee details.
- Magazine/E-Magazine content and final file formats.
- API contract, error format, pagination convention, upload constraints, and WebSocket event names.

---

**Related document:** Minerva Digital Platform SRS v1.1  
**Next planned document:** Backend, database, API, and API-key endpoint tasks
