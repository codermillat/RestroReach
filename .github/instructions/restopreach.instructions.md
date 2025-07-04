---
applyTo: '**/*.php'
---
# PHP Coding Standards & Best Practices for Restaurant Delivery Manager

## 1. WordPress & WooCommerce Context:
- This is a WordPress plugin that heavily integrates with WooCommerce.
- All WordPress Coding Standards (WPCS) MUST be followed.
- Adhere to WooCommerce best practices for extending its functionality (hooks, filters, template overrides).
- Use WordPress and WooCommerce APIs and functions wherever possible (e.g., `wc_get_order`, `$wpdb`, `wp_send_json_success`).
- Ensure compatibility with the latest stable versions of WordPress and WooCommerce.
- Support for High-Performance Order Storage (HPOS) in WooCommerce is required.

## 2. Security (CRITICAL):
- **Input Sanitization:** ALL external data (POST, GET, user input, API responses) MUST be sanitized using appropriate WordPress functions (e.g., `sanitize_text_field`, `absint`, `sanitize_email`, `wp_kses_post`).
- **Output Escaping:** ALL data output to the browser or logs MUST be escaped (e.g., `esc_html`, `esc_attr`, `esc_js`, `esc_url`).
- **Nonces:** ALL form submissions and AJAX actions MUST be protected with WordPress nonces (`wp_create_nonce`, `wp_verify_nonce`, `check_ajax_referer`).
- **Database Queries:** ALL database queries using `$wpdb` MUST use prepared statements (`$wpdb->prepare()`). Do NOT use variable concatenation directly in SQL.
- **Capability Checks:** ALL actions, especially in the admin area or affecting data, MUST be protected by capability checks (`current_user_can()`). Define and use specific capabilities for plugin roles.
- **File System Access:** Be cautious. Validate paths and permissions.
- **Data Privacy:** Handle personal data (customer info, agent location) with care. Implement features with GDPR and data minimization in mind. Default GPS data retention is 7 days.

## 3. Error Handling & Logging:
- Use `WP_Error` objects for functions that can fail, providing clear error codes and messages.
- Check for `WP_Error` return values and handle them gracefully.
- Use `wc_get_logger()` or `error_log()` for logging important events, errors, or debug information. Avoid `var_dump` or `print_r` in production code.
- Provide user-friendly error messages.

## 4. PHPDoc & Commenting:
- All classes, methods, and functions MUST have comprehensive PHPDoc blocks.
  - Include `@param`, `@return`, `@throws` with specific types.
  - Describe the purpose and any side effects.
- Inline comments should explain complex logic or the "why" behind a decision.
- Use `// TODO:`, `// FIXME:`, `// OPTIMIZE:` for actionable comments.

## 5. Naming Conventions:
- Prefix all global functions, classes, constants, and hooks with `rdm_` or `RDM_` to avoid conflicts (e.g., `rdm_get_order_details`, `class RDM_Agent_Manager`, `RDM_VERSION`).
- Follow WordPress naming conventions (lowercase with underscores for functions and variables, PascalCase for classes).

## 6. Performance:
- Write efficient database queries. Select only necessary columns. Use appropriate indexes.
- Implement caching (Transients API, Object Cache) for expensive operations or frequently accessed data (e.g., geocoding results, settings).
- Minimize direct DOM manipulation in JavaScript; use efficient selectors.
- Optimize image assets and static resources.
- Dequeue unnecessary scripts/styles on specific pages.

## 7. Database Schema & Operations:
- Use custom tables with the `$wpdb->prefix` (e.g., `{$wpdb->prefix}rdm_delivery_agents`).
- Define primary keys, foreign keys, and indexes appropriately (see `FEATURE_SPECIFICATIONS.md` for schema).
- Use `dbDelta()` for creating/updating tables during activation.
- All CRUD operations should be encapsulated in dedicated classes or functions.

