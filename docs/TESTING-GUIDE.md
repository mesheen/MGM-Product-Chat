# Testing Guide for Multi-File Upload Feature

## Prerequisites
- WordPress installation with WooCommerce plugin
- Aura Product Chatbot plugin installed and activated
- Admin access to test email functionality
- Test image files (PNG, JPG) and PDF files for upload

## Test Scenarios

### 1. Single File Upload (Backward Compatibility)
**Objective:** Ensure existing single-file upload functionality still works

**Steps:**
1. Navigate to a page with the chatbot interface
2. Locate the file upload button/field
3. Select a single image file (PNG or JPG)
4. Upload the file
5. Verify response contains:
   - `attachment_id` (number)
   - `url` (string)
   - `filename` (string)
   - `size` (number)
6. Check chat interface for confirmation message: "✓ File uploaded: [filename]"

**Expected Result:** 
- Single file uploads successfully
- Original response format maintained
- Chat shows upload confirmation

---

### 2. Multiple File Upload
**Objective:** Test new multi-file upload capability

**Steps:**
1. Navigate to the chatbot interface
2. Select multiple files (2-5 files: mix of PNG, JPG, PDF)
3. Upload all selected files
4. Verify response contains:
   - `files` array with multiple file objects
   - `count` matching number of uploaded files
   - Each file object has `attachment_id`, `url`, `filename`, `size`
5. Check chat interface for confirmation: "✓ [X] files uploaded successfully!"

**Expected Result:**
- All files upload successfully
- Response includes array of file data
- Chat shows correct file count

---

### 3. Order Summary Email with Multiple Files
**Objective:** Verify multiple artwork files appear in order confirmation email

**Steps:**
1. Upload multiple artwork files (2-3 files)
2. Add products to order
3. Complete the order process
4. Trigger order summary email to admin

**Email Body Should Include:**
```
ARTWORK FILES (3):
  - artwork1.png
    URL: https://...
  - artwork2.jpg
    URL: https://...
  - artwork3.pdf
    URL: https://...
```

**Email Attachments Should Include:**
- All uploaded artwork files (up to 10MB each)

**Expected Result:**
- Admin email lists all artwork files with URLs
- All artwork files are attached to email
- File information is clearly formatted

---

### 4. WooCommerce Order Email with Multiple Files
**Objective:** Verify artwork files appear in WooCommerce customer emails

**Steps:**
1. Create a WooCommerce order
2. Add multiple artwork files to order metadata:
   ```php
   update_post_meta($order_id, '_aura_artwork_attachment_ids', [123, 456, 789]);
   ```
3. Trigger customer processing order email

**Expected Result:**
- Order email displays all artwork files
- Each file shows name and URL
- Files are attached to email (customer_processing_order emails only)

---

### 5. Backward Compatibility Check
**Objective:** Ensure old single-file metadata still works

**Steps:**
1. Create a test order with old single-file metadata:
   ```php
   update_post_meta($order_id, '_aura_artwork_attachment_id', 123);
   ```
2. Trigger order email

**Expected Result:**
- Single file displays correctly in email
- File is attached to email
- No errors or warnings

---

### 6. Chat Confirmation Events
**Objective:** Test AuraBus event integration

**Steps:**
1. Open browser console
2. Upload a single file
3. Check console for: `[Aura Upload] File uploaded: [filename]`
4. Upload multiple files
5. Check console for: `[Aura Upload] [X] files uploaded successfully!`
6. Verify chat interface shows visual confirmation messages

**Expected Result:**
- Console logs show upload events
- Chat displays confirmation with correct styling
- Messages auto-scroll into view

---

### 7. File Type and Size Validation
**Objective:** Ensure file validation works correctly

**Test Cases:**
- ✓ Upload PNG file → Should succeed
- ✓ Upload JPG file → Should succeed
- ✓ Upload PDF file → Should succeed
- ✗ Upload TXT file → Should fail with error
- ✗ Upload file > 10MB → Should be rejected for email attachment
- ✓ Upload file < 10MB → Should be attached to email

**Expected Result:**
- Only allowed file types (PNG, JPG, JPEG, PDF) are accepted
- Files over 10MB are not attached to emails but upload succeeds
- Clear error messages for invalid files

---

### 8. Edge Cases

#### Empty Upload
**Steps:** Submit form without selecting any files
**Expected:** Error message: "No file uploaded."

#### Mixed Success/Failure
**Steps:** Upload 3 files where 1 has an error
**Expected:** 
- 2 files upload successfully
- Response includes only successful uploads
- Chat shows "✓ 2 files uploaded successfully!"

#### Large File Count
**Steps:** Upload 10+ files simultaneously
**Expected:**
- All valid files upload successfully
- Response includes all files
- Chat confirmation shows correct count

---

## Automated Testing (Future)

Since there's no test infrastructure currently, consider adding:

1. **PHPUnit Tests**
   - Test single file upload response format
   - Test multiple file upload response format
   - Test order email formatting
   - Test WooCommerce integration

2. **JavaScript Tests**
   - Test AuraBus event emission
   - Test chat confirmation display
   - Test event listener setup

3. **Integration Tests**
   - End-to-end upload → email flow
   - WordPress multisite compatibility
   - WooCommerce version compatibility

## Security Checks

- ✓ File type validation (MIME type checking)
- ✓ Nonce verification for AJAX requests
- ✓ File sanitization
- ✓ Size limits enforced
- ✓ Secure file storage location
- ✓ No direct file execution possible

## Performance Considerations

- Files stored in dedicated `/aura-artwork/` folder
- No date-based subdirectories for easier management
- Attachment metadata generated for images
- Email attachments limited to 10MB per file to prevent timeouts

## Rollback Plan

If issues occur, the feature can be rolled back by:
1. Reverting to previous commit
2. Single-file uploads continue to work as before
3. Existing artwork metadata remains intact
