# Eiho One — Foundation Build Spec (Senior → Junior)

> **Audience:** an implementing model (Kimi / DeepSeek) acting as the *junior developer*. The *senior* (reviewer) has written this spec and **verifies after every task** before the junior starts the next.
> **On approval, this document is saved to `Eiho One Backend/docs/FOUNDATION_PLAN.md`** and becomes the working reference.
> **Backend:** Laravel (`Eiho One Backend/`). **App:** Flutter (`Eiho One Flutter/`). Everything is **offline/manual** (contact or request mode). **No online payments.**

---

## How to work (rules for the junior)
1. **One task = one small change = one commit.** Never batch tasks. Commit message: `phaseN.taskM: <what>`.
2. **Write/adjust a test for every task** that changes behavior. Backend: `tests/Feature` or `tests/Unit`. Do not delete existing tests to make them pass.
3. **Run the gate commands** listed in each task and paste the output in the PR/commit description.
4. **Do not widen scope.** If you find an unrelated bug, note it in `docs/FOUNDATION_PLAN.md` under "Deferred", don't fix it inline.
5. **Never touch production directly.** All work on a branch; deploy only after Phase 0 baseline + senior sign-off.
6. **STOP at each "🔒 SENIOR GATE".** Do not proceed until the senior confirms the acceptance criteria are met.
7. **Security is not optional** — every task's acceptance criteria include its security checks. A task is not "done" if it opens a hole.

## Global gate commands
- Backend tests: `php artisan test`
- Backend lint (changed files): `php -l <file>`
- Routes sanity: `php artisan route:list` (0 duplicate names)
- Flutter: `flutter analyze` && `flutter test` (run when app code changes)

---

## Context — why we are doing this
Eiho One is a free, offline-first local-business platform for Lamka/Churachandpur. Today a business is classified along **three overlapping axes that disagree** (legacy `module_type`, `world_id`, and experiences/modules), the main admin category form **orphans categories** (never sets `world_id`), there are **two competing experience services**, the vendor flow is spread across **four half-built screens**, and several **mutation endpoints lack authorization**. We are building the clean, secure foundation once, so the long run is stable.

### Target end-state (what "foundation done" means)
- **One taxonomy tree**: Bucket(World) → Sub-industry → Category → (deeper) — via `categories.world_id` + `parent_id` + `level`. No orphans.
- **3 buckets**: **Shopping** (grocery, medicine, restaurants, retail), **Booking** (taxi, hotel, turf, salon/doctor, events), **Directory** (contact-only). "Ride" folded into Booking.
- **Two clean toggle layers**: **Global** (Launch Controls) AND **Per-vendor** (a vendor opts into what they sell/book). Effective = Global ∧ Vendor.
- **Secure**: every mutation authorized + tenant-isolated; CSRF, rate limits, validation, safe uploads, payments hard-off.
- **Vendor self-service**: AI-imported listing → vendor **claims** (or **self-creates**) → one "What do you offer?" screen → manage only relevant features → admin verifies → live.
- **Phase-1 launch surface**: Directory + Turf booking only, manual.

### Glossary (shared vocabulary)
- **Bucket / World** = top app tab (Shopping / Booking / Directory).
- **Module** = capability switch on a business (`catalog, orders, bookings, inventory, transport, turf`); deps `orders→catalog`, `turf→bookings`. Engine: `app/Services/BusinessModuleService.php`.
- **Experience** = how a customer interacts (`retail, restaurant, appointment, stay, turf, taxi, …, directory`); needs its module ON **and** real inventory to be "ready."
- **Capability Template** = a **business-type starter kit** (e.g. "Restaurant" pre-enables `catalog+orders`, experience `restaurant`, mode "request"). Convenience only; seed in `WorldSeeder.php`, applied by `CapabilityTemplate::applyTo()`.
- **Launch Controls** = global on/off master switch (`app/Services/LaunchControlService.php`; admin screen; shipped via `GET /api/platform/features`).
- **Claim** = vendor proves ownership of an imported listing (OTP flow `web.php:197–350`, `ClaimRequest`, admin claims review).

### Category hierarchy (N-level tree; confirms the owner's model)
```
Level 0  Bucket / World   Shopping | Booking | Directory        (fixed, toggleable)
Level 1  Sub-industry     Restaurant, Medicine, Electronics,     (admin-managed;
                          Grocery, General …                      becomes the app sub-tabs)
Level 2  Category         Veg / Non-Veg, Mobile / Accessories …  (admin-managed)
Level 3+ (optional)       deeper nesting if ever needed
Product/Service           hangs off the BUSINESS, tagged to its category
```
Sub-tabs are **derived from level-1 categories**, not hard-coded. **A category maps to exactly ONE world** (a `categories.world_id` is a single value). Category bucket→world map: `directory→discover`, `ordering→shop`, `booking→book`. **Drop the legacy `both` option** at the category level — a business that both sells *and* books is modelled at the **business** level (multiple `enabled_experiences` + classifications), never by a dual-world category.

