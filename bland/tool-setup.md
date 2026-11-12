# Configure Bland tools

The PHP app supplies two HTTPS endpoints for a Bland Agent's webhook tools:

1. `GET https://YOUR-DOMAIN/api/products.php`
2. `POST https://YOUR-DOMAIN/api/reorders.php`

In Bland, create each as a Webhook tool. Add the header `X-App-Tool-Token` and store its value as a Bland Secret. Use the same value for `APP_TOOL_TOKEN` in `.env`. Do not put the token in the agent prompt.

For `get_products`, use GET and require the response's `products` field. Each product includes `sku`, `brand`, `model`, `name`, `storage_gb`, and `available`. For `submit_reorder_request`, collect one model at a time with `store_id`, `call_id`, `confirmed_by_retailer`, `sku`, and `quantity_units`. Include a representative `input_schema.example` with all five fields so Bland can form complete tool arguments. The tool posts those fields directly; the app adapts that single-item request to its internal `items` list. Leave the tool's optional `speech` field blank and cap the tool at one call per conversation to avoid repeated progress announcements or duplicate attempts. Expose `ok` and the saved reorder in the response data; the agent must only claim success when both confirm submission. Tag both tools into the agent prompt. A tool must be tagged into the prompt before the agent can call it.

Use the exact public HTTPS host used by the deployed app. Bland requires HTTPS for webhook URLs. After creating tools, run them with sample fictional data in Bland's tool tester before publishing the Agent.
