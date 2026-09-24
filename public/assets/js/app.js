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

const heatmapSearch = {
  data: null,
  matches: [],
  activeIndex: -1,
  hasSearched: false,
};

const ahrefsSearch = {
  data: null,
};

const siteimproveSearch = {
  data: null,
  matches: [],
  activeIndex: -1,
  hasSearched: false,
};

function matchingSnapshots(snapshots, keyword) {
  const terms = keyword.toLowerCase().trim().split(/\s+/).filter(Boolean);
  if (terms.length === 0) return snapshots;

  return snapshots.filter((row) => {
    const searchable = [
      row.name,
      row.url,
      row.id,
      row.status,
      snapshotDevice(row),
    ].filter(Boolean).join(' ').toLowerCase();

    return terms.every((term) => searchable.includes(term));
  });
}

function currentKeyword() {
  return document.getElementById('heatmapKeyword')?.value.trim() || '';
}

function closeSuggestions() {
  const input = document.getElementById('heatmapKeyword');
  const suggestions = document.getElementById('heatmapSuggestions');
  if (!input || !suggestions) return;

  suggestions.hidden = true;
  input.setAttribute('aria-expanded', 'false');
  heatmapSearch.activeIndex = -1;
}

function hideHeatmapResults() {
  const results = document.getElementById('heatmapResults');
  const ahrefs = document.getElementById('ahrefsBlock');
  if (results) results.hidden = true;
  if (ahrefs) ahrefs.hidden = true;
  heatmapSearch.hasSearched = false;
}

function snapshotHost(url) {
  try {
    return new URL(url).hostname.replace(/^www\./, '').toLowerCase();
  } catch (error) {
    return '';
  }
}

function renderAhrefs(snapshots, keyword) {
  const block = document.getElementById('ahrefsBlock');
  const list = document.getElementById('ahrefsList');
  const meta = document.getElementById('ahrefsMeta');
  if (!block || !list || !meta) return;

  const reports = ahrefsSearch.data?.reports || [];
  if (!ahrefsSearch.data || reports.length === 0) {
    block.hidden = true;
    return;
  }

  const hosts = new Set(
    snapshots
      .map((row) => snapshotHost(row.url))
      .filter(Boolean),
  );
  const matches = reports.filter((row) => hosts.has(String(row.domain || '').toLowerCase()));

  if (matches.length === 0) {
    block.hidden = true;
    return;
  }

  const when = ahrefsSearch.data.fetched_at
    ? new Date(ahrefsSearch.data.fetched_at).toLocaleString()
    : 'cached';
  meta.textContent = `${matches.length} Ahrefs report${matches.length === 1 ? '' : 's'} matching “${keyword}” · saved ${when}`;
  list.innerHTML = matches.map((row) => {
    const keywords = (row.top_keywords || []).slice(0, 5).map((item) => {
      const phrase = item.keyword || item.keyword_merged || 'keyword';
      const position = item.best_position != null ? `#${item.best_position}` : '';
      const traffic = item.sum_traffic != null ? `${formatNumber(item.sum_traffic)} traffic` : '';
      return `<span>${escapeHtml([phrase, position, traffic].filter(Boolean).join(' · '))}</span>`;
    }).join('');

    return `<li>
      <strong>${escapeHtml(row.name || row.domain)} <small>Ahrefs</small></strong>
      <a href="${escapeHtml(row.url)}" target="_blank" rel="noopener">${escapeHtml(row.domain)}</a>
      <div class="heatmap-stats">
        <span>DR ${formatNumber(row.domain_rating)}</span>
        <span>Rank ${formatNumber(row.ahrefs_rank)}</span>
        <span>${formatNumber(row.live_backlinks)} live backlinks</span>
        <span>${formatNumber(row.live_refdomains)} ref domains</span>
        <span>${formatNumber(row.organic_keywords)} keywords</span>
        <span>${formatNumber(row.organic_traffic)} org traffic</span>
        <span>$${formatNumber(row.organic_value_usd)}</span>
      </div>
      ${keywords ? `<div class="heatmap-stats">${keywords}</div>` : ''}
    </li>`;
  }).join('');
  block.hidden = false;
}

function setActiveSuggestion(index) {
  const buttons = [...document.querySelectorAll('.heatmap-suggestion')];
  if (buttons.length === 0) return;

  heatmapSearch.activeIndex = (index + buttons.length) % buttons.length;
  buttons.forEach((button, buttonIndex) => {
    button.classList.toggle('active', buttonIndex === heatmapSearch.activeIndex);
  });
  buttons[heatmapSearch.activeIndex].scrollIntoView({ block: 'nearest' });
}