## 8. User Roles & Capabilities:
- Implement two primary custom roles: `restaurant_manager` and `delivery_agent`.
- `restaurant_manager`: Can manage orders, agents, and kitchen view. NO access to general WordPress admin settings.
- `delivery_agent`: Can only view/update assigned orders via a mobile interface. NO backend access.
- Define specific capabilities for plugin actions and check them rigorously.

## 9. Internationalization (i18n) & Localization (l10n):
- All user-facing strings MUST be internationalized using WordPress gettext functions (e.g., `__()`, `_e()`, `_n()`, `esc_html__()`).
- The text domain MUST be `restaurant-delivery-manager`.
- Provide a `.pot` file.

## 10. Domain-Specific Knowledge (Restaurant Delivery):
- **Order Statuses:** Key custom statuses are `wc-preparing`, `wc-ready` (for agent assignment), `wc-out-for-delivery`, `wc-delivered`.
- **Agent Workflow:** Agent accepts order -> picks up -> delivers -> confirms. GPS tracking during "out-for-delivery".
- **Customer Tracking:** Customers track order status and agent location (when out for delivery).
- **COD (Cash on Delivery):** Is a core payment method. Requires tracking cash collected by agents.
- **Google Maps API:** Used for distance calculation, geocoding, route display, and agent tracking. Prioritize cost-optimization (caching, batched updates, static maps where appropriate).
- **Mobile First (for Agents):** The delivery agent interface is a PWA, designed for smartphones, touch-friendly, and battery/data conscious. GPS updates every 30-60 seconds.

## 11. Code Structure:
- Modular design: Use classes and separate files for different concerns (e.g., Database, Admin UI, Mobile UI, WooCommerce Integration, User Roles, API Endpoints).
- Avoid overly long functions or files.
- Use constants for fixed values (e.g., `RDM_DEFAULT_GPS_INTERVAL`).

## 12. API Endpoints:
- Custom REST API endpoints should be under the `rdm/v1` namespace (e.g., `/wp-json/rdm/v1/orders`).
- All endpoints must implement permission callbacks using `current_user_can()` or custom capability checks.
- Validate and sanitize all input parameters for API endpoints.

## 13. Specific Function Usage:
- When updating order status, prefer using `rdm_update_order_status()` if defined, as it may contain additional logic or hooks.
- For agent assignment, look for or use `rdm_assign_order_to_agent()`.
- Utilize helper functions defined in `API_REFERENCE.md` or `FEATURE_SPECIFICATIONS.md` if they exist. (AI: Cross-reference these documents for existing utility functions).

## 14. Avoid:
- Directly modifying core WordPress or WooCommerce files.
- Using deprecated WordPress functions.
- Storing sensitive information (like API keys) directly in version-controlled code if avoidable; use constants defined in `wp-config.php` or WordPress options.
- Global variables, unless absolutely necessary (like `$wpdb`). Encapsulate in classes.
---
applyTo: '**/*.js'
---
# JavaScript Coding Standards & Best Practices for Restaurant Delivery Manager

## 1. General Principles:
- This JavaScript will be used for the Restaurant Manager Admin Dashboard, Customer Tracking Interface, and Delivery Agent Mobile PWA.
- Follow modern JavaScript (ES6+) best practices. Use `let`, `const`, arrow functions, async/await.
- Code must be clean, readable, and maintainable.

## 2. WordPress Context:
- Use `wp.apiFetch` for making REST API calls to WordPress endpoints.
- Use `wp.i18n` for internationalization (`__`, `_x`, `_n`, `_nx`).
- Enqueue scripts properly using `wp_enqueue_script`, specifying dependencies and version numbers.
- Localize data from PHP to JavaScript using `wp_localize_script`.

## 3. Comments & Documentation:
- Use JSDoc comments for all functions, describing parameters, return values, and purpose.
- Inline comments for complex logic.

## 4. Error Handling:
- Implement robust error handling for API calls (e.g., using `.catch()` with Promises, try/catch with async/await).
- Display user-friendly error messages. Log technical details to the console.

