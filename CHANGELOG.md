# Aura Product Chatbot – Modular
## 2.2.0 – 2025-10-25
- Added Communication Layer (PHP + JS) to unify module-to-module messaging and settings exchange.
- Registered `aura_chat_settings` with WordPress Options + REST and localized settings to frontend/admin via `AuraConfig`.
- Introduced lightweight JS Event Bus (`assets/js/aura-bus.js`) as a non-breaking communication primitive (`window.AuraBus`).
- Hardened REST requests with `X-WP-Nonce` helper.
- Repaired potential JS invalid escape sequences (e.g., `\d` in template strings) that caused `invalid escape sequence` errors.
- Kept existing UI/UX, assets, and module code intact while removing coupling assumptions. This is designed as an additive, non-destructive refactor.

## [2.3.0] - 2025-10-26
- Color selection upgraded to **chips** (text-only), matching artwork chip styling.
- Images unified across grid/detail/wizard with `.apc-img-wrap` and rounded corners.
- CSS consolidated to `assets/css/aura-chatbot.css` with scoped overrides.

## [2.3.1] - 2025-10-26
- Fix: define missing `aura_chatbot_enqueue_scripts()` to prevent fatal on activation.

## [2.3.2] - 2025-10-26
- Fixed PHP parse error: replaced stray backreference ("\\1") with proper `function aura_chatbot_enqueue_scripts()` and closed wrapper.

## [2.3.3] - 2025-10-26
- Fixed JS parse error: cleaned up `stepColors()` template so no stray HTML leaks outside template literals.

## [2.3.4] - 2025-10-26
- Product Detail: enforced `.apc-img-wrap.detail` for all detail and wizard images to prevent collapse.
- Color Chips: added robust delegated click handlers on side panel/content (and fallback on document) so selection toggling always works.

## [2.3.5] - 2025-10-26
- Wizard Next fixed: reads selected **color chips** instead of legacy `<select>` (`selectedOptions` null error resolved).

## [2.4.0] - 2025-10-29
### Multi-File Upload Feature
- **Upload Handler**: Enhanced `aura_upload_artwork_ajax_handler()` to support multiple file uploads while maintaining backward compatibility with single-file uploads.
  - Accepts multiple files via `artwork[]` parameter
  - Returns array of uploaded files with attachment IDs, URLs, filenames, and sizes
  - Single file uploads continue to work with original response format
- **Order Summary**: Updated `aura_send_order_ajax_handler()` to process multiple artwork files.
  - Accepts array of attachment IDs via `artwork_attachment_ids[]` parameter
  - Email body now lists all uploaded artwork files with filenames and URLs
  - Attaches all artwork files to admin email (up to 10MB per file)
  - Maintains backward compatibility with single `artwork_attachment_id` parameter
- **WooCommerce Integration**: Enhanced `EmailAugment` module to support multiple artwork files.
  - Order meta fields now display all uploaded artwork files
  - Customer emails automatically attach all artwork files
  - Reads from `_aura_artwork_attachment_ids` array (with fallback to single file)
- **Chat Confirmation**: Added visual upload confirmation in chat interface.
  - Listens for `artwork:uploaded` events on AuraBus
  - Displays success message with file count or filename
  - Green background styling for uploaded file confirmations
- **Documentation**: Added comprehensive multi-file upload documentation in `docs/MULTI-FILE-UPLOAD.md`
- **Build**: Added `.gitignore` to exclude node_modules and build artifacts from repository
