## 2025-02-19 - Accessible Channel Tabs Keyboard Navigation
**Learning:** Modern dynamic DOM tab bars constructed via client JS require explicit `role="tablist"` / `role="tab"`, `aria-selected`, descriptive `aria-label` attributes, and `keydown` event listeners for `Enter` and `Space` on both the tab element and internal action elements (such as close buttons) to provide a seamless keyboard and screen reader experience.
**Action:** Always ensure dynamic tab components set standard ARIA attributes and listen for keyboard activation (`Enter`/`Space`) on interactive sub-elements.

## 2025-03-31 - Dynamic ARIA Label Feedback for Async Clipboard Actions
**Learning:** Updating only button visible text upon copy/async action completion is insufficient for screen reader users. Dynamically setting `aria-label` to announce state changes (e.g. "Channel link copied to clipboard" or "Failed to copy channel link") alongside a temporary visible label feedback provides immediate feedback for screen reader accessibility.
**Action:** Always pair visible text updates on action feedback buttons with corresponding updates to `aria-label` and restore both after the timeout reset.

## 2025-04-15 - ARIA Tablist/Tab/Tabpanel Semantics for Chat Sidebar Panels
**Learning:** In static HTML templates with JS-driven tab switching (like chat sidebars), defining explicit `role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls`, `role="tabpanel"`, and `aria-labelledby` attributes—and dynamically updating `aria-selected` in JS click handlers—enables screen readers to accurately identify active sub-panels and navigate controls seamlessly.
**Action:** Always pair sidebar tab UI toggle logic with `aria-selected` state updates and link tabs to panel containers using `aria-controls` / `aria-labelledby`.

## 2026-09-21 - Accessible Modal Dialog Semantics and Focus Restoration
**Learning:** For overlay modals in web applications, defining `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` attributes on the overlay container is necessary for screen readers to recognize modal boundaries. Furthermore, recording `document.activeElement` when opening the modal and restoring focus back to that triggering element upon closing prevents focus loss for keyboard and screen reader users.
**Action:** Always pair modal dialog overlays with `role="dialog"`, `aria-modal="true"`, `aria-labelledby` markup and focus tracking/restoration logic in modal open/close functions.
