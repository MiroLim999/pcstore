# PC Store Management System — Project Brief

You are helping me build a complete PC store management system from scratch. Read this entire document before making recommendations or writing code. When I ask for help, refer back to these decisions rather than suggesting a different architecture.

---

## 1. Project Overview

A single web application for a physical PC parts store with three user surfaces:

1. **Client site** — public-facing. Customers browse parts, build a custom PC with a live compatibility + bottleneck analyzer, and submit the build to the cashier for in-store pickup and payment.
2. **Cashier POS** — staff-only. Handles walk-in sales and client-submitted build pickups. Keyboard-first workflow with barcode scanner support.
3. **Admin panel** — staff-only. Manages products, inventory, pricing, users, and reports.

All three share one codebase, one database, and one authentication system. Role is determined by a `role` column on the `users` table.

---

## 2. Tech Stack (Fixed — Do Not Suggest Alternatives)

- **Backend:** Vanilla PHP 8+ (no framework, no Composer packages beyond what PHP ships with)
- **Database:** MySQL 8+ (InnoDB, **MySQLi** with prepared statements — object-oriented style)
- **Frontend:** HTML5, CSS3, vanilla JavaScript (no React, Vue, jQuery, Tailwind)
- **Server:** Apache/XAMPP locally, similar in production
- **Payment:** Cash-only at the counter for MVP. No online payment gateway yet.
- **Architecture:** NOT MVC. Flat PHP file structure where each URL maps to a real PHP file. See section 6.

Do not recommend Laravel, Symfony, Slim, Node.js rewrites, or any SPA framework. I chose vanilla PHP deliberately.

---

## 3. User Roles & Permissions

| Role | Can |
|---|---|
| `client` | Register, login, browse catalog, build PC, submit build, view own build history |
| `cashier` | All client actions + access POS, take payment, process returns, manage shift |
| `admin` | All cashier actions + full product/inventory/report/user management |
| `superadmin` | Everything + create other admins |

Single `users` table with a `role` enum column. Session-based auth.

---

## 4. Feature List

### Client Features
- Register / login / password reset
- Browse full catalog with category filter, search, price sort
- Product detail page with specs, stock status, image gallery
- PC Builder (see section 7 — already built as standalone prototype)
- Live compatibility validation (server re-validates on submit)
- Live bottleneck percentage + estimated FPS across sample games
- Submit build → get a 6-digit pickup code + 48-hour reservation on parts
- My Builds page: view submitted, paid, expired, cancelled builds
- Profile with contact info, order history

### Cashier Features
- Dashboard: today's queue of submitted builds, pending pickups, today's sales
- Lookup submitted build by 6-digit pickup code, phone, or name
- Walk-in mode: blank cart, barcode/SKU scanner input with autofocus
- Add items by scan, manual SKU, or search
- Adjust quantity, remove line, apply item-level discount
- Take payment: cash, split cash/card (card = manual reference for now)
- Compute change due, print receipt
- Void a line or whole sale (with reason, logged)
- Returns: lookup receipt, select lines, refund to original method or store credit
- Shift management: clock in/out, opening drawer count, closing count, variance
- Edit a submitted build before payment (swap parts → re-validate compat → re-price)

### Admin Features
- **Dashboard:** sales today/week/month, low-stock alerts, pending builds count, best sellers
- **Products:** CRUD with SKU, barcode, category, price, cost, stock, image, specs, tags
- **Categories:** CRUD aligned to the 8 PC builder slots (cpu, cooler, mobo, ram, gpu, ssd, psu, case)
- **Inventory:** stock-in workflow (qty + unit cost + supplier), stock adjustments (damage/loss with reason), low-stock thresholds, movement history per product
- **Pricing:** base price + cost tracking (for margin), scheduled price changes, promo codes (% or fixed, min spend, date range), bundles
- **Builds:** view all submitted builds, manually expire/release reservations
- **Orders:** all completed sales, filter by date/cashier/status
- **Users:** manage clients and staff, role assignment, disable without deleting
- **Reports:** daily/weekly/monthly revenue, by category, by product, by cashier, best sellers, slow movers, profit margins, drawer reconciliation. CSV export.
- **Audit log:** every price/stock/role change recorded with who + when
- **Settings:** store name, address, tax rate, receipt footer, reservation expiry hours
- **Warranty tracking (phase 2):** serial number per sold unit, warranty dates lookup

---

## 5. Key Business Flow: Client Build → Cashier Pickup

This is the most important flow. Get it right.

