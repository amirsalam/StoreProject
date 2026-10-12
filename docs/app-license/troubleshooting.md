# Troubleshooting — Digital Script Dragon Pro license

Start with **Admin → Installation license** (state, last check, last problem) or:

```bash
php artisan app-license:status
```

## Messages when activating

| Message | What to do |
|---|---|
| *This license key is not valid for Dragon Pro. Check it and try again.* | Copy the key again exactly (`SP-` + 6 groups). Contact support if it still fails. |
| *This license is already active on another domain…* | Deactivate it on the old installation first ([moving domains](activation.md#moving-to-another-domain-or-server)), or ask support to release it. |
| *This license has moved domains too often recently…* | Contact support to approve the move. |
| *This site address (APP_URL) cannot be activated…* | Set `APP_URL` to your real domain name (not an IP address or an internal name like `.lan`). |
| *This installation is already activated.* | Nothing to do — or deactivate first to use another key. |
| *The license server address is not configured (APP_LICENSE_SERVER_URL).* | Your package is missing its license server setting — contact support. |
| *The license server could not be reached…* | Check outgoing HTTPS from your server (firewall, host restrictions, DNS) and try again. |
| *This server's clock is wrong…* | Enable automatic time (NTP) on the server. |
| *The license server's answer could not be verified…* | Update Dragon Pro to the latest release; contact support if it persists. |
| *Too many attempts…* | Wait a minute. |
| *This license is suspended / has been revoked / has expired* | Contact support or renew. |

## States on the license page

| State | Meaning | Action |
|---|---|---|
| **Licensed** | All good | — |
| **Offline grace period** | The last renewals failed; the site keeps working until the date shown | Restore outgoing connectivity; *Check now* |
| **Not licensed** — *could not be verified* | Stored license invalid (e.g. `APP_URL` changed, files copied) | Activate again |
| **Not licensed** — *not reachable for too long* | Grace period over | Restore connectivity; *Check now* restores the license immediately |
| **Not licensed** — *suspended / revoked / expired* | Decision of the license issuer | Contact support or renew |
| **Not activated** — *no longer activated on the license server* | Released by support or replaced by another installation | Activate again |

## Other problems

| Problem | Fix |
|---|---|
| Admin pages always redirect to *Installation license* | The installation is not licensed — see the state on that page |
| Visitors see *Temporarily unavailable* on checkout | Same: activate or restore the license |
| “Renewal due” date never moves | Cron is not running and no administrator has used the site; add the cron entry or click *Check now* |
| Stored key unreadable after changing `APP_KEY` | The installation identity is encrypted with `APP_KEY`; restore the old `APP_KEY`, or reactivate |
| Local development without a license | `APP_ENV=local` and `APP_LICENSE_ENFORCE=false` in `.env` (ignored in production) |
