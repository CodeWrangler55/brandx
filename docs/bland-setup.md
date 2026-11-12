# Bland agent setup

This guide configures the Bland voice agent and connects it to the Brand X PHP application. Keep the app in mock mode until the public HTTPS endpoints and Bland tools are configured and verified.

## Requirements

- A Bland account and a published agent with a production version.
- A deployed PHP application reachable over HTTPS.
- An administrator-approved test number whose owner has agreed to receive the demonstration call.
- Server-side values for `BLAND_API_KEY`, `BLAND_AGENT_ID`, `APP_TOOL_TOKEN`, `BLAND_WEBHOOK_SECRET`, and `APP_BASE_URL`.

Bland plan availability and interface labels can change. Confirm the current plan requirements in the Bland dashboard before enabling features that may be restricted.

## 1. Create and configure the agent

Create an agent in the Bland dashboard or with the v2 Agents API. Save its identifier as `BLAND_AGENT_ID` in the app's private `.env` file.

Configure the agent with:

- A clear display name, such as **Brand X Retail Demo**.
- The prompt in [../bland/agent-instructions.md](../bland/agent-instructions.md). It covers automated-assistant and demo disclosure, product lookup, explicit reorder confirmation, opt-out handling, and human follow-up.
- A supported voice and English language setting. Set the first message so the agent speaks when the call connects. Use the concise suggested greeting in the prompt and keep the spoken disclosure to “automated assistant” and “demo.”
- The prompt requests a brisk delivery, but Bland's documented call settings do not expose a numerical speech-rate multiplier. Preview different voices and tune the prompt for concise phrasing; don't confuse speaking rate with response delay or interruption settings.
- A behavior graph containing a start node connected to a scenario node. Set the scenario rule to the agent prompt and configure its entry behavior to begin the dialogue when the call connects.

A call may connect and produce a transcript without a usable conversation path or configured voice. Save the agent version after configuration.

## 2. Configure the product and reorder tools

Store the same high-entropy token used for `APP_TOOL_TOKEN` in Bland's secret manager, using the name `app_tool_token`. Reference the secret in the tool header; do not include the token in the prompt.

Create these HTTPS webhook tools:

1. `get_products`: `GET https://YOUR-DOMAIN/api/products.php`
2. `submit_reorder_request`: `POST https://YOUR-DOMAIN/api/reorders.php`

For both tools, send `X-App-Tool-Token` with the secret reference. Configure the reorder tool to provide `store_id`, `call_id`, `confirmed_by_retailer`, `sku`, and `quantity_units` for one phone model. The endpoint adapts that single-item tool call to the app's internal items list. Expose the JSON response fields to the agent. Tag both tools into the agent prompt and save a new version. See [../bland/tool-setup.md](../bland/tool-setup.md) for the request schema.

Test both tools with fictional data in Bland's tool tester before publishing.

## 3. Configure the post-call webhook

Set the agent's post-call webhook URL to `https://YOUR-DOMAIN/api/webhook.php`. Generate a webhook-signing secret in Bland and store the same value as `BLAND_WEBHOOK_SECRET` in the app's private `.env` file. The endpoint verifies Bland's `X-Webhook-Signature` HMAC-SHA256 header.

## 4. Publish and promote the agent

Publish the saved version to staging and verify its tools. Then promote the verified staging version to production. Check in the Bland dashboard or API that the production environment points to a version before making a live call.

## 5. Deploy and enable live mode

Deploy the PHP app at a public HTTPS address and set `APP_BASE_URL` to the app's full origin, including any subdirectory path. The application derives its endpoint and asset URLs from this value.

Add a phone number only after its owner agrees to receive a demonstration call. Set `APP_MODE=live`, reload the dashboard, and confirm the **Live demo setup** checklist is complete. Start a single test call from the dashboard.

If the recipient answers but hears silence, check that the production version has a voice, its start node reaches a scenario node, and the production environment is pinned to the expected version.

The application does not create Bland agents, buy phone numbers, publish versions, or place calls automatically. The Bland API key and tool token are not sent to the browser.