function renderSuggestions() {
  const input = document.getElementById('heatmapKeyword');
  const suggestions = document.getElementById('heatmapSuggestions');
  if (!input || !suggestions || !heatmapSearch.data) return;
  if (input.value.trim() === '') {
    closeSuggestions();
    return;
  }

  heatmapSearch.matches = matchingSnapshots(
    heatmapSearch.data.snapshots || [],
    input.value,
  );
  heatmapSearch.activeIndex = -1;

  suggestions.innerHTML = heatmapSearch.matches.length
    ? heatmapSearch.matches.map((row, index) => `
      <button class="heatmap-suggestion" type="button" role="option" data-index="${index}">
        <strong>${escapeHtml(row.name || 'Untitled snapshot')}</strong>
        <span>${escapeHtml(row.url || `Snapshot #${row.id || '—'}`)}</span>
      </button>
    `).join('')
    : '<p class="heatmap-suggestion-empty">No matching snapshots</p>';

  suggestions.hidden = false;
  input.setAttribute('aria-expanded', 'true');
}

function renderHeatmap(data, keyword = '') {
  const list = document.getElementById('heatmapList');
  const meta = document.getElementById('heatmapMeta');
  const results = document.getElementById('heatmapResults');
  if (!list || !meta || !results) return;

  results.hidden = false;

  if (data.needs_credentials) {
    meta.textContent = 'Add CRAZY_EGG_API_KEY and CRAZY_EGG_API_SECRET to .env, then run php bin/crazyegg.php fetch';
    list.innerHTML = '';
    return;
  }

  const snapshots = matchingSnapshots(data.snapshots || [], keyword);
  const lookingUp = keyword !== '';

  if (!data.ok && data.error) {
    meta.textContent = data.error;
  } else if (lookingUp) {
    meta.textContent = snapshots.length
      ? `${snapshots.length} snapshot${snapshots.length === 1 ? '' : 's'} matching “${keyword}”`
      : `No Crazy Egg snapshots match “${keyword}”`;
  } else {
    const when = data.fetched_at ? new Date(data.fetched_at).toLocaleString() : 'not fetched yet';
    meta.textContent = `${data.total_count || data.count || 0} snapshots · refreshed ${when} · every ${heatmapMinutes} min`;
  }

  if (snapshots.length === 0) {
    list.innerHTML = lookingUp
      ? '<li>No heatmap jobs match that keyword. Try a snapshot name, URL, status, or device.</li>'
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

  renderAhrefs(snapshots, keyword);
}

async function loadHeatmap() {
  try {
    const response = await fetch('/api/heatmap', { cache: 'no-store' });
    heatmapSearch.data = await response.json();
    if (heatmapSearch.data.needs_credentials || heatmapSearch.hasSearched) {
      renderHeatmap(heatmapSearch.data, currentKeyword());
    }
  } catch (error) {
    const meta = document.getElementById('heatmapMeta');
    const results = document.getElementById('heatmapResults');
    if (meta && results) {
      results.hidden = false;
      meta.textContent = 'Could not load saved heatmap JSON.';
    }
  }
}

async function loadAhrefs() {
  try {
    const response = await fetch('/api/ahrefs', { cache: 'no-store' });
    ahrefsSearch.data = await response.json();
    if (heatmapSearch.hasSearched && heatmapSearch.data) {
      renderHeatmap(heatmapSearch.data, currentKeyword());
    }
  } catch (error) {
    ahrefsSearch.data = null;
  }
}

async function loadSemrush() {
  try {
    await fetch('/api/semrush', { cache: 'no-store' });
  } catch (error) {
    // Cached Semrush refresh is triggered by the API; no on-page list yet.
  }
}

document.getElementById('heatmapLookup')?.addEventListener('submit', (event) => {
  event.preventDefault();
  const keyword = currentKeyword();
  if (keyword === '') {
    hideHeatmapResults();
    closeSuggestions();
    return;
  }

  heatmapSearch.hasSearched = true;
  if (heatmapSearch.data) renderHeatmap(heatmapSearch.data, keyword);
  closeSuggestions();
});

document.getElementById('heatmapKeyword')?.addEventListener('input', () => {
  if (!heatmapSearch.data) return;
  hideHeatmapResults();
  renderSuggestions();
});

document.getElementById('heatmapKeyword')?.addEventListener('focus', () => {
  renderSuggestions();
});

document.getElementById('heatmapKeyword')?.addEventListener('keydown', (event) => {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    const direction = event.key === 'ArrowDown' ? 1 : -1;
    const nextIndex = heatmapSearch.activeIndex < 0
      ? (direction === 1 ? 0 : heatmapSearch.matches.length - 1)
      : heatmapSearch.activeIndex + direction;
    setActiveSuggestion(nextIndex);
  } else if (event.key === 'Enter' && heatmapSearch.activeIndex >= 0) {
    event.preventDefault();
    document.querySelectorAll('.heatmap-suggestion')[heatmapSearch.activeIndex]?.click();
  } else if (event.key === 'Escape') {
    closeSuggestions();
  }
});

document.getElementById('heatmapSuggestions')?.addEventListener('click', (event) => {
  const option = event.target.closest('.heatmap-suggestion');
  if (!option) return;

  const row = heatmapSearch.matches[Number(option.dataset.index)];
  const input = document.getElementById('heatmapKeyword');
  if (!row || !input || !heatmapSearch.data) return;

  input.value = row.name || row.url || String(row.id || '');
  input.focus();
  closeSuggestions();
});

function currentSiteimproveKeyword() {
  return document.getElementById('siteimproveKeyword')?.value.trim() || '';
}

function matchingSiteimprove(reports, keyword) {
  const terms = keyword.toLowerCase().trim().split(/\s+/).filter(Boolean);
  if (terms.length === 0) return reports;

  return reports.filter((row) => {
    const searchable = [row.name, row.url, row.id].filter(Boolean).join(' ').toLowerCase();
    return terms.every((term) => searchable.includes(term));
  });
}

function closeSiteimproveSuggestions() {
  const input = document.getElementById('siteimproveKeyword');
  const suggestions = document.getElementById('siteimproveSuggestions');
  if (!input || !suggestions) return;
  suggestions.hidden = true;
  input.setAttribute('aria-expanded', 'false');
  siteimproveSearch.activeIndex = -1;
}

function hideSiteimproveResults() {
  const results = document.getElementById('siteimproveResults');
  if (results) results.hidden = true;
  siteimproveSearch.hasSearched = false;
}

function renderSiteimproveSuggestions() {
  const input = document.getElementById('siteimproveKeyword');
  const suggestions = document.getElementById('siteimproveSuggestions');
  if (!input || !suggestions || !siteimproveSearch.data) return;
  if (input.value.trim() === '') {
    closeSiteimproveSuggestions();
    return;
  }

  siteimproveSearch.matches = matchingSiteimprove(siteimproveSearch.data.reports || [], input.value);
  siteimproveSearch.activeIndex = -1;
  suggestions.innerHTML = siteimproveSearch.matches.length
    ? siteimproveSearch.matches.map((row, index) => `
      <button class="heatmap-suggestion" type="button" role="option" data-index="${index}">
        <strong>${escapeHtml(row.name || 'Untitled site')}</strong>
        <span>${escapeHtml(row.url || `Site #${row.id || '—'}`)}</span>
      </button>
    `).join('')
    : '<p class="heatmap-suggestion-empty">No matching Siteimprove sites</p>';
  suggestions.hidden = false;
  input.setAttribute('aria-expanded', 'true');
}

function renderSiteimprove(keyword = '') {
  const list = document.getElementById('siteimproveList');
  const meta = document.getElementById('siteimproveMeta');
  const results = document.getElementById('siteimproveResults');
  if (!list || !meta || !results) return;

  results.hidden = false;
  const data = siteimproveSearch.data;
  if (!data) {
    meta.textContent = 'Could not load saved Siteimprove JSON.';
    list.innerHTML = '';
    return;
  }

  if (data.needs_credentials) {
    meta.textContent = data.error || 'Add SITEIMPROVE_API_KEY and SITEIMPROVE_USERNAME to .env, then run php bin/siteimprove.php fetch.';
    list.innerHTML = '';
    return;
  }

  const reports = matchingSiteimprove(data.reports || [], keyword);
  if (!data.ok && data.error && reports.length === 0) {
    meta.textContent = data.error;
    list.innerHTML = '<li>No Siteimprove JSON saved yet.</li>';
    return;
  }

  meta.textContent = reports.length
    ? `${reports.length} Siteimprove site${reports.length === 1 ? '' : 's'} matching “${keyword}”`
    : `No Siteimprove sites match “${keyword}”`;

  if (reports.length === 0) {
    list.innerHTML = '<li>No Siteimprove sites match that keyword.</li>';
    return;
  }

  list.innerHTML = reports.map((row) => {
    const url = row.url
      ? `<a href="${escapeHtml(row.url)}" target="_blank" rel="noopener">${escapeHtml(row.url)}</a>`
      : '<span class="heatmap-url">No URL</span>';
    return `<li>
      <strong>${escapeHtml(row.name || 'Untitled site')} <small>Siteimprove</small></strong>
      ${url}
      <div class="heatmap-stats">
        <span>${formatNumber(row.pages)} pages</span>
        <span>${formatNumber(row.broken_links)} broken links</span>
        <span>${formatNumber(row.misspellings)} misspellings</span>
        <span>${formatNumber(row.seo_issues)} SEO issues</span>
        <span>${formatNumber(row.accessibility_issues)} a11y issues</span>
      </div>
    </li>`;
  }).join('');
}

async function loadSiteimprove() {
  try {
    const response = await fetch('/api/siteimprove', { cache: 'no-store' });
    siteimproveSearch.data = await response.json();
    if (siteimproveSearch.data.needs_credentials || siteimproveSearch.hasSearched) {
      renderSiteimprove(currentSiteimproveKeyword());
    }
  } catch (error) {
    siteimproveSearch.data = null;
  }
}

document.getElementById('siteimproveLookup')?.addEventListener('submit', (event) => {
  event.preventDefault();
  const keyword = currentSiteimproveKeyword();
  if (keyword === '') {
    hideSiteimproveResults();
    closeSiteimproveSuggestions();
    return;
  }
  siteimproveSearch.hasSearched = true;
  renderSiteimprove(keyword);
  closeSiteimproveSuggestions();
});

document.getElementById('siteimproveKeyword')?.addEventListener('input', () => {
  hideSiteimproveResults();
  renderSiteimproveSuggestions();
});

document.getElementById('siteimproveKeyword')?.addEventListener('focus', () => {
  renderSiteimproveSuggestions();
});

document.getElementById('siteimproveKeyword')?.addEventListener('keydown', (event) => {
  if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
    event.preventDefault();
    const direction = event.key === 'ArrowDown' ? 1 : -1;
    const nextIndex = siteimproveSearch.activeIndex < 0
      ? (direction === 1 ? 0 : siteimproveSearch.matches.length - 1)
      : siteimproveSearch.activeIndex + direction;
    const buttons = [...document.querySelectorAll('#siteimproveSuggestions .heatmap-suggestion')];
    if (buttons.length === 0) return;
    siteimproveSearch.activeIndex = (nextIndex + buttons.length) % buttons.length;
    buttons.forEach((button, buttonIndex) => {
      button.classList.toggle('active', buttonIndex === siteimproveSearch.activeIndex);
    });
    buttons[siteimproveSearch.activeIndex].scrollIntoView({ block: 'nearest' });
  } else if (event.key === 'Enter' && siteimproveSearch.activeIndex >= 0) {
    event.preventDefault();
    document.querySelectorAll('#siteimproveSuggestions .heatmap-suggestion')[siteimproveSearch.activeIndex]?.click();
  } else if (event.key === 'Escape') {
    closeSiteimproveSuggestions();
  }
});

document.getElementById('siteimproveSuggestions')?.addEventListener('click', (event) => {
  const option = event.target.closest('.heatmap-suggestion');
  if (!option) return;
  const row = siteimproveSearch.matches[Number(option.dataset.index)];
  const input = document.getElementById('siteimproveKeyword');
  if (!row || !input) return;
  input.value = row.name || row.url || String(row.id || '');
  input.focus();
  closeSiteimproveSuggestions();
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('#heatmapLookup')) closeSuggestions();
  if (!event.target.closest('#siteimproveLookup')) closeSiteimproveSuggestions();
});

loadStatus();
setInterval(loadStatus, refreshSeconds * 1000);
loadHeatmap();
loadAhrefs();
loadSemrush();
loadSiteimprove();
setInterval(loadHeatmap, heatmapMinutes * 60 * 1000);
setInterval(() => {
  loadAhrefs();
  loadSemrush();
  loadSiteimprove();
}, 6 * 60 * 60 * 1000);
