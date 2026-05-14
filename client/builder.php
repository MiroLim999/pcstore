<?php
/**
 * client/builder.php — PC Builder (ported from standalone prototype).
 * Uses its own layout (purple/Outfit design), not the admin sidebar.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PC Builder — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/builder.css" />
    <script>
      // Apply saved theme
      (function() {
        var theme = localStorage.getItem('builder_theme');
        if (theme === 'dark') document.documentElement.setAttribute('data-builder-theme', 'dark');
      })();
    </script>
    <style>
      /* Builder Dark Mode */
      [data-builder-theme="dark"] {
        --bg: #0f1117;
        --surface: #1a1d27;
        --surface-2: #1f2231;
        --border: rgba(255, 255, 255, 0.08);
        --border-hover: rgba(108, 71, 255, 0.4);
        --shadow-sm: 0 2px 12px rgba(0, 0, 0, 0.3);
        --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.4);
        --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.5);
        --text-primary: #f1f5f9;
        --text-secondary: #94a3b8;
        --text-muted: #64748b;
        --purple-pale: rgba(108, 71, 255, 0.12);
        --purple-soft: rgba(108, 71, 255, 0.08);
      }
      [data-builder-theme="dark"] body { background: var(--bg); color: var(--text-primary); }
      [data-builder-theme="dark"] body::before { background-image: radial-gradient(circle, rgba(108, 71, 255, 0.03) 1px, transparent 1px); }
      [data-builder-theme="dark"] .site-header { background: rgba(15, 17, 23, 0.9); border-bottom-color: var(--border); }
      [data-builder-theme="dark"] .component-card { background: var(--surface); border-color: var(--border); }
      [data-builder-theme="dark"] .component-card:hover { background: var(--surface-2); }
      [data-builder-theme="dark"] .component-card.is-empty { background: var(--surface); }
      [data-builder-theme="dark"] .card-img-wrap { background: var(--surface-2); }
      [data-builder-theme="dark"] .card-img-placeholder { background: rgba(108, 71, 255, 0.08); border-color: var(--border-hover); }
      [data-builder-theme="dark"] .performance-panel,
      [data-builder-theme="dark"] .build-panel,
      [data-builder-theme="dark"] .analysis-panel { background: var(--surface); border-color: var(--border); }
      [data-builder-theme="dark"] .perf-item { background: var(--surface-2); border-color: var(--border); }
      [data-builder-theme="dark"] .build-spec-item,
      [data-builder-theme="dark"] .build-compat-item { background: var(--surface-2); border-color: var(--border); }
      [data-builder-theme="dark"] .build-stat { background: var(--surface-2); border-color: var(--border); }
      [data-builder-theme="dark"] .summary-bar { background: var(--surface); border-top-color: var(--border); }
      [data-builder-theme="dark"] .comp-panel { background: var(--surface); }
      [data-builder-theme="dark"] .comp-search-wrap { background: var(--surface-2); border-color: var(--border); }
      [data-builder-theme="dark"] .comp-search-input { color: var(--text-primary); }
      [data-builder-theme="dark"] .comp-option-card { background: var(--surface-2); border-color: var(--border); }
      [data-builder-theme="dark"] .comp-option-card:hover { border-color: var(--purple); background: rgba(108, 71, 255, 0.1); }
      [data-builder-theme="dark"] .modal { background: var(--surface); }
      [data-builder-theme="dark"] .btn-ghost { border-color: var(--border); color: var(--text-secondary); }
      [data-builder-theme="dark"] .btn-ghost:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
      [data-builder-theme="dark"] .card-add-btn { background: var(--surface-2); border-color: var(--border-hover); color: var(--text-secondary); }
      [data-builder-theme="dark"] .sort-btn { border-color: var(--border); color: var(--text-secondary); }
      [data-builder-theme="dark"] .hero-title { color: var(--text-primary); }
    </style>
  </head>
  <body>
    <!-- ===== HEADER NAV ===== -->
    <header class="site-header">
      <div class="header-inner">
        <div class="logo">
          <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
            <rect width="28" height="28" rx="8" fill="#6C47FF" />
            <path d="M7 14h14M14 7v14" stroke="#fff" stroke-width="2.5" stroke-linecap="round" />
          </svg>
          <span><?= e(APP_NAME) ?></span>
        </div>
        <nav class="nav-links">
          <a href="<?= BASE_URL ?>client/home.php" class="nav-link">Home</a>
          <a href="<?= BASE_URL ?>client/catalog.php" class="nav-link">Catalog</a>
          <a href="<?= BASE_URL ?>client/builder.php" class="nav-link active">Builder</a>
          <a href="<?= BASE_URL ?>client/my_builds.php" class="nav-link">My Builds</a>
        </nav>
        <div class="header-actions">
          <button class="btn-ghost" id="btn-theme-toggle" title="Toggle Dark/Light Mode" style="font-size:1.1rem;padding:0.45rem 0.7rem;">☀️</button>
          <span style="color:var(--text-secondary);font-size:0.85rem;margin-right:8px;">Hi, <?= e($user['name']) ?></span>
          <button class="btn-ghost" id="btn-save">♡ Save Build</button>
          <button class="btn-primary-sm" id="btn-header-build">Send to Cashier</button>
        </div>
      </div>
    </header>

    <!-- ===== MAIN ===== -->
    <main class="page-main">
      <!-- ===== HERO SECTION ===== -->
      <section class="hero-section" aria-label="Build Hero">
        <div class="hero-badge">
          <span class="dot"></span> BALANCED <span class="sep">•</span> RELIABLE
          <span class="sep">•</span> POWERFUL <span class="dot"></span>
        </div>
        <h1 class="hero-title">PC BUILDER</h1>
        <br>
        <p class="hero-subtitle">PERFORMANCE BUILD</p>
      </section>
      <br><br><br>

      <!-- ===== DIAGRAM SECTION ===== -->
      <section class="diagram-section" aria-label="Build Diagram">
        <div class="diagram-wrapper" id="diagram-wrapper">
          <svg class="connector-svg" id="connector-svg" aria-hidden="true"></svg>

          <!-- LEFT COLUMN -->
          <div class="col col-left">
            <div class="component-card is-empty" id="card-cpu" data-dot="right">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/cpu_ryzen.png" alt="No processor selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><rect x="5" y="5" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M8 2v3M12 2v3M8 15v3M12 15v3M2 8h3M2 12h3M15 8h3M15 12h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> PROCESSOR</div>
                <h3 class="card-name">No processor selected</h3>
                <ul class="card-specs"><li>Click add to choose a CPU.</li></ul>
                <button class="card-add-btn" type="button">Add Processor</button>
              </div>
            </div>

            <div class="component-card is-empty" id="card-cooler" data-dot="right">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/cpu_cooler.png" alt="No cooler selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v4l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> CPU COOLER</div>
                <h3 class="card-name">No cooler selected</h3>
                <ul class="card-specs"><li>Click add to choose a cooler.</li></ul>
                <button class="card-add-btn" type="button">Add CPU Cooler</button>
              </div>
            </div>

            <div class="component-card is-empty" id="card-mobo" data-dot="right">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/motherboard.png" alt="No motherboard selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><rect x="3" y="3" width="14" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><rect x="7" y="7" width="6" height="6" rx="1" stroke="currentColor" stroke-width="1.3"/></svg> MOTHERBOARD</div>
                <h3 class="card-name">No motherboard selected</h3>
                <ul class="card-specs"><li>Click add to choose a motherboard.</li></ul>
                <button class="card-add-btn" type="button">Add Motherboard</button>
              </div>
            </div>

            <div class="component-card is-empty" id="card-ram" data-dot="right">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/ram_memory.png" alt="No memory selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><rect x="3" y="7" width="14" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 7V5M10 7V5M13 7V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> MEMORY</div>
                <h3 class="card-name">No memory selected</h3>
                <ul class="card-specs"><li>Click add to choose RAM.</li></ul>
                <button class="card-add-btn" type="button">Add Memory</button>
              </div>
            </div>
          </div>

          <!-- CENTER: PC IMAGE -->
          <div class="col col-center">
            <div class="center-pc-wrap">
              <div class="pc-glow"></div>
              <img src="<?= BASE_URL ?>assets/img/builder/main_pc_build.png" alt="PC Build" class="pc-image" id="pc-image" />
            </div>
          </div>

          <!-- RIGHT COLUMN -->
          <div class="col col-right">
            <div class="component-card is-empty" id="card-gpu" data-dot="left">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/gpu_rtx3060.png" alt="No graphics card selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><rect x="2" y="6" width="16" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/><circle cx="13" cy="10" r="2" stroke="currentColor" stroke-width="1.3"/><circle cx="8" cy="10" r="2" stroke="currentColor" stroke-width="1.3"/></svg> GRAPHICS CARD</div>
                <h3 class="card-name">No graphics card selected</h3>
                <ul class="card-specs"><li>Click add to choose a GPU.</li></ul>
                <button class="card-add-btn" type="button">Add Graphics Card</button>
              </div>
            </div>

            <div class="component-card is-empty" id="card-ssd" data-dot="left">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/nvme_ssd.png" alt="No storage selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><rect x="3" y="6" width="14" height="8" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M7 10h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> STORAGE</div>
                <h3 class="card-name">No storage selected</h3>
                <ul class="card-specs"><li>Click add to choose storage.</li></ul>
                <button class="card-add-btn" type="button">Add Storage</button>
              </div>
            </div>

            <div class="component-card is-empty" id="card-psu" data-dot="left">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/psu_650w.png" alt="No power supply selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><path d="M10 3L5 10h5l-1 7 6-8h-5l1-6z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg> POWER SUPPLY</div>
                <h3 class="card-name">No power supply selected</h3>
                <ul class="card-specs"><li>Click add to choose a PSU.</li></ul>
                <button class="card-add-btn" type="button">Add Power Supply</button>
              </div>
            </div>

            <div class="component-card is-empty" id="card-case" data-dot="left">
              <div class="card-img-wrap">
                <div class="card-img-placeholder" aria-hidden="true">+</div>
                <img src="<?= BASE_URL ?>assets/img/builder/pc_case_white.png" alt="No case selected" class="card-img" />
              </div>
              <div class="card-info">
                <div class="card-label"><svg class="card-icon" viewBox="0 0 20 20" fill="none"><rect x="5" y="2" width="10" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="13" r="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M8 6h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> CASE</div>
                <h3 class="card-name">No case selected</h3>
                <ul class="card-specs"><li>Click add to choose a case.</li></ul>
                <button class="card-add-btn" type="button">Add Case</button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== INSIGHTS SECTION ===== -->
      <section class="insights-section" aria-label="Build Insights">
        <div class="insights-grid">
          <!-- BUILD DETAILS -->
          <div class="build-panel" id="build-panel" aria-live="polite">
            <div class="build-panel-header">
              <div>
                <div class="build-title">BUILD DETAILS</div>
                <div class="build-sub" id="build-summary">0/8 parts selected</div>
              </div>
              <div class="build-actions">
                <button class="build-action-btn" id="btn-reset-build" type="button" title="Clear all selected parts">↺ Reset</button>
              </div>
              <div class="build-stats">
                <div class="build-stat"><span class="build-stat-label">Est. Power</span><span class="build-stat-value" id="stat-power">--</span></div>
                <div class="build-stat"><span class="build-stat-label">Socket</span><span class="build-stat-value" id="stat-socket">--</span></div>
                <div class="build-stat"><span class="build-stat-label">Memory</span><span class="build-stat-value" id="stat-memory">--</span></div>
              </div>
            </div>
            <div class="build-grid">
              <div class="build-section">
                <div class="build-section-title">Selected Components</div>
                <ul class="build-spec-list" id="build-spec-list"></ul>
              </div>
              <div class="build-section">
                <div class="build-section-title">Compatibility Checks</div>
                <ul class="build-compat-list" id="build-compat-list"></ul>
              </div>
            </div>
          </div>

          <!-- PERFORMANCE -->
          <div class="performance-panel">
            <div class="perf-title">PERFORMANCE</div>
            <div class="perf-grid">
              <div class="perf-item"><svg class="perf-icon" viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13v6l5 2.83-1 1.73-6-3.39V7h2z" fill="currentColor"/></svg><span class="perf-main" id="perf-mode-main">Gaming Ready</span><span class="perf-sub" id="perf-mode-sub">Pick a GPU</span></div>
              <div class="perf-item"><svg class="perf-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.8"/><rect x="7" y="9" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.5"/></svg><span class="perf-main" id="perf-cpu-main">CPU</span><span class="perf-sub" id="perf-cpu-sub">Not selected</span></div>
              <div class="perf-item"><svg class="perf-icon" viewBox="0 0 24 24" fill="none"><rect x="2" y="7" width="20" height="10" rx="2" stroke="currentColor" stroke-width="1.8"/><circle cx="17" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5"/><circle cx="10" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5"/></svg><span class="perf-main" id="perf-gpu-main">GPU</span><span class="perf-sub" id="perf-gpu-sub">Not selected</span></div>
              <div class="perf-item"><svg class="perf-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="8" width="18" height="8" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path d="M8 8V6M12 8V6M16 8V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span class="perf-main" id="perf-mem-main">MEMORY</span><span class="perf-sub" id="perf-mem-sub">Not selected</span></div>
              <div class="perf-item"><svg class="perf-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="12" rx="1.5" stroke="currentColor" stroke-width="1.8"/><path d="M7 10h10M7 14h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><span class="perf-main" id="perf-storage-main">STORAGE</span><span class="perf-sub" id="perf-storage-sub">Not selected</span></div>
              <div class="perf-item"><svg class="perf-icon" viewBox="0 0 24 24" fill="none"><path d="M13 2L4 14h7l-1 8 10-12h-7l1-8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg><span class="perf-main" id="perf-power-main">POWER DRAW</span><span class="perf-sub" id="perf-power-sub">Add a CPU + GPU</span></div>
            </div>
            <div class="perf-footer">
              <div class="perf-badge"><span class="perf-badge-label">Recommended</span><span class="perf-badge-value" id="perf-badge-res-val">1080p</span></div>
              <div class="perf-badge"><span class="perf-badge-label">Best for</span><span class="perf-badge-value" id="perf-badge-usecase-val">Gaming</span></div>
              <div class="perf-badge"><span class="perf-badge-label">Cooling</span><span class="perf-badge-value" id="perf-badge-cooling-val">--</span></div>
            </div>
          </div>

          <!-- PERFORMANCE ANALYSIS -->
          <div class="analysis-panel" id="analysis-panel" aria-live="polite">
            <div class="analysis-title">PERFORMANCE ANALYSIS</div>
            <div class="analysis-grid">
              <div class="analysis-section">
                <div class="analysis-section-title">Bottleneck</div>
                <div class="bn-score">
                  <div class="bn-score-ring" id="bn-ring"><span class="bn-score-value" id="bn-pct">--</span><span class="bn-score-unit">%</span></div>
                  <div class="bn-score-meta"><span class="bn-severity" id="bn-severity">Awaiting CPU + GPU</span><span class="bn-limited" id="bn-limited"></span></div>
                </div>
                <div class="bn-strength">
                  <div class="bn-strength-row"><span class="bn-strength-label">CPU</span><div class="bn-strength-bar"><span class="bn-strength-fill cpu" id="bn-cpu-fill"></span></div><span class="bn-strength-val" id="bn-cpu-val">--</span></div>
                  <div class="bn-strength-row"><span class="bn-strength-label">GPU</span><div class="bn-strength-bar"><span class="bn-strength-fill gpu" id="bn-gpu-fill"></span></div><span class="bn-strength-val" id="bn-gpu-val">--</span></div>
                </div>
                <p class="bottleneck-verdict" id="bn-verdict">Pick a CPU and GPU to analyze your build.</p>
              </div>
              <div class="analysis-section">
                <div class="analysis-section-title">Estimated FPS</div>
                <ul class="fps-list" id="fps-list"><li class="fps-row empty"><span class="fps-game">Add a GPU to see FPS estimates.</span></li></ul>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== BUILD SUMMARY BAR ===== -->
      <section class="summary-bar" aria-label="Build Summary">
        <div class="summary-inner">
          <div class="summary-price-block">
            <span class="summary-label">BUILD SUMMARY</span>
            <span class="summary-est">ESTIMATED PRICE</span>
            <span class="summary-price"><span class="currency">₱</span>0 <span class="usd">PHP</span></span>
          </div>
          <div class="summary-features">
            <div class="feature-item"><svg viewBox="0 0 24 24" fill="none"><path d="M6 12c0-3.31 2.69-6 6-6s6 2.69 6 6-2.69 6-6 6-6-2.69-6-6z" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v4l3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><div><strong>Great for</strong><br><span>Gaming &amp; Productivity</span></div></div>
            <div class="feature-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12l7-7 7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><div><strong>Upgrade Ready</strong><br><span>AM4 / LGA1700</span></div></div>
            <div class="feature-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3C7.03 3 3 7.03 3 12s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z" stroke="currentColor" stroke-width="1.8"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><div><strong>Compatibility Checked</strong><br><span>Server-validated</span></div></div>
            <div class="feature-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3l2.09 6.26H21l-5.47 3.97 2.09 6.26L12 15.52l-5.62 3.97 2.09-6.26L3 9.26h6.91L12 3z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg><div><strong>48h Reservation</strong><br><span>Parts held for you</span></div></div>
          </div>
          <button class="btn-build-cta" id="btn-build-cta">SEND TO CASHIER <svg viewBox="0 0 24 24" fill="none"><path d="M6 2h13l-1.5 10H7.5L6 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="9" cy="20" r="1.5" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="20" r="1.5" stroke="currentColor" stroke-width="1.5"/></svg></button>
        </div>
      </section>
    </main>

    <!-- ===== COMPONENT SELECTOR MODAL ===== -->
    <div class="comp-overlay" id="comp-overlay" role="dialog" aria-modal="true" aria-label="Select Component">
      <div class="comp-panel">
        <div class="comp-panel-header">
          <div class="comp-header-left">
            <div class="comp-cat-badge" id="comp-cat-badge"></div>
            <div>
              <p class="comp-cat-label" id="comp-cat-label">PROCESSOR</p>
              <h2 class="comp-panel-title" id="comp-panel-title">Choose a Processor</h2>
            </div>
          </div>
          <button class="comp-close-btn" id="comp-close-btn" aria-label="Close selector">✕</button>
        </div>
        <div class="comp-search-row">
          <div class="comp-search-wrap">
            <svg viewBox="0 0 20 20" fill="none"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.6"/><path d="M13.5 13.5L17 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="text" class="comp-search-input" id="comp-search-input" placeholder="Search…" />
          </div>
          <div class="comp-sort-btns">
            <button class="sort-btn active" id="sort-default" data-sort="default">Default</button>
            <button class="sort-btn" id="sort-asc" data-sort="asc">Price ↑</button>
            <button class="sort-btn" id="sort-desc" data-sort="desc">Price ↓</button>
          </div>
        </div>
        <div class="comp-options-grid" id="comp-options-grid"></div>
      </div>
    </div>

    <!-- ===== SUBMIT CONFIRMATION MODAL ===== -->
    <div class="modal-overlay" id="modal-overlay" role="dialog" aria-modal="true" aria-label="Build Confirmation">
      <div class="modal">
        <button class="modal-close" id="modal-close" aria-label="Close">✕</button>
        <div class="modal-icon">🎉</div>
        <h2 class="modal-title" id="modal-title">Send to Cashier?</h2>
        <p class="modal-body" id="modal-body">
          Your build will be submitted for in-store pickup.<br>
          Total: <strong><span id="modal-total">₱0</span></strong><br>
          <small>Parts will be reserved for 48 hours.</small>
        </p>
        <div class="modal-actions">
          <button class="btn-primary-lg" id="btn-checkout">Confirm & Submit</button>
          <button class="btn-outline-lg" id="btn-continue">Continue Editing</button>
        </div>
      </div>
    </div>

    <!-- Pickup code success modal -->
    <div class="modal-overlay" id="success-overlay" role="dialog" aria-modal="true" aria-label="Build Submitted">
      <div class="modal">
        <div class="modal-icon">✅</div>
        <h2 class="modal-title">Build Submitted!</h2>
        <p class="modal-body" id="success-body">Your pickup code is: <strong id="pickup-code-display">------</strong></p>
        <div class="modal-actions">
          <a href="<?= BASE_URL ?>client/my_builds.php" class="btn-primary-lg" style="text-decoration:none;text-align:center;">View My Builds</a>
          <button class="btn-outline-lg" id="btn-new-build">Start New Build</button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div class="toast" id="toast">♡ Build saved to your list!</div>

    <!-- Pass config to JS -->
    <script>
      const APP_CONFIG = {
        baseUrl: '<?= BASE_URL ?>',
        apiCatalog: '<?= BASE_URL ?>api/catalog.php',
        apiSubmit: '<?= BASE_URL ?>api/submit_build.php',
        apiCompat: '<?= BASE_URL ?>api/check_compat.php',
        currency: '₱',
        currencyCode: 'PHP'
      };
    </script>
    <script src="<?= BASE_URL ?>assets/js/builder.js"></script>
    <script>
      // Dark mode toggle for builder
      (function() {
        var btn = document.getElementById('btn-theme-toggle');
        function updateIcon() {
          var isDark = document.documentElement.getAttribute('data-builder-theme') === 'dark';
          btn.textContent = isDark ? '☀️' : '🌙';
        }
        updateIcon();
        btn.addEventListener('click', function() {
          var isDark = document.documentElement.getAttribute('data-builder-theme') === 'dark';
          if (isDark) {
            document.documentElement.removeAttribute('data-builder-theme');
            localStorage.setItem('builder_theme', 'light');
          } else {
            document.documentElement.setAttribute('data-builder-theme', 'dark');
            localStorage.setItem('builder_theme', 'dark');
          }
          updateIcon();
        });
      })();
    </script>
  </body>
</html>