## 5. Performance:
- Minimize DOM manipulations. Batch updates where possible.
- Use event delegation for dynamically added elements.
- Optimize for mobile performance, especially for the agent PWA (bundle size, execution time).
- Debounce or throttle event listeners that fire frequently (e.g., scroll, resize).

## 6. Security:
- Do not trust data from the client-side. All critical validation and business logic must occur on the server-side (PHP).
- When displaying data from APIs or user input, ensure it's properly sanitized or escaped to prevent XSS (though primary sanitization is server-side, be mindful if directly manipulating HTML).
- Be careful with `innerHTML`. Prefer `textContent` or creating DOM elements.

## 7. Mobile Agent PWA Specifics:
- **Offline First:** Design for resilience. Use Service Workers for caching assets and API responses.
- **Touch-Friendly:** Ensure all interactive elements have a minimum touch target size of 44x44px.
- **Battery Optimization:** For GPS tracking, use `navigator.geolocation` with appropriate options (`enableHighAccuracy: false` when possible, reasonable `timeout` and `maximumAge`). Interval for updates: 30-60 seconds.
- **Data Consciousness:** Minimize data transfer. Compress data if possible.
- **User Experience:** Provide clear feedback for actions, loading states, and offline status.

## 8. Google Maps API Usage:
- Interact with Google Maps JavaScript API for displaying maps, markers, routes.
- Load the API script asynchronously.
- Handle API loading errors gracefully.
- Be mindful of API call quotas; reuse objects and data where possible.

## 9. Frameworks/Libraries:
- If using a framework (e.g., React, Vue - specify if project decides to), follow its best practices.
- For now, assume vanilla JS or jQuery (if already part of WordPress admin context). Prefer vanilla JS for new frontend features to reduce dependencies.

## 10. Asynchronous Operations:
- Use Promises and async/await for managing asynchronous code.
- Handle loading states and race conditions appropriately.

## 11. Code Style:
- Use a linter (e.g., ESLint with a standard configuration like Airbnb or WordPress JavaScript Coding Standards).
- Consistent indentation, spacing, and naming conventions.
---
applyTo: '**/*.{css,scss}'
---
# CSS/SCSS Coding Standards for Restaurant Delivery Manager

## 1. General Principles:
- Write clean, maintainable, and scalable CSS.
- Use SCSS for its benefits (variables, nesting, mixins, functions). Compile to CSS.

## 2. Methodology:
- Consider BEM (Block, Element, Modifier) or a similar methodology for naming conventions to avoid conflicts and improve readability (e.g., `.rdm-order-card__title--pending`).
- Prefix all custom classes with `rdm-` to avoid conflicts with WordPress core or other plugin styles.

## 3. Responsiveness & Mobile First:
- Design mobile-first. Start with base styles for small screens and use media queries (`min-width`) to add complexity for larger screens.
- Ensure layouts are fluid and adapt to different screen sizes.
- Test on various devices and resolutions.

## 4. WordPress Admin Styles:
- For the Restaurant Manager Dashboard, try to align with WordPress admin UI conventions for a consistent experience, unless a distinct branding is required.
- Use WordPress CSS variables for colors and fonts where appropriate within the admin context.

## 5. Performance:
- Avoid overly complex selectors that are slow for the browser to parse.
- Minimize the use of `!important`.
- Keep CSS files minified for production.

## 6. Accessibility (a11y):
- Ensure sufficient color contrast.
- Support keyboard navigation (e.g., visible focus states).
- Use ARIA attributes where necessary (often handled in HTML/JS but CSS can support visibility).

## 7. SCSS Specifics:
- **Variables:** Use for colors, fonts, spacing, breakpoints.
- **Nesting:** Use judiciously; avoid excessive nesting (max 3-4 levels).
- **Mixins:** Create for reusable patterns (e.g., button styles, media query helpers).
- **Partials:** Break down SCSS into smaller, manageable partials (e.g., `_variables.scss`, `_buttons.scss`, `_forms.scss`) and import them into a main file.