# Eiho One — Flutter App Build Plan (Handoff for the Next Agent)

> **Audience:** an implementing agent building the Eiho One customer mobile app in Flutter.
> **Backend:** fully built + deployed at `https://hola.ehlom.com/api` (Laravel). All APIs the app needs exist and are tested.
> **Goal of THIS launch:** **Launch #1 = Booking + Discovery only.** The app must look and feel like **Blinkit / Zepto / Instamart** — a clean home with a **left rail of category tabs** (Grocery, Medicine, Restaurants, Beauty, Hotels, Turf, ...), search, and rich business cards. When all worlds are switched on later, the same app grows into the full super-app (Shopping, Booking, Transport, Directory all in tabs).
> **Critical backend fact:** Discovery and Booking are **one screen**. Every business card carries a `booking` object → `can_book_online` + `book_cta` (`in_app` | `call_or_whatsapp` | `none`). The card shows "Book" (opens in-app flow) OR "Call/WhatsApp". There is **no separate "Booking" tab** — it's a capability on each card in the Discover list.

---

## 0. How to work (rules)
1. **One feature = one small change = one commit.** Don't batch.
2. The backend is DONE. **Do not change the backend** unless you find a real bug — note it in the plan, don't fix inline.
3. Run `flutter analyze` + `flutter test` after each feature. Keep them green.
4. Follow the existing project conventions: there's a design system (`lib/design_system/`), an `ApiClient` (`lib/services/api.dart`), services in `lib/services/`, screens in `lib/features/<feature>/`, models in `lib/models/`.
5. **Production base URL is already correct** in `lib/config.dart` (`https://hola.ehlom.com/api`). Don't break it.
6. The app must work **offline-first** (COD / cash). Online payment (razorpay) exists but is **hard-off** on the backend — don't build a checkout that depends on it.

---

## 1. What already exists (don't rebuild)

- **`ApiClient`** (`lib/services/api.dart`) — base URL, token storage, `get/post/put/delete`, error handling. **Use it everywhere.**
- **Auth**: `lib/services/auth_service.dart`, `lib/features/auth/` (OTP flow exists).
- **Screens already scaffolded** in `lib/features/`: `discover`, `explore`, `search`, `home`, `shop`, `commerce` (cart/checkout/order-status), `restaurant`, `ride`, `transport` (seat selection, vehicle selection, rental), `turf`, `stays`, `book`, `appointments`, `notifications`, `chat`, `settings`, `activity`, `saved` (via saved_service).
- **Models**: `lib/models/` (`booking`, `order`, `user`, `vehicle`, `launch_config`, `models.dart`).
- **Services**: `api, auth, booking, business, category, launch_control, notification, order, payment, review, saved, search, trip, localization`.
- **Design system**: `lib/design_system/` (tokens, buttons, cards, forms, category grid, hero cards, bottom sheets, skeletons).
- **Maps**: `google_maps_flutter` dependency present; `lib/features/explore/` has a map.

**Your job is NOT to build from scratch — it's to CONNECT the screens to the live APIs and make the Launch #1 surface (Booking + Discovery) genuinely usable and beautiful.**

---

## 2. The Blinkit-style design system (the app's visual identity)

Replicate the Blinkit/Zepto home pattern:

