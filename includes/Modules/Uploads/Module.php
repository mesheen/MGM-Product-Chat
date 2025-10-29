<?php
declare(strict_types=1);

namespace AuraModular\Modules\Uploads;

use AuraModular\Modules\BaseModule;

final class Module extends BaseModule
{
    protected string $slug = 'aura-uploads';

    public function register(): void
    {
        add_action('wp_ajax_aura_upload_artwork', [$this, 'upload_artwork_ajax_handler']);
        // intentionally no nopriv — uploading should be authenticated via nonce/capability
    }

    public function upload_artwork_ajax_handler(): void
    {
        check_ajax_referer('aura_upload_artwork', 'upload_nonce');

        if (empty($_FILES['artwork']) || !isset($_FILES['artwork']['name'])) {
            wp_send_json_error(['message' => 'No file uploaded.'], 400);
        }
        $file = $_FILES['artwork'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Upload error code ' . $file['error']], 400);
        }

        $allowed_mimes = [
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'pdf'  => 'application/pdf'
        ];

        $overrides = [
            'test_form' => false,
            'mimes'     => $allowed_mimes
        ];

        // Force uploads into /uploads/aura-artwork (no Y/M folders)
        $__aura_filter = add_filter('upload_dir', function($dirs){
            $sub = 'aura-artwork';
            $dirs['subdir'] = '/' . $sub;
            $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
            return $dirs;
        });

        $result = wp_handle_upload($file, $overrides);

        // Remove temporary upload_dir filter
        if (isset($__aura_filter)) { remove_filter('upload_dir', $__aura_filter); }

        if (isset($result['error'])) {
            wp_send_json_error(['message' => $result['error']], 400);
        }

        $file_path = $result['file'];
        $file_url  = $result['url'];
        $mime      = $result['type'];

        // Create attachment
        $attachment = [
            'post_title'     => sanitize_file_name(pathinfo($file_path, PATHINFO_FILENAME)),
            'post_mime_type' => $mime,
            'post_status'    => 'inherit'
        ];
        $attach_id = wp_insert_attachment($attachment, $file_path);
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $meta = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $meta);

        wp_send_json_success([
            'attachment_id' => $attach_id,
            'url'           => $file_url,
            'filename'      => basename($file_path),
            'size'          => (int) filesize($file_path)
        ], 200);
    }
}