# OHIP Webapp — Nginx + PHP-FPM — Env-only friendly

This repo is designed for Docker platforms (like Hostinger Docker Manager).

## Two modes

### Mode A — Env-only (recommended for containers)
- Set `ENV_ONLY=1`
- Configure all OHIP settings via environment variables
- No Settings UI changes (settings.php shows a “disabled” message)
- With `NO_FILE_WRITES=1`:
  - token cache goes to `/tmp`
  - logs go to stdout (`docker logs`)

### Mode B — Settings UI (writes .env)
- Leave `ENV_ONLY` unset or `0`
- Use `settings.php` to save `.env` inside the container filesystem
- If your platform uses ephemeral FS, prefer Env-only mode.

## Required environment variables

- `OHIP_BASE_URL`
- `OHIP_TOKEN_PATH` (usually `/oauth/v1/tokens`)
- `OHIP_CLIENT_ID`
- `OHIP_CLIENT_SECRET`
- `OHIP_APP_KEY`
- `OHIP_HOTEL_ID`
- `OHIP_ENTERPRISE_HEADER` (e.g. `enterpriseId`)
- `OHIP_ENTERPRISE_ID` (e.g. `BHE`)
- `OHIP_SCOPE` (e.g. `urn:opc:hgbu:ws:__myscopes__`)

Recommended in containers:
- `ENV_ONLY=1`
- `LOG_STDOUT=1`
- `NO_FILE_WRITES=1`

## Local run
```bash
docker compose up --build
```
Open:
- http://localhost:8080/

## Pages
- `/` or `/index.php` : upload CSV and run batch
- `/settings.php` : disabled in env-only mode; enabled otherwise
- `/health.php` : environment checks
- `/logs.php` : only works when `LOG_STDOUT=0`

## CSV
Headers:
- `reservationId`
- `companyProfileId`
- `companyName` (optional)


## Token test endpoint
- `/test-token.php` : requests token only and returns redacted JSON

## Notes
- Nginx fastcgi timeouts increased to 180s to avoid 504 masking PHP/cURL errors.


## OAuth token
The token request uses HTTP Basic Auth with client_id:client_secret (matches Postman). The form body sends grant_type + scope.


## Troubleshooting Company link payload
Set `OHIP_PUT_MODE` to control which request field is used:
- `reservationGuests` (default in `auto`)
- `attachedProfiles`
- `reservationProfiles`

Example:
- `OHIP_PUT_MODE=reservationGuests`

The app logs the chosen payload keys as `ohip.put.payload.keys`.
In Dry Run, the response includes the full payload under `payload`.


## Company link via reservationProfiles (recommended)
Your Postman collection builds Company/TravelAgent links under:

`reservations.reservation[].reservationProfiles.reservationProfile[]`

This build supports `OHIP_PUT_MODE=reservationProfiles` and will:
1) GET reservation
2) Read existing `attachedProfiles`
3) Merge existing profiles + Company profile
4) PUT reservation with `reservationProfiles.reservationProfile` list

Env vars:
- `OHIP_PUT_MODE=reservationProfiles`
- `OHIP_MERGE_EXISTING_PROFILES=1` (default)


## Update payload
This build uses the exact Postman-proven PUT payload: reservations[] + reservationProfiles.reservationProfile[].
APP_VERSION=20260306-135223


## Merge reservationProfiles
This build GETs reservation and merges existing reservationProfiles.reservationProfile[] before PUT.
Env: OHIP_MERGE_EXISTING_PROFILES=1 (default)
APP_VERSION=20260306-141622


## RoomStay passthrough for PUT
This build:
1) GETs reservation by id: `/rsv/v1/hotels/{hotelId}/reservations/{reservationId}`
2) Extracts `roomStay` from the GET response
3) Includes that `roomStay` object in the PUT payload alongside `reservationProfiles`

Reason: some tenants require `roomStay` to be present for profile-link updates to apply.

APP_VERSION=20260309-135810


## Company-only reservationProfiles + roomStay
This build:
- GETs `/rsv/v1/hotels/{hotelId}/reservations/{reservationId}`
- Extracts `roomStay`
- PUTs company link with ONLY the new Company profile (no merge of existing profiles), plus `roomStay`

APP_VERSION=20260309-141257


APP_VERSION=20260309-141449
Token endpoint default set to /oauth/v1/tokens.


## Login
This build adds a simple session-based login.
Env:
- APP_LOGIN_EMAIL
- APP_LOGIN_PASSWORD
Default email is alex@ad-consultingservices.com.
APP_VERSION=20260309-142022

## Auto-detect Company / Travel Agent from OHIP profile ID

Upload a CSV with **reservationId,profileId**. For each row the app GETs the profile,
detects `profileType` or `customerType` as Company or TravelAgent, GETs the reservation,
preserves `roomStay` and existing `reservationProfiles`, then PUTs the association.
Unrecognized or ambiguous types fail closed; no update is sent.

Dry run performs profile and reservation GETs, and previews the exact PUT payload.
No database is required.

**IMPORTANT:** Confirm `OHIP_PROFILE_GET_PATH` for your OPERA Cloud tenant.
The default `/crm/v1/profiles/{profileId}?hotelId={hotelId}` is a configurable
starting point, not validated against your tenant. Confirm that the profile GET
response includes an unambiguous `profileType` or `customerType` and that
`TravelAgent` is the reservationProfileType accepted by your OHIP version.

Before deploying, configure `.env` or Hostinger Compose environment variables.
Do not commit real secrets. The previous ZIP contained a hardcoded login password;
rotate that password before deployment. The previous package's `test-put.php`
remains a company-only diagnostic, not the batch workflow.

The application intentionally does not overwrite existing reservation profiles
and skips associations that are already present.


## Hostinger Git-root deployment (revised)
Paste the repository root URL into Hostinger Docker Manager. The root must
contain `docker-compose.yml`, `Dockerfile`, `app/`, and `docker/`.
This Compose version intentionally has no `${VARIABLE:?required}` substitutions
so Hostinger can parse it without a `.env` file.

**Before real use:** edit `docker-compose.yml` in your private deployment
configuration or set Hostinger container environment values for the blank
`APP_LOGIN_EMAIL`, `APP_LOGIN_PASSWORD`, and OHIP variables. Do not commit
real passwords, app keys or client secrets to a public GitHub repository.
This package has empty placeholders, so deployment may start but authentication
and OHIP calls cannot work until the variables are set.

If Hostinger still reports 'Docker project not found' before any build logs,
this may be a Hostinger project-manager issue rather than a YAML parse issue.
Try a fresh project name and review Hostinger's project creation logs.
