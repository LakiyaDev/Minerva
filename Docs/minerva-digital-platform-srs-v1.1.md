# MINERVA DIGITAL PLATFORM
## Software Requirements Specification (SRS) – Updated Version 1.1

**Project:** Minerva Digital Platform  
**Organizing Institution:** IEEE EMBS Student Branch Chapter, University of Sri Jayewardenepura  
**Project Type:** Research Writing and Scientific Communication Platform  
**Status:** Confirmed requirements

---

## 1. Project Overview

The Minerva Digital Platform is a centralized web platform for managing and presenting the Minerva Scientific Communication Initiative organized by IEEE EMBS Student Branch Chapter, University of Sri Jayewardenepura.

The platform combines a static informational public website (managed directly in the frontend) with a dynamic backend-driven interactive article competition, voting, commenting, and analytics system.

The initiative includes:

1. Four online workshops.
2. The Minerva Inter-University Article Writing Competition.
3. Minerva Magazine launch and awarding ceremony.
4. Minerva E-Magazine publication.

The platform provides information about the initiative (hardcoded statically in the frontend), publishes competition articles, facilitates registered-participant voting and commenting, and supports administrative management of editorial articles, voting, moderation, users, and analytics.

## 2. Objectives

- Establish a central online presence for the Minerva initiative.
- Publish information about workshops, the competition, sponsors, organizers and the magazine.
- Allow administrators and authorized publishers to publish competition articles.
- Enable registered participants to vote on multiple articles, with only one vote permitted per article.
- Display live voting results to registered participants.
- Allow registered participants to comment on articles.
- Provide administrators with moderation and platform management capabilities.
- Allow authorized publishers to view analytics for their own articles.
- Provide a secure, maintainable and scalable platform.

## 3. User Roles

The system shall support four primary user roles.

### 3.1 Public Visitor

A public visitor is an individual accessing the website without logging in.

**Permissions:**
- View the Minerva homepage and project information.
- View the initiative timeline.
- View workshop information.
- View sponsors and organizing committee details.
- Browse published articles.
- View magazine and E-Magazine information.
- Access registration and login pages.

**Restrictions:**
- Cannot vote.
- Cannot comment.
- Cannot publish articles.
- Cannot access participant, publisher or administrator dashboards.
- Cannot view registered-user-only voting results.

### 3.2 Registered Participant

A registered participant is a user who has created an account and successfully logged in.

**Permissions:**
- Access the participant dashboard.
- Browse published competition articles.
- Vote on multiple articles.
- Cast only one final vote per article.
- View live voting results immediately after they become available, provided they are logged in.
- Comment on published articles.
- View their own voting and comment activity.
- Manage permitted account information.

**Restrictions:**
- Cannot publish articles unless separately granted publisher privileges.
- Cannot edit or delete another user's comments.
- Cannot moderate comments.
- Cannot change or withdraw a submitted vote.
- Cannot access administrator functions.

A participant may also receive publisher privileges. Roles must therefore be assignable independently rather than treating participant and publisher as mutually exclusive identities.

### 3.3 Authorized Article Publisher

An authorized publisher is a registered user who has been granted article publishing permissions by an administrator.

**Permissions:**
- Access the publisher dashboard.
- Create and submit articles.
- Edit their own articles according to the editorial workflow and assigned permissions.
- Upload permitted article images and supporting files.
- View analytics for their own articles.
- Monitor available article engagement information, including vote counts and comment activity.
- Track the review and publication status of their articles.

**Restrictions:**
- Cannot grant publishing permissions to other users.
- Cannot access administrator-only platform settings.
- Cannot moderate comments unless separately granted that permission.
- Cannot access analytics belonging to other publishers.
- Cannot publish directly unless the administrator has explicitly granted direct-publishing permission.

### 3.4 Administrator

An administrator has platform-wide management authority.

**Permissions:**
- Access the administrator dashboard.
- Manage user accounts and roles.
- Grant and revoke publisher permissions.
- Configure publishing permissions, including direct-publishing access.
- Create, edit, publish, unpublish and feature articles.
- View platform-wide voting and article analytics.
- Monitor and moderate comments.
- Remove inappropriate comments.
- Manage voting periods and competition settings.
- Review relevant platform activity and audit logs.

> **Scope note:** Workshops, event timelines, sponsors, organizing committee details, and magazine/E-Magazine informational pages are statically maintained in the frontend codebase; no administrative backend CMS module is required.

## 4. Functional Requirements

### 4.1 Public Website Content (Frontend-Managed)

**FR-01:** The system shall provide a public homepage introducing the Minerva initiative.

**FR-02:** The system shall provide a dedicated page explaining the initiative's objectives, activities and structure.