**Ownership (confirmed, use everywhere):** a business is owned via **`businesses.created_by`** (User). Claim approval reassigns `created_by` to the claimant and upgrades their role to `owner` (`ClaimController` + `web.php` claims.approve). `BusinessPolicy` and vendor self-create both key off `created_by`. Do **not** invent a separate `owner_id`.

---

# PHASE 0 — Safety net & single source of truth  *(no feature code yet)*
**Why:** production has heavy uncommitted drift (124 modified + 193 untracked files) on the same commit as local. Deploying over it would destroy live-only work. Establish one reviewed baseline first.

- **Task 0.1 — Back up production.** DB dump + uploaded media from the live server; store off-box. *Accept:* backup file exists and restores in a scratch DB.
- **Task 0.2 — Capture live drift.** On the server, commit the 124+193 files to a branch `live-snapshot-<date>` so production's real state is in git (never lose it). *Accept:* `git status` clean on server; branch pushed.
- **Task 0.3 — Reconcile to one baseline.** Diff local vs `live-snapshot`; merge into a reviewed `foundation` branch that is the single source of truth. *Accept:* one branch builds, no lost live changes.
- **Task 0.4 — Green baseline.** `php artisan test` green; record pass count. `php artisan route:list` → 0 duplicate names. *Accept:* recorded baseline numbers in `docs/FOUNDATION_PLAN.md`.
- **🔒 SENIOR GATE 0:** backups verified, baseline branch chosen, tests green. No feature work before this passes.

> ### ✅ PHASE 0 COMPLETE — 2026-08-03
> - **0.1 Backups:** server `/home/ehlom-hola/backups/phase0-20260803-174252/` (`db-hola.sql.gz` 6 MB, `media.tar.gz` 174 MB) **and** off-box on Mac `~/Projects/eiho-one-backups/phase0-20260803-174252/` — all integrity-verified.
> - **0.2 Live drift captured:** server branch `live-snapshot-20260803` (commit `0efd827`, 124 mod + 193 untracked committed) + off-box git bundle `eiho-live-snapshot-20260803.bundle`. Nothing can be lost.
> - **0.3 Baseline decided:** **local working tree = single source of truth.** Verified live has NO unique real work — its extras were stale flat-view duplicates (0 route references) + junk (`._` files, root strays). Local is ahead (reorganized admin/vendor views, 3 extra tests incl. security). Committed as branch **`foundation`** (commit `7c34c85`), working tree clean. (Original baseline commit `8b2cf74` was amended: `.env.production-backup` contained live secrets and was removed from git history + gitignored.)
> - **0.4 Green baseline:** `php artisan test` → **70 passed, 323 assertions** (in-memory sqlite). `php artisan route:list` → **496 routes, 0 duplicate names** (the 41 duplicate `integration.*` names from the 2026-07-28 audit are resolved). Record these numbers; Phase 1+ must not drop below them.
> - **0.2 follow-up (closed 2026-08-03):** `live-snapshot-20260803` pushed to origin (verified no secrets in snapshot before push).
> - **Deploy note:** production still runs the old drift on `live-snapshot-20260803`; the `foundation` branch is NOT yet deployed. First deploy happens only after Phase 1 per the deploy-safety rules.

---

# PHASE 1 — Security foundation  *(cross-cutting; do before feature phases)*
**Why:** the audit found unauthenticated mutation routes and vendor CSRF failures. Lock these before building more on top.

