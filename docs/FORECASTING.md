# Demand Forecasting and Smart Inventory Recommendations

**System:** LGU Property Inventory Management System  
**Module:** Planning → Forecast (administrator only)  
**Mode:** Live (reads this database; not the old synthetic demo series)  
**Purpose of this document:** Explain what the forecast does, how numbers are produced, how an administrator should read the screen, and what the module does *not* claim.

---

## 1. What this module is for

The forecast helps GSO / property staff **plan the next three months**. It does not create a purchase request and it does not change inventory.

From predicted demand and current stock it answers:

| Question | What the system shows |
|---|---|
| When to reorder | Groups that should be replenished before they run short |
| How much to order | Suggested units = 3-month need + safety − on-hand − incoming PRs |
| What may run out soon | Stockout-risk groups |
| What may become overstocked | High on-hand versus low expected demand |
| What to prioritize | The first groups to buy, hold, or transfer |

A **group** is one **office + item category** (example: *Office Equipment & Tools at Health Office*), not a single property number.

---

## 2. Who can use it

| Role | Access |
|---|---|
| **Administrator** | Full page under **Planning → Forecast** |
| **Department / staff** | No Forecast page. Department users see only their office procurement workspace |

Sign in as administrator, then open **Forecast**. After a code update, hard-refresh the browser so the latest scripts load.

**Refresh forecast** rebuilds history from current items, issuances, returns, and open PRs, then recomputes recommendations.

---

## 3. How to explain the screen (top to bottom)

### 3.1 Smart Inventory Recommendations (header)

Plain-language intro. The meta line states:

- **As of** — latest dated record used as the cutoff (example: 27 August 2026)
- **History** — months used to train the series (example: January 2023 – August 2026)
- **Forecast** — the next three months after that cutoff (example: September – November 2026)
- How many office/category groups were scored

### 3.2 What to do now

One sentence for the whole municipality. Examples:

- Purchases needed: *“3 groups need purchasing action before September 2026.”*
- No buys, surplus instead: *“No stockouts expected. Priority is to avoid buying more for 79 overstocked groups.”*

### 3.3 Five recommendation cards

Each card has a **large highlighted number**, a short explanation, and (when there is work) **specific groups** with:

- **On hand** — current Active / Issued quantity
- **Needed / 3 mo** — model prediction for the next three months
- **Order** — suggested buy (if any)
- **Extra** or **Short** — surplus or gap
- A bar comparing need versus on-hand

Click a highlighted row to jump to that office and category in the table.

| Card | Highlight | How to explain it |
|---|---|---|
| **When to Reorder** | Count of groups to buy now | Replenish before the next forecast month |
| **How Much to Order** | Total suggested units | Uses the order formula below |
| **What May Run Out Soon** | Count at stockout risk | On-hand (+ incoming) is below next-month demand |
| **What May Become Overstocked** | Count + extra units | Hold new purchases; consider transfer instead of buying |
| **What to Prioritize** | First actions | Stockout → reorder → watch; or hold surplus if nothing is urgent |

### 3.4 Count tiles

Tap a tile to filter the detail table:

- May run out  
- Buy now (stockout + reorder)  
- Watch  
- Enough stock  
- Overstocked  

### 3.5 Demand so far vs next 3 months (chart)

Municipality-wide totals (all offices added together):

| Visual | Meaning |
|---|---|
| **Gray bars** | Recorded demand in the last 12 months |
| **Red line + pink fill** | ML forecast for the next 3 months |
| **Dashed blue line** | Simple 3-month moving average (baseline) |
| **Vertical mark “Forecast →”** | Last dated month; everything to the right is a projection |

Three callouts above the chart:

1. **Last recorded month** — units already in history (example: Aug 2026 = 216)  
2. **Next 3 months (forecast)** — sum of the three projected months  
3. **Versus last 3 recorded** — higher, lower, or about the same as the previous quarter  

Month labels use short names (`Aug 26`, `Sep 26`) so the time axis is readable.