**FR-03:** The system shall display the four online workshops, including titles, descriptions, dates, speakers and related resources when provided.

**FR-04:** The system shall display the Minerva Inter-University Article Writing Competition information, rules, eligibility criteria and important dates.

**FR-05:** The system shall display the initiative timeline.

**FR-06:** The system shall display sponsor information and logos.

**FR-07:** The system shall display organizing committee members and their respective teams or positions.

**FR-08:** The system shall provide a dedicated Minerva Magazine page.

**FR-09:** The system shall provide access to the published E-Magazine.

**FR-10:** *(Updated — Frontend-Managed)* Public informational website content (`FR-01`–`FR-09`: initiative intro, workshops, competition rules, timeline, sponsors, organizing committee, and magazine/E-Magazine content) shall be maintained statically/hardcoded in the frontend codebase; no backend CMS module, CMS database tables, or administrative CMS API endpoints are required.

### 4.2 User Registration and Authentication

**FR-11:** Users shall be able to register for an account.

**FR-12:** Registered users shall be able to log in and log out securely.

**FR-13:** The system shall support password recovery.

**FR-14:** The system shall restrict participant, publisher and administrator functions to authenticated users with the appropriate permissions.

**FR-15:** The system shall support role-based access control.

**FR-16:** A user account shall be capable of holding participant and publisher permissions simultaneously.

### 4.3 Article Management and Publishing

**FR-17:** Administrators and authorized publishers shall be permitted to publish articles.

**FR-18:** Administrators shall be able to grant or revoke publishing permissions for selected registered users.

**FR-19:** Authorized publishers shall be permitted to publish their own articles directly without an editorial review queue.

**FR-20:** Authorized publishers shall be able to create and publish articles through the publisher dashboard.

**FR-21:** Publishers shall be able to edit their own draft articles according to their assigned permissions.

**FR-22:** The system shall support the following article lifecycle statuses:

- Draft
- Published
- Unpublished

**FR-23:** *(Removed — no editorial review queue; publishers publish directly.)*

**FR-24:** Only administrators shall be able to unpublish (remove) any published article, republish an unpublished article, or feature articles.

**FR-25:** Only published articles shall be visible to public visitors as competition entries.

**FR-26:** The system shall store article information, including title, author, abstract or introduction, article body, publication date, category and associated files where applicable.

**FR-27:** The system shall maintain article ownership and publication history.

### 4.4 Voting System

**FR-28:** Only authenticated registered participants shall be permitted to vote.

**FR-29:** Public visitors and unauthenticated users shall not be permitted to vote.

**FR-30:** A registered participant shall be allowed to vote on multiple different articles.

**FR-31:** Each registered participant shall be permitted to cast only one vote per article.

**FR-32:** Once a vote has been submitted successfully, it shall be final.

**FR-33:** Participants shall not be permitted to change, withdraw or resubmit their votes.

**FR-34:** The system shall validate voting eligibility and duplicate votes on the server side.

**FR-35:** The database shall enforce a unique combination of participant ID and article ID to prevent duplicate voting.

**FR-36:** The system shall update voting counts immediately after a valid vote is recorded.

**FR-37:** Live voting results shall be visible immediately to authenticated registered participants.

**FR-38:** Public visitors and unauthenticated users shall not be able to access registered-user-only voting results.

**FR-39:** Administrators shall be able to monitor voting activity and view platform-wide voting statistics.

**FR-40:** The system shall support administrator-configured voting start and end times.

**FR-41:** The system shall prevent votes from being submitted outside the permitted voting period.

### 4.5 Article Commenting and Moderation

**FR-42:** Only authenticated registered participants shall be permitted to comment on published articles.

**FR-43:** Participants shall be able to submit comments on eligible articles.

**FR-44:** The system shall associate each comment with its author, article and submission timestamp.

**FR-45:** Administrators shall be able to view and moderate comments.

**FR-46:** Administrators shall be able to remove inappropriate, irrelevant or abusive comments.

**FR-47:** The system shall provide administrators with access to comment moderation controls.

**FR-48:** Participants shall not be able to moderate or delete comments belonging to other users.

**FR-49:** Commenting and moderation activities shall be recorded for accountability.

### 4.6 Publisher Analytics

**FR-50:** Authorized publishers shall be able to view analytics for their own articles.

**FR-51:** Article analytics shall include available engagement information such as:
- Total votes received.
- Comment count.
- Article publication status.
- Available article engagement statistics.

**FR-52:** Publishers shall not be able to access another publisher's private article analytics.

**FR-53:** Administrators shall be able to view platform-wide article and voting analytics.

**FR-54:** The system shall ensure that analytics access is checked using the authenticated user's permissions and article ownership.

### 4.7 Dashboards

