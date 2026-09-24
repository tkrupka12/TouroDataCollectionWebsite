<?php

$config = require APP_ROOT . '/config/app.php';
$measurementId = htmlspecialchars($config['measurement_id'], ENT_QUOTES, 'UTF-8');
$targetName = htmlspecialchars($config['target_name'], ENT_QUOTES, 'UTF-8');
$targetUrl = htmlspecialchars($config['target_url'], ENT_QUOTES, 'UTF-8');
$refreshSeconds = (int) $config['refresh_seconds'];
$heatmapMinutes = max(1, (int) (env_value('CRAZY_EGG_REFRESH_MINUTES', '15') ?? '15'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site_name']) ?> · <?= $targetName ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $measurementId ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?= $measurementId ?>');
    </script>
</head>
<body
    data-refresh="<?= $refreshSeconds ?>"
    data-heatmap-minutes="<?= $heatmapMinutes ?>"
    data-target-name="<?= $targetName ?>"
    data-target-url="<?= $targetUrl ?>"
>
    <main class="workspace">
        <div class="source-panels">
        <section class="heatmap-panel" aria-label="Crazy Egg heatmap snapshots">
            <div class="heatmap-head">
                <p class="eyebrow">Heatmap</p>
                <h2>Crazy Egg snapshots</h2>
                <form class="heatmap-lookup" id="heatmapLookup">
                    <label for="heatmapKeyword">Search snapshots by keyword</label>
                    <div class="heatmap-lookup-row">
                        <div class="heatmap-search-box">
                            <input
                                id="heatmapKeyword"
                                name="keyword"
                                type="search"
                                placeholder="Start typing a name or URL…"
                                autocomplete="off"
                                spellcheck="false"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-controls="heatmapSuggestions"
                                aria-expanded="false"
                            >
                            <div class="heatmap-suggestions" id="heatmapSuggestions" role="listbox" hidden></div>
                        </div>
                        <button type="submit">Search</button>
                    </div>
                </form>
            </div>
            <div class="heatmap-results" id="heatmapResults" hidden>
                <p class="heatmap-meta" id="heatmapMeta"></p>
                <ul class="heatmap-list" id="heatmapList"></ul>
                <div class="ahrefs-block" id="ahrefsBlock" hidden>
                    <p class="heatmap-meta" id="ahrefsMeta"></p>
                    <ul class="heatmap-list" id="ahrefsList"></ul>
                </div>
                <p class="heatmap-note">
                    This lists snapshot jobs (URL, status, visits). Crazy Egg does not return the colored heatmap image here — export CSV/JSON from the dashboard for click-level data.
                </p>
            </div>
        </section>

        <section class="heatmap-panel" aria-label="Siteimprove site quality">
            <div class="heatmap-head">
                <p class="eyebrow">Quality</p>
                <h2>Siteimprove</h2>
                <form class="heatmap-lookup" id="siteimproveLookup">
                    <label for="siteimproveKeyword">Search Siteimprove sites</label>
                    <div class="heatmap-lookup-row">
                        <div class="heatmap-search-box">
                            <input
                                id="siteimproveKeyword"
                                name="keyword"
                                type="search"
                                placeholder="Start typing a site name or URL…"
                                autocomplete="off"
                                spellcheck="false"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-controls="siteimproveSuggestions"
                                aria-expanded="false"
                            >
                            <div class="heatmap-suggestions" id="siteimproveSuggestions" role="listbox" hidden></div>
                        </div>
                        <button type="submit">Search</button>
                    </div>
                </form>
            </div>
            <div class="heatmap-results" id="siteimproveResults" hidden>
                <p class="heatmap-meta" id="siteimproveMeta"></p>
                <ul class="heatmap-list" id="siteimproveList"></ul>
                <p class="heatmap-note">
                    Cached Siteimprove quality, SEO, and accessibility summaries. Refresh with php bin/siteimprove.php fetch.
                </p>
            </div>
        </section>
        </div>

        <header class="topbar">
            <div>
                <p class="eyebrow">Practice dashboard</p>
                <h1>Website tracker</h1>
                <p class="lede">
                    Live <?= $targetName ?> status stays in the side panel.
                    This main area is reserved for additional sites and metrics later.
                </p>
            </div>
        </header>

        <section class="grid" aria-label="Open tracker slots">
            <article class="card add-slot">
                <div>
                    <h2>Add another site</h2>
                    <p>Leave this column open for a second domain, campaign, or uptime check.</p>
                </div>
                <div class="plus" aria-hidden="true">+</div>
            </article>
            <article class="card add-slot">
                <div>
                    <h2>Traffic notes</h2>
                    <p>Use this space later for page-level notes, events, or a second measurement stream.</p>
                </div>
                <div class="plus" aria-hidden="true">+</div>
            </article>
            <article class="card add-slot">
                <div>
                    <h2>Custom metric</h2>
                    <p>Room for forms, conversions, or anything else you want to watch beside <?= $targetName ?>.</p>
                </div>
                <div class="plus" aria-hidden="true">+</div>
            </article>
        </section>
    </main>

    <aside class="sidebar" aria-label="<?= $targetName ?> live tracker">
        <div class="live-row">
            <span class="pulse"><span class="dot" id="liveDot"></span> Live</span>
            <span class="refresh-meta" id="refreshMeta">Checking…</span>
        </div>
        <h2><?= $targetName ?></h2>
        <a class="target-link" id="targetLink" href="<?= $targetUrl ?>" target="_blank" rel="noopener"><?= $targetUrl ?></a>

        <div class="stat-grid">
            <div class="stat"><span>Status</span><strong id="statusValue">—</strong></div>
            <div class="stat"><span>Response</span><strong id="speedValue">—</strong></div>
            <div class="stat"><span>Uptime</span><strong id="uptimeValue">—</strong></div>
            <div class="stat"><span>Avg speed</span><strong id="avgValue">—</strong></div>
        </div>

        <svg class="spark" id="spark" viewBox="0 0 300 54" preserveAspectRatio="none" aria-hidden="true"></svg>

        <dl class="details">
            <div>
                <dt>HTTP</dt>
                <dd id="httpValue">—</dd>
            </div>
            <div>
                <dt>Final URL</dt>
                <dd id="finalUrl">—</dd>
            </div>
            <div>
                <dt>What we see</dt>
                <dd id="summaryValue">—</dd>
            </div>
            <div>
                <dt>SSL</dt>
                <dd id="sslValue">—</dd>
            </div>
            <div>
                <dt>Measurement ID</dt>
                <dd><?= $measurementId ?></dd>
            </div>
            <div>
                <dt>Last check</dt>
                <dd id="checkedValue">—</dd>
            </div>
        </dl>
        <p class="sidebar-note">
            This panel refreshes on its own. Google Analytics tag <?= $measurementId ?> is active on this dashboard so visits here are counted live.
        </p>
    </aside>

    <script src="/assets/js/app.js"></script>
</body>
</html>