### 3.6 Office and category detail (table)

Every group, with a one-sentence recommendation. Columns:

- Office  
- Category (and SE / PPE)  
- On hand now  
- Need next 3 months  
- Order this  
- Action (badge)  
- Recommendation (plain text)

Search and the status filter match the cards and tiles.

---

## 4. Where the numbers come from

### 4.1 As-of date

The series **stops on the latest dated record** in this database (acquisition date or issuance date), not “today” if today has no data.

History starts at **January 2023** (or later if the as-of date is earlier). The forecast is always the **next three calendar months** after the as-of month.

### 4.2 What counts as “demand” in a month

For each item, the system assigns a month, then adds quantity to that office and category:

1. **Exact acquisition date** when the day is known (not only 1 January of a year).  
2. **Year-only records** (Excel year, or property number starting with `19xx` / `20xx`, or an acquisition date of `YYYY-01-01`) are placed in **one month of that year**, never after the as-of month. That is required because most imported property cards have a year, not a true monthly time series.  
3. **Issuance records** add to the issue-date month.  
4. **Return records** subtract from the return-date month.  
5. Months with no events stay **zero** so the time axis is real (no invented trend).

Items that cannot be dated are **excluded from history** but still count in **on-hand** if they are Active or Issued.

### 4.3 Categories

Articles / item groups are folded into planning categories, for example:

IT Equipment · Furniture & Fixtures · Office Equipment & Tools · Communication Equipment · Agricultural Equipment · Medical Equipment · Infrastructure · Buildings & Structures · Land · Vehicles · Utilities & Plant · Disaster Response Equipment · Other PPE

### 4.4 On-hand and incoming

- **On-hand** = sum of `quantity` for items with status **Active** or **Issued** in that office and category.  
- **Incoming** = line quantities on purchase requests still in **quoting** or **po_issued**, mapped to a category from the line description.

---

## 5. The two models

Both models run on the monthly **net demand** series (office × category, and again on the municipality-wide total for the chart). Horizon = **3 months**.

### 5.1 Baseline — 3-month moving average (not ML)

Average of the last three observations. That average is then used as the next month; the window rolls forward for month 2 and month 3.

Use this when you explain “a simple, transparent forecast.”

### 5.2 Machine learning — gradient boosting (stumps)

A **small boosting model written in PHP** (same *family* as XGBoost, **not** Python XGBoost and not a separately trained file).

If a series has fewer than 8 points, the system falls back to the moving average.

**Features used for each month**

| Feature | Meaning |
|---|---|
| `t` | Time index |
| `month` | Calendar month 1–12 |
| `lag1` | Demand last month |
| `lag3` | Demand three months ago |
| `ma3` | Average of the last three months |

**How it fits**

- Builds training rows from the history.  
- Starts from the mean, then adds up to **16 decision stumps** (one-split trees).  
- Learning rate **0.2**.  
- Hold-out **MAE** (mean absolute error) is computed on the last 20% of training rows and shown as model MAE in the payload (not the main admin headline).

The **red line** on the chart and the **Need next 3 mo / Order this** columns use this boosting forecast. The dashed blue line is the moving-average baseline so you can compare.

**Accurate sentence for a panel or paper**

> The system uses a live monthly demand series from this inventory and a lightweight gradient-boosting ensemble (decision stumps) in PHP, with a 3-month moving average as baseline. It is operational planning ML, not a deployed XGBoost or cloud training pipeline.

---

## 6. Order quantity and status

### 6.1 Formula

```
Recommended order qty
  = Predicted demand (3 months, boosting)
  + Safety stock
  − Current inventory (on-hand)
  − Incoming orders (open PR lines)
```

Result is rounded and never negative.

**Safety stock** = at least 1, otherwise **1.25 × average of the last 3 months** of that group.

### 6.2 Status rules (action badge)

Let **available** = on-hand + incoming (category incoming).  
Let **reorder point** = next-month prediction + safety.