**FR-55:** The system shall provide a participant dashboard containing relevant account, voting and commenting information.

**FR-56:** The system shall provide a publisher dashboard containing article creation, submission, publication status and own-article analytics.

**FR-57:** The system shall provide an administrator dashboard containing user management, article management, voting statistics, voting period controls, and comment moderation.

## 5. Role and Permission Matrix

| Function | Public Visitor | Registered Participant | Authorized Publisher | Administrator |
|---|---|---|---|---|
| View public website | Yes | Yes | Yes | Yes |
| Browse published articles | Yes | Yes | Yes | Yes |
| Register and log in | Yes | Yes | Yes | Yes |
| Vote on articles | No | Yes | Yes, if also a participant | Yes, if also a participant |
| View live voting results | No | Yes | Yes, if logged in | Yes |
| Comment on articles | No | Yes | Yes, if logged in | Yes |
| Moderate comments | No | No | No, unless separately authorized | Yes |
| Create articles | No | No | Yes | Yes |
| Publish own articles directly | No | No | Yes | Yes |
| Unpublish / remove any published article | No | No | No | Yes |
| Feature or republish articles | No | No | No | Yes |
| View own article analytics | No | No | Yes | Yes |
| View other publishers' private analytics | No | No | No | Yes |
| Grant publisher permissions | No | No | No | Yes |
| Manage workshops, sponsors, and magazine | Frontend static | Frontend static | Frontend static | Frontend static (no backend CMS) |
| Manage users and platform settings | No | No | No | Yes |

**Permission note:** Publisher status does not automatically grant voting or commenting rights. These functions require the user to be authenticated as a registered participant. Administrators may also participate as voters or commenters only if their accounts have participant eligibility and the competition rules permit it.

## 6. Main System Workflows

### 6.1 User Registration and Login Workflow

1. User opens the Minerva website.
2. User selects Register.
3. User enters the required registration details.
4. System validates the information.
5. System creates the account.
6. User logs in.
7. System identifies the user's assigned roles and permissions.
8. System displays the appropriate dashboard and available features.

### 6.2 Article Publishing Workflow

1. Administrator creates a publisher account or grants publishing permissions to a staff user.
2. Authorized publisher logs in.
3. Publisher creates an article and saves it as a draft.
4. Publisher edits the draft and uploads attachments as needed.
5. Publisher publishes the article directly (`draft` -> `published`).
6. Published article becomes immediately visible on the public competition page.
7. Publisher can view analytics for their own article.
8. Only administrators can unpublish/remove any published article (`published` -> `unpublished`) or feature articles.

### 6.3 Voting Workflow

1. Participant registers and logs in.
2. Participant opens the competition page.
3. Participant browses published articles.
4. Participant selects an article.
5. System checks whether voting is open.
6. System checks whether the participant has already voted on that article.
7. If no previous vote exists, the participant submits a vote.
8. System validates the vote and stores it.
9. The database prevents a second vote by the same participant on the same article.
10. The voting count is updated.
11. Updated results become visible immediately to authenticated registered participants.
12. The participant cannot change or withdraw the submitted vote.

### 6.4 Commenting and Moderation Workflow

1. Registered participant logs in.
2. Participant opens a published article.
3. Participant submits a comment.
4. System validates and stores the comment.
5. The comment is displayed according to the platform's moderation policy.
6. Administrator reviews comments through the moderation dashboard.
7. Administrator may remove inappropriate comments.
8. The moderation action is recorded.

### 6.5 Publisher Analytics Workflow

1. Authorized publisher logs in.
2. Publisher opens the publisher dashboard.
3. System identifies the publisher's article ownership.
4. System retrieves analytics for articles owned by that publisher.
5. Publisher views available voting, comment and engagement information.
6. The system prevents access to private analytics for articles owned by other publishers.

### 6.6 Administrator Management Workflow

1. Administrator logs in.
2. Administrator opens the dashboard.
3. Administrator manages users and publisher permissions.
4. Administrator manages articles and editorial approvals.
5. Administrator monitors votes and comments.
6. Administrator manages public website content.
7. Administrator reviews analytics and platform activity.

## 7. Business Rules

**BR-01:** Only registered and authenticated participants may vote.

**BR-02:** A participant may vote on multiple articles.

**BR-03:** A participant may vote only once on each individual article.

**BR-04:** A submitted vote is final and cannot be changed or withdrawn.

**BR-05:** Live voting results are available immediately to authenticated registered participants.

**BR-06:** Public visitors cannot view registered-user-only voting results.

**BR-07:** Only registered participants may comment on articles.

**BR-08:** Administrators have authority to moderate comments.

**BR-09:** Only administrators and users granted publishing permissions may publish articles.

