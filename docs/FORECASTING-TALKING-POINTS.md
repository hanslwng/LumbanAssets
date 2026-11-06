# Forecasting — talking points

Use this when presenting. Full detail: `docs/FORECASTING.md`.

---

## One sentence

The administrator Forecast page turns **live inventory dates** into a **3-month plan**: what to buy, how many units, what may run out, what is already too much, and what to do first.

---

## Where to click

Sign in as **administrator** → **Planning → Forecast**.  
Department users do not see this page.

---

## Say this while you scroll

1. **Header.** “As of the latest dated record in this database. History through that month. Next three months are projections, not PRs.”
2. **What to do now.** Read the one-line headline (buy vs hold).
3. **Four cards + priority.** Large numbers first. Click a highlighted office/category to open it in the table.
4. **Chart.** Gray bars = last 12 recorded months. Red = forecast. Dashed blue = simple average. Vertical mark = “history ends here.”
5. **Table.** Every office × category with a written recommendation.

---

## How the number is computed (whiteboard)

```
Order qty = Predicted 3-month demand + Safety − On-hand − Incoming PRs
```

- **Predicted demand:** gradient boosting (PHP stumps) — ML, not XGBoost.  
- **Baseline on the chart:** 3-month moving average.  
- **Safety:** 1.25 × last 3 months’ average (minimum 1).  
- **On-hand:** Active + Issued quantity.  
- **Incoming:** PR lines still quoting or with PO.

**Status:** Stockout risk → Reorder now → Watch → Sufficient → Overstock.

---

## Data (be precise)

| We use | We do not claim |
|---|---|
| Acquisition dates, property-number years, issuances, returns | That Excel RPCSE/RPCPPE is already a monthly ML dataset |
| Year-only items placed in a month of that year, up to the as-of date | That January 1 on a property card is a real January delivery |
| Current physical on-hand | That high stock means the model failed — a snapshot is often *overstock* vs thin monthly demand |

---

## If they ask “is it ML?”

Yes. One model is **gradient boosting** (16 shallow trees, features: time, calendar month, lag-1, lag-3, 3-month average).  
No. It is **not** Python XGBoost, not a neural net, and not an approved budget.

---

## If they ask “why is order qty 0?”

The Excel load is a **full physical count**. Predicted demand is dated movement, which is still thin. The correct recommendation is **do not buy more** until issuances accumulate. Refresh after encoding real issues.

---

## Close

“This is a planning aid. GSO still decides. The module makes the same recommendation every time from the same records, in language an admin can read.”
