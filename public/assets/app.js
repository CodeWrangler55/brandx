(() => {
  const app = document.getElementById('app');
  if (!app) return;
  const csrf = app.dataset.csrf;
  const base = app.dataset.base || '';
  const $ = (selector) => document.querySelector(selector);
  const state = { stores: [], numbers: [], selectedStore: null };
  const feedback = $('#call-feedback');

  async function api(path, options = {}) {
    const response = await fetch(`${base}/api/${path}`, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, ...(options.headers || {}) },
      ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) throw new Error(data.error || `Request failed (${response.status})`);
    return data;
  }

  function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function showFeedback(message, type = '') {
    feedback.textContent = message;
    feedback.className = `feedback ${type}`;
  }

  function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.valueOf()) ? '—' : date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function storeChanged() {
    state.selectedStore = state.stores.find((store) => store.store_id === $('#store-select').value) || null;
    const host = $('#store-facts');
    host.replaceChildren();
    if (!state.selectedStore) return;
    const details = [
      ['Contact', state.selectedStore.contact_name],
      ['Location', state.selectedStore.location],
      ['Last order', state.selectedStore.previous_order_date],
    ];
    details.forEach(([label, value]) => {
      const item = el('div', 'fact');
      item.append(el('span', '', label), el('strong', '', value || '—'));
      host.append(item);
    });
  }

  function renderStores(stores) {
    state.stores = stores;
    const select = $('#store-select');
    select.replaceChildren();
    stores.forEach((store) => {
      const option = document.createElement('option');
      option.value = store.store_id;
      option.textContent = `${store.store_name} · ${store.store_id}`;
      select.append(option);
    });
    select.addEventListener('change', storeChanged);
    storeChanged();
  }

  function renderChecks(checks, ready, mode) {
    const host = $('#setup-checks');
    host.replaceChildren();
    const items = [
      ['API key in .env', checks.api_key],
      ['Published Bland agent ID', checks.agent_id],
      ['Public HTTPS app address', checks.https_webhook],
      ['Bland tool token', checks.tool_token],
      ['Approved demo phone', checks.approved_numbers],
      ['Webhook signing secret', checks.webhook_signature],
    ];
    items.forEach(([label, okay]) => {
      const item = el('div', `check-item ${okay ? 'check-ok' : ''}`);
      item.append(el('span', 'check-icon', okay ? '✓' : '·'), el('span', '', label));
      host.append(item);
    });
    const badge = $('#mode-badge');
    badge.textContent = mode === 'live' ? (ready ? 'LIVE READY' : 'LIVE SETUP INCOMPLETE') : 'MOCK MODE';
    badge.classList.toggle('mode-live', mode === 'live');
    $('#live-call').hidden = !(mode === 'live' && ready);
    if (mode === 'live' && !ready) {
      $('#live-call').hidden = true;
      showFeedback('Live mode is selected, but the setup checklist is incomplete. Mock mode is still available.');
    }
  }

  function renderNumbers(numbers) {
    state.numbers = numbers;
    const select = $('#test-number-select');
    select.replaceChildren();
    numbers.forEach((number) => {
      const option = document.createElement('option');
      option.value = number.id;
      option.textContent = `${number.label} · ${number.phone_number}`;
      select.append(option);
    });
    const host = $('#approved-numbers');
    host.replaceChildren();
    if (!numbers.length) {
      host.append(el('p', 'muted number-empty', 'No approved numbers yet. Add a number only after its owner agrees to the demo call.'));
      return;
    }
    numbers.forEach((number) => {
      const row = el('div', 'approved-number');
      const details = el('div', '');
      details.append(el('strong', '', number.label), el('span', '', number.phone_number));
      const remove = el('button', 'text-button danger-button', 'Remove');
      remove.type = 'button';
      remove.addEventListener('click', async () => {
        if (!window.confirm(`Remove ${number.label} from approved demo numbers?`)) return;
        try {
          const updated = await api('test-numbers.php', { method: 'DELETE', body: JSON.stringify({ id: number.id }) });
          renderNumbers(updated.numbers);
          refreshStatus();
        } catch (error) { showFeedback(error.message, 'error'); }
      });
      row.append(details, remove);
      host.append(row);
    });
  }

  function renderTranscript(transcript) {
    const list = el('div', 'transcript');
    const entries = Array.isArray(transcript) ? transcript : [];
    if (!entries.length && typeof transcript === 'string' && transcript) {
      list.append(el('p', 'transcript-line', transcript));
    } else {
      entries.forEach((entry) => {
        const speaker = entry.speaker || entry.role || 'Call';
        const line = el('div', 'transcript-line');
        line.append(el('small', '', String(speaker)), el('p', '', String(entry.text || entry.content || '')));
        list.append(line);
      });
    }
    return list;
  }

  function renderResult(call, reorder, simulated) {
    $('#latest-result').hidden = true;
    const host = $('#result-content');
    host.hidden = false;
    host.replaceChildren();
    const banner = el('div', reorder ? 'result-banner result-success' : 'result-banner result-neutral');
    banner.append(el('span', 'result-check', reorder ? '✓' : '•'), el('div', '', ''));
    const copy = banner.lastChild;
    copy.append(el('strong', '', reorder ? 'Reorder request saved' : 'Call finished — no reorder saved'));
    copy.append(el('span', '', reorder ? 'Pending human review · not a purchase order' : (call.follow_up_required ? 'A human follow-up is flagged' : 'No request was submitted')));
    host.append(banner);
    const metadata = el('div', 'result-meta');
    metadata.append(el('span', '', `${call.store_name || state.selectedStore?.store_name || 'Demo store'} · ${simulated ? 'Simulated' : 'Bland call'}`));
    metadata.append(el('span', '', `Call ID: ${call.bland_call_id || call.local_call_id || 'pending'}`));
    if (reorder) metadata.append(el('span', '', `${reorder.request_id} · ${reorder.items.map((item) => `${item.quantity_units} ${item.quantity_units === 1 ? 'unit' : 'units'} ${item.name || item.sku}`).join(', ')}`));
    host.append(metadata);
    const transcript = renderTranscript(call.transcript);
    if (transcript.childElementCount) host.append(transcript);
  }

  function renderList(hostId, entries, emptyText, kind) {
    const host = $(hostId);
    host.replaceChildren();
    if (!entries.length) {
      host.append(el('p', 'muted', emptyText));
      return;
    }
    entries.forEach((entry) => {
      const row = el('div', 'activity-row');
      const main = el('div', '');
      const title = kind === 'call' ? `${entry.store_name || entry.store_id} · ${entry.mode === 'mock' ? 'Mock' : 'Live'}` : `${entry.request_id} · ${entry.store_id}`;
      main.append(el('strong', '', title));
      const detail = kind === 'call'
        ? `${entry.status || 'queued'}${entry.follow_up_required ? ' · follow-up needed' : ''}${entry.summary ? ` · ${entry.summary}` : ''}`
        : `${entry.items.map((item) => `${item.quantity_units} × ${item.name || item.sku}`).join(', ')} · ${entry.status}`;
      main.append(el('span', '', detail));
      row.append(main, el('time', '', formatDate(entry.completed_at || entry.created_at)));
      host.append(row);
    });
  }

  async function refreshStatus() {
    const data = await api('status.php');
    renderChecks(data.checks, data.live_ready, data.mode);
  }

  async function refreshActivity() {
    const data = await api('activity.php');
    renderList('#calls-list', data.calls, 'No calls yet. Your first demo will appear here.', 'call');
    renderList('#reorders-list', data.reorders, 'No reorder requests yet.', 'reorder');
  }

  async function waitForLiveResult(localCallId) {
    for (let attempt = 0; attempt < 24; attempt += 1) {
      await new Promise((resolve) => window.setTimeout(resolve, 5000));
      try {
        const data = await api('activity.php');
        const call = data.calls.find((item) => item.local_call_id === localCallId);
        if (!call) continue;
        const reorder = data.reorders.find((item) => item.call_id === call.bland_call_id) || null;
        const terminal = ['completed', 'complete', 'failed', 'error'].includes(String(call.status).toLowerCase());
        if (terminal || call.summary || (Array.isArray(call.transcript) && call.transcript.length)) {
          renderResult(call, reorder, false);
          showFeedback(reorder ? 'Call results received. Reorder request saved for human review.' : 'Call results received. No reorder request was saved.', reorder ? 'success' : '');
          renderList('#calls-list', data.calls, 'No calls yet.', 'call');
          renderList('#reorders-list', data.reorders, 'No reorder requests yet.', 'reorder');
          return;
        }
      } catch (_) {
        // Keep the queued result visible; the manual Refresh button remains available.
      }
    }
    showFeedback('The call request was accepted. Results have not arrived yet; use Refresh to check again.');
  }

  $('#mock-call').addEventListener('click', async () => {
    const button = $('#mock-call');
    button.disabled = true;
    showFeedback('Running the simulated conversation…');
    try {
      const data = await api('demo.php', { method: 'POST', body: JSON.stringify({ store_id: $('#store-select').value, scenario: $('#scenario-select').value }) });
      renderResult(data.call, data.reorder, true);
      showFeedback('Mock demo complete. No phone call was placed.', 'success');
      await refreshActivity();
    } catch (error) { showFeedback(error.message, 'error'); }
    finally { button.disabled = false; }
  });

  $('#live-call-button').addEventListener('click', async () => {
    const phone = state.numbers.find((item) => item.id === $('#test-number-select').value);
    if (!phone || !window.confirm(`Place a live Bland call to ${phone.label} (${phone.phone_number}) about the demo account ${state.selectedStore?.store_name}? This may use Bland credits.`)) return;
    const button = $('#live-call-button');
    button.disabled = true;
    showFeedback('Sending the call request to Bland…');
    try {
      const data = await api('call.php', { method: 'POST', body: JSON.stringify({ store_id: $('#store-select').value, test_number_id: phone.id }) });
      renderResult(data.call, null, false);
      showFeedback(data.message, 'success');
      await refreshActivity();
      waitForLiveResult(data.call.local_call_id);
    } catch (error) { showFeedback(error.message, 'error'); }
    finally { button.disabled = false; }
  });

  $('#number-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.consent_confirmed = payload.consent_confirmed === 'on';
    try {
      const data = await api('test-numbers.php', { method: 'POST', body: JSON.stringify(payload) });
      form.reset();
      renderNumbers(data.numbers);
      await refreshStatus();
      showFeedback('Approved test number saved. Live calls remain limited to this list.', 'success');
    } catch (error) { showFeedback(error.message, 'error'); }
  });

  $('#refresh-activity').addEventListener('click', refreshActivity);
  $('#setup-toggle').addEventListener('click', () => {
    const expanded = $('#setup-toggle').getAttribute('aria-expanded') === 'true';
    $('#setup-toggle').setAttribute('aria-expanded', String(!expanded));
    $('#setup-content').hidden = expanded;
    $('.chevron').textContent = expanded ? '⌄' : '⌃';
  });

  async function init() {
    try {
      const [accounts, numbers] = await Promise.all([api('accounts.php'), api('test-numbers.php')]);
      renderStores(accounts.stores);
      renderNumbers(numbers.numbers);
      await Promise.all([refreshStatus(), refreshActivity()]);
    } catch (error) {
      showFeedback(`Could not load demo data: ${error.message}`, 'error');
    }
  }
  init();
})();