1. Client logged in, uses the PC builder, picks compatible parts.
2. Clicks "Send to Cashier" → POST to `/api/submit_build.php`
3. Server-side PHP **re-runs compatibility checks** (never trust JS)
4. If valid: create `builds` row with `status='submitted'`, generate 6-digit `pickup_code`, reserve stock via `inventory_transactions` with `type='reserve'`, reservation expires in 48h.
5. Client sees confirmation with pickup code + instructions.
6. Client walks into store. Cashier searches by code.
7. Cashier reviews build, confirms parts still available (they are, because reserved).
8. Customer pays cash → cashier clicks "Take Payment".
9. Server: DB transaction — update build status to `paid`, convert reservation to `sale` in `inventory_transactions`, decrement `products.stock_qty`, decrement `products.reserved_qty`, create `orders` row, create `order_items` rows with price snapshots, generate receipt number, log shift sale.
10. Receipt prints. Done.

**Critical:** a scheduled cron job (or manual admin action) expires reservations older than 48h and releases stock. Cashier can manually cancel a build with one click (which also releases).

---

## 6. File Structure (Flat, Beginner-Friendly)

Do not propose a router, service container, dependency injection, or MVC folders. Keep it flat — every URL maps to a real `.php` file.

```
/pcstore
├── index.php                         ← landing, redirects by role
├── login.php
├── logout.php
├── register.php
│
├── /client
│   ├── home.php
│   ├── catalog.php
│   ├── product.php                   ← ?id=123
│   ├── builder.php                   ← existing PC builder UI (see sec 7)
│   ├── my_builds.php
│   ├── build_detail.php              ← ?id=5
│   └── profile.php
│
├── /cashier
│   ├── dashboard.php
│   ├── new_sale.php
│   ├── lookup_build.php
│   ├── checkout.php                  ← ?build=5 or walk-in cart
│   ├── receipt.php                   ← ?order=12
│   ├── returns.php
│   └── shift.php
│
├── /admin
│   ├── dashboard.php
│   ├── products.php
│   ├── product_form.php              ← ?id= for edit, blank for add
│   ├── categories.php
│   ├── stock_in.php
│   ├── stock_adjust.php
│   ├── builds.php
│   ├── orders.php
│   ├── users.php
│   ├── reports.php
│   └── settings.php
│
├── /includes                         ← shared PHP, not visited directly
│   ├── config.php                    ← db creds + constants
│   ├── db.php                        ← opens MySQLi connection ($mysqli)
│   ├── auth.php                      ← is_logged_in, require_login, require_role
│   ├── helpers.php                   ← e(), money(), csrf, flash
│   ├── header.php                    ← opens <html><head><body><nav>
│   ├── footer.php                    ← closes </body></html>
│   └── compatibility.php             ← server-side compat rules (mirrors JS)
│
├── /api                              ← JSON endpoints for JS
│   ├── catalog.php
│   ├── submit_build.php
│   └── check_compat.php
│
├── /assets
│   ├── /css
│   ├── /js
│   └── /img
│
├── /uploads                          ← product images, with .htaccess blocking PHP
│
└── /sql
    ├── schema.sql
    └── seed.sql
```

Every page is a normal PHP file that includes `config.php`, `db.php`, `auth.php`, `header.php`, does its thing, includes `footer.php`. That's it.

---

## 7. Existing PC Builder Prototype

I already have a working standalone prototype of the PC builder at `/pcstore/pcbuilder` with these files:
- `index.html` — the full builder UI with 8 component cards in a diagram layout, connector lines to a center PC image
- `script.js` — hardcoded `CATALOG`, drag-and-drop, compatibility checker, bottleneck calculator, FPS estimator
- `style.css` — complete visual design

**The plan:** port this prototype into `/client/builder.php`. Replace the hardcoded `CATALOG` in `script.js` with a fetch to `/api/catalog.php` that returns the same shape from MySQL. Add a "Send to Cashier" button that POSTs to `/api/submit_build.php`. The server re-runs compatibility checks using `/includes/compatibility.php` (port the JS logic to PHP one-to-one).

Keep all the existing JS functionality:
- Drag-to-reposition cards with custom `INITIAL_POSITIONS`
- Component picker modal with search, sort, recommended badge
- Live bottleneck % with severity tiers + verdict
- FPS estimates for 6 games
- Performance panel with CPU/GPU/Memory/Storage/Power tiles + recommendation badges

---

## 8. UI Direction — Reuse MEIS2_FRONTEND Styling

I have a previous project called **MEIS2_FRONTEND** whose visual design I want to reuse for the admin panel and cashier POS. Copy its:
- Color palette (CSS variables)
- Typography (font families, sizes, weights)
- Sidebar navigation structure
- Data table styling
- Form control styling
- Modal patterns
- Button hierarchy (primary/secondary/ghost)
- Card/panel look

**What to do:** when I share files or screenshots from MEIS2_FRONTEND, adapt its styles into `/assets/css/admin.css` and `/assets/css/cashier.css`. Do NOT invent a new design system — match MEIS2_FRONTEND.