```
┌─────────────────────────────────────────────┐
│  [Location: Lamka ▾]    [Search 🔍]  [Cart] │  ← top bar
├─────────────────────────────────────────────┤
│  City / Location picker (horizontal chips)  │  ← Lamka, Delhi, Kolkata, Guwahati...
├─────────────────────────────────────────────┤
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐      │
│  │ 🛒 │ │ 💊 │ │ 🍜 │ │ 💇 │ │ 🏨 │ │ ⚽ │  │  ← LEFT RAIL: category tabs
│  │Gro │ │Med │ │Rest│ │Bty │ │Htl │ │Turf│  │     (vertical icon rail, sticky)
│  └───┘ └───┘ └───┘ └───┘ └───┘ └───┘      │
├─────────────────────────────────────────────┤
│  Categories:  Food  ·  Beauty  ·  Hotels  ·  │  ← horizontal chip row (sub-cats)
├─────────────────────────────────────────────┤
│  ┌─────────────────────────────┐            │
│  │ Featured / bookable hero     │            │
│  └─────────────────────────────┘            │
│  ┌─────────────────────────────┐            │
│  │ Business card               │            │
│  │  name · rating · distance   │            │
│  │  [Book] or [Call/WhatsApp]  │            │
│  └─────────────────────────────┘            │
└─────────────────────────────────────────────┘
```

**Key Blinkit patterns to follow:**
- **Sticky left rail** of category icons (vertical, scrolls with page) — tap filters the main list.
- **Horizontal chip row** for sub-categories/tabs under the selected rail item.
- **Skeleton loading** on first fetch (design system has `skeletons.dart`).
- **Bottom sheet** for filters / booking options (design system has `eiho_bottom_sheet.dart`).
- Cards show: photo, name, rating (⭐ x.x), category, distance, and the **primary action button** driven by `booking`.
- Generous whitespace, rounded cards, soft shadows — clean like Instamart, not dense like Swiggy.

---

## 3. Launch #1 scope — BUILD THIS FIRST (Booking + Discovery)

### 3.1 App shell + navigation
- Bottom nav: **Home (Discover)** · **Search** · **My Orders/Bookings** · **Profile**. (When Shop/Ride turn on later, tabs grow — plan the shell to be extensible.)
- **City picker** in the top bar: fetches `GET /api/cities`, shows chips; selecting a city re-fetches businesses with `?city_id=<id>`. Default = Lamka (home).

### 3.2 The Discover home (Blinkit-style)
- Fetch `GET /api/businesses?city_id=X&per_page=20`.
- **Left rail** categories from `GET /api/categories` (or the `category` field on each business). Rail = the top-level canonical categories that HAVE businesses.
- Tap a rail item → fetch `GET /api/businesses?category=<slug>&city_id=X` OR filter client-side.
- Each card reads the `booking` object:
  - `can_book_online == true` → primary button **"Book"** (opens the matching in-app flow by `primary_experience`).
  - else → **"Call"** (tel: `contact.phone`) and **"WhatsApp"** (wa.me `contact.whatsapp`).
- Include the `booking.ready_experiences` to decide the flow label (appointment/stay/turf/seat).

### 3.3 Business detail screen
- `GET /api/businesses/{slug}` → business + `prototype_data` (services, products, vehicles, next available slot).
- Photo gallery, working hours, rating, reviews (read-only), call/WhatsApp/directions buttons, **Save** (uses `saved_service`).
- If bookable → show services list → tap → booking flow.

### 3.4 The 4 booking flows (all offline/cash)
Backend modes and the endpoints to call:

| Mode | Customer picks | API |
|---|---|---|
| **Appointment** (barber, electrician, clinic, tutor) | service + date + time | `GET /businesses/{slug}/services`, then `POST /businesses/{slug}/bookings` (start_time) |
| **Stay** (hotel/lodge) | service + check-in + check-out + rooms | same endpoints with check_in_date/check_out_date/reservation_units |
| **Turf/Slot** | service + date + slot | `GET /services/{serviceId}/slots?date=`, then `POST /businesses/{slug}/bookings` (time_slot_id) |
| **Seat/Event** | service + date + seat(s) | `GET /services/{serviceId}/slots?date=`, then `POST .../bookings` (seat_labels) |

- After booking: show a **success screen** with the booking reference, "pay the business directly (cash/COD)" message, and the business phone/WhatsApp. Store the booking for "My Bookings".
- Anonymous customers: capture a `client_reference` (send it) so they can look it up later via `GET /bookings/lookup?phone=&client_reference=`.

