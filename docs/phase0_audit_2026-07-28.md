# Phase 0 — Baseline Audit Report

**Date:** 2026-07-28  
**Repositories:**
- Laravel: `/Users/moldokipgen/Projects/Eiho One/Eiho One Backend`
- Flutter: `/Users/moldokipgen/Projects/Eiho One/Eiho One Flutter`

---

## 1. Git Status (Both Repositories)

### Laravel Backend
```
On branch main
Your branch is up to date with 'origin/main'.

Changes not staged for commit:
  (use "git add <file>..." to update what will be committed)
  (use "git restore <file>..." to discard changes in working directory)
        modified:   .env
        modified:   app/Models/Business.php
        modified:   app/Services/BusinessModuleService.php
        modified:   config/app.php
        modified:   config/payments.php
        modified:   database/migrations/2024_01_01_000000_create_businesses_table.php
        modified:   routes/api.php

Untracked files:
  (use "git add <file>..." to include in what will be committed)
        docs/
        storage/logs/laravel.log
```

### Flutter App
```
On branch main
Your branch is up to date with 'origin/main'.

Changes not staged for commit:
  (use "git add <file>..." to update what will be committed)
  (use "git restore <file>..." to discard changes in working directory)
        modified:   lib/main.dart
        modified:   lib/models/models.dart
        modified:   lib/screens/booking_screen.dart
        modified:   lib/screens/bookings_screen.dart
        modified:   lib/screens/business_detail_screen.dart
        modified:   lib/screens/home_screen.dart
        modified:   lib/screens/order_screen.dart
        modified:   lib/screens/shopping_screen.dart
        modified:   lib/screens/trip_booking_screen.dart
        modified:   lib/services/api.dart

Untracked files:
  (use "git add <file>..." to include in what will be committed)
        DEEPSEEK_FULL_IMPLEMENTATION_PLAN.md
```

**Note:** Both worktrees contain user changes. No reset/clean will be performed.

---

## 2. Test Results

| Suite | Command | Result |
|-------|---------|--------|
| Laravel PHPUnit | `php artisan test` | **58 tests passed, 282 assertions** |
| Flutter Dart Analysis | `dart analyze` | **No issues found** |
| Flutter Widget Tests | `flutter test` | **1 test passed** |

---

## 3. Production Health Audit

### `/health` Endpoint
```json
{
  "status": "ok",
  "checks": {
    "application": true,
    "database": true,
    "storage": true,
    "scheduler": true,
    "queue": true
  },
  "checked_at": "2026-07-28T20:04:47+00:00"
}
```
**All checks: PASS**

### `/api/payments/config` Endpoint
```json
{
  "payment_mode": "offline",
  "razorpay": { "enabled": false },
  "cashfree": { "enabled": false },
  "cod": { "enabled": true },
  "config": { "online_enabled": false }
}
```
**Payment mode: OFFLINE (COD only) — Correct for current release.**

---

## 4. Route Audit

| Metric | Count |
|--------|-------|
| Total routes | 370 |
| Duplicate route names | **41** (all named `integration.`) |

**Action required:** Fix duplicate route names in Phase 1.

---

## 5. Business Capability Counts (Production)

### Active Businesses
- **Total active:** 44

### Raw Capability Flags (`enabled_modules`)
| Capability | Count | % of Active |
|------------|-------|-------------|
| catalog | 23 | 52% |
| orders | 21 | 48% |
| bookings | 16 | 36% |
| inventory | 2 | 5% |
| transport | 0 | 0% |
| turf | 0 | 0% |

### Legacy Fields
| Field | True | False |
|-------|------|-------|
| `is_bookable` | 16 | 28 |

### Service Type Values
- `buyable`: catalog + orders
- `bookable`: bookings
- `directory`: no transaction modules
- `hybrid`: multiple modules

### Contradictions Found
- 16 businesses have `is_bookable=true` but only 16 have `bookings` capability — **consistent**
- 0 businesses have `transport` or `turf` enabled — **matches zero transport/turf businesses in discovery**

---

## 6. Transactional Inventory Counts

### Active Services (by `booking_mode`)
| Mode | Count |
|------|-------|
| appointment | 4 |
| slot | 0 |
| stay | 0 |
| seat | 0 |

### Active Vehicles (by `service_mode`)
| Mode | Count |
|------|-------|
| *(none)* | 0 |

**Critical gap:** Zero transport/turf businesses configured → Phases 6–11 cannot be validated without pilot data.

---

## 7. Incomplete Capability JSON

- Businesses with malformed `enabled_modules` (missing one of 6 keys): **1**

---

## 8. Enabled-but-Unready Businesses

| Experience | Capability Enabled | Active Inventory | Ready? |
|------------|-------------------|------------------|--------|
| Retail/Restaurant | orders (21) | 21 have products | Partial — need product freshness check |
| Appointment | bookings (16) | 4 appointment services | **Yes** (4 services) |
| Stay | bookings (16) | 0 stay services | **No** |
| Turf | bookings + turf (0) | 0 slot services | **No** |
| Taxi | transport (0) | 0 vehicles | **No** |
| Shared Transport | transport (0) | 0 vehicles/routes | **No** |
| Vehicle Rental | transport (0) | 0 vehicles | **No** |
| Goods Transport | transport (0) | 0 vehicles | **No** |
| Seat Event | bookings (16) | 0 seat services | **No** |

---

## 9. Existing Failures Documented

| Area | Issue | Severity |
|------|-------|----------|
| Route names | 41 duplicates (`integration.`) | Medium |
| Transport module | 0 businesses, 0 vehicles | **Blocker for Phases 6, 10–11** |
| Turf module | 0 businesses, 0 slot services | **Blocker for Phase 8** |
| Stay module | 0 stay services | **Blocker for Phase 9** |
| Seat Event | 0 seat services | **Blocker for Phase 11** |
| Capability JSON | 1 malformed record | Low |

---

## 10. Phase 0 Exit Criteria — Status

| Criterion | Status |
|-----------|--------|
| No code changes | ✅ |
| Baseline report contains exact counts | ✅ |
| All existing failures documented | ✅ |

---

## 11. Recommendations Before Phase 1

1. **Seed pilot data** in staging for each experience type (3 per type: ready, request-mode, contact-only).
2. **Fix duplicate route names** during Phase 1 migration.
3. **Add database indexes** for `primary_experience`, `enabled_experiences`, area/category filtering.
4. **Verify `BusinessModuleService::normalize`** produces all 6 keys consistently before running backfill.

---

**Phase 0 Complete. Ready for Phase 1A (additive experience migration).**