| Status | When it is assigned | What staff should do |
|---|---|---|
| **Stockout risk** | Available &lt; next-month prediction | Buy or transfer in immediately |
| **Reorder now** | Order qty &gt; 0 and available ≤ reorder point | Raise a PR this cycle |
| **Watch** | Order qty &gt; 0, or stock is near the reorder point | Prepare a PR if demand continues |
| **Overstock** | No order needed and stock is high versus demand (or demand is ~0 and stock &gt; 8) | Do not buy; consider transfer |
| **Sufficient** | Everything else | No purchase from this forecast |

These statuses drive the five cards, the tiles, and the table.

---

## 7. Current live reading (this database)

These figures change when you click **Refresh forecast** after encoding or importing.

| Item | Typical current values |
|---|---|
| As-of date | Latest dated record (e.g. 27 August 2026) |
| History window | January 2023 – August 2026 |
| Forecast window | September – November 2026 |
| Groups scored | About 90+ office × category series |
| Common result on a full physical-count load | Many **Overstock** / **Sufficient**, **order qty 0** |

That last point is expected. RPCSE / RPCPPE Excel files are a **physical-count snapshot**. On-hand is the full tagged inventory. Predicted “demand” is dated acquisitions and the few issuances on file — not years of monthly consumable issues. The honest recommendation is often **hold purchases**, not **buy more**.

As more **issuance dates** and completed PRs accumulate, buy / stockout cards will start to fill.

---

## 8. What this is not

Do **not** claim the following:

- That RPCSE / RPCPPE Excel tabs are ready-made monthly ML time series. They are a **point-in-time count**. The module *constructs* a monthly series from dates and property-number years.  
- That PPE worksheet tabs are departments. Tabs are **asset groups**; custody in this system is the assigned office.  
- That the red line is XGBoost, TensorFlow, or a cloud model.  
- That a recommendation is an approved purchase. It is **planning advice only**.  
- That year-only items have a true acquisition month. The month inside the year is assigned so the series can run; the **year** is what the record supports.

---

## 9. How to refresh and where the code lives

| Step | Where |
|---|---|
| Open the page | Admin → **Planning → Forecast** |
| Rebuild from live tables | **Refresh forecast** (needs edit permission) |
| CLI rebuild | `C:\xampp\php\php.exe scripts\seed-demo-forecast.php` (uses current Settings forecast mode) |
| Switch live vs demonstration | Admin **Settings → System → Forecast mode** (live is the working mode) |

| File | Role |
|---|---|
| `includes/demo_forecast.php` | History build, MA3, boosting, statuses, smart text |
| `api/forecast.php` | JSON API (`GET` load, `POST {action: rebuild}`) |
| `assets/js/forecast-module.js` | Cards, chart, table |
| `theme/index.php` | Forecast page markup |
| `demo_demand_history` / `demo_forecast_cache` | Stored monthly series and cached payload (`is_demo = 0` for live) |

Demonstration mode can still generate a synthetic 2023–2026 series for comparison. It is **not** what the live page uses when Settings = Live.

---

## 10. Suggested 3-minute explanation

1. **Need.** The LGU must plan purchases from inventory, not only from a printed physical count.  
2. **Input.** Live records: acquisition dates, property-number years, issuances, returns, current on-hand, open PRs. Cutoff = latest dated record.  
3. **Model.** Two forecasts for the next three months: a 3-month average (baseline) and gradient boosting (ML).  
4. **Output.** Plain-language cards: when to reorder, how much, what may run out, what is overstocked, what to do first.  
5. **Decision.** Staff still encode a PR. The module does not spend money.  
6. **Honesty.** On a full Excel load, many groups show overstock because stock is a snapshot. The value is a **consistent, repeatable recommendation** that will tighten as issuance history grows.

---

## 11. Related files

- Talking points (short): `docs/FORECASTING-TALKING-POINTS.md`  
- Earlier feasibility notes on Excel-only snapshots remain valid as background; they describe the *workbook*, not this live module.