- **Task 1.1 — Centralize authorization into Policies (NOT "add auth").** Correction after code review: the media/delivery endpoints **already have `auth:sanctum`** (`api.php:183,190`) and `MediaController::destroy` already checks `$media->user_id === $user->id`. The real problem is authorization is **scattered inline** — 30+ ad-hoc `where('created_by', …)` / `abort_unless(...)` checks across the 3,740-line `web.php`, with **no Policy layer**, so coverage is inconsistent and unauditable. Create `app/Policies/BusinessPolicy.php` and `app/Policies/MediaPolicy.php`; register in `AuthServiceProvider`. Audit **every** mutation for a missing/incorrect ownership check — in particular **verify `DeliveryConfigController::update` enforces ownership** (auth alone ≠ authorization). *Accept:* a Feature test proves a non-owner gets 403 on media delete, delivery-config update, and a sample vendor mutation; owner gets 200.
- **Task 1.2 — Apply the policy to every vendor mutation.** Ownership = **`business.created_by === user.id`** (or role admin/super_admin). Replace the inline `where('created_by')` closures with `$this->authorize('update', $business)` in **every vendor mutation** (`web.php:2680–3729`) and cascade to child resources (a product/service/order is owned via its business). *Accept:* Feature test: vendor A cannot edit vendor B's product/order/booking/vehicle (403). Most important security task — verify thoroughly.
- **Task 1.3 — Fix vendor CSRF 419.** Vendor hotel/turf/booking POST forms fail with 419. Ensure `@csrf` in every vendor form + correct `web` middleware/session. *Accept:* the previously-419 vendor flows submit successfully; add/repair the failing tests noted in the audit.
- **Task 1.4 — Rate limits.** Throttle OTP send/verify (claim flow `web.php:203–350`), login, and public request endpoints (`throttle:` middleware). *Accept:* Feature test hits the limit → 429.
- **Task 1.5 — Validation, mass-assignment, upload safety.** Audit `$fillable` on `Business`/`Product`/`Service` for sensitive fields (e.g. `verification_status`, `claim_status`, `owner`); guard them from vendor mass-assignment. Enforce MIME + size limits on media upload; store outside webroot or via signed URLs. *Accept:* test that a vendor cannot set `verification_status` via update; oversized/non-image upload rejected.
- **Task 1.6 — Payments kill-switch: ONE source of truth.** Correction after code review: there are currently **two independent switches** for online payments — `PaymentController` reads `Setting::get('payment_online_enabled')` (`PaymentController.php:20,210`) while `LaunchControlService` reads the `payments.online` FeatureFlag. Flipping the Setting could enable online payments while LaunchControl still reports off. **Make `LaunchControlService` `payments.online` the single authority**: `PaymentController::isOnlineEnabled()` (and `/api/payments/config`) must read the LaunchControl flag (or drive the Setting from it), and it must default OFF. *Accept:* toggling only the old `payment_online_enabled` Setting has **no** effect while `payments.online` flag is off; `GET /api/payments/config` shows offline only; test proves the client cannot enable online payments.
- **🔒 SENIOR GATE 1:** all mutations authorized + tenant-isolated, CSRF/rate-limit/validation/upload checks pass. Re-run full suite.

---

# PHASE 2 — Taxonomy unification (the active bug)
**Why:** admin category store/update (`web.php:921–963`) never sets `world_id`/`parent_id` → orphaned categories; legacy `subcategories` + `parent_id` both live; counts flow through legacy FK.

- **Task 2.1 — Category form sets world + parent.** Add `parent_id` (nullable, `exists:categories,id`) to validation in the store & update closures; **derive & set `world_id`** from `module_type` (map above, one world per category). **Remove the `both` option** from the category form and validation (`in:directory,ordering,booking`); migrate any existing `module_type='both'` rows to `ordering` (their booking side is expressed at the business level). *Files:* `routes/web.php:921–963`, category form blade. *Accept:* creating a category in the normal form persists a non-null single `world_id`; no `both` remains; Feature test.
- **Task 2.2 — Backfill existing categories.** Data migration: set `world_id` from `module_type` for all rows where null; set `level`. *Accept:* 0 categories with null `world_id` after migrate; migration is idempotent.
- **Task 2.3 — One write path.** Route the "Category Tree Manager" (`web.php:2624–2645`) and the standard form through a shared store/update helper so both set the same fields. *Accept:* both screens produce identical field coverage; test.
- **Task 2.4 — Migrate legacy subcategories → `parent_id` children.** Migration copies each `subcategories` row into `categories` (parent = its category, inherit `world_id`, `level=2`); repoint `businesses.subcategory_id` to the new child category id (add nullable `category_id` link if needed). Keep old table read-only; hide the admin Subcategories screen. *Accept:* every business still resolves a category; counts unchanged; test. (Drop `subcategories` table only in a later, separate migration after production verification.)
- **Task 2.5 — Reconcile business↔category truth.** Choose `business_classifications` as source of truth; backfill a primary classification for every business from `businesses.category_id`; update `Category::businesses()` / `withCount('businesses')` accordingly. *Accept:* world/category counts equal classification counts; test.
- **Task 2.6 — Fix `Business::worlds()`.** Resolve through populated `world_id`. *Files:* `app/Models/Business.php:250–255`. *Accept:* returns correct world(s) for a classified business; test.
- **Task 2.7 — Remove the legacy `module_type` fallback (added after code review).** Once `world_id` is backfilled (2.2), the legacy reads of `category->module_type` in `BusinessModuleService::recommendedFor()` and `Business::scopeOfModule()` become dead weight that can make counts/worlds drift. Replace those fallbacks with the unified world/module source, or delete them. *Accept:* grep shows no runtime dependence on `category->module_type` for module/world resolution; counts unchanged; test.
- **🔒 SENIOR GATE 2:** no orphan categories, one tree, `Business::worlds()` correct, counts consistent, no legacy `module_type` fallback, migrations idempotent + reversible.

