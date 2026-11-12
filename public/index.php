<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
start_app_session();
$authenticated = is_admin();
$csrf = csrf_token();
$appEnv = strtolower((string) config('APP_ENV', 'local'));
$adminPasswordConfigured = trim((string) config('APP_ADMIN_PASSWORD', '')) !== '' || $appEnv === 'local';
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f7f8f5">
      <title>Brand X Mobile · Retail call demo</title>
  <link rel="stylesheet" href="<?= base_url('assets/style.css') ?>">
</head>
<body>
<?php if (!$authenticated): ?>
  <main class="login-wrap">
    <section class="login-card" aria-labelledby="login-title">
      <div class="brand-mark">BX</div>
      <p class="eyebrow">APPLIED AI DEMO</p>
      <h1 id="login-title">Brand X Mobile Retail Call</h1>
      <p class="muted">A simple demo of an AI assistant checking fictional phone inventory and saving reorder requests for human review.</p>
      <?php if (!$adminPasswordConfigured): ?>
        <div class="notice notice-warn">Admin access is not configured. Set <code>APP_ADMIN_PASSWORD</code> in the private <code>.env</code> file.</div>
      <?php else: ?>
        <?php if (!empty($_SESSION['login_error'])): ?><div class="notice notice-error"><?= h((string) $_SESSION['login_error']) ?></div><?php unset($_SESSION['login_error']); endif; ?>
        <form method="post" action="<?= base_url('auth.php') ?>" class="stack-form">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="login">
          <label for="password">Admin password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
          <button class="button button-primary button-wide" type="submit">Open the demo</button>
          <?php if ($appEnv === 'local' && trim((string) config('APP_ADMIN_PASSWORD', '')) === ''): ?><small class="muted">Local default: <code>demo</code>. Set your own password before hosting.</small><?php endif; ?>
        </form>
      <?php endif; ?>
      <p class="tiny-note">Fictional accounts and products · No automatic calling</p>
    </section>
  </main>
