# MONSTOPIA Client Workspace — deployment checklist

## Current state

The marketing site, public interactive demo and brief builder run as static pages. The real workspace is **disabled by default**. No customer invitations or real emails have been sent. No cloud database has been created by this change.

Implemented: Supabase email OTP integration, project-scoped reads, admin project creation/edit/archive, milestones and updates CRUD, invitation allowlist/revocation, client milestone approval, change/support requests and staff replies, copyable current-state reports, database audit events. The demo has synthetic, in-memory data only; a reload resets it. Real records are never used as a demo fallback.

Not included: automatic weekly emails, attachments, payment/invoicing, a public signup flow, production SMTP setup, backups, monitoring, or a live Supabase integration test. A public company website and private project records are different access layers; the current Sites publication remains owner-only until separately approved for public access.

## 1. Prepare Supabase

Use a new dedicated Supabase project, or review and adapt the migration against an existing database before running it. The migration creates `portal_*` tables and attaches a verification trigger to `auth.users`. It is transactional and intended to run once. Do not disable RLS.

Apply `supabase/migrations/202609040001_client_portal.sql` in the Supabase SQL editor (or through your migration pipeline). Never put the database password, secret key or service_role key into this website or its repository.

### Bootstrap the owner and optional staff

Replace these example addresses with approved company accounts. These are SQL examples, not preconfigured accounts:

```sql
insert into public.portal_staff(email, role)
values ('owner@your-company.example', 'admin');

-- Optional. A staff account also needs a project membership with role=staff.
insert into public.portal_staff(email, role)
values ('developer@your-company.example', 'staff');
```

Role promotion/deactivation is SQL-dashboard-only. The browser cannot write `portal_staff` or its own identity. To deactivate a staff account, set `active=false`; then review any separate client memberships as well. Keep the number of administrators small.

## 2. Configure managed email authentication

Before enabling the website:

1. Enable the email provider and email verification. Keep anonymous login and unused OAuth/phone providers disabled.
2. Enable **Before User Created** Auth hook and choose `public.portal_before_user_created`. This is required to restrict new accounts to the database invitation allowlist. The migration grants invocation only to `supabase_auth_admin`.
3. Keep email signup technically enabled for invited OTP recipients: the hook performs the allowlist check. A blanket disable-signup setting also prevents first-time invited users from logging in. Existing noninvited Auth accounts may authenticate, but have no project access through RLS.
4. Configure the email login template to display `{{ .Token }}` as an OTP instead of relying only on a magic link. The UI verifies email + token, and does not process tokens from URLs. Choose an appropriate short OTP expiry (for example 10 minutes) and rate limits in Supabase.
5. Set the production Site URL, approved redirect origins and secure email-change settings. Changing an email must be verified; access additionally requires an exact match to the invitation's verified email, not only a JWT field.
6. Configure a production SMTP provider, sender domain, SPF/DKIM/DMARC and sending limits. Supabase's default mail service is not a production customer-mail setup. Configure and test only with approved test inboxes first; do not send customer invitations as part of setup.
7. Review Auth rate limits, logs and abuse controls. If enabling CAPTCHA, also integrate the chosen widget and pass its token in `signInWithOtp` before enabling enforcement; this UI currently relies on Supabase rate limiting, not CAPTCHA.