### 3.5 My Bookings
- Signed-in: `GET /my-bookings` + `GET /my-bookings/{id}` (detail) + `PUT /my-bookings/{id}/cancel` + `PUT /my-bookings/{id}/reschedule`.
- Anonymous: a "Find my booking" entry → phone + reference → `GET /bookings/lookup`.

### 3.6 Auth
- Phone OTP via `auth_service` (backend has OTP endpoints). Needed for My Bookings/Orders. Keep it simple.

---

## 4. Post-launch-1 scope (build AFTER Booking+Discovery is live & clean)

### 4.1 Shopping (turns on when `world.shop` is enabled)
- Storefront (`GET /businesses/{slug}/products`), **server-side cart** (`GET/POST/PUT/DELETE /businesses/{slug}/cart*` — already built, use `X-Guest-Token` header for guests), COD checkout (`POST /businesses/{slug}/orders`), order tracking (`GET /my-orders/{id}/track`).
- The **Grocery/Medicine/Restaurant** tabs in the left rail become full shopping experiences (Blinkit-style item grid with "+" add buttons).

### 4.2 Transport (turns on when `world.ride` is enabled)
- Taxi request + fare quote, bus seat picker (visual map from `vehicle.seat_layout`), vehicle rental date picker. `trip_service`, `lib/features/transport/`, `lib/features/ride/` already scaffolded.

### 4.3 Push notifications
- Firebase Messaging is already a dependency. Register device token via the backend push-token endpoint; surface `notifications` service + screen.

---

## 5. API reference — the exact endpoints the app uses

(All prefixed `https://hola.ehlom.com/api`.)

### Discovery
- `GET /cities` → `{ cities: [{id, name, slug, state, district, pincode, is_home, businesses_count}] }`
- `GET /businesses?city_id=&category=&module=&experience=&pincode=&district=&area_id=&per_page=&sort=` → `{ businesses: { data: [BusinessSummary], ... } }`
- `GET /businesses/{slug}` → `{ business, prototype_data }`
- `GET /businesses/nearby`, `GET /businesses/featured`, `GET /businesses/trending`, `GET /businesses/new`, `GET /businesses/clusters` (map)
- `GET /businesses/{slug}/related`, `GET /businesses/{slug}/services`
- `GET /categories` → `{ categories: [{id,name,slug,icon,subcategories, businesses_count}] }`
- `GET /categories/{slug}/businesses`
- `GET /search/universal?q=&world=` (global search)
- `GET /businesses/{business}/reviews`

**`BusinessSummary` card shape (this is what every list card renders):**
```json
{
  "id": 1, "name": "Cozy Stay Inn", "slug": "cozy-stay-inn",
  "photo": "https://...", "photos": ["..."],
  "rating": 4.5, "average_rating": 4.5, "review_count": 12,
  "distance": 1.2, "address": "...", "locality": "...",
  "district": "...", "pincode": "...", "state": "...",
  "city": { "id": 1, "name": "Lamka (Churachandpur)", "slug": "...", "state": "Manipur" },
  "category": { "id": 2, "name": "Hotels & Lodges", "slug": "hotels-lodges", "icon": "🏨" },
  "phone": "...", "whatsapp": "...",
  "booking": {
    "can_book_online": true,
    "book_cta": "in_app",                 // "in_app" | "call_or_whatsapp" | "none"
    "ready_experiences": ["stay"],
    "primary_experience": "stay",
    "contact": { "phone": "...", "whatsapp": "..." }
  },
  "primary_action": { "type": "check_availability", "label": "Check Availability" },
  "capabilities": { "catalog": false, "orders": false, "bookings": true, "transport": false, "turf": false }
}
```

