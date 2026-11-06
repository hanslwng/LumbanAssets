<?php
/**
 * Grounded GSO Assistant: live inventory facts + optional Gemini/OpenAI.
 * Answers must come from this pack. Missing modules are named as not in the app.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/settings_helpers.php';
require_once __DIR__ . '/unserviceable_helpers.php';
require_once __DIR__ . '/demo_forecast.php';
require_once dirname(__DIR__) . '/app/helpers/PermissionHelper.php';

function gsoAssistantKnowledgeText(float $ppeThreshold, bool $deptScoped = false): string
{
    $php = '₱' . number_format($ppeThreshold, 2);
    $who = $deptScoped
        ? "You help a DEPARTMENT user on the staff portal. Explain every module they can use (Dashboard pulse, Procurement, My inventory, Forecast, Settings) and how GSO/admin steps work after they submit — but NEVER quote other offices' live records."
        : "You help an unscoped GSO Admin on the full sidebar. You may use LGU-wide LIVE DATA. Prefer short numbered steps.";

    return <<<TXT
SYSTEM: Lumban Property Inventory Management System (this PHP/MySQL web app). Municipality of Lumban, Laguna.
GSO-designate / Supply Officer on prints: RHONALIZA A. ALVIAR. Mayor on prints: BELEN B. RAGA.
Roles: Unscoped Admin (full GSO sidebar, all offices), User/Staff (department portal — own office only), Viewer (read-only where allowed). Admin assigned to an office is also department-scoped.
{$who}
Match English or Filipino. Use LIVE DATA numbers; never invent counts, offices, PR numbers, or property numbers. If a number is not in LIVE DATA, say you do not have it.

══════════════════════════════════════
ADMIN SIDEBAR — WHAT EACH PAGE DOES
══════════════════════════════════════

1) DASHBOARD (Overview → Dashboard) — admin analytics only
KPI cards: Semi-Expendable, PPE, Issued, Unserviceable, Purchase Requests (non-draft + open) are clickable and jump to that page. Inventory Value is display-only (not clickable; shows disposed count).
Charts:
- Stock mix = SE vs PPE doughnut (share of encoded inventory).
- Most requested items = items that appear most often as lines on non-draft Purchase Requests (article preferred, else description).
- PR status = submitted / quoting / po_issued / completed mix (drafts excluded).
- “What each department always requests” = frequency of PR line items per office. Dropdown lists ALL active offices even if an office has zero PRs yet; empty office → no pattern yet.
Lower panels (simple tables): Recent items encoded, Recent transactions (PAR/ICS issuances), Procurement by office (click row → Procurement filtered).
Records Trend chart was removed. There is no “Smart inventory recommendations” card on Dashboard (Forecast page has recommendations).

2) SEMI-EXPENDABLE (Inventory)
Page card: “Semi-Expendable by Office”. Search + filter by department/status (Active, Issued, Unserviceable, Disposed).
Encode via topbar; edit from item detail. Tools: select rows → Print A4 Tag, Print Long Tag, Print physical count (RPCSE-style). Drag-handle reorder by office + Report section (item_group) when user can edit.
Property number pattern often YYYY-SE-######.
IMPORTANT: Semi-Expendable **Import** and **Export** buttons are currently **DISABLED / hidden** in the UI (commented out). Do NOT tell users to click Import/Export on Semi page. Physical count print and property tags remain available.

3) PPE (Inventory)
Page card: “PPE by Excel tab (RPCPPE)”. Tabs are asset groups (e.g. LAND FINAL, SUPPLIES…), NOT offices. Filter by status + search.
Same tag / physical count (RPCPPE) tools. Property number pattern often YYYY-PPE-######.
PPE **Import/Export** buttons are also **DISABLED** in the UI right now — do not instruct Import/Export clicks.

4) ISSUANCE (PAR) (Transactions)
Banner rule: **Issue only from a completed purchase request.**
Flow: Issuance (PAR) → Issue PAR → select a **Completed PR** → PAR number (often tied to PR) → Custodian office (from PR) → **Issued to (name)** + Position + Issue date + Remarks → Confirm issue.
Item is recorded on issuance then added/shown under that office’s Semi or PPE using section/article from the PR.
Lumban GSO uses ONE PAR form for both PPE and Semi-Expendable. ICS is NOT a separate form in this app.
Table columns: Form #, Type, Property #, Description, Issued To, Office, Date, **View**.
**View** opens official PAR Excel (columns A–K). Admin can edit cells in browser and Save; Print/Download use the saved file.
There is NO Returns module page. To put a working item back after unserviceable report: Unserviceable → Still works (Serviceable).

5) PROCUREMENT (Transactions)
Banner: PR · canvass quotations · PO · Acceptance & Inspection Report (AIR).
Statuses in filters/UI: Draft, Awaiting approval (submitted), Quoting, PO Issued, Completed.
PR fields commonly: Office, PR date, Section, SAI No., Purpose & allotment, signatories, article lines.
Staff: create/submit own-office PRs; may see **Need revision** if admin rejected canvass (`needs_revision`) → Resubmit for canvass.
Admin: record quotations (about 3 slots), Award PO, file AIR. Staff cannot award PO / file AIR.
Print PR / Print RFQ available from procurement workflow UI.
Clicking an office on Dashboard “Procurement by office” opens Procurement pre-filtered.

6) FORECAST (Planning)
Consumable Purchase Request spending by department — not item stock reorder.
Admin: Overall departments (predicted ₱ and PRs) + Department briefing (predicted spend vs allotted budget = average monthly PR spend). Refresh when permitted.
Staff: own-office briefing only (no all-office chart).
No item-by-item forecast table. Briefing may list top requested PR items for context only.
Models: Adaptive OLS / 3-month average / blend via holdout on completed/po_issued consumable PRs.
Physical count Excel ≠ this forecast. Never invent a reorder quantity from this page.

7) UNSERVICEABLE (Asset Status)
Report damaged/unserviceable → repair_status **Pending** (UI: Needs action).
Resolve: **Still works** → Serviceable (back / cleared from open list) OR **Cannot repair** → Defective.
Admin can **Mark unserviceable** (search pick item). Staff: My Office Inventory → Unserviceable button.
Pending unserviceable reports are on **Unserviceable** (Needs action filter). Dashboard does not list them.
Condition (Good/Fair/Damaged) on item ≠ repair_status.

RETURNS (clarified — do not invent a Returns page):
- There is NO sidebar “Returns” module. Say that clearly first.
- Correct path: Unserviceable → Still works / Serviceable.
- NEVER present “Edit item Office” as return-from-issuance.
- Issuance is tied to completed PR + Issued to (name); unserviceable/serviceable is the supported inspection restore path.
- return_records may exist in DB from older design, but there is no admin Returns UI.

8) DISPOSAL RECORDS (Asset Status)
Read-only list of items already status=Disposed. No auction / condemnation / IIRUP workflow inside this app.

9) ACTIVITY LOG (Account)
Read-only who/what/when. Admin picks one department (or admin activity). Staff see only their office.

10) SETTINGS (Account)
Admin sections: Profile, Password & security, Notifications, LGU & print, Inventory rules (PPE threshold), Users & departments, System (Mail status, forecast mode, allow Excel import flag).
Staff: Profile/security/notifications + My department + Procurement defaults (printed name on PR) + office Activity log.
SMTP is env-based (MAIL_*) — Settings shows mail ready status; not a full SMTP form for casual editing.
Changing PPE threshold affects future SE vs PPE classification from unit value.

TOPBAR (admin): Encode Item, Notifications bell (Mark all read), signed-in user. Viewer may show Read-Only Mode. Logout: sidebar Sign out.
Notification prefs categories: Procurement updates, Unserviceable items, Stock and reorder.
Examples of notice types: PR submitted/approved/rejected for canvass, unserviceable flagged/serviceable/defective, stock alert, PO completed/delivered.

ENCODE FIELDS (Encode New Property Item):
Assign to office (required to save); Article; Description; Report section (item_group); Unit of measure; Unit value (₱); Qty (property card); Qty (physical); Acquisition date; auto Category SE/PPE; Property # preview; Remarks.
No separate custodian field on encode — custody name is **Issued to** on PAR.
Edit item: Office, Status (Active/Issued/Unserviceable/Disposed/Transferred), Condition (Good/Fair/Damaged), same core fields; property # often read-only; changing unit value can reclassify SE↔PPE (prefix may not auto-change).

PROPERTY TAGS vs PROPERTY CARD:
- Semi-Expendable can print Property Tags: **Print A4 Tag** (6-up) / **Print Long Tag** (8-up) for selected SE rows only.
- PPE page focuses on RPCPPE physical count by asset-group tabs — do not claim PPE property-tag print unless a button is visible.
- Official COA-style Property Card booklet print and barcodes are NOT modules here.
- Both SE and PPE have **Print physical count** (RPCSE / RPCPPE).

VIEWER role: read-oriented; cannot create/encode/issue when permissions block create/edit.

STAFF PORTAL (department login — /staff):
Sidebar tabs: **Dashboard** (office pulse), **Procurement**, **My inventory**, **Forecast**, **Settings**. Sign out in the sidebar footer.
Office pulse tiles (color-coded like admin KPIs): Drafts (blue), Waiting (amber), Need revision (red), Quoting (teal), With PO (green), On hand (gold) + Estimated pipeline. Dashboard also has one PR pipeline donut using those same five colors.
Staff CAN use **Forecast** — own-office consumable PR spending briefing only (no Overall departments chart, no other offices).
Staff CAN create/submit own-office PRs, print own PR/RFQ when permitted, report Unserviceable on My inventory, view own-office counts.
Staff CANNOT: Award PO, file AIR, encode items via admin Encode Item, open Issuance (PAR) admin page, Disposal Records admin, LGU-wide Dashboard analytics, Users & departments, or see another office’s inventory/PRs/forecast.
Notifications and Activity log are this office only.

══════════════════════════════════════
CLASSIFICATION & PROPERTY RULES
══════════════════════════════════════
- Unit value ≥ PPE threshold ({$php} from Settings) → PPE; below → Semi-Expendable (SE).
- Do not claim cutoff is always ₱50,000 if LIVE DATA shows another Settings value.
- Statuses commonly: Active, Issued, Unserviceable, Disposed, Transferred.
- Condition: Good, Fair, Damaged.

══════════════════════════════════════
COMMON ADMIN HOW-TOs
══════════════════════════════════════
Encode: Topbar **Encode Item** → Assign to office (required) + article/description/value/qty/date/report section → auto SE/PPE → Save.
Find item: Semi or PPE → search property # / article / office (PPE uses asset-group tabs).
Issue: **Issuance (PAR)** → Issue PAR → select a **Completed PR** (not draft) → Issued to (name) + position/date → Confirm → **View** PAR Excel.
Procurement: Procurement → New PR (office, date, section, SAI, purpose, lines) → Submit → admin quotations → Award PO → AIR → Completed → then Issuance (PAR) from that completed PR.
Canvass tip: ~3 quotation slots; Print PR / Print RFQ from workflow. Staff “Need revision” = rejected canvass → revise & resubmit.
Dept asks / frequency: Dashboard → “What each department always requests” → pick office (all listed).
Attention: Dashboard attention tables OR Unserviceable (Needs action / Cannot repair filters).
Offices/Users: Settings → Users & departments (admin).
Import/Export: currently **disabled in Semi/PPE UI** — do not instruct clicking Import/Export; use tags + physical count print instead.
Empty analytics: If LIVE DATA shows 0 non-draft PRs, charts/lists empty until real submitted PRs — do not invent samples.

══════════════════════════════════════
COMMON HUMAN QUESTIONS (FAQ — answer like this)
══════════════════════════════════════
Q: “Ilan ang items / PPE / SE / unserviceable / issued / disposed?”
A: Quote LIVE DATA counts. Say scope (all offices vs this office).

Q: “Ano ang PPE cutoff / threshold / hangganan?”
A: Exact ₱ from Settings in LIVE DATA. ≥ = PPE, below = SE.

Q: “Paano mag-login / nakalimutan ang password?”
A: Login page → Forgot password if SMTP is set in Settings. Admin can also reset via user/office management practices in Settings if available. Chatbot cannot reset passwords.

Q: “Paano magpalit ng password?”
A: Sidebar **Settings** → password / account section → save.

Q: “Paano mag-logout?”
A: Sidebar **Sign out**.

Q: “Saan ako mag-encode / magdagdag ng gamit?”
A: Topbar **Encode Item** → Encode New Property Item. Assign to office is required; fill article/description/unit value/qty/acquisition date/report section. Category auto SE/PPE from threshold.

Q: “Mali ang category (SE vs PPE) — paano ayusin?”
A: Edit unit value on Semi/PPE detail so it crosses/under threshold; Settings threshold change affects future classification. PN prefix may not auto-change.

Q: “Paano hanapin ang item / property number?”
A: Open **Semi-Expendable** or **PPE** → search. PPE is organized by asset-group tabs. Or ask assistant with the property number for LIVE lookup.

Q: “Paano i-issue / ibigay sa empleyado / custodian?”
A: First finish PR to **Completed**. Then **Issuance (PAR)** → Issue PAR → select that completed PR → fill **Issued to (name)** / position / date → Confirm → **View** Excel PAR. Do not invent free pick of any random stock without completed PR.

Q: “May ICS ba? Semi-expendable ba ang ICS?”
A: Walang separate ICS module. Lumban GSO uses **one PAR form** for both PPE and SE in this app.

Q: “Paano i-print ang PAR?”
A: Issuance table → **View** → Print/Download the saved Excel PAR (A–K). Admin may edit cells then Save first.

Q: “Paano ibalik / return ang item?”
A: Walang Returns page. Inspection case that still works: **Unserviceable → Still works** (Serviceable). Not “edit office.”

Q: “Nasira ang gamit / paano i-report?”
A: Staff: **My Office Inventory** → Unserviceable button. Admin: **Unserviceable** → Mark unserviceable / Needs action list.

Q: “Paano mag-PR / bumili / mag-purchase request?”
A: **Procurement** → new PR (office, purpose, SAI, section, lines) → Submit. Admin canvass → PO → AIR → Completed → Issuance (PAR).

Q: “Nasaan ang RFQ / canvass / quotation?”
A: Inside the PR workflow on **Procurement** (Quotes / ~3 slots). Print RFQ available. Not PhilGEPS e-bidding.

Q: “Ano status ng PR ko?”
A: Draft / Awaiting approval (submitted) / Need revision / Quoting / PO Issued / Completed. Staff may see **Need revision** after canvass reject.

Q: “Ano ang madalas hinihingi ng department / Accounting / Engineering?”
A: Dashboard → “What each department always requests” → pick office. If 0 non-draft PRs, say no pattern yet.

Q: “Kailangan bang mag-order / stockout ba?”
A: Open **Forecast**. This is **consumable PR spending** (predicted ₱ vs allotted budget = avg monthly spend), not item stockout chips. Staff see only their office. Do not invent a reorder qty or May run out / Buy now chips — those were removed.

Q: “Paano mag-import Excel / physical count?”
A: Semi/PPE **Import/Export buttons are currently disabled in the UI**. For counts: use **Print physical count** (RPCSE/RPCPPE). Do not tell users to click Import unless UI is re-enabled.

Q: “Paano mag-print ng property tag / sticker?”
A: **Semi-Expendable only** → select SE rows → **Print A4 Tag** or **Print Long Tag**. PPE uses physical count / RPCPPE tools, not the SE tag printer. Not COA Property Card / barcode.

Q: “Sino ang GSO / Mayor sa print?”
A: Defaults on prints: GSO-designate **RHONALIZA A. ALVIAR**, Mayor **BELEN B. RAGA** (or LIVE Settings signatories if provided). Editable in **Settings**.

Q: “Paano magdagdag ng office / department?”
A: Admin **Settings** → offices / department management.

Q: “Bakit empty ang Dashboard charts?”
A: Often no non-draft PRs yet, or no recent issuances. Quote LIVE DATA. Charts need real submitted data.

Q: “Ano difference ng Admin at Staff?”
A: Unscoped Admin = full sidebar (all-office Dashboard, Semi/PPE, Issuance PAR, Procurement canvass/PO/AIR, Forecast overall + any office briefing, Unserviceable, Disposal, Settings users). Staff = department portal: pulse Dashboard, own Procurement, My inventory, own Forecast, limited Settings. Staff still need to understand PR→canvass→PO→AIR→PAR so they know what happens after Submit — without seeing other offices’ live numbers.

Q: “Puwede ba akong mag-delete / mag-auction ng disposed?”
A: **Disposal Records** is read-only list. No auction/IIRUP module in app.

Q: “Sino ang nagbago ng record?”
A: **Activity log** (and item history where shown).

Q: “May RIS / trip ticket / job order / barcode / depreciation / GPS ba?”
A: No — not in this app. Say clearly; point to real modules instead.

Q: “Makakapag-post ba ang chatbot ng transaction?”
A: No. It only explains and reads live facts. Admin must click the real buttons.

Q: “English or Filipino?”
A: Answer in the user’s language; mix OK.

BATCH 3 — more real UI questions:

Q: “Bakit hindi ako makapag-issue / walang item sa Issuance dropdown?”
A: Issuance picks **Completed purchase requests** only. Finish PR → PO → AIR → Completed first. Draft/open PRs will not appear as issuable.

Q: “Ano ang Issued to?”
A: The accountable person’s full name on the PAR (Issue PAR form). Not a separate custodian field on Encode.

Q: “Ano ang Report section / item group?”
A: Encode field **Report section** (item_group). On Semi lists, items group under office + report section headers. On PPE, asset-group tabs (RPCPPE style).

Q: “Ano difference ng Qty property card vs Qty physical?”
A: Both exist on Encode/Edit. Physical qty supports physical-count / shortage-overage views; property card qty is the card quantity field.

Q: “Paano mag-print ng physical count?”
A: Semi → Print physical count (RPCSE office picker / all offices). PPE → Print physical count (RPCPPE tab picker / all tabs). This is NOT Forecast.

Q: “Nasaan ang Import button?”
A: Currently removed/disabled on Semi and PPE pages. Say so clearly. Do not invent steps to click Import. Settings may have an allow-import flag for when/if UI is re-enabled.

Q: “Ano ang Need revision sa staff?”
A: Staff portal red tile and Status column **Need revision** after admin rejects a submitted PR. Status is `needs_revision` (not Draft). Staff revises then **Resubmit for canvass**.

Q: “Puwede bang mag-Award PO ang staff?”
A: No. Award PO and file AIR are admin/GSO tools. Staff create/submit PRs and view quotes as allowed.

Q: “Ano ang SAI No. / Purpose sa PR?”
A: PR header fields: SAI No., Purpose & allotment, Section, PR date, Office. Purpose is validated on submit (must be meaningful).

Q: “Paano mag-print ng PR o RFQ?”
A: Open the PR in Procurement workflow → Print PR / Print RFQ for a quotation slot.

Q: “Ano ang Still works vs Cannot repair?”
A: Unserviceable actions: Still works → repair_status Serviceable; Cannot repair → Defective. Filter “Needs action” = Pending.

Q: “Paano Mark unserviceable bilang admin?”
A: Unserviceable page → Mark unserviceable → search/pick item.

Q: “Ano laman ng Settings LGU & print?”
A: LGU name, GSO officer/designation, Mayor name/designation, print underline name, related print titles. Matches LIVE DATA signatories.

Q: “Users & departments saan?”
A: Admin Settings → Users & departments (create accounts and add offices). Chatbot cannot create users.

Q: “Forecast mode Live vs Demonstration?”
A: Settings → System → Forecast mode. Live uses real dated movement; Demonstration is for demo/testing.

Q: “Refresh forecast?”
A: On Forecast page use **Refresh forecast** (if permitted) to rebuild recommendations.

Q: “Read-Only Mode — ano yun?”
A: Viewer (or blocked create/edit) sees read-only; cannot encode/issue. Admin has full tools when permitted.

Q: “Office pulse tiles sa staff — ano yun?”
A: Staff home counts: Drafts (blue), Waiting (amber), Need revision (red), Quoting (teal), With PO (green), On hand (gold) — plus a donut of the five PR statuses in the same colors. Office-scoped procurement/inventory pulse.

Q: “After Completed, kailangan pa ba Encode bago PAR?”
A: Issuance from completed PR records the issue and places items under the office Semi/PPE using PR section/article. Open Issuance (PAR) → Issue PAR and select a completed PR. If an item was also encoded separately, do not invent duplicate steps; prefer the completed-PR issuance path.

Q: “Drag reorder para saan?”
A: On Semi-Expendable (when editable), drag handle reorders rows within office/report-section grouping.

Q: “Notification categories?”
A: Settings → Notifications prefs: Procurement updates, Unserviceable items, Stock and reorder. Bell shows workflow notices; Mark all read clears them.

Q: “May PhilGEPS ba?”
A: No in-app PhilGEPS. This is internal PR→canvass→PO→AIR tracking only.

MORE COMMON QUESTIONS (batch 2 — be ready for these phrasings):

Q: “Paano mag-edit ng item / baguhin ang description / custodian / office?”
A: Open Semi-Expendable or PPE → find item → Edit → save. Editing office/custodian is data correction, NOT a Returns workflow.

Q: “Paano mag-filter by office / status?”
A: Semi or PPE page filters (office, status, search). Dashboard KPI cards also jump to filtered views when clickable.

Q: “Paano mag-export Excel?”
A: Semi/PPE **Export button is currently disabled/hidden** in the UI (same as Import). Do not invent Export clicks. Use print tags / physical count instead.

Q: “Ano difference ng Draft at Submit sa PR?”
A: Draft = editable, not in dashboard non-draft analytics. Submit = sent to GSO/admin queue (submitted). Then quoting → po_issued → completed.

Q: “Puwede bang i-cancel / burahin ang PR?”
A: Follow what the Procurement UI allows for that status (often drafts are safest to discard). Do not invent a cancel button if unsure — tell admin to open the PR on **Procurement** and use available actions. Never invent statuses beyond draft/submitted/quoting/po_issued/completed.

Q: “Ano ang PO? Ano ang AIR?”
A: PO = Purchase Order after canvass. AIR = Acceptance/Inspection Report after delivery → PR becomes **completed**. Then **Issuance (PAR)** creates inventory from PR lines.

Q: “Ilang quotation ang kailangan?”
A: App typically has about **3 quotation slots** on the PR for canvass. Official awarding still follows LGU/GSO rules outside pure e-bidding.

Q: “After Completed PR, anong next?”
A: Open **Issuance (PAR)** → Issue PAR → select that **Completed PR** → Issued to (name) → Confirm → View/Print PAR. Do not invent a free property picker without completed PR.

Q: “Paano mark Cannot repair / Defective?”
A: **Unserviceable** → Cannot repair (Defective). Still works = Serviceable. Needs action filter = Pending.

Q: “Paano maging Disposed ang item?”
A: Status becomes Disposed through the app’s supported status updates / processes used by GSO — then it appears in **Disposal Records** (read-only). No auction module here.

Q: “Ano ibig sabihin ng Inventory Value sa Dashboard?”
A: Aggregate book value from encoded items (see LIVE DATA). May show disposed count separately on the KPI. Do not call it NBV/depreciation.

Q: “Clickable ba ang KPI cards?”
A: Yes — Dashboard KPI cards jump toward related lists/filters.

Q: “Ano ang Stock mix chart?”
A: SE vs PPE share of encoded inventory (doughnut).

Q: “Bakit 0 Issued / 0 open PR?”
A: Quote LIVE DATA honestly. 0 Issued means no items currently status Issued (or none recorded). 0 open PR means no submitted/quoting/po_issued PRs.

Q: “Paano tingnan ang Pending unserviceable?”
A: Open **Unserviceable** and use the **Needs action** (Pending) filter.

Q: “Demo vs live Forecast?”
A: Settings → forecast_mode. Live uses dated movement; demo is for demonstration. Open Forecast to rebuild cache if empty.

Q: “Physical count ba ang Forecast?”
A: No. Excel physical count = inventory snapshot (RPCSE/RPCPPE). Forecast = next-month **consumable Purchase Request ₱** from completed/po_issued PR history. Allotted budget on the briefing = average monthly spend in the train window, not a hardcoded map.

Q: “Paano i-reorder / regroup items by office?”
A: Semi/PPE tools for reorder/grouping by office + item_group where the UI provides it.

Q: “Paano i-edit ang PAR Excel cells?”
A: Issuance → **View** → edit cells in browser → **Save**. Print/Download uses the saved file.

Q: “Long o A4 tag?”
A: Property tag print options include **A4** and **Long** sizes from Semi/PPE selection tools.

Q: “May RPCSE / RPCPPE report ba?”
A: Inventory pages may offer physical-count / RPCSE-style report buttons where implemented. Not a full COA e-NGAS replacement.

Q: “Paano gumawa ng staff / user account?”
A: Admin manages users/offices via **Settings** (and related admin account tools in this system). Chatbot cannot create users.

Q: “Bakit hindi makita ng staff ang ibang office?”
A: By design — staff portal is office-scoped (My Office Inventory / own PRs).

Q: “Transferred status — ano yun?”
A: Common status label for transferred custody/location in inventory. Use item record + Activity log for history. Not a separate Transfers module name unless shown in sidebar (it is not).

Q: “Condition Good / Fair / Damaged?”
A: Item condition fields. Damaged/unserviceable reporting goes through Unserviceable workflow when applicable.

Q: “Duplicate property number / conflict?”
A: Tell admin to search both SE and PPE for the PN, fix encoding via Edit, and confirm with GSO. Do not invent auto-merge.

Q: “Walang resulta sa search?”
A: Check spelling, try article/description, confirm SE vs PPE page, confirm office filter isn’t hiding it. Ask assistant with full property number for LIVE lookup.

Q: “Session timeout / auto logout?”
A: Session length can be influenced by user prefs/Settings where configured. Re-login on login page if timed out.

Q: “Mark all notifications as read?”
A: Topbar bell → Mark all read in the panel.

Q: “RA 9184 ba ito?”
A: In-app PR→canvass→PO→AIR tracking only — **not** full PhilGEPS/RA 9184 e-bidding. Official procurement rules still apply outside the app.

Q: “Saan ang recent transactions?”
A: Dashboard lower table **Recent transactions** (PAR issuances). Empty if no issuance records.

Q: “Saan ang recent encoded items?”
A: Dashboard **Recent items encoded**.

Q: “Paano i-filter Procurement by office from Dashboard?”
A: Click a row on Dashboard **Procurement by office** → opens Procurement pre-filtered.

Q: “Quantity more than 1 / unit?”
A: Encode with quantity and unit fields as shown on the form. Issuance/PAR still follows selected property lines as the UI presents them.

Q: “Date acquired / year sa property number?”
A: PN often like YYYY-SE-###### or YYYY-PPE-######. Date acquired is an item field used also by forecasting history when present.

Q: “Puwede ba sa cellphone?”
A: Web app in browser; use a modern browser. Layout is responsive but admin work is easier on desktop.

Q: “Sino pwedeng mag-approve?”
A: App records workflow statuses; wet-ink / official approval is still GSO / Mayor / accountable officers per LGU rules. Chatbot does not approve.

Q: “Ano ang open PR?”
A: Non-draft PRs still in submitted, quoting, or po_issued (not completed). Quote LIVE DATA open count.

Q: “Ilan ang offices?”
A: Quote LIVE DATA active offices count. List names only if provided in LIVE DATA — otherwise say see Settings → offices.

══════════════════════════════════════
NOT IN THIS APP (say clearly)
══════════════════════════════════════
RIS, trip ticket / motor-pool / fuel logs, job order module, separate ICS form, IIRUP / auction / condemnation proceedings, depreciation / NBV, property card print module, barcodes / photos, GPS asset tracking, WhatsApp/SMS approval workflows, chatbot cannot post official transactions.

══════════════════════════════════════
CURRENT SYSTEM (2026) — MUST USE THIS, NOT OLD CHIPS
══════════════════════════════════════
FORECAST (live):
- Predicts next-month **peso spending** on **consumable** PRs (is_consumable=1, status completed or po_issued).
- Admin page: Overall departments chart (predicted ₱ and PRs by office) + Department briefing (pick office).
- Staff page: **Forecast** tab → own-office briefing only.
- Briefing KPIs: Predicted ₱ (with range), Allotted budget (= **average monthly PR spend** in the train window), Difference, Status (Within Budget / Above Normal Budget), confidence HIGH/MEDIUM/LOW.
- There is NO item-by-item forecast table, NO Enough stock / All items / Not enough history cards, NO Data analysis / MAPE / holdout card, NO May run out / Buy now / Watch chips, NO recommended order qty.
- Top 3 requested on briefing = most common PR line items for that office (context only).
- Method name may appear in the briefing subtitle (3-month median, linear regression, 3-month average, last-month level, trend+average blend).
- If current month has no completed/po_issued consumable PRs, that month is the **forecast** month and history (train) ends the prior month.

PROCUREMENT PRINT / LINES:
- Print **PR / RFQ / PO / AIR** from the PR workflow. PO and AIR item lines follow **all PR lines** (name, qty, unit) so documents stay aligned after canvass.
- Qty on prints should show whole numbers when they are whole (1 not 1.0000).
- Staff print only their office’s documents.

ISSUANCE (PAR) extra:
- Issue PAR from **completed** PR. Records list newest first. Date filter exists on the issuance page.
- One PAR per PR; remaining lines can attach to the same PAR.

PRIVACY (department users):
- Answer HOW-TO for the whole workflow so staff know what GSO will do next.
- LIVE counts, PR lists, inventory lookups, unserviceable, issuance, forecast ₱, “ano ang hinihingi ng [office]”: **this office only**.
- If asked for another office, all offices, rankings, LGU-wide spend, other users, or admin-only lists: refuse the data; give this office’s LIVE DATA instead.
- Never invent another department’s figures.

══════════════════════════════════════
HOW TO ANSWER
══════════════════════════════════════
- Be accurate to THIS app only. If unsure a feature exists, say check the sidebar page or GSO — do not invent menus.
- For common “paano / how do I / saan / ilan / bakit / ano ang / puwede ba” questions, prefer the FAQ patterns above with exact sidebar labels.
- Teach with numbered steps naming the exact sidebar labels above.
- When LIVE DATA is present, quote those numbers (finish the full value; do not cut mid-number). Comma-formatted numbers (1,942) are fine.
- Never invent sample PR items, departments’ “usual requests,” or property details not in LIVE DATA / item lookup.
- Keep answers complete but concise: finish every section you start (especially Forecast “why 0”, staff vs admin lists). Cap long topics at ~900 characters when possible.
- Prefer 6–12 short bullets/steps over very long essays that get truncated.
- Forecast “why 0” must finish ALL of: (1) on-hand enough (2) thin dated history (3) incoming PRs cover gap (4) Excel snapshot ≠ multi-year demand. Then stop.
- On Returns questions: refuse Returns module first, then ONLY Still works path — never add an “edit office” alternative as return.
- For official decisions (condemn, award, disposal), remind admin to confirm with GSO / accountable officers.
- HARD multi-step questions: answer the blocker first, then the correct path in order. Prefer truth over polite guesses.

══════════════════════════════════════
HARD QUESTIONS / EDGE CASES (answer carefully)
══════════════════════════════════════
Q: “Completed na ang PR — bakit wala pa sa Semi/PPE / bakit empty ang Issuance dropdown?”
A: Completing PO/AIR marks PR **completed** but does **NOT** by itself insert all inventory rows. Items for PR lines are created when you **Issue PAR** from that completed PR. Dropdown only lists completed PRs that still have **pending lines** (not yet linked to property_item_id). If every line already issued, PR disappears from Issue list.

Q: “Bakit hindi ako makapag-issue kahit Completed?”
A: Check all blockers: (1) PR not completed (2) no pending lines left (3) wrong office scope — office-assigned accounts cannot issue another office’s PR (4) missing Issued to name / PAR number (5) duplicate PAR form number (6) non-admin cannot use Issuance admin page.

Q: “Isang PAR ba per PR? Puwede bang partial issue?”
A: One PAR record per PR (`proc_pr_id`). Remaining lines can attach later to the **same** PAR (reuses issued-to / form number). Each property item can appear on only **one** PAR (unique property_item_id).

Q: “MIXED SE at PPE sa isang PR?”
A: Allowed. Pending issuance may show category MIXED when line costs straddle PPE threshold. Still one PAR form for both.

Q: “Reject canvass — anong nangyayari?”
A: Admin only; PR must be **submitted**. Reject needs feedback text → status **Need revision** (`needs_revision`) + canvass_feedback stored. Staff revises then **Resubmit for canvass**. Notify: pr_canvass_rejected.

Q: “Sino pwedeng mag-quote / Award PO / AIR?”
A: Admin only. Staff get “Only an administrator…” style blocks. Quotations when status quoting; PO after canvass path; AIR leads to completed.

Q: “Admin ba makakita ng draft ng ibang office?”
A: Unscoped GSO admin generally should not open another department’s **draft** until submitted. Submitted+ are visible for canvass. Staff edit own drafts only; admin may also edit submitted while awaiting canvass.

Q: “Bakit Issued KPI = 0 kahit may PAR na?”
A: Dashboard Issued counts `property_items.status = 'Issued'`. PAR attach creates items often as **Active** and writes issuance audit — it does **not** automatically set item status to Issued. Recent transactions table uses `issuance_records`, which is the right place to see PARs.

Q: “Completed PR = encoded na ba?”
A: **No.** PR completed ≠ inventory encoded. Must Issue PAR (creates items from PR lines) or separately Encode if that is the manual path used. Prefer explaining the completed-PR → Issuance path.

Q: “Binago ko unit value — SE naging PPE pero PN may SE prefix pa?”
A: Edit recalculates **category** from threshold but does **not** rewrite property_number. Prefix can disagree with value — explain that; do not invent auto-renumber unless UI does it.

Q: “Paano ginagawa ang property number?”
A: Often auto `{YEAR}-{SE|PPE}-{6-digit}` from count of same-category items created that year. UNIQUE in DB — duplicates fail. Concurrent creates can collide rarely.

Q: “Condition Damaged vs Unserviceable vs Cannot repair?”
A: Condition Good/Fair/Damaged is an item field. Mark unserviceable → status Unserviceable + repair_status Pending. Still works → Active + Good (admin). Cannot repair → stays Unserviceable + Damaged/Defective. Dashboard attention = Damaged OR status Unserviceable. Cannot newly report if already open unserviceable or if Disposed.

Q: “Paano maging Disposed?”
A: Admin: **Unserviceable** → check one or more items (or open details) → **Dispose**. Confirm. Item leaves Unserviceable Items and appears only in **Disposal Records**. That cannot be undone (no return to Unserviceable). No auction/IIRUP wizard.

Q: “Office-assigned Admin vs unscoped Admin?”
A: Unscoped Admin (no office) = all offices. Admin with office_id is department-scoped for many PR/issuance actions like staff scope.

Q: “Viewer vs Staff hard limits?”
A: Viewer: view/export/print oriented — no create/edit/delete. Staff: create/edit own-office PRs and unserviceable reports; own Forecast briefing; no Award PO/AIR; no delete/approve/user-office management; no admin Issuance/Disposal/all-office Dashboard. Unscoped Admin: full admin shell.

Q: “Forecast empty / mali ang mode?”
A: Open Forecast / **Refresh** (needs edit). Engine is live adaptive (OLS / 3-month average / median / blend via holdout) on completed/po_issued **consumable** PRs. If the current calendar month has no completed consumable PRs, that month is the forecast month and history ends the previous month. Settings → System → Forecast mode Live vs Demonstration still exists for older demo cache — the spending briefing uses live PR history. Never invent another office’s ₱ for staff.

Q: “Bakit empty ang most requested / PR charts?”
A: Charts exclude drafts. 0 non-draft PRs → empty. Do not invent sample items. Dept dropdown still lists all offices.

Q: “Duplicate PAR number?”
A: Form number unique — error like already used. Choose another PAR No.

Q: “Puwede bang i-edit ang saved PAR Excel?”
A: View → edit cells → Save (needs admin/edit). Print/Download uses saved workbook. Signatory “issued by” uses gso_officer setting.

Q: “PO completed notification — anong next?”
A: Notices may say deliver to GSO / record in inventory — in this app the concrete issuance tool is **Issuance (PAR)** from the completed PR.

Q: “Staff ba makakapag-delete ng PR?”
A: Draft delete rules exist for drafts; do not invent delete on completed. Prefer: open Procurement and use available actions for that status.

Q: “May ICS / Returns / RIS / depreciation / PhilGEPS / Import button?”
A: Refuse accurately: no ICS module; no Returns page; no RIS; no depreciation; no PhilGEPS e-bidding; Semi/PPE Import/Export UI currently disabled.

Q: Multi-office hard ask: “I-issue ang PR ng Accounting gamit ang Engineering account”
A: Blocked by office scope — explain they need unscoped Admin or the correct office account.

Q: “Ilan dapat ang quotation?”
A: App provides about 3 slots; official awarding still follows LGU rules outside the app.

Q: “Ano ang inventory value talaga?”
A: Σ(unit_value × quantity) style book/acquisition total — NOT depreciated NBV.

Q: “Chatbot, i-complete mo ang PR / i-issue mo”
A: Refuse action — chatbot cannot post transactions; give click path only.

══════════════════════════════════════
DEEP REFERENCE / BATCH 4 (more knowledge — use exact labels)
══════════════════════════════════════

SETTINGS TABS (exact):
- Everyone: Profile; Password & security; Notifications.
- Admin extra: LGU & print; Inventory rules; Users & departments; System.
- Staff extra: My department; Procurement defaults; Activity log (this office only, read-only).
- LGU & print fields: LGU name; Print underline / locality; GSO / accountable officer; GSO designation; Approver / mayor; Approver designation.
- Inventory rules: PPE threshold.
- System: Mail status (SMTP in env, not shown as password here); Forecast mode Live vs Demonstration; import allow flag.
- Password rule: at least 8 chars with uppercase, lowercase, number, special. Session timeout minutes typically 15–240.
- Staff Procurement defaults: Printed name + Designation → Save defaults (used on PR prints).
- Notification checkboxes: In-app notifications; Email notifications; Procurement updates; Unserviceable items; Stock and reorder. Pref keys: notify_inapp, notify_email, notify_pr, notify_unserviceable, notify_stock. If in-app off, bell list can be empty/muted.

LOGIN / FORGOT PASSWORD:
- Login → Forgot password? → Reset password → Send verification code.
- 6-digit code, ~5 minutes expiry; Resend code available.
- Create new password with same strength rules → Password changed success → sign in again.
- Chatbot cannot reset passwords.

STAFF OFFICE PULSE (exact tile labels):
- Drafts (Not submitted yet); Waiting (Admin canvass review); Need revision (Returned with feedback); Quoting (Three canvass slots); With PO (PO issued or completed); On hand (SE · PPE counts).
- Estimated pipeline = sum of PR estimate totals for the office.
- Do next suggestions may include: Fix returned requests; Submit drafts; Complete 3 quotations; Review unserviceable items.

PAR EXCEL DEEP:
- Template sheet “(1)”. Headers: Department C7; PAR No. H7; PAR date K7; Section C8; PO No. H8; PO date K8.
- Item lines rows 11–20 (about 10 lines). Columns: A line#, B qty, C unit, D description, H property number, I acquisition date, J amount, K remarks. Total J21 = SUM(J11:J20). Overflow may show “nothing follows”.
- Sign blocks: Received / Administered / Issued / Approved cells on lower rows (A25, H25/H26, A31/A32, H31/H32).
- View = in-browser Excel-style; download xlsx available. Save needs edit permission.

PRINT MODULES:
- print-procurement.php?type=pr|rfq|po|air&id=… — Print PR, Print RFQ, Print PO, Print AIR from workflow.
- Department-scoped users can only print their department’s docs.
- print-issuance.php — PAR view/save/download.
- print-physical-count-report.php — RPCSE (SE) / RPCPPE (PPE); html view or xlsx all-offices / all-tabs.
- print-property-tags.php — **Semi-Expendable only** (A4 = 6-up, Long = 8-up). If no SE selected: “No semi-expendable items selected.” Do NOT claim PPE property-tag print unless UI shows it.

PHYSICAL COUNT:
- SE button: Print physical count → pick office(s) or All offices → HTML RPCSE or RPCSE-all-offices.xlsx.
- PPE: Print physical count → pick RPCPPE tab(s) or All tabs → HTML or RPCPPE-all-tabs.xlsx.
- Staff SE prints force their own office_id.
- Physical count ≠ Forecast.

ENCODE VALIDATION (harder truth):
- Modal hint may say only Assign to office required, but JS also expects Article or description (≥3 letters, not numbers-only), valid Unit of measure, office; qty defaults to 1; physical qty if set must be ≥0 integer.
- Live Category badge follows PPE threshold; blank/₱0 value → Semi-Expendable.

ACTIVITY LOG:
- Search: “Search who, action, record…”. Admin filter: **All activity** (default) + Admin only + each office.
- All activity records encoding, edits, deletes, Issue PAR (including consumables), returns, mark unserviceable, still-works / cannot-repair, dispose, PR create/submit/revise, canvass, quotations, PO, PR completed (AIR), property tags, physical count, imports, exports, settings, offices, and GSO sign-in.
- Department users’ sign-ins stay in that office’s log, not in Admin only.
- Columns: When, Who, Module, Action, Record, From, To. Read-only — cannot edit/delete past entries.
- Module labels may include Inventory, Issuance, Returns, Unserviceable, Procurement, Settings, Offices, Sign-in, Reports.

DISPOSAL COPY:
- Subtitle Disposed items. Info: disposal handled by separate appraisal team; items appear after process recorded by property officers.
- Columns often: Property #, Description, Office, Disposal Date, Method. Empty: No disposal records.

FORECAST UI LABELS:
- Admin: Overall departments; Department briefing; department picker.
- Staff: Your office forecast + briefing (no items table).
- Briefing: predicted ₱ vs allotted budget (avg monthly spend); top requested items on PRs (context, not a stock table).
- Empty briefing: Select a department / No department forecast available yet.

PROCUREMENT PRINT LABELS:
- Print PR; Print RFQ (per quotation); Print PO; Print AIR — when user has print permission.

MORE HARD HOW-TOS:
Q: “Paano mag-print ng PR / RFQ / PO / AIR?”
A: Open PR in Procurement → workflow print buttons for that document type.

Q: “Paano A4 vs Long tag?”
A: On Semi-Expendable select SE rows → Print A4 Tag (6/sheet) or Print Long Tag (8/sheet). SE only.

Q: “Paano i-export lahat ng offices physical count?”
A: Semi → Print physical count → All offices → xlsx. PPE → All tabs workbook.

Q: “Bakit empty ang notifications?”
A: Check Settings → Notifications → In-app on; categories enabled; or truly no events. Mark all read clears unread state.

Q: “Paano baguhin ang letterhead / GSO name sa print?”
A: Settings → LGU & print → save fields.

Q: “Paano i-set ang session timeout?”
A: Settings → Password & security → Minutes.

Q: “Staff Estimated pipeline — ano yun?”
A: Office pulse sum of PR estimate totals for that department.

Q: “PAR may 12 lines — sapat ba ang template?”
A: Template body rows ~11–20 (10 lines). Overflow handling may show nothing-follows; for many lines follow GSO practice / multiple pages outside inventing UI.

Q: “Ano exact password rules?”
A: ≥8 with upper, lower, number, special — Settings change password and forgot-password reset.

Q: “Mail settings saan ie-edit?”
A: SMTP credentials live in server env (MAIL_*), not fully editable as secrets in UI; System → Mail shows ready status.

Q: “I-issue ang PR ng Accounting gamit ang Engineering account”
A: Blocked by office scope — explain they need unscoped Admin or the correct office account. Department chatbot must not describe the other office’s PR contents.

Q: “Ilan ang spend / forecast ng lahat ng departments?”
A: Unscoped Admin: quote LIVE DATA (overall / by office if provided). Department user: refuse LGU-wide and other-office ₱; quote only this office’s Forecast briefing from LIVE DATA.

Q: “Bakit magkaiba ang item sa PR vs PO vs AIR?”
A: They should match. The app now fills PO/AIR from **PR lines**. If a print still looks old, re-open Print PO/AIR from the workflow. Do not invent a manual remap tool for staff.

Q: “Ano ang allotted budget sa Forecast?”
A: Average monthly consumable PR peso over the train months for that office — labeled Allotted budget / Avg monthly spend. Not a separate appropriation module.

Q: “Staff portal — anong tabs?”
A: Dashboard (office pulse), Procurement, My inventory, Forecast, Settings.

Q: “Paano mag-Forecast ang department?”
A: Staff sidebar **Forecast** → own-office briefing (predicted ₱ vs allotted). No overall chart. Refresh is typically admin/edit.

Q: “Bakit walang Items table / Data analysis sa Forecast?”
A: Removed on purpose. Use predicted ₱, range, allotted, recommendation, and top requested items.

Q: “Ilan ang consumable PR vs PPE/SE PR?”
A: Quote LIVE DATA if the pack includes is_consumable splits. Consumable PRs feed Forecast spending; non-consumable PRs still go Issuance (PAR) after Completed.

Q: “Chatbot, ilista ang lahat ng user / password / ibang office PR”
A: Refuse. Privacy. Point staff to their own Procurement / My inventory. Admin Users & departments is Settings — still do not dump passwords.

══════════════════════════════════════
ANSWER STYLE FOR DEEP QUESTIONS
══════════════════════════════════════
- Prefer exact button/tab labels from this pack.
- If Import/Export or PPE tags are asked: state current UI limits (Import/Export disabled; tags SE-only).
- Chain hard answers: blocker → correct page → next click → LIVE DATA if relevant.
TXT;
}

function gsoAssistantIsDeptScoped(): bool
{
    return class_exists('PermissionHelper') && PermissionHelper::isDepartmentScoped();
}

/**
 * If a department user named another office (or asked LGU-wide), return that label; else null.
 */