<?php else: ?>
  <div class="app-shell" id="app" data-csrf="<?= h($csrf) ?>" data-base="<?= h(base_url('')) ?>">
    <header class="topbar">
      <a class="brand" href="<?= base_url('') ?>" aria-label="Brand X demo home"><span class="brand-mark">BX</span><span>Brand X <small>RETAIL CALL DEMO</small></span></a>
      <div class="topbar-actions"><span class="mode-badge" id="mode-badge">MOCK MODE</span><form method="post" action="<?= base_url('auth.php') ?>"><input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button class="text-button">Sign out</button></form></div>
    </header>

    <main class="main-content">
      <section class="hero">
        <div>
          <p class="eyebrow">A LIVE, END-TO-END WALKTHROUGH</p>
          <h1>Make one retail check-in call.</h1>
          <p class="hero-copy">The assistant checks fictional cell phone inventory, confirms the requested units, and saves a reorder request for a person to review.</p>
          <p class="tiny-note"><strong>Demo catalog:</strong> Apple iPhone 17 · iPhone 17 Pro Max · iPhone 17 Pro</p>
        </div>
        <div class="hero-side"><span class="hero-dot"></span><span><strong>Demo only</strong><small>Fictional store data. Calls go only to numbers you approve.</small></span></div>
      </section>

      <section class="demo-grid">
        <article class="panel call-panel">
          <div class="section-heading"><span class="step-number">1</span><div><h2>Choose the store</h2><p>Use a fictional account for the conversation.</p></div></div>
          <label for="store-select" class="visually-hidden">Fictional cell phone retailer</label>
          <select id="store-select" class="store-select"></select>
          <div class="store-facts" id="store-facts"></div>

          <div class="section-heading second-step"><span class="step-number">2</span><div><h2>Start the call</h2><p>Run the safe simulation or place a live call to an approved test number.</p></div></div>
          <label for="scenario-select" class="field-label">Mock scenario</label>
          <select id="scenario-select" class="control">
            <option value="confirmed_reorder">Requests 3 Apple iPhone 17 units and confirms</option>
            <option value="no_reorder">Has enough phones in stock</option>
            <option value="product_unavailable">Asks for unavailable iPhone 17 Pro</option>
            <option value="human_follow_up">Asks for pricing or delivery terms</option>
            <option value="retailer_busy">Is busy and asks to follow up later</option>
          </select>
          <button type="button" id="mock-call" class="button button-secondary button-wide">Run mock demo</button>

          <div class="live-call" id="live-call" hidden>
            <div class="divider-label"><span>OR PLACE A LIVE CALL</span></div>
            <label for="test-number-select" class="field-label">Approved demo phone</label>
            <select id="test-number-select" class="control"></select>
            <button type="button" id="live-call-button" class="button button-primary button-wide">Start live Bland call</button>
            <p class="tiny-note">This places one real call and may use Bland credits. The assistant identifies itself as automated and calls about the selected demo account. The mock scenario selector above does not control a live call.</p>
            <div class="script-card"><strong>Ideas for the conversation</strong><p>Answer naturally. You can try any of these:</p><ul>
              <li>“We’re almost out of iPhone 17s. What’s available?”</li>
              <li>“We have enough for now, thanks.”</li>
              <li>“Do you carry iPhone 17 Pro?”</li>
              <li>“Can you offer a discount or guarantee delivery?”</li>
              <li>“I’m busy. Could someone follow up?”</li>
            </ul><p>If you request a reorder, the assistant will read back the item and quantity and ask you to confirm before it submits the request for human review.</p></div>
          </div>

          <div id="call-feedback" class="feedback" role="status" aria-live="polite"></div>
          <div class="script-card"><strong>Mock demo cue</strong><p>The selector above chooses the simulated retailer response. For a reorder, choose the first scenario to see the confirmation and human-review flow.</p></div>
        </article>

        <article class="panel result-panel">
          <div class="section-heading"><span class="step-number">3</span><div><h2>See what happened</h2><p>A successful request is saved as pending human review.</p></div></div>
          <div id="latest-result" class="empty-state"><div class="empty-icon">↗</div><strong>Your call result will appear here</strong><span>Run the mock demo to see the full flow instantly.</span></div>
          <div id="result-content" hidden></div>
          <div class="truth-note"><span>✓</span><p><strong>Clear success signal</strong><br>A saved request is not a purchase order. A completed call without a saved request is never reported as a reorder.</p></div>
        </article>
      </section>

      <section class="panel setup-panel">
        <button class="setup-toggle" type="button" id="setup-toggle" aria-expanded="true" aria-controls="setup-content"><span><span class="step-number step-muted">⚙</span><span><strong>Live demo setup</strong><small>Check what Bland still needs before a real call.</small></span></span><span class="chevron">⌃</span></button>
        <div id="setup-content" class="setup-content">
          <div id="setup-checks" class="setup-checks"></div>
          <div id="number-manager" class="number-manager">
            <div class="number-manager-title"><strong>Approved test numbers</strong><span>Only these numbers can receive live calls.</span></div>
            <form id="number-form" class="number-form">
              <input class="control" name="label" maxlength="60" placeholder="Label (e.g. John Doe’s phone)" aria-label="Label for approved test phone" required>
              <input class="control" name="phone_number" type="tel" placeholder="+1 415 555 0123" aria-label="Approved test phone number" required>
              <label class="consent-check"><input name="consent_confirmed" type="checkbox" required><span>I own this number or its owner agreed to this demo call.</span></label>
              <button class="button button-outline" type="submit">Approve number</button>
            </form>
            <div id="approved-numbers" class="approved-numbers"></div>
          </div>
          <p class="setup-hint">To enable live mode, set <code>APP_MODE=live</code>, <code>BLAND_AGENT_ID</code>, <code>APP_BASE_URL</code>, and <code>APP_TOOL_TOKEN</code> in <code>.env</code>. Publish your agent and configure its two tools in Bland. Live calls also need an HTTPS webhook signing secret.</p>
        </div>
      </section>

      <section class="history-grid">
        <article class="panel history-panel"><div class="history-title"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>Call history</h2></div><button type="button" id="refresh-activity" class="text-button">Refresh</button></div><div id="calls-list" class="activity-list"><p class="muted">Loading calls…</p></div></article>
        <article class="panel history-panel"><div class="history-title"><div><p class="eyebrow">HUMAN REVIEW QUEUE</p><h2>Reorder requests</h2></div></div><div id="reorders-list" class="activity-list"><p class="muted">Loading requests…</p></div></article>
      </section>

      <footer class="footer-note">Brand X Mobile and the retailers are fictional. This demo makes no real purchase orders and never calls automatically.</footer>
    </main>
  </div>
  <script src="<?= base_url('assets/app.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
