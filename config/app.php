<?php

return [
    'site_name' => 'Touro Tracker',
    'target_name' => 'touro.org',
    'target_url' => 'https://www.touro.org',
    'measurement_id' => 'G-XFMLTB1G8M',
    'refresh_seconds' => 15,
    'history_limit' => 40,
    'history_file' => APP_ROOT . '/storage/site-checks/history.json',
    'heatmap_storage' => APP_ROOT . '/storage/heatmap-snapshots',
    'semrush_storage' => APP_ROOT . '/storage/semrush',
    'ahrefs_storage' => APP_ROOT . '/storage/ahrefs',
    'siteimprove_storage' => APP_ROOT . '/storage/siteimprove',
    'timeout_seconds' => 12,
];
