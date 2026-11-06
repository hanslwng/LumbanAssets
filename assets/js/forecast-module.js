/**
 * Consumables forecast UI — department briefing and overall spending charts.
 */
(function () {
    let deptReqChart = null;
    let deptQtyChart = null;
    let overallBudgetChart = null;
    let overallQtyChart = null;
    let overallFuseChart = null;
    let overallTrendChart = null;
    let deptRows = [];
    let meta = {};
    let scopedOnly = false;
    let selectedDeptId = null;

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fmt(n) {
        const x = Number(n);
        if (!Number.isFinite(x)) return '0';
        return x.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    function fmt1(n) {
        const x = Number(n);
        if (!Number.isFinite(x)) return '0';
        return x.toLocaleString(undefined, { maximumFractionDigits: 1 });
    }

    function fmtMoney(n) {
        const x = Number(n);
        if (!Number.isFinite(x) || x <= 0) return '—';
        return '₱' + x.toLocaleString(undefined, { maximumFractionDigits: 0 });
    }

    function fmtMoneySigned(n) {
        const x = Number(n);
        if (!Number.isFinite(x)) return '—';
        const abs = Math.abs(x);
        const body = '₱' + abs.toLocaleString(undefined, { maximumFractionDigits: 0 });
        if (x > 0.5) return '+' + body;
        if (x < -0.5) return '−' + body;
        return body;
    }

    function monthFullLabel(ym) {
        const dt = String(ym || '');
        const parts = dt.split('-');
        if (parts.length < 2) return dt || '—';
        const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        return (names[Number(parts[1]) - 1] || parts[1]) + ' ' + parts[0];
    }

    function fmtMoneyRange(range) {
        if (!range) return '—';
        const low = Number(range.low);
        const high = Number(range.high);
        if (!Number.isFinite(low) || !Number.isFinite(high)) return '—';
        if (low === high) return fmtMoney(low);
        return fmtMoney(low) + '–' + fmtMoney(high);
    }

    function fmtRange(range, suffix) {
        if (!range) return '—';
        const low = Number(range.low);
        const high = Number(range.high);
        if (!Number.isFinite(low) || !Number.isFinite(high)) return '—';
        const s = suffix ? (' ' + suffix) : '';
        if (low === high) return fmt(low) + s;
        return fmt(low) + '–' + fmt(high) + s;
    }

    function monthLabel(ym) {
        const parts = String(ym || '').split('-');
        if (parts.length < 2) return String(ym || '');
        const names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return (names[Number(parts[1]) - 1] || parts[1]) + ' ' + String(parts[0]).slice(2);
    }

    function trendText(t) {
        if (t === 'Increasing') return '↑ Increasing';
        if (t === 'Decreasing') return '↓ Decreasing';
        if (t === 'Stable') return '→ Stable';
        return '—';
    }

    function trendPill(t) {
        const cls = t === 'Increasing' ? 'up' : (t === 'Decreasing' ? 'down' : 'flat');
        return `<span class="fcx-trend-pill fcx-trend-${cls}">${esc(trendText(t))}</span>`;
    }

    function yesNo(v) {
        if (v === true) return '<strong class="fcx-yes">Yes</strong>';
        if (v === false) return '<strong class="fcx-no">No</strong>';
        return '—';
    }

    function destroyChart(ch) {
        if (ch) {
            try { ch.destroy(); } catch (e) { /* ignore */ }
        }
        return null;
    }

    function accent() {
        const a = getComputedStyle(document.body).getPropertyValue('--staff-accent').trim();
        return a || '#0F766E';
    }

    function renderDeptBriefing(row) {
        const box = document.getElementById('fc-dept-briefing');
        if (!box) return;
        if (!row) {
            box.innerHTML = '<p class="muted fcx-brief-empty">No department forecast available yet.</p>';
            return;
        }
        if (row.status === 'Insufficient historical data') {
            box.innerHTML = `
                <div class="fcx-brief-head">
                    <div>
                        <p class="fcx-brief-kicker">Department forecast briefing</p>
                        <h3>${esc(row.office_name)}</h3>
                    </div>
                    <span class="fcx-trend-pill fcx-trend-flat">No forecast yet</span>
                </div>
                <section class="fcx-rec-card" aria-label="Recommendation">
                    <div class="fcx-rec-card-hd">
                        <span class="fcx-rec-badge">Recommendation</span>
                    </div>
                    <p class="fcx-rec-text">${esc(row.recommendation || 'Keep recording completed consumable Purchase Requests so a reliable forecast can be built.')}</p>
                </section>`;
            return;
        }

        const forecastMonth = monthFullLabel(row.forecast_month || meta.forecast_ym || '');
        const trainFrom = monthFullLabel(row.train_from || meta.train_start || '2026-01');
        const trainTo = monthFullLabel(row.train_to || meta.train_end || '2026-08');
        const monthlyBudget = Number(row.monthly_budget || 0);
        const budPoint = Number(row.forecast_budget_range?.point ?? row.forecast_budget_next_month ?? 0);
        const diff = Number(row.budget_diff ?? (budPoint - monthlyBudget));
        const budgetStatus = String(row.budget_status || (monthlyBudget > 0 && budPoint > monthlyBudget ? 'Above Normal Budget' : 'Within Budget'));
        const statusIn = budgetStatus === 'Within Budget';
        const cur = row.current_month || null;
        const briefingLines = String(row.briefing || '').split('\n').filter(Boolean);
        const rec = String(row.recommendation || '').trim();
        const months = Number(row.months_observed || (row.history || []).length || 0);
        const conf = String(row.confidence || 'medium').toLowerCase();
        const trend = String(row.trend_label || 'Stable');
        const trendClass = trend === 'Increasing' ? 'up' : (trend === 'Decreasing' ? 'down' : 'flat');
        const histRows = (row.history || []).map((d) => `
            <tr class="${d.anomaly ? 'fcx-spend-anomaly' : ''}">
                <td>${esc(monthFullLabel(d.month))}${d.anomaly ? ' <span class="fcx-anomaly-tag" title="Unusual spike vs typical months">spike</span>' : ''}</td>
                <td class="tar">${esc(fmt(d.requests))}</td>
                <td class="tar">${esc(fmtMoney(d.peso))}</td>
            </tr>`).join('');
        const analysis = row.analysis || {};
        const modelLabel = String(row.forecast_method || analysis.model_label || 'Adaptive forecast');
        const sepRow = cur ? `
            <tr class="fcx-spend-ongoing">
                <td>${esc(monthFullLabel(cur.month))} — Ongoing<br><span class="muted">Data through ${esc(cur.through || meta.current_through || '')}</span></td>
                <td class="tar">${esc(fmt(cur.requests))}</td>
                <td class="tar">${esc(fmtMoney(cur.peso))}</td>
            </tr>` : '';
        box.innerHTML = `
            <div class="fcx-brief-head">
                <div>
                    <p class="fcx-brief-kicker">Department forecast briefing</p>
                    <h3>${esc(row.office_name)}</h3>
                    <p class="fcx-brief-sub">Forecast: ${esc(forecastMonth)} · Method: ${esc(modelLabel)} · History: ${esc(trainFrom)} – ${esc(trainTo)}</p>
                </div>
                ${trendPill(row.trend_label)}
            </div>

            <div class="fcx-dept-kpi" aria-label="Budget vs forecast">
                <article class="fcx-dept-kpi-chip fcx-kpi-budget">
                    <div class="fcx-kpi-top">
                        <span class="fcx-kpi-ico" aria-hidden="true"><i class="fas fa-coins"></i></span>
                        <em>Predicted ${esc(forecastMonth)}</em>
                    </div>
                    <strong>${esc(fmtMoney(budPoint))}</strong>
                    <span class="fcx-kpi-sub">${esc(fmtMoneyRange(row.forecast_budget_range))}</span>
                </article>
                <article class="fcx-dept-kpi-chip fcx-kpi-req">
                    <div class="fcx-kpi-top">
                        <span class="fcx-kpi-ico" aria-hidden="true"><i class="fas fa-wallet"></i></span>
                        <em>Allotted budget</em>
                    </div>
                    <strong>${esc(fmtMoney(monthlyBudget))}</strong>
                    <span class="fcx-kpi-sub">Avg monthly spend</span>
                </article>
                <article class="fcx-dept-kpi-chip fcx-kpi-qty">
                    <div class="fcx-kpi-top">
                        <span class="fcx-kpi-ico" aria-hidden="true"><i class="fas fa-plus-minus"></i></span>
                        <em>Difference</em>
                    </div>
                    <strong>${esc(fmtMoneySigned(diff))}</strong>
                    <span class="fcx-kpi-sub">Forecast minus allotted</span>
                </article>
                <article class="fcx-dept-kpi-chip fcx-kpi-signal ${statusIn ? 'fcx-kpi-trend-flat' : 'fcx-kpi-trend-up'}">
                    <div class="fcx-kpi-top">
                        <span class="fcx-kpi-ico" aria-hidden="true"><i class="fas fa-flag"></i></span>
                        <em>Status</em>
                    </div>
                    <strong class="${statusIn ? 'fcx-budget-in' : 'fcx-budget-over'}">${esc(budgetStatus)}</strong>
                    <span class="fcx-kpi-sub">
                        <span class="fcx-kpi-conf fcx-conf-${esc(conf)}">${esc(conf.toUpperCase())}</span>
                        ${fmt(months)} mo
                    </span>
                </article>
            </div>

            <div class="fcx-brief-narrative">
                ${briefingLines.map((line) => `<p>${esc(line)}</p>`).join('')}
            </div>

            <section class="fcx-rec-card" aria-label="Recommendation">
                <div class="fcx-rec-card-hd">
                    <span class="fcx-rec-badge">Recommendation</span>
                    <span class="fcx-rec-meta">
                        <em class="fcx-conf fcx-conf-${esc(row.confidence || 'medium')}">${esc(String(row.confidence || 'medium').toUpperCase())} confidence</em>
                    </span>
                </div>
                <p class="fcx-rec-text">${esc(rec || 'This forecast helps the department anticipate its possible consumables budget requirement for the upcoming month.')}</p>
            </section>

            <section class="fcx-brief-card fcx-top-items-card" aria-label="Top requested items">
                <div class="fcx-top-items-hd">
                    <span class="fcx-top-items-ico" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                    <h4>Top 3 requested</h4>
                </div>
                ${(row.top_items && row.top_items.length) ? `
                    <ol class="fcx-top-items">
                        ${row.top_items.slice(0, 3).map((it, i) => {
                            const rankIcon = i === 0 ? 'fa-medal' : (i === 1 ? 'fa-award' : 'fa-certificate');
                            return `
                            <li class="fcx-top-item fcx-top-item-${i + 1}">
                                <span class="fcx-top-rank" aria-hidden="true"><i class="fas ${rankIcon}"></i></span>
                                <div class="fcx-top-item-body">
                                    <strong title="${esc(it.name || 'Item')}">${esc(it.name || 'Item')}</strong>
                                    <span>${fmt(it.request_count)} · qty ${fmt(it.total_qty)}</span>
                                </div>
                            </li>`;
                        }).join('')}
                    </ol>
                ` : '<p class="muted fcx-top-empty">No PR item lines yet.</p>'}
            </section>

            <div class="fcx-brief-grid">
                <section class="fcx-brief-card fcx-brief-card-accent">
                    <h4>Forecast result</h4>
                    <dl class="fcx-dl">
                        <div><dt>Predicted consumables budget</dt><dd>${fmtMoney(budPoint)}</dd></div>
                        <div><dt>Allotted monthly budget</dt><dd>${fmtMoney(monthlyBudget)}</dd></div>
                        <div><dt>Difference</dt><dd>${esc(fmtMoneySigned(diff))}</dd></div>
                        <div><dt>Status</dt><dd>${esc(budgetStatus)}</dd></div>
                    </dl>
                </section>
                <section class="fcx-brief-card">
                    <h4>Historical basis</h4>
                    <dl class="fcx-dl">
                        <div><dt>Completed months</dt><dd>${esc(trainFrom)} – ${esc(trainTo)}</dd></div>
                        ${cur && cur.month
                            ? `<div><dt>Current month</dt><dd>${esc(monthFullLabel(cur.month))} — Ongoing</dd></div>`
                            : `<div><dt>Forecast month</dt><dd>${esc(forecastMonth)} (no actuals yet)</dd></div>`}
                        <div><dt>Avg PR spending / month</dt><dd>${fmtMoney(row.avg_peso_per_month)}</dd></div>
                        <div><dt>Avg PRs / month</dt><dd>${fmt1(row.avg_requests_per_month)}</dd></div>
                    </dl>
                </section>
            </div>

            <div class="fcx-spend-wrap">
                <h4>Historical monthly PR spending</h4>
                <table class="fcx-spend-table">
                    <thead>
                        <tr><th>Month</th><th class="tar">PRs</th><th class="tar">Actual consumables PR spending</th></tr>
                    </thead>
                    <tbody>
                        ${histRows}
                        ${sepRow}
                    </tbody>
                </table>
            </div>

            <div class="fcx-brief-charts">
                <div class="fcx-brief-chart-card fcx-apex-card">
                    <div class="fcx-chart-card-hd">
                        <div>
                            <h4>PRs per month</h4>
                            <span class="fcx-chart-axis-lbl">Purchase requests</span>
                        </div>
                        <span class="fcx-chart-hint">Month</span>
                    </div>
                    <div class="fcx-apex-wrap">
                        <div class="fcx-chart-box fcx-chart-box-dept fcx-apex-plot">
                            <canvas id="fc-dept-req-chart"></canvas>
                        </div>
                        <div class="fcx-apex-legend" id="fc-dept-req-legend" role="group" aria-label="Series"></div>
                    </div>
                </div>
                <div class="fcx-brief-chart-card">
                    <div class="fcx-chart-card-hd">
                        <h4>Consumables PR spending</h4>
                        <span class="fcx-chart-hint">History + ${esc(forecastMonth)}</span>
                    </div>
                    <div class="fcx-chart-box fcx-chart-box-dept"><canvas id="fc-dept-qty-chart"></canvas></div>
                </div>
            </div>`;

        renderDeptCharts(row);
    }

    function rollingAvg(values, windowSize) {
        const win = Math.max(2, windowSize || 3);
        return values.map((_, i) => {
            const start = Math.max(0, i - win + 1);
            const slice = values.slice(start, i + 1);
            const sum = slice.reduce((a, b) => a + Number(b || 0), 0);
            return Math.round((sum / slice.length) * 10) / 10;
        });
    }

    function bindApexLegend(legendEl, chart, seriesMeta) {
        if (!legendEl || !chart) return;
        legendEl.innerHTML = seriesMeta.map((s, i) => `
            <label class="fcx-apex-leg-item">
                <input type="checkbox" data-ds="${i}" checked />
                <span class="fcx-apex-swatch" style="background:${s.color}"></span>
                <span class="fcx-apex-leg-name">${esc(s.label)}</span>
            </label>
        `).join('');
        legendEl.querySelectorAll('input[type="checkbox"]').forEach((input) => {
            input.addEventListener('change', () => {
                const idx = Number(input.getAttribute('data-ds'));
                const meta = chart.getDatasetMeta(idx);
                if (!meta) return;
                meta.hidden = !input.checked;
                chart.update();
            });
        });
    }

    function renderDeptCharts(row) {
        if (typeof Chart === 'undefined') return;
        const hist = row.history || [];
        const labels = hist.map((d) => monthLabel(d.month));
        const reqsHist = hist.map((d) => Number(d.requests || 0));
        const pesos = hist.map((d) => Number(d.peso || 0));
        const histLen = labels.length;
        const forecastReq = row.forecast_requests_range
            ? Number(row.forecast_requests_range.point || row.forecast_requests_next_month || 0)
            : null;
        const forecastPeso = Number(row.forecast_budget_range?.point ?? row.forecast_budget_next_month ?? 0);

        if (row.current_month) {
            labels.push(monthLabel(row.current_month.month) + '*');
            reqsHist.push(Number(row.current_month.requests || 0));
            pesos.push(Number(row.current_month.peso || 0));
        }
        const midLen = labels.length;
        if (forecastReq != null || forecastPeso) {
            labels.push(monthLabel(row.forecast_month) || 'Oct');
            pesos.push(forecastPeso || 0);
        }

        const trainLen = histLen;
        const reqActual = labels.map((_, i) => (i < midLen ? reqsHist[i] : null));
        const reqAvg = rollingAvg(reqsHist.slice(0, trainLen), 3);
        const reqAvgSeries = labels.map((_, i) => {
            if (i < trainLen) return reqAvg[i];
            return reqAvg.length ? reqAvg[reqAvg.length - 1] : null;
        });
        // Full-span OLS fit so Forecast is a continuous blue line (not a lone end-dot).
        const reqFit = (() => {
            const y = reqsHist.slice(0, trainLen);
            const n = y.length;
            if (n < 2 || forecastReq == null) return null;
            let sumX = 0;
            let sumY = 0;
            let sumXY = 0;
            let sumXX = 0;
            for (let i = 0; i < n; i++) {
                const x = i + 1;
                const yi = Number(y[i] || 0);
                sumX += x;
                sumY += yi;
                sumXY += x * yi;
                sumXX += x * x;
            }
            const den = (n * sumXX) - (sumX * sumX);
            let slope = 0;
            let intercept = sumY / n;
            if (Math.abs(den) >= 1e-12) {
                slope = ((n * sumXY) - (sumX * sumY)) / den;
                intercept = (sumY - (slope * sumX)) / n;
            }
            return { slope, intercept, n };
        })();
        const lastIdx = labels.length - 1;
        const reqForecast = labels.map((_, i) => {
            if (!reqFit) return null;
            if (i === lastIdx) return forecastReq;
            const x = i + 1;
            const v = reqFit.intercept + (reqFit.slope * x);
            return Math.max(0, Math.round(v * 10) / 10);
        });

        const reqCanvas = document.getElementById('fc-dept-req-chart');
        const qtyCanvas = document.getElementById('fc-dept-qty-chart');
        const reqLegend = document.getElementById('fc-dept-req-legend');
        deptReqChart = destroyChart(deptReqChart);
        deptQtyChart = destroyChart(deptQtyChart);

        const tickStyle = { color: '#94A3B8', font: { size: 11, weight: '500', family: 'system-ui,sans-serif' } };
        const gridStyle = { color: 'rgba(148,163,184,.28)', drawBorder: false, lineWidth: 1 };
        const tip = {
            backgroundColor: 'rgba(15,23,42,.92)',
            titleFont: { size: 12, weight: '600' },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
            boxPadding: 4,
        };

        const areaColors = {
            requests: { stroke: '#00C9A7', fill: 'rgba(0,201,167,0.28)' },
            average: { stroke: '#FF6B8A', fill: 'rgba(255,107,138,0.22)' },
            forecast: { stroke: '#3B9EFF', fill: 'rgba(59,158,255,0.22)' },
        };

        if (reqCanvas) {
            deptReqChart = new Chart(reqCanvas.getContext('2d'), {
                type: 'line',
            data: {
                labels,
                datasets: [
                    {
                            label: 'Requests',
                            data: reqActual,
                            borderColor: areaColors.requests.stroke,
                            backgroundColor: areaColors.requests.fill,
                            fill: true,
                            tension: 0.45,
                            borderWidth: 2.5,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: areaColors.requests.stroke,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            spanGaps: false,
                        },
                        {
                            label: '3-mo average',
                            data: reqAvgSeries,
                            borderColor: areaColors.average.stroke,
                            backgroundColor: areaColors.average.fill,
                        fill: true,
                            tension: 0.45,
                            borderWidth: 2.5,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: areaColors.average.stroke,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                        spanGaps: false,
                        },
                        {
                            label: 'Forecast',
                            data: reqForecast,
                            borderColor: areaColors.forecast.stroke,
                            backgroundColor: areaColors.forecast.fill,
                            fill: true,
                            tension: 0.45,
                            borderWidth: 2.5,
                        pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: areaColors.forecast.stroke,
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                        spanGaps: false,
                            segment: {
                                borderDash: (ctx) => (ctx.p0DataIndex >= trainLen - 1 ? [6, 4] : undefined),
                            },
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    animation: { duration: 500, easing: 'easeOutQuart' },
                plugins: {
                        legend: { display: false },
                        tooltip: tip,
                    },
                    scales: {
                        x: {
                            grid: { ...gridStyle, drawTicks: false },
                            border: { display: false },
                            ticks: tickStyle,
                        },
                        y: {
                            beginAtZero: true,
                            grace: '8%',
                            grid: gridStyle,
                            border: { display: false },
                            ticks: { ...tickStyle, precision: 0 },
                            title: {
                                display: false,
                            },
                        },
                    },
                },
            });
            bindApexLegend(reqLegend, deptReqChart, [
                { label: 'Requests', color: areaColors.requests.stroke },
                { label: '3-mo average', color: areaColors.average.stroke },
                { label: 'Forecast', color: areaColors.forecast.stroke },
            ]);
        }
        if (qtyCanvas) {
            deptQtyChart = new Chart(qtyCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'PR spending ₱',
                        data: pesos,
                        borderColor: '#0F766E',
                        backgroundColor: (ctx) => {
                            const { chart } = ctx;
                            const { ctx: c, chartArea } = chart;
                            if (!chartArea) return 'rgba(15,118,110,.12)';
                            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, 'rgba(15,118,110,.28)');
                            g.addColorStop(1, 'rgba(15,118,110,.02)');
                            return g;
                        },
                        fill: true,
                        tension: 0.35,
                        pointRadius: pesos.map((_, i) => (i >= trainLen ? 5 : 3)),
                        pointHoverRadius: 6,
                        pointBackgroundColor: pesos.map((_, i) => (i >= trainLen ? '#B22222' : '#0F766E')),
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        borderWidth: 2.5,
                        segment: {
                            borderDash: (ctx) => (ctx.p1DataIndex >= trainLen ? [5, 4] : undefined),
                        },
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 420, easing: 'easeOutQuart' },
                    plugins: { legend: { display: false }, tooltip: tip },
                    scales: {
                        x: { grid: { display: false }, border: { display: false }, ticks: tickStyle },
                        y: {
                            beginAtZero: true,
                            grid: gridStyle,
                            border: { display: false },
                            ticks: tickStyle,
                        },
                    },
                },
            });
        }
    }

    function shortOfficeLabel(name, maxLen) {
        const n = String(name || 'Office').trim();
        const max = maxLen || 16;
        return n.length > max ? n.slice(0, max - 1) + '…' : n;
    }

    /** Full office name for chart axis (wrap long names onto 2 lines). */
    function officeChartLabel(name, maxPerLine) {
        const n = String(name || 'Office').trim();
        const max = maxPerLine || 28;
        if (n.length <= max) return n;
        const words = n.split(/\s+/);
        const lines = [];
        let cur = '';
        words.forEach((w) => {
            const next = cur ? `${cur} ${w}` : w;
            if (next.length > max && cur) {
                lines.push(cur);
                cur = w;
            } else {
                cur = next;
            }
        });
        if (cur) lines.push(cur);
        if (lines.length <= 1) return n;
        // Keep at most 2 lines so the axis stays readable.
        if (lines.length > 2) {
            return [lines[0], lines.slice(1).join(' ')];
        }
        return lines;
    }

    function renderOverall() {
        const panel = document.getElementById('fc-panel-overall');
        const summary = document.getElementById('fc-overall-summary');
        const countEl = document.getElementById('fc-overall-count');
        if (scopedOnly) {
            if (panel) panel.hidden = true;
            return;
        }
        if (panel) panel.hidden = false;
        const usable = deptRows
            .filter((r) => r.status !== 'Insufficient historical data')
            .slice()
            .sort((a, b) => {
                const ba = Number(a.forecast_budget_range?.point ?? a.forecast_budget_next_month ?? 0);
                const bb = Number(b.forecast_budget_range?.point ?? b.forecast_budget_next_month ?? 0);
                return bb - ba;
            });
        const insuff = deptRows.length - usable.length;
        let budgetLow = 0;
        let budgetHigh = 0;
        let qtyPoint = 0;
        let reqPoint = 0;
        let up = 0;
        let flat = 0;
        let down = 0;
        usable.forEach((r) => {
            budgetLow += Number(r.forecast_budget_range?.low ?? r.forecast_budget_next_month ?? 0);
            budgetHigh += Number(r.forecast_budget_range?.high ?? r.forecast_budget_next_month ?? 0);
            qtyPoint += Number(r.forecast_qty_range?.point ?? r.forecast_qty_next_month ?? 0);
            reqPoint += Number(r.forecast_requests_range?.point ?? r.forecast_requests_next_month ?? 0);
            if (r.trend_label === 'Increasing') up += 1;
            else if (r.trend_label === 'Decreasing') down += 1;
            else flat += 1;
        });
        if (countEl) {
            countEl.textContent = usable.length
                ? `${usable.length} office${usable.length === 1 ? '' : 's'} with forecast`
                : 'No forecast data yet';
        }
        if (summary) {
            summary.innerHTML = `
                <div class="fcx-overall-chip"><em>With forecast</em><strong>${fmt(usable.length)}</strong></div>
                <div class="fcx-overall-chip"><em>Need history</em><strong>${fmt(insuff)}</strong></div>
                <div class="fcx-overall-chip"><em>PRs (Oct)</em><strong>${fmt(reqPoint)}</strong></div>
                <div class="fcx-overall-chip"><em>Predicted ₱</em><strong>${fmtMoney(budgetLow) === '—' && fmtMoney(budgetHigh) === '—' ? '—' : fmtMoney(budgetLow) + '–' + fmtMoney(budgetHigh)}</strong></div>
                <div class="fcx-overall-chip"><em>Trend mix</em><strong>${fmt(up)}↑ ${fmt(flat)}→ ${fmt(down)}↓</strong></div>`;
        }
        if (typeof Chart === 'undefined') return;

        const n = usable.length;
        const labels = usable.map((r) => officeChartLabel(r.office_name, n > 16 ? 14 : 18));
        const budgets = usable.map((r) => Number(r.forecast_budget_range?.point ?? r.forecast_budget_next_month ?? 0));
        const qtys = usable.map((r) => Number(r.forecast_requests_range?.point ?? r.forecast_requests_next_month ?? 0));

        const fuseWrap = document.getElementById('fc-overall-fuse-wrap');
        if (fuseWrap) {
            // Full-width landscape chart.
            const h = Math.max(300, Math.min(380, 260 + Math.min(n, 24) * 4));
            fuseWrap.style.height = `${h}px`;
            fuseWrap.classList.add('is-tall', 'has-full-labels');
        }

        overallBudgetChart = destroyChart(overallBudgetChart);
        overallQtyChart = destroyChart(overallQtyChart);
        overallFuseChart = destroyChart(overallFuseChart);
        overallTrendChart = destroyChart(overallTrendChart);

        const fuseCanvas = document.getElementById('fc-overall-fuse-chart');

        const tickStyle = {
            color: '#57534E',
            font: { size: n > 18 ? 9 : 10, weight: '600', family: 'system-ui,sans-serif' },
        };
        const gridStyle = { color: 'rgba(148,163,184,.28)', drawBorder: false, lineWidth: 1 };
        const commonPlugins = {
            legend: { display: false },
            tooltip: {
                backgroundColor: 'rgba(28,25,23,.92)',
                titleFont: { size: 12, weight: '600' },
                bodyFont: { size: 12 },
                padding: 10,
                cornerRadius: 8,
                displayColors: true,
                boxPadding: 4,
            },
        };

        // Fused landscape grouped columns (red = ₱, teal = PRs).
        if (fuseCanvas) {
            overallFuseChart = new Chart(fuseCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels.length ? labels : ['No data'],
                    datasets: [
                        {
                            label: 'Predicted ₱',
                            data: budgets.length ? budgets : [0],
                            backgroundColor: '#B22222',
                            hoverBackgroundColor: '#8B0000',
                            borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
                            borderSkipped: false,
                            yAxisID: 'yPeso',
                            order: 1,
                        },
                        {
                            label: 'Predicted PRs',
                            data: qtys.length ? qtys : [0],
                            backgroundColor: '#0F766E',
                            hoverBackgroundColor: '#115E59',
                            borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
                            borderSkipped: false,
                            yAxisID: 'yPr',
                            order: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    layout: { padding: { top: 6, right: 8, bottom: 0, left: 4 } },
                    animation: { duration: 450, easing: 'easeOutQuart' },
                    datasets: {
                        bar: {
                            categoryPercentage: 0.72,
                            barPercentage: 0.96,
                            maxBarThickness: 28,
                        },
                    },
                    plugins: {
                        ...commonPlugins,
                        tooltip: {
                            ...commonPlugins.tooltip,
                            callbacks: {
                                title(items) {
                                    const i = items[0]?.dataIndex ?? 0;
                                    return usable[i]?.office_name || '';
                                },
                                label(ctx) {
                                    const v = Number(ctx.raw || 0);
                                    if (ctx.dataset.yAxisID === 'yPeso') {
                                        return ` Predicted ₱: ₱${v.toLocaleString('en-PH')}`;
                                    }
                                    return ` Predicted PRs: ${v.toLocaleString('en-PH')}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                ...tickStyle,
                                autoSkip: false,
                                maxRotation: 55,
                                minRotation: n > 10 ? 40 : 0,
                                padding: 4,
                            },
                        },
                        yPeso: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            grace: '8%',
                            grid: gridStyle,
                            border: { display: false },
                            ticks: {
                                ...tickStyle,
                                color: '#B22222',
                                callback(v) {
                                    return '₱' + Number(v).toLocaleString('en-PH', {
                                        notation: 'compact',
                                        maximumFractionDigits: 1,
                                    });
                                },
                            },
                            title: {
                                display: true,
                                text: 'Predicted ₱',
                                color: '#B22222',
                                font: { size: 10, weight: '700' },
                            },
                        },
                        yPr: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            grace: '8%',
                            grid: { drawOnChartArea: false },
                            border: { display: false },
                            ticks: {
                                ...tickStyle,
                                color: '#0F766E',
                                precision: 0,
                            },
                            title: {
                                display: true,
                                text: 'Predicted PRs',
                                color: '#0F766E',
                                font: { size: 10, weight: '700' },
                            },
                        },
                    },
                },
            });
        }
    }

    function fillDeptSelect() {
        const sel = document.getElementById('fc-dept-select');
        if (!sel || scopedOnly) return;
        const current = selectedDeptId != null ? String(selectedDeptId) : sel.value;
        const sorted = [...deptRows].sort((a, b) => String(a.office_name || '').localeCompare(String(b.office_name || '')));
        sel.innerHTML = '<option value="">Select department…</option>' + sorted.map((r) => {
            const insuf = r.status === 'Insufficient historical data';
            const label = insuf ? `${r.office_name} (no PR history yet)` : r.office_name;
            return `<option value="${Number(r.office_id)}">${esc(label)}</option>`;
        }).join('');
        if (current && sorted.some((r) => String(r.office_id) === current)) {
            sel.value = current;
            selectedDeptId = Number(current);
        } else if (sorted.length) {
            selectedDeptId = Number(sorted[0].office_id);
            sel.value = String(selectedDeptId);
        } else {
            selectedDeptId = null;
        }
    }

    function renderDepts() {
        if (scopedOnly) {
            const row = deptRows[0] || null;
            selectedDeptId = row ? Number(row.office_id) : null;
            renderDeptBriefing(row);
            return;
        }

        fillDeptSelect();
        renderOverall();
        const selected = deptRows.find((r) => Number(r.office_id) === Number(selectedDeptId)) || null;
        renderDeptBriefing(selected);
    }

    function applyPayload(data) {
        meta = data.meta || {};
        scopedOnly = !!meta.office_scoped && document.body.classList.contains('staff-portal');
        deptRows = Array.isArray(data.departments) ? data.departments : [];

        const metaEl = document.getElementById('fc-meta');
        if (metaEl) {
            const from = monthFullLabel(meta.train_start || '2026-01');
            const to = monthFullLabel(meta.train_end || '2026-08');
            const fc = monthFullLabel(meta.forecast_ym || '');
            const hasCur = !!meta.has_current_actuals && !!(meta.current_ym);
            const cur = monthFullLabel(meta.current_ym || '');
            metaEl.hidden = false;
            metaEl.textContent = hasCur
                ? `History ${from}–${to} · ${cur} ongoing through ${meta.current_through || ''} · Forecast ${fc} · Adaptive model (OLS / 3-mo avg / blend via holdout)`
                : `History ${from}–${to} · Forecast ${fc} · Adaptive model (OLS / 3-mo avg / blend via holdout)`;
            const overallSub = document.getElementById('fc-overall-sub');
            if (overallSub) {
                overallSub.textContent = `${fc} outlook from completed consumable Purchase Request spending`;
            }
            const chartTitle = document.getElementById('fc-overall-chart-title');
            if (chartTitle) {
                chartTitle.innerHTML = `${esc(fc)} predicted ₱ &amp; PRs`;
            }
            const deptSub = document.getElementById('fc-dept-panel-sub');
            if (deptSub) {
                deptSub.textContent = `Pick an office for ${fc} predicted spending vs allotted budget (avg monthly spend)`;
            }
            const heroLead = document.getElementById('fc-hero-lead');
            if (heroLead) {
                heroLead.textContent = `Based on completed Purchase Requests by department. Forecast predicts ${fc} from ${from}–${to} spending.`;
            }
            const staffTitle = document.getElementById('fc-smart-title');
            if (staffTitle) {
                staffTitle.textContent = scopedOnly
                    ? `Your office ${fc} forecast`
                    : 'Consumables PR spending forecast';
            }
        }

        const depts = document.getElementById('fc-panel-depts');
        const overall = document.getElementById('fc-panel-overall');
        const briefingInsideDepts = !!(depts && depts.querySelector('#fc-dept-briefing'));
        if (scopedOnly) {
            if (overall) overall.hidden = true;
            if (depts) depts.hidden = !briefingInsideDepts;
            const picker = document.getElementById('fc-dept-select');
            if (picker && picker.closest('.fcx-toolbar')) {
                picker.closest('.fcx-toolbar').hidden = true;
            }
        } else {
            if (depts) depts.hidden = false;
            if (overall) overall.hidden = false;
            const toolbar = depts && depts.querySelector('.fcx-toolbar');
            if (toolbar) toolbar.hidden = false;
        }

        renderDepts();
    }

    async function loadForecast() {
        const result = await apiGet('forecast.php');
        if (!result.success) {
            showToast(result.message || 'Forecast failed', 'error');
            return;
        }
        applyPayload(result.data || {});
    }

    async function rebuildForecast() {
        if (typeof checkPermission === 'function' && !checkPermission('edit')) return;
        const btn = document.getElementById('forecast-rebuild-btn');
        if (btn) btn.disabled = true;
        try {
            const result = await apiPost('forecast.php', { action: 'rebuild' });
            if (!result.success) {
                showToast(result.message || 'Refresh failed', 'error');
            return;
        }
            applyPayload(result.data || {});
            showToast(result.message || 'Updated', 'success');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function bindUi() {
        document.getElementById('fc-dept-search')?.addEventListener('input', renderDepts);
        document.getElementById('fc-dept-select')?.addEventListener('change', (e) => {
            const v = e.target.value;
            selectedDeptId = v ? Number(v) : null;
            const selected = deptRows.find((r) => Number(r.office_id) === Number(selectedDeptId)) || null;
            renderDeptBriefing(selected);
        });
    }

    window.loadForecast = loadForecast;
    window.rebuildForecast = rebuildForecast;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindUi);
    } else {
        bindUi();
    }
})();
