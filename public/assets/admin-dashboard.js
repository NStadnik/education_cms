(function () {
    const head = document.querySelector('[data-news-trend]');
    const chart = document.querySelector('[data-trend-chart]');
    if (!head || !chart) return;
    let period = head.querySelector('[data-trend-period].is-active')?.dataset.trendPeriod || '30_days';
    let offset = 0;

    async function load() {
        const compare = head.querySelector('[data-trend-compare]').checked;
        head.classList.add('is-loading');
        try {
            const url = new URL(head.dataset.endpoint, window.location.origin);
            url.searchParams.set('period', period);
            url.searchParams.set('compare', compare ? '1' : '0');
            url.searchParams.set('offset', String(offset));
            const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error('Не вдалося завантажити графік.');
            render(data);
        } catch (error) {
            head.querySelector('[data-trend-summary]').textContent = error.message;
        } finally { head.classList.remove('is-loading'); }
    }

    function render(data) {
        head.querySelector('[data-trend-label]').textContent = data.label;
        head.querySelectorAll('[data-trend-period]').forEach(button => button.classList.toggle('is-active', button.dataset.trendPeriod === data.period));
        head.querySelector('[data-trend-next]').disabled = !data.can_go_next;
        const legend = head.querySelector('[data-trend-legend]');
        legend.replaceChildren(legendItem(data.label, false));
        if (data.compare) legend.append(legendItem(data.previous_label, true));
        const summary = head.querySelector('[data-trend-summary]');
        summary.replaceChildren(metric(data.total + ' публікацій'));
        if (data.compare) summary.append(metric('Попередній: ' + data.previous_total), changeMetric(data.change));
        const max = Math.max(1, ...data.points.flatMap(point => data.compare ? [point.current, point.previous] : [point.current]));
        const bars = document.createElement('div');
        bars.className = 'dashboard-chart-bars';
        bars.style.setProperty('--chart-points', data.points.length);
        data.points.forEach((point, index) => {
            const node = document.createElement('div'); node.className = 'dashboard-chart-point';
            const value = document.createElement('span'); value.className = 'dashboard-chart-value'; value.textContent = point.current || '';
            const pair = document.createElement('span'); pair.className = 'dashboard-chart-pair';
            pair.append(bar(point.current, max, false)); if (data.compare) pair.append(bar(point.previous, max, true));
            const time = document.createElement('time'); time.dateTime = point.date; time.textContent = data.period === 'academic_year' || index % 5 === 0 || index === data.points.length - 1 ? point.label : '';
            node.title = point.label + ': ' + point.current + (data.compare ? ' / попередній: ' + point.previous : '');
            node.append(value, pair, time); bars.append(node);
        });
        chart.querySelector('.dashboard-chart-bars').replaceWith(bars);
    }
    function bar(value, max, previous) { const node = document.createElement('span'); node.className = 'dashboard-chart-bar' + (value ? ' has-value' : '') + (previous ? ' is-previous' : ''); node.style.setProperty('--bar-height', (value / max * 100).toFixed(2) + '%'); return node; }
    function metric(text) { const node = document.createElement('span'); const strong = document.createElement('strong'); strong.textContent = text; node.append(strong); return node; }
    function changeMetric(value) { const node = metric((value > 0 ? '+' : '') + value + '%'); node.classList.add(value > 0 ? 'is-positive' : value < 0 ? 'is-negative' : 'is-neutral'); return node; }
    function legendItem(label, previous) { const node = document.createElement('span'); const dot = document.createElement('i'); if (previous) dot.className = 'is-previous'; node.append(dot, document.createTextNode(label)); return node; }
    head.querySelectorAll('[data-trend-period]').forEach(button => button.addEventListener('click', () => { period = button.dataset.trendPeriod; offset = 0; load(); }));
    head.querySelector('[data-trend-previous]').addEventListener('click', () => { offset = Math.max(-24, offset - 1); load(); });
    head.querySelector('[data-trend-next]').addEventListener('click', () => { offset = Math.min(0, offset + 1); load(); });
    head.querySelector('[data-trend-compare]').addEventListener('change', load);
})();