References: [Supabase email OTP](https://supabase.com/docs/guides/auth/auth-email-passwordless), [Before User Created hook](https://supabase.com/docs/guides/auth/auth-hooks/before-user-created-hook), [RLS](https://supabase.com/docs/guides/database/postgres/row-level-security), [custom SMTP](https://supabase.com/docs/guides/auth/auth-smtp).

## 3. Supply public configuration

Copy `.env.example` to `.env.local` and supply the Project URL and **publishable** key from Supabase. Leave `PORTAL_ENABLED=false` until database policies and email settings are verified. The build accepts the new `sb_publishable_…` key format only, and rejects secret keys and legacy JWT keys.

```dotenv
PORTAL_ENABLED=false
PORTAL_SUPABASE_URL=https://your-project-ref.supabase.co
PORTAL_SUPABASE_PUBLISHABLE_KEY=sb_publishable_REPLACE_ME
```

These values are deliberately public and compiled into `portal-config.js`. Authorization is enforced by the database, not by hiding that file or disabling the frontend. `.env.local` is ignored by Git. Do not use Sites runtime secrets for this static build: configuration is applied at build time.

Run `pnpm build` and `pnpm test`. When ready, switch `PORTAL_ENABLED=true`, rebuild and redeploy the validated static output. Update the homepage's “ระบบลูกค้าจริงจะเปิดหลัง…” readiness note only after the live acceptance checklist passes. Files deployed from `dist` contain only the public website, not SQL, tests, source docs, or environment files.

## 4. Live acceptance checks — required before customer use

Local tests execute the migration and RLS against PostgreSQL in PGlite with simulated Auth roles. They do **not** test Supabase's hosted email delivery, JWT validation, dashboard hook configuration, real sessions, or email provider. Run these with separate approved test accounts on the configured project:

- An uninvited address cannot create an account; anonymous API reads fail.
- Admin can receive and verify an OTP; a wrong/expired OTP fails; server-side rate limits work.
- Create two test projects, invite test client A to project A and B to B. Both can log in independently. A must not read or mutate any B data by changing project IDs in direct REST/RPC calls.
- Grant a test staff account one project only. Staff must not see other projects, grant members, archive projects, or change company roles.
- A client can submit a request and approve a submitted milestone only; a repeated or stale approval fails. Approved milestones cannot be silently edited/deleted by staff.
- Revoke membership, archive a project, and change the Auth email: fresh reads/mutations must fail immediately. The page clears data when hidden and rechecks access on return or refresh. Previously viewed content cannot be remotely erased or prevented from being copied by an authorized viewer.
- Logout clears the workspace and local tab session. No project data is stored in localStorage/sessionStorage. Only Supabase auth tokens use per-tab sessionStorage, not business records. For shared devices, log out and close the tab; use MFA for administrators as a future enhancement.
- Create, edit, and delete test milestones/updates; confirm `updated_at` concurrency checks prevent overwriting stale records. Verify audit rows for mutations.
- Open on mobile, check keyboard access, dialogs, empty/error/loading states, actual mail delivery and browser compatibility. Browser UI testing has not been run in this implementation turn.
- Verify backup/restore, monitoring, retention and incident handling. Review privacy notices and vendor terms for your actual data flow before collecting personal data.

## 5. Everyday workflow

Admin logs into `portal.html`, creates a project, adds client email(s) under **สิทธิ์เข้าถึง**, then privately shares the workspace URL through the company's existing channel. Adding the email does not send an invitation. Once clients verify their email, a database trigger binds the invitation to their Auth UUID.

Staff updates milestones and publishes progress notes. **สรุปรายงาน** prepares a current-state summary for manual review/copy; it is not an automated weekly scheduler or a historical weekly snapshot. Publish a note of type “สรุปประจำสัปดาห์” to keep a weekly record. Clients can approve work or submit a request. A request is not automatic approval of additional cost or scope.

Project deletion is intentionally **archive/restore**, to preserve delivery history. Milestones and updates support create/read/update/delete, except approved milestones which are immutable through the app. Request resolution is a status workflow, not deletion. Member access is granted/revoked. Staff identities are maintained outside the app by an administrator.

Preview links must be HTTPS and point to a system with its own access controls. The portal does not make external preview URLs private. Do not place passwords, bearer tokens, public customer files, or signed links intended for another recipient in these fields.

## Structure for future development

- `src/portal/repository.js`: real Supabase data/auth adapter; no embedded customer records.
- `src/portal/demo.js`: isolated synthetic demo adapter.
- `src/portal/domain.js`: shared status vocabulary, safe link validation, report formatting.
- `src/portal/portal.js`: customer and role-aware staff screens; RLS is the authority.
- `src/brief.js`: public brief generation; no persistence or automatic transmission.
- `supabase/migrations`: reviewed, versioned database changes.
- `tests/database.test.mjs`: cross-client access and mutation checks using real PostgreSQL semantics.

Recommended next increments after live verification: private file storage with per-project authorization and short-lived download links; admin MFA; transactional approval notifications; immutable scope/change approvals; scheduled weekly summaries with opt-in recipients; audit viewer with filters; backup/restore drills. Add these as reviewed migrations and server-side workflows, not browser-only access checks.
