# Optima Claims Portal – Implementation Plan

This plan outlines how to build the insurance claims portal on top of your existing WordPress theme (Timber/Twig, ACF, Tailwind, Gulp).

---

## Summary of Requirements

| Requirement | Description |
|-------------|-------------|
| **API sync** | Connect to an external API; download and store claim data |
| **Claims CPT** | Each claim stored as a “Claims” custom post type |
| **Claim lookup** | Users can look up a claim from the front end or the portal (dashboard) |
| **Image uploads** | Matched claims accept image uploads |
| **View images** | Portal users can view stored images |

---

## Phase 1: Claims Custom Post Type & Data Model

**Goal:** Register the Claims CPT and define how claim data and images are stored.

### 1.1 Register “Claims” CPT

- **File:** `_functions/CPT.php` (uncomment pattern and adapt, or add new block).
- **Post type slug:** `claim` (singular), `claims` (plural in labels).
- **Supports:** `title` (e.g. claim ref/number), no editor if all data is meta; or minimal editor for notes.
- **Public:** Yes for front-end lookup and single claim view; consider `publicly_queryable => true` and a clean rewrite (e.g. `/claims/{claim-ref}/` or by post ID).
- **Admin:** Show in menu with a clear label (e.g. “Claims”), icon e.g. `dashicons-clipboard`.

### 1.2 Claim meta / ACF fields

Store API-sourced and portal-only data as post meta (or ACF fields). Suggested fields:

- **Claim reference / number** (unique id from API) – also good for title or slug.
- **API id** – external system id (for sync/deduplication).
- **Status** – e.g. draft, matched, closed (optional taxonomy or meta).
- **Claimant name**, **policy number**, **date of loss**, **contact email/phone** (as required by API/brief).
- **Synced at** – datetime last updated from API.
- **Matched at** – when a user first “claimed” this claim (optional).

Use either:

- **ACF:** Field group “Claim details” attached to post type `claim` (recommended if you already use ACF everywhere), or  
- **Plain post meta** with a small helper to get/set claim data.

### 1.3 Images storage

- **Option A (recommended):** Use WordPress media (attachments) linked to the claim post:
  - On “upload”, set `post_parent` to the claim post ID.
  - Query with `get_children( [ 'post_parent' => $claim_id, 'post_type' => 'attachment' ] )` or ACF relationship/gallery.
- **Option B:** ACF gallery field on the claim post type (simplest for “view stored images” and upload UI).

Plan the rest of the flow (lookup, upload, view) assuming one claim post → many attachment posts (or one gallery field).

---

## Phase 2: API Connection & Storing Claim Data

**Goal:** Connect to the external API and create/update claim posts.

### 2.1 Configuration

- Store API base URL and credentials securely:
  - **ACF Options:** e.g. “Claims API” option page with fields: API URL, API Key (or client id/secret), optional “sync enabled” toggle.
  - Or **wp-config constants** if you prefer no DB storage for secrets.
- Use **transients or options** for rate limiting / last sync time if the API has limits.

### 2.2 Sync service (PHP)

- **New file:** e.g. `_functions/ClaimsApi.php` or `_inc/claims-api.php` (and include from `functions.php` or `_functions/`).
- **Responsibilities:**
  - **Fetch claims:** `wp_remote_get()` (or `wp_remote_post()`) to the API; parse JSON; handle errors and timeouts.
  - **Map response → post/metadata:** For each claim in the response:
    - Check if a claim already exists (by API id or claim reference) – e.g. `get_posts( [ 'post_type' => 'claim', 'meta_key' => 'api_id', 'meta_value' => $id ] )`.
    - If not, create a new post (`wp_insert_post()`); if yes, update (`wp_update_post()` + `update_post_meta()`).
  - Set title (e.g. claim reference), slug, and all ACF/meta fields from the API.
  - Update “synced at” timestamp.

### 2.3 When to run sync

- **WP-Cron:** Register a daily (or hourly) event that calls your sync function (e.g. `wp_schedule_event()` in a plugin or theme and a hook that runs the sync).
- **Manual trigger:** Admin page or “Sync now” button that calls the same sync function (useful for testing and on-demand refresh).
- Optionally expose a **REST or admin-ajax endpoint** protected by capability/capability check for external cron (e.g. cron job hitting a secret URL).

---

## Phase 3: Claim Lookup (Front End & Portal)

**Goal:** Let users find a claim by reference (or other identifier) from the public site and from the portal.

### 3.1 Lookup by claim reference

- **Input:** Single field (or a short form): “Claim reference” (and optionally policy number or last name for extra verification).
- **Back end:**  
  - Search for a post of type `claim` where meta “claim_reference” (or title) equals the submitted value.  
  - If not found, return “No claim found” (no need to leak existence of other claims).
  - If found, redirect to the claim view (see below) or show a minimal “claim found” view with next steps (e.g. “Upload images” or “View details”).

### 3.2 Where to put the lookup

- **Front end (public):**
  - **Option A:** Dedicated page “Look up a claim” (e.g. page slug `look-up-claim`).
  - **Option B:** Block or shortcode that outputs the lookup form and result message.
- **Portal (logged-in area):**
  - Same form on a “Claims” or “My claims” page in the dashboard; after lookup, show the claim and allow uploads/view (see Phases 4–5).