**BR-10:** Direct publishing by an authorized publisher is permitted only when explicitly granted by an administrator.

**BR-11:** Authorized publishers may access analytics only for their own articles.

**BR-12:** Administrators may access platform-wide analytics and management functions.

**BR-13:** Publisher privileges do not automatically grant administrator privileges.

**BR-14:** All access restrictions must be enforced by the backend, not merely by hiding frontend buttons or pages.

## 8. Non-Functional Requirements

### Security
- Secure authentication and password hashing.
- Role-based access control.
- Server-side permission validation.
- Protection against SQL injection, cross-site scripting and cross-site request forgery where applicable.
- Secure handling of uploaded files.
- Rate limiting for authentication, voting and commenting endpoints.
- Audit logging for important administrative actions.
- Protection against duplicate votes using database constraints and transactional operations.

### Performance
- Responsive page loading.
- Efficient article and voting data retrieval.
- Real-time voting updates without requiring manual page refresh.
- Support for the expected daily user load, with concurrent-user capacity verified through load testing.

### Availability and Reliability
- Reliable hosting and database operation.
- Automated backups.
- Error logging and recovery procedures.
- Secure deployment and maintenance processes.

### Usability
- Responsive desktop, tablet and mobile interfaces.
- Clear navigation and dashboards.
- Simple article submission and voting processes.
- Accessible design and readable article presentation.

### Maintainability
- Modular frontend and backend architecture.
- Documented REST API.
- Database migrations and version control.
- Automated testing and deployment procedures.

## 9. Proposed Technology Stack

| Component | Proposed Technology |
|---|---|
| Frontend | React + TypeScript |
| Backend | Laravel |
| Database | MySQL |
| API | REST API |
| API Documentation | OpenAPI 3.1 |
| Real-time Updates | Laravel Reverb / WebSockets |
| File Storage | AWS S3 |
| Hosting | AWS Lightsail |
| Containerization | Docker Compose |
| DNS and CDN | Cloudflare |
| SSL | Let's Encrypt |
| Version Control | Git and GitHub |
| CI/CD | GitHub Actions |

## 10. Database Requirements

The database shall support the following main entities:

- Users
- Roles
- Permissions
- Role assignments
- Publisher permissions
- Articles
- Article categories
- Article attachments
- Article status history
- Voting periods & votes
- Comments
- Comment moderation records
- Article analytics or engagement records
- Audit logs

> **Note:** Workshops, workshop resources, timeline entries, sponsors, organizing committee members, and magazine/E-Magazine pages are statically maintained in the frontend and do not require backend database entities.

### Critical Database Constraints

**Voting:** A unique database constraint must exist on `(user_id, article_id)` in the votes table. This guarantees that a participant cannot cast more than one vote for the same article.

**Article ownership:** Each article must have an identifiable owner or author.

**Publisher access:** Article analytics queries must be restricted to articles owned by the authenticated publisher unless the requester is an administrator.

**Comments:** Each comment must be linked to an authenticated user and an article.

**Permissions:** Publishing and administrative permissions must be stored and checked independently of ordinary participant access.

## 11. API Requirements

The REST API shall provide endpoint groups for:

- Authentication and registration.
- User profiles and role management.
- Publisher permission management.
- Articles and article attachments.
- Editorial approval and publishing.
- Voting and voting results.
- Comments and comment moderation.
- Publisher analytics.
- Administrator analytics and platform settings.

*(Static informational pages—initiative overview, workshops, timeline, sponsors, organizing committee, and magazine—are hardcoded in the frontend with no backend CMS API required.)*

All protected endpoints shall validate authentication and authorization on the backend.

## 12. Completion and Acceptance Criteria

The platform shall be considered functionally complete when:

1. Public visitors can access the Minerva website and its public content.
2. Users can register, log in and access their permitted dashboards.
3. Administrators can grant and revoke publisher permissions.
4. Administrators and authorized publishers can create and publish articles according to their assigned permissions.
5. Registered participants can vote on multiple articles.
6. The system prevents a participant from voting more than once on the same article.
7. Submitted votes cannot be changed or withdrawn.
8. Voting results update immediately and are visible to authenticated registered participants.
9. Only registered participants can comment on articles.
10. Administrators can moderate comments.
11. Authorized publishers can view analytics for their own articles.
12. Publishers cannot access another publisher's private analytics.
13. Administrators can manage editorial articles, voting windows, moderation, users, and view platform-wide analytics.
14. Security, functionality, usability and performance testing have been completed successfully.

---

**Document status:** Updated SRS – Version 1.1  
**Decision status:** The six user-policy decisions have been confirmed and incorporated.  
**Remaining project inputs:** Final workshop titles and dates, competition rules and dates, article submission format, branding assets, sponsor details, committee details and magazine content.