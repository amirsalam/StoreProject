# Activating Digital Script Dragon Pro

Your purchase came with a **license key** that looks like this:

```
SP-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
```

One license covers **one production website** (your real domain) plus **two development
copies** (for example `localhost` or `yourshop.test`). All store subdomains and custom
domains of your installation are included.

## Before you activate

1. **`APP_URL` is your real address.** In your `.env` file:
   `APP_URL=https://www.your-domain.com` (with `https://`). The license is bound to this
   domain (`www.` is ignored).
2. **The scheduler runs.** Add this cron entry (your host's control panel usually has a
   “Cron jobs” page):

   ```cron
   * * * * * cd /path/to/dragon-pro && php artisan schedule:run >> /dev/null 2>&1
   ```

   Without cron the license still renews when an administrator uses the site, but cron
   is recommended.
3. **The server clock is correct** (automatic time / NTP).
4. **Outgoing HTTPS is allowed** to the license server (some hosts block outgoing
   connections by default).

## Activate

1. Sign in as an administrator.
2. Open **Admin → Installation license**.
3. Paste your license key and click **Activate this installation**.

You should see **Licensed**, the domain it was activated for, *Production* (or
*Development* on a local copy) and the next renewal date.

Until the installation is activated, the admin area, vendor workspaces and checkout are
disabled; your storefront stays online.

## Day to day

Nothing to do. Dragon Pro checks the license about once a day. On the license page you
can see the last check and use **Check now** at any time (for example after your
license was renewed or reinstated).

From the server's command line:

```bash
php artisan app-license:status     # current state (no network)
php artisan app-license:verify     # check in now
```

## If the license server cannot be reached

Your site keeps working. The license is renewed every 72 hours; if renewals fail,
administrators see a notice and the site continues for a **14-day grace period**. As
soon as the connection is back, the next check renews it automatically.

## Moving to another domain or server

1. On the **old** installation: *Admin → Installation license* → **Deactivate this
   installation** (or `php artisan app-license:deactivate`).
2. On the **new** installation (with `APP_URL` set to the new domain): activate the same
   key.

If the old server is no longer available, contact support: they can release the old
activation for you.

- **Reinstalling on the same domain** needs no deactivation — the new installation takes
  over automatically.
- **Changing `APP_URL`** on an activated installation (or copying it to another domain)
  makes it unlicensed until you activate it again for the new address.
- For security, a license can move to at most 3 new production domains within 30 days;
  support can approve a move beyond that.

## Staging and local copies

Copies on `localhost`, `*.localhost`, `*.test`, `*.local` or a private IP address are
**development** activations and do not use your production slot (two per license).
A public staging address such as `staging.your-domain.com` counts as production.