### Booking
- `GET /businesses/{slug}/services` → `{ services: [{id,name,price,price_unit,booking_mode,duration,capacity,inventory_units,image,...}] }`
- `GET /services/{serviceId}/slots?date=&party_size=&reservation_units=` → `{ slots: [{time_slot_id?, start_time, end_time, capacity, available, can_accommodate, price, resource_id?, resource_name?}] }`
- `POST /businesses/{slug}/bookings` → `{ booking, duplicate, payment_mode: "offline", business_contact: {phone, whatsapp} }`
  - payload: `service_id`, `booking_date` OR `check_in_date`+`check_out_date`, `start_time` OR `time_slot_id`, `party_size`, `reservation_units`, `seat_labels[]`, `customer_name`, `customer_phone`, `customer_email`, `client_reference`, `notes`
- Auth: `GET /my-bookings`, `GET /my-bookings/{id}`, `PUT /my-bookings/{id}/cancel`, `PUT /my-bookings/{id}/reschedule` (to_date/to_time/to_slot_id)
- Anonymous: `GET /bookings/lookup?phone=&client_reference=`

### Auth (OTP)
- `POST /auth/...` (request OTP, verify) — see `auth_service` for existing calls.

### Platform
- `GET /platform/features` → `{ worlds: {shop, ride, book, discover}, enabled_tabs: [...] }` — the app can hide tabs whose world is OFF. **Currently: `ride, book, discover` are ON; `shop` is OFF.**

---

## 6. Acceptance criteria for Launch #1

1. App opens to the **Discover home** (Blinkit-style: location picker, left rail, category chips, business cards).
2. City picker switches data correctly (Lamka default; Delhi/Kolkata/Guwahati show their businesses).
3. Every business card shows the **right CTA** from `booking`:
   - `can_book_online` → **Book** opens the correct in-app flow.
   - else → **Call** + **WhatsApp** buttons work (url_launcher).
4. All 4 booking flows (appointment, stay, turf, seat) complete end-to-end against the live API and show the offline-payment success screen with the reference + contact.
5. "My Bookings" shows a user's bookings; cancel + reschedule work; anonymous lookup works with phone + reference.
6. **The Shopping and Transport tabs are hidden** (worlds off) but the code is ready to reveal them.
7. `flutter analyze` clean; existing tests + any new tests pass.
8. Polish: skeletons, empty states, error toasts, offline-friendly messages.

---

## 7. Suggested build order (sessions)

- **Session 1 — Shell + Discover list:** bottom nav, city picker, left rail, business cards with correct CTA. (The whole app feel in one session.)
- **Session 2 — Business detail + booking flows:** detail screen, then the 4 booking flows + success screens.
- **Session 3 — Auth + My Bookings:** OTP, my-bookings list/detail/cancel/reschedule, anonymous lookup.
- **Session 4 — Polish:** skeletons, empty/error states, reviews read-only, saved, map on explore, notifications stub.
- **Session 5+ — Shopping/Transport reveal** (only when worlds turn on): cart wiring, storefront, order tracking; then transport flows.

---

## 8. Important backend facts (don't fight these)

- **Payments are OFFLINE/COD.** Every booking/order returns `payment_mode: "offline"`. Do not gate the flow on a payment step.
- **Discovery + Booking = one screen.** There is no separate "Booking tab" — the `booking` object on each card decides the CTA.
- **City filter** uses `?city_id=` (numeric) or `?city=<slug>`. Vendors pick a city at registration; AI imports attach a city when you use Import-by-Link.
- **Shopping is Lamka-only** right now (`world.shop` OFF). Hide the Shopping experience until it's enabled.
- **Photos** may come back as `storage/...` relative paths or full URLs — resolve relative ones with the origin (`https://hola.ehlom.com/`). The backend now stores photo **references** and downloads them server-side, so URLs are safe to load.
- Use `GET /platform/features` to decide which bottom tabs / rail items to show. It's the single source of truth for "what's on".

---

*Handoff created 2026-08-13. Backend for Launch #1 (Booking + Discovery) is complete and deployed. Build the app to match the Blinkit-style design above and wire it to the live APIs.*