- **Implementation:**  
  - Form POST to the same page or to a dedicated handler.  
  - Handler: validate input → query claim → set a variable for the Twig template (e.g. `context['claim']`, `context['lookup_error']`) and re-render, or redirect to the claim URL.

### 3.3 Claim URL and single view

- **Single claim template:** Add `single-claim.twig` (and ensure `single.php` already uses `single-{post_type}.twig` – it does in your theme).
- **Access control:**
  - **Public:** If claims are public, anyone with the link can view. Prefer not exposing claim ref in URL if possible; use a token or require lookup first.
  - **Portal:** Restrict `single-claim.twig` (or the claim lookup result) to logged-in users with a “portal” role/capability; redirect others to login or lookup page.
- **Twig:** In `single-claim.twig` (and any portal claim view), output claim meta (reference, status, claimant, dates) and a section for “Upload images” and “Stored images” (Phases 4–5).

---

## Phase 4: Image Uploads for Matched Claims

**Goal:** Only “matched” claims accept uploads; portal users can upload images that are stored against that claim.

### 4.1 “Matched” status

- When a user successfully looks up a claim (and optionally confirms identity), mark the claim as “matched” (e.g. set meta `claim_matched` = 1 and `claim_matched_at` = now).
- In your upload handler, only allow uploads if the claim exists and is matched (and the current user is allowed to upload for that claim).

### 4.2 Upload mechanism

- **Option A – Form upload (no JS):**  
  - Form on the claim page: `enctype="multipart/form-data"`, action to a PHP handler.  
  - Handler: validate claim + “matched” + user; use `media_handle_upload()` or `wp_handle_upload()` then attach the file to the claim post (`post_parent` = claim ID).  
  - Redirect back to the claim page with success/error message.
- **Option B – AJAX upload:**  
  - Same validation and `media_handle_upload()` in an `admin_ajax` or REST endpoint; front end sends the file via `FormData`.  
  - Better UX (no full reload, progress, multiple files).

### 4.3 Security

- Nonce verification on form or AJAX.
- Check user is logged in and has permission (e.g. “portal user” or “upload_claim_images” capability).
- Validate file types (e.g. images only: jpeg, png, gif, webp) and size limits.
- Ensure the claim ID in the request belongs to a valid, matched claim.

---

## Phase 5: Viewing Stored Images

**Goal:** Portal users can see all images stored for a claim.

### 5.1 Querying images

- For a given claim post ID, get attachments:  
  `get_children( [ 'post_parent' => $claim_id, 'post_type' => 'attachment', 'post_mime_type' => 'image' ] )`  
  or use ACF gallery field if you stored them there.
- Pass the list to your Twig template (e.g. `context['claim_images']`).

### 5.2 Display

- In `single-claim.twig` (and portal claim view), add a “Stored images” section:
  - Thumbnails (e.g. `Timber\Image` or `wp_get_attachment_image()`) linking to full size or a lightbox.
  - Optional: caption, date uploaded, uploaded by (if you store that).

### 5.3 Permissions

- Only show the “Stored images” section to users who are allowed to see that claim (same rules as for upload: e.g. logged-in portal users who have accessed this claim via lookup).

---

## Suggested File / Code Structure

| Item | Location |
|------|----------|
| Claims CPT registration | `_functions/CPT.php` |
| Claim ACF field group | `_functions/AcfFields.php` (or ACF JSON) |
| API config (options) | ACF Options or `_functions/ClaimsOptions.php` |
| API fetch & sync logic | `_functions/ClaimsApi.php` |
| Cron registration & sync hook | Same file or `_functions/ClaimsCron.php` |
| Lookup form handler | `_functions/ClaimsLookup.php` + page or shortcode |
| Upload handler (form or AJAX) | `_functions/ClaimsUpload.php` or inside ClaimsLookup |
| Twig: lookup form | `_views/claims/claim-lookup.twig` or block |
| Twig: single claim | `_views/single-claim.twig` |
| Twig: portal claim view | Reuse single-claim or `_views/claims/portal-claim.twig` |
| JS for AJAX upload (if used) | `_assets/scripts/` and build with existing Gulp/Rollup |

---

## Security Checklist

- [ ] API keys in options or wp-config, not in front-end.
- [ ] Lookup and claim view: no listing of all claims; only allow access by ref + optional verification.
- [ ] Upload: nonce, capability check, claim “matched” check, file type/size validation.
- [ ] Sync endpoint (if URL-triggered): secret token or capability; rate limit.
- [ ] Escape output in Twig (Timber does this by default; avoid `|raw` for user data).

---

## Optional Enhancements

- **Email verification:** Before marking “matched”, send a link or code to the email on the claim.
- **Roles:** Dedicated “Claims Portal User” role with minimal capabilities.
- **REST API:** Expose claim lookup (and maybe upload) via WP REST for a future SPA or mobile app.
- **Logging:** Log sync errors and upload events for support.
- **Thumbnails:** Ensure image sizes are registered so list views stay fast.

---

## Implementation Order

1. **Phase 1** – CPT + ACF/meta + image strategy.  
2. **Phase 2** – API config + sync (manual first, then cron).  
3. **Phase 3** – Lookup form + single claim template + access rules.  
4. **Phase 4** – Upload form or AJAX + “matched” logic.  
5. **Phase 5** – Display of stored images on claim page.

After Phase 1 you can test creating claims manually in the admin and viewing `single-claim.twig`. After Phase 2 you’ll have real data to look up. Phases 3–5 complete the user-facing portal.