Exception: the **client-facing** builder page keeps its existing purple/Outfit font design from the prototype. Client pages can use a lighter, friendlier style; admin/cashier use MEIS2's professional look.

---

## 9. Database Essentials

At minimum I'll need these tables (details TBD):

- `users` — id, email, password_hash, role, name, phone, created_at, is_active
- `categories` — id, slug (cpu/cooler/mobo/ram/gpu/ssd/psu/case), label
- `products` — id, category_id, sku, barcode, name, description, price, cost, stock_qty, reserved_qty, image_url, is_active, created_at
- `product_specs` — id, product_id, key, value (or a JSON column for flexibility)
- `compatibility_attrs` — structured per-category columns: socket, tdp, form_factor, memory_type, m2_slots, wattage, etc. (mirrors your JS OPTION_META)
- `builds` — id, user_id, status, pickup_code, total_price, reserved_until, created_at
- `build_items` — id, build_id, product_id, price_snapshot, qty
- `orders` — id, build_id (nullable for walk-ins), cashier_id, receipt_no, subtotal, tax, total, paid_amount, payment_method, status, created_at
- `order_items` — id, order_id, product_id, price_snapshot, qty, discount
- `inventory_transactions` — id, product_id, type (sale/restock/reserve/release/adjust/return), qty, reference_type, reference_id, created_by, reason, created_at
- `shifts` — id, cashier_id, opened_at, closed_at, opening_cash, closing_cash, expected_cash, variance
- `promos` — id, code, type (percent/fixed), value, min_spend, starts_at, ends_at, usage_count, max_uses
- `audit_log` — id, user_id, action, entity_type, entity_id, old_value, new_value, created_at
- `settings` — key-value store for store name, tax rate, reservation hours, etc.

All stock changes MUST go through `inventory_transactions`. Never update `products.stock_qty` directly. It's a ledger.

---

## 10. Security Baseline (Non-Negotiable)

- All SQL uses **MySQLi prepared statements** with `bind_param()`. No string concatenation, ever.
- `password_hash()` with bcrypt for passwords. Never MD5/SHA/plain.
- CSRF token on every POST form, checked server-side via `csrf_check()`.
- `htmlspecialchars()` on every echo of user-supplied data (via `e()` helper).
- Session regenerated on login (`session_regenerate_id(true)`).
- Login rate limit: track failed attempts, lock for N minutes after 5 failures.
- File uploads: whitelist extensions (jpg/png/webp), re-encode with GD, store in `/uploads/` with `.htaccess` blocking PHP execution.
- HTTPS in production.
- Role guards on every cashier/admin page via `require_role()`.
- Server-side validation of everything — including compatibility rules on build submit.
- Never expose DB errors to users. Log them, show a generic message.

---

## 11. Development Priority / Phases

### Phase 1 — Minimum Viable Store (target: 2 weeks)
1. Folder skeleton + config + db + auth helpers + header/footer includes
2. Login, register, logout
3. Admin: products CRUD + categories CRUD + stock_in
4. Client: catalog browsing
5. Port existing PC builder to `/client/builder.php`, fetch catalog from DB
6. `/api/submit_build.php` with server-side compat check, pickup code, reservation
7. Cashier: queue, lookup by code, take payment, basic HTML receipt
8. Handle stock decrement + reservation release on payment

### Phase 2 — Usable POS
- Walk-in mode with barcode scanner
- Low-stock alerts + dashboard
- Daily/weekly sales reports
- Returns workflow


---

## 12. How to Help Me

When I ask you questions about this project:

- **Refer to this document first.** If I ask about architecture or features, match what's written here. If I want to deviate, I'll say so explicitly.
- **Write plain PHP.** No frameworks, no Composer (unless for something truly necessary like a PDF library later).
- **Keep files small and self-contained.** Each page file should be readable top-to-bottom.
- **Always use MySQLi prepared statements (object-oriented style) with `bind_param()`.** Show me the full query with `?` placeholders. No PDO, no procedural MySQLi.
- **Show the full file when creating new ones** so I can copy-paste into XAMPP and run.
- **Point out security concerns** when I'm about to do something unsafe.
- **Be honest about tradeoffs.** If a beginner approach will bite me later, say so and offer the upgrade path.
- **Don't over-engineer.** Simple > clever. I'm learning.
- **Mirror the JS compatibility logic in PHP exactly.** The rules for socket match, cooler TDP, RAM type, case form factor, M.2, PSU wattage, CPU/GPU balance must be identical server-side.
- **Use the MEIS2_FRONTEND visual language for admin/cashier** when I share those files. Keep the client builder's existing purple design.

When I'm stuck, walk me through the reasoning. When I want code, give me clean, working code. When I'm about to make a beginner mistake, flag it kindly.

Let's build this.
