# Multi-File Upload Feature

## Overview
The upload module now supports uploading multiple files simultaneously while maintaining backward compatibility with single-file uploads.

## Features

### 1. Multi-File Upload Handler
**Location:** `includes/Modules/Legacy/Original.php` - `aura_upload_artwork_ajax_handler()`

- Accepts both single file and multiple file uploads via the `artwork` parameter
- Returns different response formats based on upload type:
  - **Single file:** Returns object with `attachment_id`, `url`, `filename`, `size`
  - **Multiple files:** Returns object with `files` array and `count`
- All files are stored in `/wp-content/uploads/aura-artwork/` directory
- Supports PNG, JPG, JPEG, and PDF file types

### 2. Order Summary Email Enhancement
**Location:** `includes/Modules/Legacy/Original.php` - `aura_send_order_ajax_handler()`

- Accepts multiple artwork attachment IDs via:
  - `artwork_attachment_id` (single file - backward compatible)
  - `artwork_attachment_ids[]` (array of IDs for multiple files)
- Email body now includes:
  - List of all uploaded artwork files with filenames
  - Direct URLs to each file
  - Total count of artwork files
- Attaches all artwork files to admin email (up to 10MB per file)

### 3. WooCommerce Email Integration
**Location:** `includes/Modules/EmailAugment/Module.php`

#### Order Meta Fields
- Displays all artwork files in WooCommerce order emails
- Shows filename and URL for each file
- Backward compatible with single file metadata

#### Email Attachments
- Automatically attaches all artwork files to customer processing order emails
- Reads from `_aura_artwork_attachment_ids` post meta (array)
- Falls back to `_aura_artwork_attachment_id` for single file (backward compatible)

### 4. Chat Confirmation
**Location:** `includes/Modules/Legacy/Original.php` - inline script

- Listens for `artwork:uploaded` events on the AuraBus
- Displays visual confirmation message in chat interface
- Shows appropriate message based on upload type:
  - Single file: "✓ File uploaded: filename.png"
  - Multiple files: "✓ 3 files uploaded successfully!"
- Green background styling for success confirmation
- Automatically scrolls chat to show confirmation

## Usage

### Backend (PHP)
```php
// Send order with multiple artwork files
$artwork_ids = [123, 456, 789]; // Array of attachment IDs

wp_remote_post(admin_url('admin-ajax.php'), [
    'body' => [
        'action' => 'aura_send_order',
        'order' => json_encode($order_items),
        'artwork_attachment_ids' => $artwork_ids
    ]
]);
```

### Frontend (JavaScript)
```javascript
// Trigger upload confirmation event
if (window.AuraBus) {
    window.AuraBus.emit('artwork:uploaded', {
        count: 3,
        files: [/* uploaded file data */]
    });
}
```

## Backward Compatibility

All changes maintain full backward compatibility with the existing single-file upload system:
- Single file uploads continue to work with the same response format
- Single `artwork_attachment_id` parameter still supported
- Single file post meta `_aura_artwork_attachment_id` still read as fallback
- Existing client code does not need to be modified

## Testing Checklist

- [ ] Upload single file - verify backward compatibility
- [ ] Upload multiple files (2-5 files)
- [ ] Verify chat confirmation appears for both single and multi-file uploads
- [ ] Send order with multiple artwork files
- [ ] Check admin email receives all attachments
- [ ] Verify WooCommerce order emails show all artwork files
- [ ] Test with different file types (PNG, JPG, PDF)
- [ ] Verify file size limits are enforced (10MB per file)