---

# PHASE 3 — Collapse 4 worlds → 3 buckets (fold Ride into Booking)
- **Task 3.1 — Reseed worlds.** `WorldSeeder.php:123–176`: display `Shop→Shopping`, `Book→Booking`, `Discover→Directory`; **deactivate `ride`**. Remove hard-coded `nav_config.sub_tabs`; seed initial level-1 categories per world (Shopping: Restaurants, Grocery, Medicine, Electronics, General; Booking: Taxi, Hotel, Turf, Salon, Doctor, Events; Directory: Businesses, Professionals, Institutions, Places). *Accept:* `GET /api/worlds` → 3 worlds; `/api/worlds/{slug}/categories` returns seeded level-1 categories as sub-tabs.
- **Task 3.2 — Remap transport experiences to Booking.** `LaunchControlService.php`: `EXPERIENCE_REQUIREMENTS` (46–58) taxi/shared/rental/goods `world` `ride→book`; `worldAvailable()` (77–92) move transport under `book`, drop `ride` branch. *Accept:* taxi gates under `book`; `publicConfig()['enabled_tabs']` never contains `ride`; test.
- **🔒 SENIOR GATE 3:** exactly 3 buckets everywhere (API, admin, Flutter nav), taxi lives under Booking.

---

# PHASE 4 — Merge the two experience services
**Why:** `App\Services\BusinessExperienceService` (legacy, raw module read) vs `App\Services\Experience\BusinessExperienceService` (newer, normalized, per-experience). Public API uses newer; vendor screen + templates use legacy — they disagree on staleness/readiness.
- **Task 4.1 — Port & repoint.** Keep the **newer** service; port `setAvailabilityMode()`, `getPrimaryExperienceReadiness()`, `scopeReadyOnly()` into it. Repoint vendor experiences route (`web.php:3524–3525`) and `CapabilityTemplate::applyTo()` to it. *Accept:* vendor readiness + template apply work via one service; test.
- **Task 4.2 — Delete legacy.** Remove `app/Services/BusinessExperienceService.php`; `grep` shows no `use App\Services\BusinessExperienceService;`. One canonical staleness table. *Accept:* grep clean; suite green.
- **🔒 SENIOR GATE 4:** single experience service; readiness identical across customer + vendor.

---

# PHASE 5 — Wire the two toggle layers together
- **Task 5.1 — Global read helpers.** Add `enabledModuleKeys()`, `enabledExperienceKeys()` to `LaunchControlService`. *Accept:* unit test.
- **Task 5.2 — Gate per-vendor UIs.** Admin/vendor **modules** blades + **experiences** screens render only globally-ON options; disabled + tooltip for OFF. *Accept:* with `module.transport` OFF, Transport not selectable in UI.
- **Task 5.3 — Enforce server-side.** `BusinessModuleService::update()` + experiences update reject/drop globally-OFF keys. *Accept:* test: enabling a globally-OFF module server-side is refused.
- **🔒 SENIOR GATE 5:** effective capability = Global ∧ Vendor, enforced in UI **and** server.

---

# PHASE 6 — Vendor onboarding & dashboard (claim-first + self-create)
**Reuse:** OTP claim flow (`web.php:197–350`), `ClaimRequest`, `Business.claim_status`/`verification_status`/`source`, AI import stack, real vendor CRUD (`web.php:2680–3729`), `BusinessModuleService`, newer experience service, workflow services, `app/Models/VendorSetup.php` (checklist, currently API-only).
- **Task 6.1 — Onboarding lifecycle.** Canonical states: imported → `unclaimed/pending`; claim → `claimed`; self-create (NEW vendor `businesses.create/store`, owner=current user, forced `verification_status=pending`) ; admin verify → `verified/live`. Directory listings may auto-verify low-risk; transactional require admin verify. *Accept:* Feature test walks claim→verify and self-create→verify.
- **Task 6.2 — One "What do you offer?" screen.** Replace the 4 overlapping screens with a single step: cards *Sell products (Shopping)* / *Take bookings (Booking)* / *Just be listed (Directory)*, each applying a `CapabilityTemplate`. Keep modules editor as "advanced". Delete/redirect the broken 9-step wizard (`$currentStep` undefined) and quick-setup. *Accept:* choosing "Take bookings" enables `bookings`; test.
- **Task 6.3 — Readiness checklist in web dashboard.** Surface `VendorSetup` (profile, items added, photos, hours). *Accept:* dashboard shows % complete + next step.
- **Task 6.4 — Fix bugs.** `Vendor/AnalyticsController::overview()` undefined `$businesses`; vendor layout "Turf shown if catalog" mislabel → gate on `bookings`+`turf`. *Accept:* analytics loads; turf nav gates correctly.
- **🔒 SENIOR GATE 6:** a vendor can claim or self-create, pick what they offer, see readiness, manage only relevant features — all tenant-isolated (Phase 1).

