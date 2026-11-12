# Namecheap Stellar Plus deployment

## Prepare the hosting account

- Choose PHP 8.1 or newer in cPanel and confirm the cURL and session extensions are enabled.
- Point the domain's document root to the repository's `public/` folder when the hosting panel allows it.
- Enable HTTPS before configuring Bland webhooks. Bland webhook delivery requires an HTTPS URL.

If cPanel cannot point the document root at `public/`, place the repository at the domain root and use the supplied root `.htaccess` and wrapper `index.php`. The rules deny direct requests to `src/`, `storage/`, `tests/`, `bland/`, `.git/`, and `.env*`, then route asset and API paths to `public/`. Confirm Apache allows `.htaccess` overrides before exposing the site.

## Upload and configure

1. Upload the project folder with File Manager or FTP. Do not upload `.git/` or a machine-specific local `.env` to a public folder.
2. Create `.env` in the project root (one level above `public/`) from `.env.example` and set a strong unique `APP_ADMIN_PASSWORD`.
3. Set `APP_BASE_URL` to the final HTTPS domain. Set `APP_MODE=mock` until Bland is ready.
4. Add the Bland API key, published agent ID, app tool token, and webhook signing secret to `.env`.
5. Ensure PHP can write to `storage/`. Start with directory permissions 755; use 775 only if the host's PHP user needs group write access. Do not make the folder world-writable.
6. Use the dashboard's **Live demo setup** checklist. Add only numbers whose owners have consented.
7. Test mock mode, HTTPS API endpoints, and Bland tool tester before setting `APP_MODE=live`.

## Protect the deployment

- Keep `.env`, `storage/`, and PHP source outside the public document root where possible.
- Do not commit `.env`, upload it to GitHub, or put secrets in JavaScript.
- Limit access to the dashboard password and Bland account. Transcripts contain caller information.
- JSON storage assumes one server and local disk. Back up the data files securely and move to a database before using real business data or supporting concurrent production traffic.

## Confirm

- Open the HTTPS dashboard and log in.
- Confirm `/api/products.php` rejects a request without `X-App-Tool-Token`.
- Confirm the mock reorder appears with `Pending human review`.
- Confirm the Bland webhook rejects missing or invalid signatures.
- Only then make an explicitly approved test call to a consenting number.
