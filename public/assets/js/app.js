const refreshSeconds = Number(document.body.dataset.refresh || 15);
const heatmapMinutes = Number(document.body.dataset.heatmapMinutes || 15);

const els = {
  liveDot: document.getElementById('liveDot'),
  refreshMeta: document.getElementById('refreshMeta'),
  statusValue: document.getElementById('statusValue'),
  speedValue: document.getElementById('speedValue'),
  uptimeValue: document.getElementById('uptimeValue'),
  avgValue: document.getElementById('avgValue'),
  httpValue: document.getElementById('httpValue'),
  finalUrl: document.getElementById('finalUrl'),
  summaryValue: document.getElementById('summaryValue'),
  sslValue: document.getElementById('sslValue'),
  checkedValue: document.getElementById('checkedValue'),
  spark: document.getElementById('spark'),
};

function formatTime(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function formatNumber(value) {
  if (value == null) return '—';
  return Number(value).toLocaleString();
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function drawSpark(history) {
  const points = (history || []).map((row) => Number(row.response_ms) || 0);
  if (points.length < 2) {
    els.spark.innerHTML = '';
    return;
  }

  const max = Math.max(...points, 1);
  const width = 300;
  const height = 54;
  const step = width / (points.length - 1);
  const path = points
    .map((value, index) => {
      const x = index * step;
      const y = height - (value / max) * 46 - 4;
      return `${index === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');

  els.spark.innerHTML = `
    <path d="${path}" fill="none" stroke="#c9a227" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"></path>
  `;
}

async function loadStatus() {
  els.refreshMeta.textContent = 'Checking…';
  try {
    const response = await fetch('/api/status', { cache: 'no-store' });
    const data = await response.json();
    const online = Boolean(data.ok);

    els.liveDot.classList.toggle('on', online);
    els.liveDot.classList.toggle('off', !online);
    els.statusValue.innerHTML = `<span class="status-pill${online ? '' : ' down'}">${online ? 'Online' : 'Down'}</span>`;
    els.speedValue.textContent = data.response_ms != null ? `${data.response_ms} ms` : '—';
    els.uptimeValue.textContent = data.averages?.uptime != null ? `${data.averages.uptime}%` : '—';
    els.avgValue.textContent = data.averages?.avg_ms != null ? `${data.averages.avg_ms} ms` : '—';
    els.httpValue.textContent = data.status_code ? String(data.status_code) : (data.error || 'No response');
    els.finalUrl.textContent = data.final_url || data.target_url || '—';
    els.summaryValue.textContent = data.summary || data.error || 'No details yet.';
    els.sslValue.textContent = data.ssl
      ? `${data.ssl.valid ? 'Valid' : 'Issue'} · ${data.ssl.issuer || 'unknown issuer'} · exp ${data.ssl.expires || 'n/a'}`
      : 'No certificate details';
    els.checkedValue.textContent = formatTime(data.checked_at);
    els.refreshMeta.textContent = `Every ${refreshSeconds}s`;
    drawSpark(data.history);
  } catch (error) {
    els.liveDot.classList.remove('on');
    els.liveDot.classList.add('off');
    els.statusValue.innerHTML = '<span class="status-pill down">Error</span>';
    els.refreshMeta.textContent = 'Retrying…';
    els.summaryValue.textContent = 'Could not reach the local tracker API.';
  }
}

function snapshotDevice(row) {
  return row.raw?.device || row.device || '';
}

function renderHeatmap(data) {
  const list = document.getElementById('heatmapList');
  const meta = document.getElementById('heatmapMeta');
  if (!list || !meta) return;

  if (data.needs_credentials) {
    meta.textContent = 'Add CRAZY_EGG_API_KEY and CRAZY_EGG_API_SECRET to .env, then run php bin/crazyegg.php fetch';
    list.innerHTML = '';
    return;
  }

  const snapshots = data.snapshots || [];
  const lookingUp = Boolean(data.lookup_url);

  if (!data.ok && data.error) {
    meta.textContent = data.error;
  } else if (lookingUp) {
    meta.textContent = snapshots.length
      ? `${snapshots.length} snapshot${snapshots.length === 1 ? '' : 's'} for ${data.lookup_url}`
      : `No Crazy Egg snapshots for ${data.lookup_url}`;
  } else {
    const when = data.fetched_at ? new Date(data.fetched_at).toLocaleString() : 'not fetched yet';
    meta.textContent = `${data.total_count || data.count || 0} snapshots · refreshed ${when} · every ${heatmapMinutes} min`;
  }

  if (snapshots.length === 0) {
    list.innerHTML = lookingUp
      ? '<li>No heatmap jobs match that URL. Check spelling, or create a snapshot for it in Crazy Egg.</li>'
      : '<li>No snapshot JSON saved yet.</li>';
    return;
  }

  list.innerHTML = snapshots.map((row) => {
    const url = row.url
      ? `<a href="${escapeHtml(row.url)}" target="_blank" rel="noopener">${escapeHtml(row.url)}</a>`
      : '<span class="heatmap-url">No URL</span>';
    const dates = [row.started_at, row.ended_at, row.created_at].filter(Boolean).join(' → ');
    const device = snapshotDevice(row);
    return `<li>
      <strong>${escapeHtml(row.name || 'Untitled snapshot')} <small>#${escapeHtml(row.id || '—')}</small></strong>
      ${url}
      <div class="heatmap-stats">
        <span>${escapeHtml(row.status || 'unknown')}</span>
        ${device ? `<span>${escapeHtml(device)}</span>` : ''}
        <span>${formatNumber(row.visits)} visits</span>
        <span>${formatNumber(row.clicks)} clicks</span>
        <span>${escapeHtml(dates || 'no dates')}</span>
      </div>
    </li>`;
  }).join('');
}

async function loadHeatmap() {
  const input = document.getElementById('heatmapUrl');
  const url = input?.value.trim() || '';
  const query = url ? `?url=${encodeURIComponent(url)}` : '';
  try {
    const response = await fetch('/api/heatmap' + query, { cache: 'no-store' });
    renderHeatmap(await response.json());
  } catch (error) {
    const meta = document.getElementById('heatmapMeta');
    if (meta) meta.textContent = 'Could not load saved heatmap JSON.';
  }
}

document.getElementById('heatmapLookup')?.addEventListener('submit', (event) => {
  event.preventDefault();
  loadHeatmap();
});

loadStatus();
setInterval(loadStatus, refreshSeconds * 1000);
loadHeatmap();
setInterval(loadHeatmap, heatmapMinutes * 60 * 1000);
