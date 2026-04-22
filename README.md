# Exit Intent Popup

A WordPress plugin for displaying exit intent and timed popups with A/B testing and Google Analytics 4 integration.

---

## Overview

Exit Intent Popup lets you build custom modal content using the WordPress block editor (Gutenberg), then assign those modals to any page or post on your site. Modals can appear when a visitor is about to leave the page, after a set amount of time, or both. Multiple popups can be assigned to a single page to run A/B tests, with a built-in results dashboard to compare performance.

---

## Features

- **Exit intent detection** — Triggers when the visitor's mouse moves toward the top edge of the browser viewport (the classic "about to close the tab" signal).
- **Auto-appear timer** — Optionally show the popup after a fixed number of seconds, independent of exit intent.
- **Popup delay** — Set a minimum time the visitor must spend on the page before exit intent is allowed to fire. Prevents the popup from interrupting someone who just arrived.
- **A/B testing** — Assign multiple popups to the same page. One is selected at random per page load. Impressions, conversions, and closes are tracked separately for each.
- **A/B results dashboard** — View aggregated stats per popup per page, including impression count, conversion count, close count, and conversion rate. Filterable by page.
- **Conversion tracking** — Any link click inside the modal content is counted as a conversion. A browser cookie is set so a converted visitor is never shown that popup again.
- **Frequency control** — Choose how often the popup is allowed to appear: always, once per session, or once every N days.
- **Google Analytics 4 integration** — Fires `gtag()` events automatically when the popup appears, when it is closed, and when a CTA link is clicked. Requires GA4 to already be installed on the site.
- **Six position options** — Place the modal at the top, bottom, left, right, center, or at the position where the mouse exits the viewport.
- **Three size options** — Small, medium, or large modal width.
- **Light and dark themes** — Built-in light and dark color schemes.
- **Overlay click to close** — Optionally allow visitors to dismiss the modal by clicking the background overlay.
- **Accessibility** — Modal uses `role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-hidden`, keyboard ESC to close, and focus is moved into the modal on open.
- **Reduced motion support** — Animations are disabled when the visitor has `prefers-reduced-motion` set.
- **Body scroll lock** — Page scroll is disabled while a modal is open.

---

## Creating a Popup

1. Go to **Intent Popups → Add New Popup** in the WordPress admin.
2. Give the popup a descriptive title (used in the assignment selector and results dashboard).
3. Use the Gutenberg block editor to build the modal content — text, images, buttons, and any other blocks are supported.
4. Configure the popup behavior in the **Popup Settings** panel in the right sidebar.
5. Publish the popup.

---

## Popup Settings

These options appear in the right sidebar when editing a popup.

### Popup Delay (seconds)
The number of seconds a visitor must be on the page before exit intent is allowed to trigger. Useful for giving visitors time to read before the popup can appear. Set to `0` to allow exit intent immediately.

### Auto Appear (seconds)
Automatically show the popup after this many seconds, regardless of whether the visitor shows exit intent. Set to `0` to disable auto-appear and rely on exit intent only. If both delay and auto-appear are set, auto-appear fires at its own timer independently — it is not blocked by the delay.

### Frequency
Controls how often the popup is shown to the same visitor.

| Option | Behavior |
|---|---|
| **Always** | Show every time the visitor loads the page. |
| **Session** | Show once per browser session. Hidden again after the browser is closed and reopened. |
| **Time** | Show once, then suppress for a set number of days. |

When **Time** is selected, a **Show again after (days)** field appears to configure the suppression period.

> Frequency state is stored in `localStorage` (for time-based) and `sessionStorage` (for session-based). Conversion state is stored in `localStorage` and persists indefinitely.

### Popup Position
Where the modal appears on screen.

| Option | Description |
|---|---|
| **Top** | Full-width bar anchored to the top of the viewport. |
| **Bottom** | Full-width bar anchored to the bottom of the viewport. |
| **Left** | Full-height panel on the left side. |
| **Right** | Full-height panel on the right side. |
| **Center** | Centered overlay modal (default). |
| **Mouse Exit Position** | The modal appears near the cursor's last known position when exit intent fires. |

### Size
Controls the width of the modal panel. Applies to center and mouse exit position modes. Top, bottom, left, and right positions span the full edge.

| Option | Width |
|---|---|
| **Small** | 360px |
| **Medium** | 560px |
| **Large** | 820px |

### Allow Overlay Click to Close
When checked, clicking the semi-transparent background overlay dismisses the modal. When unchecked, the visitor must use the close button (×) or press Escape.

### Theme
| Option | Description |
|---|---|
| **Light** | White background, dark text. |
| **Dark** | Near-black background, light text. |

---

## Assigning Popups to Pages and Posts

1. Open any **Page** or **Post** in the WordPress editor.
2. Find the **Exit Intent Popups** panel in the right sidebar.
3. Select one or more published popups from the list. Hold Ctrl (Windows) or Cmd (Mac) to select multiple.
4. Save or update the page.

When a single popup is assigned, it will be the one shown. When multiple popups are assigned, one is chosen at random per page load — this is the A/B testing mechanism.

---

## A/B Testing

Assign two or more popups to the same page to run an A/B test. On each page load, the plugin selects one eligible popup at random and shows it (subject to frequency and conversion rules).

**What is tracked per popup per page:**

| Event | When it fires |
|---|---|
| **Impression** | The popup is shown to the visitor. |
| **Conversion** | The visitor clicks any link inside the modal content. |
| **Close** | The visitor dismisses the modal via the close button, overlay click, or Escape key. |

### Viewing Results

Go to **Intent Popups → A/B Results** in the admin sidebar. The table shows impression count, conversion count, close count, and conversion rate for every popup on every page. Use the **Filter by Page / Post** dropdown to narrow results to a specific page.

**Conversion rate** is calculated as `(conversions / impressions) × 100`. Color coding:
- Green — 10% or higher
- Orange — 5–9%
- No color — below 5%

---

## Google Analytics 4 Integration

The plugin fires the following `gtag()` events automatically, provided GA4 is already installed on the site (the plugin does not embed GA4 itself).

| Event name | When it fires | Parameters |
|---|---|---|
| `eip_popup_shown` | Modal becomes visible | `popup_id`, `page_id` |
| `eip_popup_closed` | Modal is dismissed | `popup_id`, `page_id` |
| `eip_cta_click` | Visitor clicks a link inside the modal | `popup_id`, `page_id` |

Use these event names in GA4 to build custom reports, funnels, or conversion goals.

---

## Conversion & Frequency — How They Interact

1. On page load, all popups assigned to the current page are checked against frequency and conversion rules.
2. Only eligible popups (not converted, not suppressed by frequency) are considered.
3. One eligible popup is selected at random.
4. If no popups are eligible, nothing is shown.

Once a visitor clicks a CTA link inside a popup, that popup is marked as converted in `localStorage`. A converted popup is never shown to that visitor again on any page, regardless of frequency setting.

---

## Notes

- The plugin does not paginate or limit A/B test data. For high-traffic sites with many events, consider periodically pruning the `wp_eip_events` database table.
- Popup content is rendered through WordPress's `the_content` filter, so all standard blocks and shortcodes work inside modals.
- The tracking REST endpoint (`/wp-json/eip/v1/event`) requires the WordPress REST nonce sent automatically by the plugin's frontend script. Requests without a valid nonce are rejected with a 403.

---

> **Assisted by [Claude Code](https://claude.ai/claude-code)**