---

# PHASE 7 — Simplify capability templates
- **Task 7.1 — Trim to essentials.** Templates drive only `enabled_modules`, `enabled_experiences`, `default_availability`; keep richer fields in table but not required in UI. Expose as the WS6 starter-kit cards. `applyTo()` uses the merged experience service. *Accept:* "Turf & Sports" → `bookings+turf`, experience `turf`, mode `request`; test.
- **🔒 SENIOR GATE 7:** templates are a clear starter kit; nothing depends on the removed complexity.

---

# PHASE 8 — Slim the admin sidebar  *(cosmetic / low-risk — not a foundation blocker; do late or opportunistically)*
*Note after code review: the sidebar is already ~9 top-level items (with some duplicate Settings/Transactions/Launch-Control entries to de-dupe). This phase is polish, not foundation — do not let it block Phases 0–6.*
- **Task 8.1 — Rework `resources/views/layouts/admin.blade.php`.** Target: Overview[Dashboard]; Catalog[Businesses, Categories, Areas, Launch Controls]; Operations[Bookings, Claims, Reports & Reviews]; People[Vendors, Users]; System[**Settings** (restore — currently hidden), AI Imports]; everything else under one "Advanced" `<details>`. **Remove Transactions** (no payments). Hide Orders/Products/Services until Shopping launches. *Accept:* ~10 items; Settings reachable; no Transactions link.
- **🔒 SENIOR GATE 8:** admin nav clean, nothing important lost (only hidden).

---

# PHASE 9 — Phase-1 launch configuration (Directory + Turf)
- **Task 9.1 — Set Launch Control flags** (seeder or admin screen): **ON** `world.discover, experience.directory, world.book, module.bookings, module.turf, experience.turf`; **OFF** `world.shop, world.ride`, all other experiences/modules; `payments.online` OFF. *Accept:* `GET /api/platform/features` → `enabled_tabs=[discover, book]`; only those tabs render.
- **Task 9.2 — End-to-end turf booking (manual).** Real turf vendor: create service + time slot → customer sends request → vendor confirms → "pay at venue". *Accept:* full journey passes on staging with real data.
- **🔒 SENIOR GATE 9:** launch surface = Directory + Turf only, manual, secure. Ready for controlled deploy.

---

## Cross-cutting security checklist (must stay green through all phases)
- AuthN: Sanctum for API; vendor/admin web behind auth middleware.
- AuthZ: `BusinessPolicy` on **every** owner-scoped mutation; cross-tenant tests exist.
- CSRF on all web forms; rate limits on OTP/login/public requests.
- Validation on every input; mass-assignment guards on `verification_status`/`claim_status`/ownership.
- Uploads: MIME + size validated, not executable, ownership-checked.
- Payments: `payments.online=false` enforced server-side (hard kill switch).
- Secrets in `.env` only; no keys in code; API keys encrypted (integration).
- Imports idempotent (source id) + human review for low-confidence (AI Scout governance).
- Concurrency: when live inventory arrives later, use DB transactions/locks for slots/rooms/seats (out of Phase-1 scope, noted for the long run).
- Audit trail on claims, verification, and status changes.

## Deployment safety (every deploy)
DB backup → run migrations → `php artisan test` green → smoke test `GET /health`, `/api/platform/features`, `/api/worlds` → keep rollback (previous release + DB dump). Never deploy over uncommitted server drift (Phase 0).

## Deferred (not now — noted so they aren't forgotten)
- Drop legacy `subcategories` table (after 2.4 verified in prod).
- Live/real-time inventory + concurrency locks (turf slots → hotel rooms → seats).
- Shopping launch (grocery/restaurant/medicine), then Taxi.
- Online payments program (separate, disabled by default).
