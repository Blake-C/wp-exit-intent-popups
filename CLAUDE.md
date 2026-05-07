# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

**Exit Intent Popup** (v1.0.0) — WordPress plugin for displaying exit-intent and timed popups with A/B testing and GA4 integration. Text domain: `wp-exit-intent-popups`. Constants: `EIP_VERSION`, `EIP_PLUGIN_DIR`, `EIP_PLUGIN_URL`, `EIP_DB_VERSION`.

## WordPress standards

- Follow WordPress Coding Standards (WPCS) for all PHP files.
- Run `phpcs` with the WordPress ruleset before committing PHP.
- Follow BEM naming convention for all CSS/SCSS.
- When creating new blocks, check existing blocks for the established pattern and follow it.
- Always check for accessibility (WCAG 2.1 AA) when building new UI or block features.
- Prefer native WordPress APIs over custom solutions — hooks, REST API, block API, etc.
- Avoid jQuery; use vanilla JS or the block editor's React/wp.* packages instead.

## Build & Linting

There is no build pipeline — assets are vanilla JS and CSS, enqueued directly by WordPress. No `package.json` or bundler.

**PHP linting (run from repo root via Docker):**
```sh
docker compose exec cli_tools zsh
# Then inside container:
../../vendor/bin/phpcs --standard=../../phpcs.xml includes/ wp-exit-intent-popups.php
```

Refer to the project-level CLAUDE.md for Docker setup, pre-commit hooks, and PHPCS path details.

## Architecture

### PHP Class Map

All classes live in `includes/` and are instantiated in the main plugin file (`wp-exit-intent-popups.php`) on `plugins_loaded`.

| Class | File | Responsibility |
|---|---|---|
| `EIP_Post_Type` | `class-eip-post-type.php` | Registers `exit_intent_popup` CPT; handles popup cloning |
| `EIP_Popup_Settings` | `class-eip-popup-settings.php` | Per-popup meta box (delay, frequency, position, size, theme, schedule) |
| `EIP_Page_Assignment` | `class-eip-page-assignment.php` | Assigns one or more popups to pages/posts; bulk actions |
| `EIP_Settings` | `class-eip-settings.php` | Global plugin settings (colors, sizes, GA4 event names) stored as `eip_settings` option |
| `EIP_Frontend` | `class-eip-frontend.php` | Enqueues assets + renders modal HTML in footer; outputs CSS custom properties |
| `EIP_AB_Testing` | `class-eip-ab-testing.php` | Database table `wp_eip_events`; REST endpoints for event tracking and results |
| `EIP_Admin` | `class-eip-admin.php` | A/B results dashboard, CSV export, clear-data action |

### Meta Field Naming

All per-popup meta fields are prefixed `_eip_`: `_eip_popup_delay`, `_eip_auto_appear`, `_eip_frequency`, `_eip_frequency_days`, `_eip_position`, `_eip_size`, `_eip_overlay_click`, `_eip_theme`, `_eip_start_date`, `_eip_end_date`. Page/post assignment uses `_eip_assigned_popups` (array of popup post IDs).

All meta fields are registered for REST API access (Gutenberg-compatible).

### REST API

Namespace: `/wp-json/eip/v1/`

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/event` | POST | WP REST nonce | Track impression / conversion / close |
| `/events` | DELETE | `manage_options` | Truncate all event data |
| `/results` | GET | `manage_options` | Aggregated A/B results (optional `page_id` filter) |

### Database

Table `wp_eip_events` (popup_id, page_id, event_type, created_at). Created/updated on activation and `plugins_loaded` via `EIP_AB_Testing::create_table()`. Dropped in `uninstall.php`.

### Frontend JS (`assets/js/exit-intent.js`)

Vanilla JS IIFE. Config injected from PHP as `eipConfig` (restUrl, nonce, pageId, GA4 event names).

Key flows:
- **Eligibility check** — frequency (always/session/time), converted flag (localStorage), popup delay, schedule dates all checked client-side before any trigger fires.
- **A/B selection** — when multiple popups are assigned, one is picked at random client-side from eligible candidates.
- **Exit intent** — desktop: `mouseleave` when `clientY <= 0`; mobile: upward scroll reversal heuristic (>50 px up after >100 px down).
- **Auto-appear** — independent `setTimeout` using `_eip_auto_appear` value.
- **Conversion tracking** — any link click inside `.eip-modal__content` fires `conversion` event.
- **Focus trap** — keyboard Tab/Shift+Tab cycles within open modal.

### Frontend CSS (`assets/css/modal.css`)

All theme values are CSS custom properties output by `EIP_Frontend::build_css_vars()` into a `<style>` block:
`--eip-light-bg`, `--eip-light-color`, `--eip-dark-bg`, `--eip-dark-color`, `--eip-overlay-bg`, `--eip-radius`, `--eip-size-small/medium/large`.

Modal state is toggled by adding/removing `.eip-is-active` on `.eip-modal-wrapper`. Body gets `.eip-modal-open` during open state (blocks scroll). Respects `prefers-reduced-motion`.

### Admin JS (`assets/js/admin.js`)

jQuery. Manages three UI behaviours: frequency-days field visibility, bulk popup selector positioning, and the clear-all-data confirmation + REST DELETE call.

## Security scanning

- Always run `snyk_code_scan` for new or modified first-party code.
- If issues are found, fix them using the Snyk results context, then rescan.
- Repeat until no new issues remain.
- Run `snyk_sca_scan` when adding or upgrading dependencies.
