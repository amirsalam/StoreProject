# Installation license — Digital Script Dragon Pro

Every installation of **Digital Script Dragon Pro** (“Dragon Pro”) is activated with a
license key and checks in with the vendor's license server (IndependentLicenseServer).
This is separate from the product licenses Dragon Pro sells to its own customers
(*Admin → Licensing*, `/api/v1/licenses/*`), which this system never touches.

> **Naming.** Product name: *Digital Script Dragon Pro*. Technical identifiers that stay
> as they are: the application folder/repository `StoreProject`, the license product
> slug `storeproject` (signed into every token), the module `App\Domain\AppLicense`.

| Guide | Audience |
|---|---|
| [Activation & moving domains](activation.md) | Customers / site administrators |
| [Troubleshooting](troubleshooting.md) | Customers / support |
| [Integration guide](integration.md) | Developers, release manager |

License-server guides (installation, administration, API, keys, security) are in the
IndependentLicenseServer repository under `docs/`.

## In one minute

1. The customer enters the license key in *Admin → Installation license*.
2. Dragon Pro binds the license to its `APP_URL` domain and receives a token signed by
   the license server; it verifies it locally with public keys shipped in its code.
3. About once a day it checks in (signed request) and receives a fresh token.
4. Without a valid license: the admin area, vendor workspaces and starting a checkout
   are disabled. The storefront, sign-in, buyers' purchases and downloads, payment
   completion and the product-license API always keep working.
5. If the license server is unreachable, everything keeps working for up to 72 hours
   plus a 14-day grace period.