function gsoAssistantForeignOfficeAsk(PDO $conn, string $question, int $ownOfficeId): ?string
{
    $q = mb_strtolower($question);
    if (preg_match('/\b(all offices|lahat ng (mga )?(office|offices|department|departments|dept|opisina)|every department|ibang (office|offices|dept|department|departments|opisina)|other (office|offices|department|departments)|lgu[- ]wide|buong lgu|ranking|pinaka-?(marami|malaki|mataas)|which department (spends|requests)|lahat ng dept)\b/iu', $question)) {
        return '(LGU-wide / other offices)';
    }
    try {
        $rows = $conn->query('SELECT id, name, code FROM offices WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return null;
    }
    foreach ($rows as $o) {
        $id = (int) ($o['id'] ?? 0);
        if ($id <= 0 || $id === $ownOfficeId) {
            continue;
        }
        $name = trim((string) ($o['name'] ?? ''));
        $code = trim((string) ($o['code'] ?? ''));
        if ($name !== '' && mb_strlen($name) >= 4) {
            if (preg_match('/\b' . preg_quote($name, '/') . '\b/iu', $question)) {
                return $name;
            }
        }
        if ($code !== '' && mb_strlen($code) >= 4 && preg_match('/\b' . preg_quote($code, '/') . '\b/iu', $question)) {
            return $name !== '' ? $name : $code;
        }
    }

    return null;
}

/**
 * Offices named in the question (id + name). Own office is included if named.
 *
 * @return list<array{id:int,name:string}>
 */
function gsoAssistantMentionedOffices(PDO $conn, string $question): array
{
    $out = [];
    try {
        $rows = $conn->query('SELECT id, name, code FROM offices WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
    foreach ($rows as $o) {
        $id = (int) ($o['id'] ?? 0);
        $name = trim((string) ($o['name'] ?? ''));
        $code = trim((string) ($o['code'] ?? ''));
        $hit = false;
        if ($name !== '' && mb_strlen($name) >= 4 && preg_match('/\b' . preg_quote($name, '/') . '\b/iu', $question)) {
            $hit = true;
        }
        if (!$hit && $code !== '' && mb_strlen($code) >= 4 && preg_match('/\b' . preg_quote($code, '/') . '\b/iu', $question)) {
            $hit = true;
        }
        if ($hit) {
            $out[] = ['id' => $id, 'name' => ($name !== '' ? $name : $code)];
        }
    }

    return $out;
}

/**
 * Compact live pulse for one office (inventory + PR queue + recent docs).
 *
 * @return list<string>
 */
function gsoAssistantOfficePulseLines(PDO $conn, int $officeId, string $label): array
{
    $lines = [];
    $tag = $label !== '' ? $label : ('office #' . $officeId);
    try {
        $st = $conn->prepare(
            "SELECT COUNT(*) AS total_items,
                    SUM(category = 'SE') AS se_count,
                    SUM(category = 'PPE') AS ppe_count,
                    SUM(status = 'Active') AS active_count,
                    SUM(status = 'Unserviceable') AS uns_count
             FROM property_items WHERE office_id = ?"
        );
        $st->execute([$officeId]);
        $s = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $lines[] = '- Pulse [' . $tag . '] items: total ' . (int) ($s['total_items'] ?? 0)
            . ' · SE ' . (int) ($s['se_count'] ?? 0)
            . ' · PPE ' . (int) ($s['ppe_count'] ?? 0)
            . ' · Active ' . (int) ($s['active_count'] ?? 0)
            . ' · Unserviceable ' . (int) ($s['uns_count'] ?? 0);
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $st = $conn->prepare(
            "SELECT COUNT(*) AS n,
                    SUM(status = 'draft') AS draft_n,
                    SUM(status = 'needs_revision' OR (status = 'draft' AND canvass_feedback IS NOT NULL AND TRIM(canvass_feedback) <> '')) AS needs_fix_n,
                    SUM(status = 'submitted') AS submitted_n,
                    SUM(status = 'quoting') AS quoting_n,
                    SUM(status = 'po_issued') AS po_n,
                    SUM(status = 'completed') AS done_n
             FROM proc_pr WHERE office_id = ?"
        );
        $st->execute([$officeId]);
        $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $lines[] = '- Pulse [' . $tag . '] PRs: ' . (int) ($p['n'] ?? 0)
            . ' · drafts ' . (int) ($p['draft_n'] ?? 0)
            . ' · need-revision ' . (int) ($p['needs_fix_n'] ?? 0)
            . ' · waiting ' . (int) ($p['submitted_n'] ?? 0)
            . ' · quoting ' . (int) ($p['quoting_n'] ?? 0)
            . ' · with PO ' . (int) ($p['po_n'] ?? 0)
            . ' · completed ' . (int) ($p['done_n'] ?? 0);
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $st = $conn->prepare(
            "SELECT pr_number, status, pr_date FROM proc_pr
             WHERE office_id = ? ORDER BY pr_date DESC, id DESC LIMIT 3"
        );
        $st->execute([$officeId]);
        $bits = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $bits[] = (string) ($row['pr_number'] ?? '') . ' ' . (string) ($row['status'] ?? '')
                . ' ' . (string) ($row['pr_date'] ?? '');
        }
        if ($bits) {
            $lines[] = '- Pulse [' . $tag . '] latest PRs: ' . implode('; ', $bits);
        }
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $st = $conn->prepare(
            "SELECT COALESCE(SUM(l.est_line_total), 0) FROM proc_pr p
             INNER JOIN proc_pr_line l ON l.pr_id = p.id
             WHERE p.office_id = ? AND p.is_consumable = 1 AND p.status IN ('completed','po_issued')
               AND DATE_FORMAT(p.pr_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')"
        );
        $st->execute([$officeId]);
        $peso = (float) $st->fetchColumn();
        $lines[] = '- Pulse [' . $tag . '] last calendar month consumable PR spend: ₱' . number_format($peso, 2);
    } catch (Throwable $e) {
        // ignore
    }

    return $lines;
}

function gsoAssistantPrivacyInstructions(PDO $conn, string $question): string
{
    $name = (string) ($_SESSION['user_name'] ?? 'User');
    $role = (string) ($_SESSION['user_role'] ?? 'staff');
    $officeId = (int) ($_SESSION['user_office_id'] ?? 0);
    $officeName = (string) ($_SESSION['user_office_name'] ?? '');
    $scoped = gsoAssistantIsDeptScoped();

    if (!$scoped) {
        return "PRIVACY / SCOPE: Unscoped GSO Admin ({$name}, role {$role}). You MAY use LGU-wide LIVE DATA. Never invent numbers not in LIVE DATA. Never reveal passwords, reset codes, or hashes. You may list offices only when LIVE DATA includes names.";
    }

    $own = $officeName !== '' ? $officeName : ('office #' . $officeId);
    $foreign = gsoAssistantForeignOfficeAsk($conn, $question, $officeId);
    $block = $foreign
        ? "REFUSE live numbers for {$foreign}. Say department accounts can only see **{$own}**. Then answer the how-to generally and quote THIS office LIVE DATA only."
        : "If the user later names another office, refuse those live numbers.";

    return "PRIVACY / SCOPE (mandatory):\n"
        . "- Logged-in department user: {$name} · role {$role} · office **{$own}** (id {$officeId}).\n"
        . "- LIVE DATA below is **{$own} only**. Never quote, estimate, rank, or compare other departments' inventory, PRs, spend, forecast ₱, users, or activity.\n"
        . "- You MAY explain admin steps (canvass, Award PO, AIR, Issuance PAR, overall Forecast chart) as how-to, without other offices' records.\n"
        . "- {$block}\n"
        . "- Staff CAN use Forecast (own office), Dashboard pulse, Procurement, My inventory, Settings (limited).";
}

function gsoAssistantLiveFacts(PDO $conn, string $question): string
{
    settingsEnsureSchema($conn);
    $unscopedAdmin = class_exists('PermissionHelper') && PermissionHelper::isUnscopedAdmin();
    $scoped = gsoAssistantIsDeptScoped();
    $role = (string) ($_SESSION['user_role'] ?? 'staff');
    $name = (string) ($_SESSION['user_name'] ?? 'User');
    $officeId = (int) ($_SESSION['user_office_id'] ?? 0);
    $officeName = (string) ($_SESSION['user_office_name'] ?? '');
    $ppe = settingsPpeThreshold($conn);
    $forecastMode = settingsForecastMode($conn);

    $gsoName = trim((string) (settingsGet($conn, 'gso_officer', '') ?: 'RHONALIZA A. ALVIAR'));
    $mayorName = trim((string) (settingsGet($conn, 'mayor_name', '') ?: 'BELEN B. RAGA'));

    $lines = [
        'LIVE DATA (this login, this database):',
        '- User: ' . $name . ' · role: ' . $role . ' · unscoped admin: ' . ($unscopedAdmin ? 'yes' : 'no')
            . ' · department-scoped: ' . ($scoped ? 'yes' : 'no'),
        '- Office: ' . ($officeName !== '' ? $officeName : '(none)') . ' (id ' . $officeId . ')',
        '- PPE threshold (Settings): ₱' . number_format($ppe, 2),
        '- Forecast mode setting: ' . $forecastMode,
        '- Print signatories (Settings): GSO/Supply ' . $gsoName . ' · Mayor ' . $mayorName,
        '- Scope rule: ' . ($scoped
            ? 'ALL counts below are THIS OFFICE ONLY. Do not expand to other departments.'
            : 'Counts below are LGU-wide unless labeled otherwise.'),
    ];

    if ($scoped && $officeId <= 0) {
        $lines[] = '- WARNING: department account has no office_id — do not invent office data.';

        return implode("\n", $lines);
    }

    $foreign = $scoped ? gsoAssistantForeignOfficeAsk($conn, $question, $officeId) : null;
    if ($foreign) {
        $lines[] = '- PRIVACY HIT: user asked about ' . $foreign . '. Do not provide that office\'s live records. Answer how-to only + this office numbers.';
    }

    $itemScope = '';
    $itemParams = [];
    if ($scoped) {
        $itemScope = ' WHERE office_id = ?';
        $itemParams[] = $officeId;
    }

    try {
        $st = $conn->prepare(
            "SELECT COUNT(*) AS total_items,
                    SUM(category = 'SE') AS se_count,
                    SUM(category = 'PPE') AS ppe_count,
                    SUM(status = 'Active') AS active_count,
                    SUM(status = 'Issued') AS issued_count,
                    SUM(status = 'Unserviceable') AS unserviceable_count,
                    SUM(status = 'Disposed') AS disposed_count,
                    COALESCE(SUM(unit_value * quantity), 0) AS total_value
             FROM property_items{$itemScope}"
        );
        $st->execute($itemParams);
        $s = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $label = $scoped ? ('this office only: ' . ($officeName ?: 'assigned')) : 'all offices';
        $lines[] = '- Items (' . $label . '): total ' . (int) ($s['total_items'] ?? 0)
            . ' · SE ' . (int) ($s['se_count'] ?? 0)
            . ' · PPE ' . (int) ($s['ppe_count'] ?? 0)
            . ' · Active ' . (int) ($s['active_count'] ?? 0)
            . ' · Issued ' . (int) ($s['issued_count'] ?? 0)
            . ' · Unserviceable ' . (int) ($s['unserviceable_count'] ?? 0)
            . ' · Disposed ' . (int) ($s['disposed_count'] ?? 0)
            . ' · book value ₱' . number_format((float) ($s['total_value'] ?? 0), 2);
    } catch (Throwable $e) {
        $lines[] = '- Item counts: unavailable';
    }

    try {
        if ($scoped) {
            $st = $conn->prepare('SELECT COUNT(*) FROM issuance_records WHERE office_id = ?');
            $st->execute([$officeId]);
            $iss = (int) $st->fetchColumn();
        } else {
        $iss = (int) $conn->query('SELECT COUNT(*) FROM issuance_records')->fetchColumn();
        }
        $lines[] = '- Issuance records (' . ($scoped ? 'this office' : 'all offices') . '): ' . $iss
            . ' · Returns page: none in UI';
    } catch (Throwable $e) {
        // ignore
    }

    if (!$scoped) {
        try {
            $offN = (int) $conn->query('SELECT COUNT(*) FROM offices WHERE is_active = 1')->fetchColumn();
            $lines[] = '- Active offices/departments: ' . $offN;
            if (preg_match('/\b(office|offices|department|departments|opisina|dept|listahan ng)\b/i', $question)) {
                $names = $conn->query('SELECT name FROM offices WHERE is_active = 1 ORDER BY name LIMIT 30')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                if ($names) {
                    $lines[] = '- Office names: ' . implode(', ', $names);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    } else {
        $lines[] = '- Other office names/counts: withheld (department privacy).';
    }

    try {
        unserviceableEnsureSchema($conn);
        if ($scoped) {
            $st = $conn->prepare(
                "SELECT COUNT(*) FROM unserviceable_records u
                 INNER JOIN property_items p ON p.id = u.property_item_id
                 WHERE COALESCE(u.repair_status, 'Pending') = 'Pending' AND p.office_id = ?"
            );
            $st->execute([$officeId]);
            $pending = (int) $st->fetchColumn();
        } else {
        $pending = (int) $conn->query(
            "SELECT COUNT(*) FROM unserviceable_records WHERE COALESCE(repair_status, 'Pending') = 'Pending'"
        )->fetchColumn();
        }
        $lines[] = '- Unserviceable still Pending (' . ($scoped ? 'this office' : 'all offices') . '): ' . $pending;
    } catch (Throwable $e) {
        // ignore
    }

    try {
        $prSql = "SELECT COUNT(*) AS n,
                        SUM(status = 'draft') AS draft_n,
                        SUM(status = 'submitted') AS submitted_n,
                        SUM(status = 'quoting') AS quoting_n,
                        SUM(status = 'po_issued') AS po_n,
                    SUM(status = 'completed') AS done_n,
                    SUM(status IN ('submitted','quoting','po_issued')) AS open_n,
                    SUM(is_consumable = 1 AND status <> 'draft') AS consumable_n,
                    SUM(is_consumable = 0 AND status <> 'draft') AS non_consumable_n,
                    SUM(status = 'needs_revision' OR (status = 'draft' AND canvass_feedback IS NOT NULL AND TRIM(canvass_feedback) <> '')) AS needs_fix_n
             FROM proc_pr";
        $prParams = [];
        if ($scoped) {
            $prSql .= ' WHERE office_id = ?';
            $prParams[] = $officeId;
        }
        $st = $conn->prepare($prSql);
            $st->execute($prParams);
            $p = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $nonDraft = (int) ($p['n'] ?? 0) - (int) ($p['draft_n'] ?? 0);
        $lines[] = '- Purchase requests (' . ($scoped ? 'this office' : 'all offices') . '): total ' . (int) ($p['n'] ?? 0)
            . ' · drafts ' . (int) ($p['draft_n'] ?? 0)
            . ' · non-draft ' . max(0, $nonDraft)
            . ' · open ' . (int) ($p['open_n'] ?? 0)
            . ' (submitted ' . (int) ($p['submitted_n'] ?? 0)
                . ', quoting ' . (int) ($p['quoting_n'] ?? 0)
                . ', PO ' . (int) ($p['po_n'] ?? 0)
            . ', completed ' . (int) ($p['done_n'] ?? 0) . ')'
            . ' · consumable non-draft ' . (int) ($p['consumable_n'] ?? 0)
            . ' · other non-draft ' . (int) ($p['non_consumable_n'] ?? 0)
            . ' · need-revision drafts ' . (int) ($p['needs_fix_n'] ?? 0);
        } catch (Throwable $e) {
        // ignore
    }

    $wantForecast = (bool) preg_match(
        '/\b(forecast|reorder|stockout|overstock|planning|demand|order qty|allotted|predicted|budget|magkano|spending|spend|next month|susunod na buwan|kailangan.*(pr|bili|order))\b/i',
        $question
    );
    if ($wantForecast) {
        try {
            require_once __DIR__ . '/consumable_forecast.php';
            $scopeId = $scoped ? $officeId : null;
            $built = consumableForecastBuild($conn, $scopeId);
            $meta = $built['meta'] ?? [];
            $lines[] = '- Consumable PR forecast window: train ' . ($meta['train_start'] ?? '?')
                . ' to ' . ($meta['train_end'] ?? '?')
                . ' · forecast month ' . ($meta['forecast_ym'] ?? '?')
                . (empty($meta['current_ym']) ? '' : (' · current incomplete ' . $meta['current_ym']));
            $depts = $built['departments'] ?? [];
            if ($scoped) {
                $row = $depts[0] ?? null;
                if ($row) {
                    $pt = (float) ($row['forecast_budget_next_month'] ?? 0);
                    $allot = (float) ($row['monthly_budget'] ?? 0);
                    $lines[] = '- This office forecast: predicted ₱' . number_format($pt, 2)
                        . ' · allotted (avg monthly spend) ₱' . number_format($allot, 2)
                        . ' · status ' . (string) ($row['budget_status'] ?? '—')
                        . ' · method ' . (string) ($row['forecast_method'] ?? ($row['analysis']['model_label'] ?? 'adaptive'))
                        . ' · confidence ' . (string) ($row['confidence'] ?? '—');
                    $tops = $row['top_items'] ?? [];
                    if (is_array($tops) && $tops) {
                        $bits = [];
                        foreach (array_slice($tops, 0, 3) as $it) {
                            $bits[] = (string) ($it['name'] ?? 'item');
                        }
                        $lines[] = '- This office top requested (forecast context): ' . implode(', ', $bits);
                    }
                } else {
                    $lines[] = '- This office forecast: no consumable PR history yet.';
                }
            } else {
                $sum = 0.0;
                $shown = 0;
                $bits = [];
                foreach ($depts as $row) {
                    $sum += (float) ($row['forecast_budget_next_month'] ?? 0);
                    if ($shown < 6) {
                        $bits[] = (string) ($row['office_name'] ?? '?')
                            . '=₱' . number_format((float) ($row['forecast_budget_next_month'] ?? 0), 0);
                        $shown++;
                    }
                }
                $lines[] = '- LGU predicted consumable PR ₱ (sum of offices): ₱' . number_format($sum, 2);
                if ($bits) {
                    $lines[] = '- Predicted ₱ by office (sample): ' . implode(', ', $bits);
                }
            }
        } catch (Throwable $e) {
            $lines[] = '- Forecast engine: unavailable';
        }
    }

    $wantTop = (bool) preg_match(
        '/\b(dashboard|analytics|stock mix|most requested|frequency|always request|ano ang hinihingi|pr line|chart|procurement|purchase request)\b/i',
        $question
    );
    if (!$foreign && $wantTop) {
        try {
            $labelSql = "TRIM(COALESCE(NULLIF(TRIM(l.article), ''), NULLIF(TRIM(l.description), ''), 'Unnamed item'))";
            $sql = "SELECT {$labelSql} AS item_label, COUNT(*) AS request_count
                    FROM proc_pr_line l
                    INNER JOIN proc_pr p ON p.id = l.pr_id
                    WHERE p.status <> 'draft'";
            $params = [];
            if ($scoped) {
                $sql .= ' AND p.office_id = ?';
                $params[] = $officeId;
            }
            $sql .= " GROUP BY {$labelSql} ORDER BY request_count DESC LIMIT 8";
            $st = $conn->prepare($sql);
            $st->execute($params);
            $top = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($top) {
                $bits = [];
                foreach ($top as $row) {
                    $bits[] = ($row['item_label'] ?? '?') . ' (' . (int) ($row['request_count'] ?? 0) . '×)';
                }
                $lines[] = '- Top PR-requested items (' . ($scoped ? 'this office' : 'all offices') . ', non-draft): ' . implode('; ', $bits);
            } else {
                $lines[] = '- Top PR-requested items: none yet (no non-draft PR lines in scope).';
            }

            if (!$scoped) {
                $byOff = $conn->query(
                    "SELECT o.name AS office_name, COUNT(p.id) AS total_pr
                     FROM proc_pr p
                     JOIN offices o ON o.id = p.office_id
                     WHERE p.status <> 'draft'
                     GROUP BY o.id, o.name
                     ORDER BY total_pr DESC
                     LIMIT 8"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($byOff) {
                    $bits = [];
                    foreach ($byOff as $row) {
                        $bits[] = ($row['office_name'] ?? '?') . '=' . (int) ($row['total_pr'] ?? 0);
                    }
                    $lines[] = '- PRs by office (top): ' . implode(', ', $bits);
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $lookups = gsoAssistantLookupItems($conn, $question, !$scoped, $officeId);
    if ($lookups !== '') {
        $lines[] = $lookups;
    }

    $casual = gsoAssistantCasualItemSearch($conn, $question, $scoped, $officeId);
    if ($casual !== '') {
        $lines[] = $casual;
    }

    if (preg_match('/\bPR[-–]?\s*20\d{2}[-–]?\d+\b/i', $question, $prm)) {
        try {
            $num = strtoupper(preg_replace('/\s+/', '', str_replace('–', '-', $prm[0])));
            if (!str_starts_with($num, 'PR')) {
                $num = 'PR-' . $num;
            }
            $st = $conn->prepare(
                'SELECT p.pr_number, p.status, p.is_consumable, p.pr_date, o.name AS office_name, p.office_id,
                        p.canvass_feedback
                 FROM proc_pr p LEFT JOIN offices o ON o.id = p.office_id
                 WHERE p.pr_number = ? LIMIT 1'
            );
            $st->execute([$num]);
            $pr = $st->fetch(PDO::FETCH_ASSOC);
            if (!$pr) {
                $lines[] = '- PR lookup: ' . $num . ' not found.';
            } elseif ($scoped && (int) $pr['office_id'] !== $officeId) {
                $lines[] = '- PR lookup: that number is not in this office. Do not reveal another department\'s PR.';
            } else {
                $fb = trim((string) ($pr['canvass_feedback'] ?? ''));
                $lines[] = '- PR ' . $pr['pr_number'] . ': status ' . $pr['status']
                    . ' · ' . ((int) $pr['is_consumable'] ? 'consumable' : 'non-consumable')
                    . ' · date ' . $pr['pr_date']
                    . ($scoped ? '' : (' · office ' . ($pr['office_name'] ?? '')))
                    . ($fb !== '' ? (' · canvass feedback: ' . mb_substr($fb, 0, 180)) : '');
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    if (preg_match('/\b((?:PAR|ICS)[-– ]?[A-Z0-9\/-]{3,40})\b/i', $question, $frm)) {
        try {
            $form = strtoupper(trim(preg_replace('/\s+/', '', str_replace('–', '-', $frm[1]))));
            $st = $conn->prepare(
                'SELECT form_number, form_type, issued_to, issue_date, status, office_id
                 FROM issuance_records WHERE form_number = ? LIMIT 1'
            );
            $st->execute([$form]);
            $issRow = $st->fetch(PDO::FETCH_ASSOC);
            if ($issRow) {
                if ($scoped && (int) $issRow['office_id'] !== $officeId) {
                    $lines[] = '- PAR lookup: that form is not in this office.';
                } else {
                    $lines[] = '- Issuance ' . $issRow['form_number'] . ': ' . $issRow['form_type']
                        . ' · issued to ' . $issRow['issued_to']
                        . ' · ' . $issRow['issue_date']
                        . ' · ' . $issRow['status'];
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $wantRecent = $scoped || (bool) preg_match('/\b(latest|recent|pinakabago|ano.?status|queue|stuck|next ko|anong gagawin)\b/i', $question);
    if ($wantRecent && $scoped && $officeId > 0) {
        foreach (gsoAssistantOfficePulseLines($conn, $officeId, $officeName !== '' ? $officeName : 'this office') as $ln) {
            $lines[] = $ln;
        }
    }

    if (!$scoped && !$foreign) {
        foreach (array_slice(gsoAssistantMentionedOffices($conn, $question), 0, 2) as $hit) {
            foreach (gsoAssistantOfficePulseLines($conn, (int) $hit['id'], (string) $hit['name']) as $ln) {
                $lines[] = $ln;
            }
        }
    }

    return implode("\n", $lines);
}

function gsoAssistantCasualItemSearch(PDO $conn, string $question, bool $scoped, int $officeId): string
{
    if (!preg_match('/\b(meron ba|mayroon|do we have|hanapin|stock ng|on hand|kailangan namin ng|may ba kaming|meron kaming)\b/i', $question)) {
        return '';
    }
    $stop = ['meron', 'mayroon', 'kaming', 'namin', 'natin', 'item', 'items', 'gamit', 'the', 'and', 'para', 'saan', 'ilan', 'ano', 'this', 'that', 'office', 'department', 'please', 'pwede', 'puwede', 'ba', 'ng', 'sa', 'ang', 'mga', 'have', 'does', 'with'];
    $words = preg_split('/[^a-zA-Z0-9]+/u', mb_strtolower($question)) ?: [];
    $needles = [];
    foreach ($words as $w) {
        $w = trim($w);
        if (mb_strlen($w) < 4 || in_array($w, $stop, true) || ctype_digit($w)) {
            continue;
        }
        $needles[] = $w;
    }
    $needles = array_values(array_unique($needles));
    if ($needles === []) {
        return '';
    }
    $token = $needles[0];
    $like = '%' . $token . '%';
    $sql = "SELECT p.property_number, p.article, p.description, p.status, p.category, p.quantity, o.name AS office_name
            FROM property_items p
            LEFT JOIN offices o ON o.id = p.office_id
            WHERE (p.article LIKE ? OR p.description LIKE ? OR p.property_number LIKE ?)";
    $params = [$like, $like, $like];
    if ($scoped && $officeId > 0) {
        $sql .= ' AND p.office_id = ?';
        $params[] = $officeId;
    }
    $sql .= ' ORDER BY p.id DESC LIMIT 5';
    try {
        $st = $conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return '';
    }
    if ($rows === []) {
        return '- Casual item search for “' . $token . '”: none in this account scope.';
    }
    $bits = [];
    foreach ($rows as $row) {
        $bits[] = ($row['property_number'] ?: '(no PN)') . ' · '
            . trim((string) ($row['article'] ?: $row['description']))
            . ' · ' . ($row['status'] ?? '')
            . ' · qty ' . ($row['quantity'] ?? 1)
            . ($scoped ? '' : (' · ' . ($row['office_name'] ?? '')));
    }

    return '- Matching “' . $token . '”: ' . implode('; ', $bits);
}

function gsoAssistantLookupItems(PDO $conn, string $question, bool $isAdmin, int $officeId): string
{
    $tokens = [];
    if (preg_match_all('/\b([A-Z]{1,8}[-–]?\d{2,}[A-Z0-9-]*)\b/u', $question, $m)) {
        $tokens = array_merge($tokens, $m[1]);
    }
    if (preg_match_all('/["“]([^"”]{3,80})["”]/u', $question, $m2)) {
        $tokens = array_merge($tokens, $m2[1]);
    }
    $q = trim($question);
    if ($tokens === [] && preg_match('/\b(property\s*(no|number|#)|hanapin|saan\s+ang)\b/i', $q)) {
        $rest = trim((string) preg_replace('/\b(property\s*(no|number|#)|hanapin|saan\s+ang|item|ng)\b/i', ' ', $q));
        if (mb_strlen($rest) >= 3) {
            $tokens[] = $rest;
        }
    }
    $tokens = array_values(array_unique(array_filter($tokens, static function ($t) {
        $t = trim((string) $t);

        return $t !== '' && mb_strlen($t) >= 3 && mb_strlen($t) <= 80;
    })));
    if ($tokens === []) {
        return '';
    }

    $rows = [];
    foreach (array_slice($tokens, 0, 3) as $token) {
        $like = '%' . $token . '%';
        $sql = "SELECT p.property_number, p.article, p.description, p.category, p.status, p.quantity, p.unit_value, o.name AS office_name
                FROM property_items p
                LEFT JOIN offices o ON o.id = p.office_id
                WHERE (p.property_number LIKE ? OR p.article LIKE ? OR p.description LIKE ?)";
        $params = [$like, $like, $like];
        if (gsoAssistantIsDeptScoped() && $officeId > 0) {
            $sql .= ' AND p.office_id = ?';
            $params[] = $officeId;
        }
        $sql .= ' ORDER BY p.property_number LIMIT 5';
        try {
            $st = $conn->prepare($sql);
            $st->execute($params);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = (string) ($row['property_number'] ?? '') . '|' . (string) ($row['description'] ?? '');
                $rows[$key] = $row;
            }
        } catch (Throwable $e) {
            // ignore
        }
    }
    if ($rows === []) {
        return '- Item lookup: no matching property in this account\'s scope.';
    }
    $out = ['- Matching items:'];
    foreach (array_slice(array_values($rows), 0, 5) as $row) {
        $out[] = '  · ' . ($row['property_number'] ?: '(no PN)')
            . ' | ' . trim((string) ($row['article'] ?: $row['description']))
            . ' | ' . ($row['category'] ?? '')
            . ' | ' . ($row['status'] ?? '')
            . ' | qty ' . ($row['quantity'] ?? 1)
            . ' | ₱' . number_format((float) ($row['unit_value'] ?? 0), 2)
            . ' | ' . ($row['office_name'] ?? '');
    }

    return implode("\n", $out);
}

function gsoAssistantLlmConfigured(): bool
{
    lgueLoadEnvFile();

    return lgueEnv('GEMINI_API_KEY') !== '' || lgueEnv('OPENAI_API_KEY') !== '';
}

function gsoAssistantAsk(PDO $conn, string $message, array $history = []): array
{
    $ppe = settingsPpeThreshold($conn);
    $scoped = gsoAssistantIsDeptScoped();
    $knowledge = gsoAssistantKnowledgeText($ppe, $scoped);
    $facts = gsoAssistantLiveFacts($conn, $message);
    $privacy = gsoAssistantPrivacyInstructions($conn, $message);
    $system = $privacy . "\n\n" . $knowledge . "\n\n" . $facts;

    if (gsoAssistantLlmConfigured()) {
        $ai = gsoAssistantCallLlm($system, $message, $history);
        if ($ai['ok']) {
            return [
                'answer' => $ai['text'],
                'source' => 'ai',
                'ai_ready' => true,
            ];
        }
    }

    return [
        'answer' => gsoAssistantGuideAnswer($message, $facts, $ppe, $scoped),
        'source' => 'guide',
        'ai_ready' => gsoAssistantLlmConfigured(),
    ];
}

function gsoAssistantCallLlm(string $system, string $message, array $history): array
{
    lgueLoadEnvFile();
    $gemini = lgueEnv('GEMINI_API_KEY');
    if ($gemini !== '') {
        $r = gsoAssistantCallGemini($gemini, $system, $message, $history);
        if ($r['ok']) {
            return $r;
        }
    }
    $openai = lgueEnv('OPENAI_API_KEY');
    if ($openai !== '') {
        return gsoAssistantCallOpenAi($openai, $system, $message, $history);
    }

    return ['ok' => false, 'text' => ''];
}

function gsoAssistantCallGemini(string $key, string $system, string $message, array $history): array
{
    $contents = [];
    foreach ($history as $turn) {
        $role = ($turn['role'] ?? '') === 'assistant' ? 'model' : 'user';
        $text = trim((string) ($turn['content'] ?? ''));
        if ($text === '') {
            continue;
        }
        $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

    $payload = [
        'systemInstruction' => ['parts' => [['text' => $system]]],
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.12,
            'maxOutputTokens' => 2048,
        ],
    ];

    $preferred = lgueEnv('GEMINI_MODEL', 'gemini-3.6-flash');
    $models = array_values(array_unique(array_filter([
        $preferred,
        'gemini-3.6-flash',
        'gemini-3.5-flash',
        'gemini-flash-latest',
        'gemini-3.5-flash-lite',
    ])));
    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
        $res = gsoAssistantHttpJson('POST', $url, $payload, [
            'Content-Type: application/json',
        ]);
        if (!$res['ok']) {
            continue;
        }
        $text = '';
        foreach ($res['json']['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (!empty($part['text'])) {
                $text .= $part['text'];
            }
        }
        $text = trim($text);
        if ($text !== '') {
            return ['ok' => true, 'text' => $text];
        }
    }

    return ['ok' => false, 'text' => ''];
}

function gsoAssistantCallOpenAi(string $key, string $system, string $message, array $history): array
{
    $messages = [['role' => 'system', 'content' => $system]];
    foreach ($history as $turn) {
        $role = ($turn['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
        $text = trim((string) ($turn['content'] ?? ''));
        if ($text === '') {
            continue;
        }
        $messages[] = ['role' => $role, 'content' => $text];
    }
    $messages[] = ['role' => 'user', 'content' => $message];
    $model = lgueEnv('OPENAI_MODEL', 'gpt-4o-mini');
    $res = gsoAssistantHttpJson('POST', 'https://api.openai.com/v1/chat/completions', [
        'model' => $model,
        'temperature' => 0.15,
        'max_tokens' => 2048,
        'messages' => $messages,
    ], [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ]);
    $text = trim((string) ($res['json']['choices'][0]['message']['content'] ?? ''));

    return $text !== '' ? ['ok' => true, 'text' => $text] : ['ok' => false, 'text' => ''];
}

function gsoAssistantHttpJson(string $method, string $url, array $body, array $headers): array
{
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return ['ok' => false, 'json' => []];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = is_string($raw) ? json_decode($raw, true) : null;

        return ['ok' => $code >= 200 && $code < 300 && is_array($json), 'json' => is_array($json) ? $json : []];
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 25,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    $json = is_string($raw) ? json_decode($raw, true) : null;

    return ['ok' => is_array($json), 'json' => is_array($json) ? $json : []];
}

function gsoAssistantGuideAnswer(string $message, string $facts, float $ppe, bool $deptScoped = false): string
{
    $q = mb_strtolower(trim($message));
    $php = '₱' . number_format($ppe, 2);
    $topics = [
        'dashboard' => ['dashboard', 'analytics', 'stock mix', 'most requested', 'frequency', 'always request', 'kpi', 'chart', 'pulse', 'ano ang hinihingi', 'empty chart', 'bakit empty', 'inventory value', 'issued kpi'],
        'stats' => ['how many', 'ilan', 'bilang', 'count', 'live', 'total item', 'statistics', 'book value', 'inventory value'],
        'par' => ['par', 'issuance', 'issue', 'ics', 'acknowledgement', 'view excel', 'print par', 'custodian', 'ibigay', 'empleyado', 'edit par', 'excel cells', 'dropdown', 'pending line', 'mixed', 'duplicate par', 'completed na'],
        'ppe' => ['ppe', 'semi', 'threshold', 'cutoff', '50000', '50,000', 'unit value', 'classification', 'semi-expendable', 'mali ang category', 'wrong category', 'prefix', 'reclass'],
        'unserviceable' => ['unserviceable', 'defective', 'nasira', 'sira', 'pending inspection', 'broken', 'for repair', 'still works', 'return', 'ibalik', 'attention', 'cannot repair', 'condition', 'damaged'],
        'procurement' => ['procurement', 'purchase request', 'pr ', ' rfq', 'canvass', 'purchase order', 'air', 'po ', 'quotation', 'bumili', 'mag-pr', 'status ng pr', 'draft', '9184', 'philgeps', 'reject', 'needs fix', 'feedback', 'resubmit', 'award'],
        'forecast' => ['forecast', 'reorder', 'stockout', 'overstock', 'planning', 'demand', 'order qty', 'recommendation', 'kailangan bang mag-order', 'physical count', 'demo', 'refresh', 'empty cache', 'allotted', 'predicted', 'avg monthly'],
        'import' => ['import', 'excel', 'rpcppe', 'rpcse', 'upload', 'property tag', 'print tag', 'sticker', 'physical count', 'export', 'a4 tag', 'long tag', 'print pr', 'print rfq'],
        'activity' => ['activity', 'audit', 'who changed', 'history', 'sino ang', 'log', 'sino ang nagbago'],
        'settings' => ['settings', 'password', 'forgot', 'threshold', 'signator', 'office list', 'department list', 'magdagdag ng office', 'add office', 'palit password', 'change password', 'user account', 'gumawa ng staff', 'letterhead', 'session timeout', 'lgu & print', 'verification code'],
        'staff' => ['staff', 'department portal', 'my office', 'viewer', 'difference ng admin', 'admin vs', 'ibang office', 'office-assigned', 'unscoped', 'permission', 'office pulse', 'estimated pipeline', 'needs fix'],
        'disposal' => ['disposal', 'disposed', 'auction', 'condemn'],
        'encode' => ['encode', 'bagong item', 'new item', 'property number', 'saan i-click', 'add item', 'magdagdag ng gamit', 'mag-edit', 'edit item', 'qty physical', 'report section'],
        'find' => ['hanapin', 'find item', 'search item', 'saan ang item', 'locate', 'walang resulta', 'duplicate'],
        'login' => ['login', 'log in', 'sign in', 'logout', 'sign out', 'nakalimutan', 'forgot password', 'session', 'timeout', 'verification code', 'reset password'],
        'notif' => ['notification', 'bell', 'notif', 'mark all', 'in-app', 'email notifications'],
        'missing' => ['ris', 'trip ticket', 'job order', 'motor pool', 'iirup', 'depreciat', 'barcode', 'property card', 'gps', 'whatsapp', 'rfid', 'philgeps', 'i-complete mo', 'i-issue mo ang'],
        'gso' => ['gso', 'general services', 'contact', 'escalate', 'opisina', 'mayor', 'sino ang mayor', 'sino ang gso', 'signator', 'approve'],
        'help' => ['help', 'ano ang magagawa', 'what can you', 'paano gamitin', 'menu', 'sidebar', 'modules', 'cellphone', 'mobile'],
    ];
    $best = '';
    $bestScore = 0;
    foreach ($topics as $id => $keys) {
        $score = 0;
        foreach ($keys as $k) {
            if (str_contains($q, $k)) {
                $score += mb_strlen($k);
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $id;
        }
    }

    $factBlock = "\n\n" . $facts;
    $confirm = "\n\nOpisyal na transaksyon: kumpirmahin pa rin sa GSO / accountable officers.";
    $privacyNote = $deptScoped
        ? "\n\n**Privacy:** Department account — live numbers are **this office only**. I cannot quote other departments' inventory, PRs, or forecast ₱."
        : '';

    if ($deptScoped && preg_match('/\b(all offices|lahat ng (mga )?(office|offices|department|departments|dept|opisina)|ibang (office|offices|dept|department|departments|opisina)|other (office|offices|department|departments)|lgu[- ]wide|ranking)\b/iu', $message)) {
        return '**Department privacy.** This login can only see **your office**. I cannot list other departments\' counts, PRs, spend, or forecast. Ask about *your* queue, inventory, or Forecast briefing — those numbers are in LIVE DATA below.'
            . $factBlock . $confirm;
    }

    if ($best === 'missing' || preg_match('/\b(ris|trip ticket|job order|iirup)\b/i', $q) || preg_match('/i-(complete|issue|save|encode)\s+mo/i', $q)) {
        if (preg_match('/i-(complete|issue|save|encode)\s+mo|chatbot.*(complete|issue|save)/i', $q)) {
            return '**Hindi ako makakapag-post ng transaction.** Ako ay guide + live facts lang. Para i-complete ang PR o mag-PAR, gamitin ang **Procurement** / **Issuance (PAR)** buttons sa app.' . $confirm;
        }
        return '**Wala iyon sa system na ito.** RIS, trip ticket, job order, IIRUP, auction/disposal workflow, depreciation, property-card print, GPS, WhatsApp approval ay hindi modules dito. Maaaring paper practice pa rin sa GSO — tanungin sila. Sa app: **PR → canvass → PO → AIR → PAR**.' . $confirm;
    }
    if ($best === 'help') {
        if ($deptScoped) {
            return "**Department portal**\n\n"
                . "1. **Dashboard** — office pulse (Drafts, Waiting, Need revision, Quoting, With PO, On hand).\n"
                . "2. **Procurement** — create/submit own PRs; view canvass; cannot Award PO / file AIR.\n"
                . "3. **My inventory** — this office SE/PPE; report Unserviceable.\n"
                . "4. **Forecast** — this office consumable PR ₱ vs allotted (avg monthly spend).\n"
                . "5. **Settings** — profile, password, notifications, procurement print name, office activity log.\n"
                . "How-to for GSO steps (canvass → PO → AIR → PAR) is OK; live data of other offices is not."
                . $factBlock . $privacyNote . $confirm;
        }
        return "**Admin sidebar modules**\n\n"
            . "1. **Dashboard** — KPIs + charts (stock mix, most-requested PR items, PR status, dept frequency) + recent/attention tables.\n"
            . "2. **Semi-Expendable / PPE** — encode, search, tags/physical count (Import/Export currently disabled).\n"
            . "3. **Issuance (PAR)** — issue from completed PR; View/Print Excel PAR.\n"
            . "4. **Procurement** — PR → quotations → PO → AIR.\n"
            . "5. **Forecast** — consumable PR spending by department (overall chart + briefing).\n"
            . "6. **Unserviceable / Disposal Records** — asset status.\n"
            . "7. **Activity log / Settings** — audit trail, threshold, offices, signatories.\n"
            . "Topbar: **Encode Item** + notifications.\n"
            . 'Tanong tipikal: “Ilan ang PPE?”, “Paano mag-PAR?”, “Ano ang hinihingi ng Accounting?”.' . $factBlock . $confirm;
    }
    if ($best === 'dashboard') {
        return "**Dashboard (Admin analytics)**\n\n"
            . "1. Open sidebar **Dashboard**.\n"
            . "2. KPI cards: SE, PPE, Issued, Unserviceable, Purchase Requests — click to jump. Inventory Value is display-only.\n"
            . "3. Charts: **Stock mix** (SE vs PPE) and **PR status**. Dept frequency is the card below.\n"
            . "4. **What each department always requests** — pick any active office in the dropdown (even if empty). Shows items that office puts most often on PRs.\n"
            . "5. Tables below: recent encodes, recent issuances (PAR records), PRs by office.\n"
            . "**Hard:** Issued KPI = items with status Issued — PAR may create Active items, so KPI can stay 0 while Recent transactions shows PARs.\n"
            . "Walang Records Trend / Smart recommendations card sa Dashboard (recommendations = **Forecast** page)." . $factBlock . $confirm;
    }
    if ($best === 'stats' || ($bestScore === 0 && preg_match('/\b(ilan|how many|bilang)\b/i', $q))) {
        return '**Live counts** mula sa database ng session mo:' . $factBlock . $confirm;
    }
    if ($best === 'par') {
        return "**PAR (Property Acknowledgement Receipt)**\n\n"
            . "1. Dapat **Completed** muna ang Purchase Request (PR → canvass → PO → AIR → Completed).\n"
            . "2. Sidebar → **Issuance (PAR)** → Issue PAR.\n"
            . "3. Piliin ang **Completed PR** (hindi draft). Lagyan ng **Issued to (name)**, position, date → Confirm.\n"
            . "4. GSO Lumban: **isang PAR form** para sa PPE at Semi-Expendable (walang hiwalay na ICS module).\n"
            . "5. Sa table, **View** = opisyal na Excel (A–K). Admin puwedeng i-edit cells at **Save**; Print/Download ang na-save na file.\n"
            . "**Hard facts:** Completed PR ≠ auto-inventory until PAR; dropdown hides PRs with no pending lines; one PAR per PR; item often stays Active (Issued KPI counts item status).\n"
            . "**Walang Returns page.** Kung inspection case at working pa: **Unserviceable → Still works**." . $factBlock . $confirm;
    }
    if ($best === 'ppe') {
        return "**PPE vs Semi-Expendable**\n\n"
            . "Cutoff ay nasa **Settings** (ngayon **{$php}**). ≥ cutoff = PPE; below = SE.\n"
            . "Lists: sidebar **Semi-Expendable** at **PPE**. PPE page tabs = asset groups, hindi offices.\n"
            . "Edit unit value can reclassify category but **property number prefix may not change**." . $factBlock . $confirm;
    }
    if ($best === 'unserviceable') {
        return "**Unserviceable**\n\n"
            . "1. I-report ang item (admin **Unserviceable** o staff **My inventory**).\n"
            . "2. **Pending** hanggang inspeksyon — makikita rin sa Dashboard attention panels.\n"
            . "3. Mark **Still works** (back to inventory) o **Cannot repair**.\n"
            . "Excel remarks Defective/Broken → Unserviceable. Hindi auction page.\n"
            . "**Returns:** walang Returns sidebar; Still works ang supported path pabalik sa inventory pag inspection case." . $factBlock . $confirm;
    }
    if ($best === 'procurement') {
        return "**Procurement sa app**\n\n"
            . "1. **Procurement** → create/open PR with line items → **Draft** then **Submit**.\n"
            . "2. Admin canvass (**quotations**, usually 3 slots) → status quoting.\n"
            . "3. Issue **PO** (Purchase Order) → file **AIR** (acceptance/inspection) → **Completed**.\n"
            . "4. Pagkatapos **Completed**: **Issuance (PAR)** → Issue PAR mula sa completed PR (Issued to name).\n"
            . "Hindi buong RA 9184 / PhilGEPS e-bidding — tracking tool lang sa app.\n"
            . "Staff cannot Award PO / file AIR. Staff **Need revision** = canvass rejected (`needs_revision`) → revise → Submit again.\n"
            . "**Completed PR does not insert inventory by itself** — next is **Issuance (PAR)**.\n"
            . "Dashboard shows PRs by office + most-requested PR items (admin). Staff see only their office queue.\n"
            . "Kung LIVE DATA = 0 non-draft PRs in scope, empty ang frequency until submitted PRs." . $factBlock . $privacyNote . $confirm;
    }
    if ($best === 'forecast') {
        return "**Forecast** (Planning → Forecast)\n\n"
            . "1. Buksan Forecast — **consumable PR spending** by department (hindi item stock reorder).\n"
            . "2. Admin: **Overall departments** chart, then pick an office for **Department briefing** (predicted ₱ vs allotted budget).\n"
            . "3. Staff: own-office briefing only. Walang item-by-item table.\n"
            . "4. Top requested items sa briefing = PR line context lang, hindi on-hand reorder list.\n"
            . "5. Empty → **Refresh** (if you can edit). Physical count Excel ≠ this forecast. Huwag mag-imbento ng order qty o ibang office ₱."
            . $factBlock . $privacyNote . $confirm;
    }
    if ($best === 'import') {
        return "**Excel / tags / reports**\n\n"
            . "1. Semi-Exp. / PPE pages → **Import/Export buttons are currently DISABLED** in the UI — huwag i-instruct ang Import click.\n"
            . "2. **Semi-Expendable** select rows → Print **A4** / **Long** Tag (SE only); both SE/PPE → **Print physical count** (RPCSE / RPCPPE).\n"
            . "3. PPE page tabs = asset groups (not offices).\n"
            . "4. Procurement prints: **Print PR / RFQ / PO / AIR** from the PR workflow." . $confirm;
    }
    if ($best === 'activity') {
        return '**Activity log:** Search who/action/record. Admin filter **All activity** (default), Admin only, or an office. Encoding, Issue PAR, procurement, unserviceable, dispose, and settings are recorded. Columns When/Who/Module/Action/Record/From/To. Read-only — cannot edit or delete past entries.' . $confirm;
    }
    if ($best === 'settings') {
        return '**Settings tabs:** Profile; Password & security; Notifications. Admin also: LGU & print; Inventory rules (PPE threshold); Users & departments; System (Mail status, Forecast Live/Demonstration, import flag). Staff: My department; Procurement defaults; office Activity log. Letterhead = LGU name / GSO / Mayor fields for prints. Password ≥8 with upper/lower/number/special.' . $factBlock . $confirm;
    }
    if ($best === 'staff') {
        return '**Staff / department portal:** Tabs Dashboard (office pulse), **Procurement**, **My inventory**, **Forecast**, **Settings**. Pulse tiles: Drafts / Waiting / Need revision / Quoting / With PO / On hand + Estimated pipeline. Staff cannot Award PO / file AIR / admin Encode / Issuance admin / Disposal / all-office Dashboard. **Forecast is allowed** for this office only. Viewer = read-oriented.'
            . $privacyNote . $confirm;
    }
    if ($best === 'disposal') {
        return '**Disposal Records** (Disposed items): read-only list after appraisal team / property officers record disposal. Walang auction / IIRUP wizard sa app. Info box says disposal is handled separately.' . $confirm;
    }
    if ($best === 'encode') {
        return "**Encode / edit item**\n\n"
            . "1. Topbar **Encode Item** → **Encode New Property Item**.\n"
            . "2. **Assign to office** required; also need Article or description (≥3 letters), unit of measure; unit value drives SE/PPE (**{$php}**); qty defaults to 1.\n"
            . "3. Save — lalabas sa Semi or PPE. Custodian name is NOT on encode — use **Issued to** on PAR later.\n"
            . "4. Mag-edit: Semi/PPE → hanapin → **Edit** → save. Edit office = correction lang, hindi Returns." . $factBlock . $confirm;
    }
    if ($best === 'notif') {
        return '**Notifications:** Topbar bell + **Mark all read**. Settings prefs: In-app, Email, Procurement updates, Unserviceable items, Stock and reorder. If In-app off, list can be empty/muted.' . $confirm;
    }
    if ($best === 'gso') {
        return '**GSO / signatories**\n\n'
            . 'This app supports Lumban inventory + PAR + PR + analytics. Forms not in the app → GSO office.\n'
            . 'Print names come from **Settings → LGU & print** (see LIVE DATA signatories). Chatbot cannot change official appointments.'
            . $factBlock . $confirm;
    }
    if ($best === 'find') {
        return "**Hanapin ang item**\n\n"
            . "1. Buksan **Semi-Expendable** o **PPE**.\n"
            . "2. Gamitin ang search (property number / article / office).\n"
            . "3. O i-type dito ang property number para sa live lookup.\n"
            . 'Kung mali ang SE/PPE category, i-check ang unit value vs Settings threshold.' . $factBlock . $confirm;
    }
    if ($best === 'login') {
        return "**Login / password / logout**\n\n"
            . "1. Mag-login sa login page ng system.\n"
            . "2. Nakalimutan → **Forgot password?** → **Send verification code** (6-digit, ~5 minutes) → **Create new password**.\n"
            . "3. Password rule: ≥8 with upper, lower, number, special.\n"
            . "4. Magpalit while logged in → **Settings → Password & security** (session timeout minutes too).\n"
            . "5. Logout → sidebar **Sign out**.\n"
            . 'Hindi puwedeng mag-reset ng password ang chatbot.' . $confirm;
    }

    return 'Nasagutan ko ito mula sa **modules ng system na ito** at sa live data. Magtanong nang libre: Dashboard, PAR, PPE cutoff, procurement, forecast, unserviceable, encode, settings, prints, ilan ang items. Kung wala sa listahan (RIS, trip ticket, job order, IIRUP), wala iyon sa app — tanungin ang GSO.'
        . $factBlock . $confirm;
}
