# Exit Intent Popup

New Wordpress plugin plugin

This plugin will be used to display a modal to a Wordpress website user under difference circumstances. The main scenario will be when a user goes to leave the website. This is called an exit intent popup. The popups or modals contents should try and keep the user on the site. The goal here is to create a backend UI that can be used to create custom modal content and then assign the modal to a specific or set of pages in Wordpress.

Create new custom post type that uses Gutenberg editor.

Contents of post get loaded loaded in as modal on front-end of site.

Use the wp-content/plugins/wp-exit-intent-popups project folder to build features. Create git repo in wp-content/plugins/wp-exit-intent-popups to track as a new project.

## Post type fields:

These should appear in the right hand sidebar at the top, on the backend of Wordpress when viewing the post types post item.

- Popup delay - text field, number of seconds the popup (modal) should wait before allowing exit intent appearance. Not auto appearance, but give some time before we allow the popup to appear even if the user intends to exit the site.
- Auto appear - text field, number of seconds the popup (modal) should wait before displaying to user.
- Frequency time - select list - options: Always, Session, time.
- Popup position - select list - options: top, bottom, right, left, mouse exit position.
- Size - select list - options: small, medium, large.
- Allow overlay click - check box - if on, allow the user to click the modal background overlay to dismiss modal. If not, the user must click close button to exit modal.
- Theme - select list - option: light, dark. Light theme and dark themes.

## Page & Post field:

- Multi-select list to choose popups from the exit intent modal post type on all pages & posts.

## Notes

- User moves mouse to edge of viewport to trigger exit intent popup.
- User converts, don't open modal again. track via browser cookie?
- We will want the ability to assign multiple popups to a single post or page so that we perform A/B testing.
- We will need a UI to view A/B test results so we can determine the best performing popups.
- We will need Google Analytics v4 integration for tracking on page.
    - When the modal appears
    - User action when modal is interacted with, close or click CTA in modal to go to specific landing page.

## Additions

- Add ability to clear A/B test data from backend interface. There should be a confirm popup to be sure the admin user is sure they want to go through with the clearing of data.
- We should add a backend global settings page to the plugin for:
    - Setting: to change dark and light mode background colors.
    - Setting: to change the overlay background color.
    - Setting: to add border radius to modal popups.
    - Setting: to change the 3 sizes to custom sizes.
    - Setting: to customize the Google Analytics 4 Integration even names.
- Use CSS variables where possible to allow styles customizations.
- When the modal is open we should trap the tab index into the modal window so that user can't tab into the background page making it difficult to exit the popup (modal) when tabbing.
