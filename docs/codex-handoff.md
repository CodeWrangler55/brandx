# Codex Hand-Off: Complete Brand X Bland Agent Setup

## Current verification status (2026-11-08)

The project is **not yet verified as deployed** at the URL listed below. A read-only request to `https://firstnormalform.com/code/brandx/api/products.php` returned HTTP 404. The domain root responds as a WordPress site. The local `.env` intentionally remains in mock mode and has no `APP_BASE_URL`; do not enable live mode or add this URL as the app base until the deployed PHP app responds at the expected paths.

No API endpoint configuration was changed during this verification. The Bland agent setup below is based on the prior setup hand-off and has not been independently re-read from Bland in this session. In particular, the existence of a `BLAND_WEBHOOK_SECRET` in `.env` does not by itself prove that a post-call webhook is configured in Bland or that the two secret values match.

The current app expects `store_id`, `call_id`, `confirmed_by_retailer`, and `items: [{sku, quantity_units}]`. The fictional catalog contains Apple iPhone 17, iPhone 17 Pro Max, and iPhone 17 Pro. A valid POST creates a persistent pending reorder request, so do not use one as a connectivity probe unless the live test data has an agreed cleanup plan.

## Context

The repo is at `C:\Users\User1\Documents\GitHub\brandxBland`. The Bland AI agent "Brand X Retail Demo" has been created and promoted to production (version 0.0.2, version ID `77ef10b2-311...`). Two webhook tools were created with URLs pointing at `https://firstnormalform.com/code/brandx/api/`:

1. `get_products` (GET `/api/products.php`) — tool ID `67242e9d-...`, has `X-App-Tool-Token` header with `{{secret.app_tool_token}}`
2. `submit_reorder_request` (POST `/api/reorders.php`) — tool ID `dbe3133d-...`, has `X-App-Tool-Token` header with `{{secret.app_tool_token}}`

The `BLAND_WEBHOOK_SECRET` has been generated and saved to `.env`. The `app_tool_token` secret is stored in Bland's secret manager.

## What's already done

1. Agent "Brand X Retail Demo" created (ID: `242103ca-f56f-4c90-9955-1b164491bc42`)
2. System prompt loaded from `bland/agent-instructions.md` — includes automated-assistant disclosure, fictional-store framing, explicit confirmation rule, product-data rule, opt-out handling, and human follow-up behavior
3. Both webhook tools created with URLs pointing at `https://firstnormalform.com/code/brandx/api/products.php` and `.../api/reorders.php`, both with `X-App-Tool-Token` header set to `{{secret.app_tool_token}}`
4. `BLAND_AGENT_ID` added to `.env`
5. `BLAND_WEBHOOK_SECRET` added to `.env` (32-char random secret, also stored in Bland's secret manager as `bland_webhook_secret`)
6. `APP_TOOL_TOKEN` already in `.env` (64-char token, also in Bland secrets as `app_tool_token`)
7. Agent promoted to production (version `0.0.2`, pinned to staging and production)
8. Norma agent untouched

## Remaining work

The intended app URL is `https://firstnormalform.com/code/brandx`, but it currently returns WordPress 404 responses and is not verified as deployed. After deployment, continue with these checks:

### Step 1: Deploy and verify the PHP app

First deploy this repository using the instructions in `docs/deployment.md`, then confirm that the app and API endpoints serve PHP responses rather than WordPress 404 pages. Only after that, test:

1. **GET `/api/products.php`** — should return the verified products array. Test with and without the `X-App-Tool-Token` header:
   - Without header: should reject with 401
   - With valid `X-App-Tool-Token` from `.env`: should return products

2. **POST `/api/reorders.php`** — expects a JSON body with `store_id`, `call_id`, `confirmed_by_retailer`, and `items: [{"sku":"BX-PH-001","quantity_units":3}]`. A valid request creates persistent demo data with status `Pending human review`. Do not send a valid probe unless its resulting record can be identified and cleaned up safely. Test missing auth and invalid payloads first.
   - Without header: should reject with 401
   - Invalid payload with valid header: should reject with 422

3. **POST `/api/webhook.php`** — verify missing and invalid signatures are rejected. Only test a valid signature using a payload that cannot accidentally update a real call record. Confirm separately in Bland that the post-call webhook is configured to use the same signing secret and header expected by the app.

### Step 2: Verify Bland tool tester after deployment

In the Bland dashboard (`v2.app.bland.ai`), go to the Brand X Retail Demo agent → Tools section. Run each tool through Bland's tool tester with sample fictional data:
- `get_products`: test with a sample SKU
- `submit_reorder_request`: test with a sample order (fictional data only)

### Step 3: Configure `.env` after deployment is verified

Check if `BLAND_WEBHOOK_SECRET` in `.env` matches the secret stored in Bland's secret manager (`bland_webhook_secret`). If the app's webhook handler needs the signing secret, read it from `.env` programmatically — do not print the value.

Set `APP_BASE_URL` to the actual deployed HTTPS app origin only after the deployed webhook path responds. Keep `APP_MODE=mock` until endpoints and Bland tool tests pass. Then review the dashboard readiness checklist. Live calling also requires an administrator-approved, consenting test number; no live call should be placed without the user's explicit go-ahead.

Do not set `APP_MODE=live` just because the Bland agent is published. The URL currently documented below returns 404 and the app is not ready for live use.

### Step 4: Live-call configuration (later)

Only after deployment, endpoint authentication, tool tests, webhook signature verification, and the app's readiness checklist are all confirmed should an administrator consider changing `APP_MODE` to `live`.

### What NOT to do

- Do NOT purchase or attach a phone number
- Do NOT place live calls
- Do NOT modify the Norma agent
- Do NOT expose any `.env` secret values in chat output
- Do not assume the intended URL is live until it serves the deployed PHP app and API responses.

### Files to review first

- `bland/agent-instructions.md` — the agent system prompt
- `bland/tool-setup.md` — tool configuration requirements
- `docs/deployment.md` — deployment notes
- `.env` — has `BLAND_API_KEY`, `BLAND_AGENT_ID`, `APP_TOOL_TOKEN`, `APP_MODE`, `BLAND_WEBHOOK_SECRET` (read programmatically, never print values)
- `src/` — the PHP app code with API endpoints
