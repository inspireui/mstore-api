<?php
class FlutterValidator {
    public static function cleanText($value) {
        return sanitize_text_field($value);
    }

    public static function escapeAttribute($value) {
        return esc_attr($value);
    }

    public static function escapeUrl($value) {
        return esc_url($value);
    }

    public static function sanitizeEmail($value) {
        return sanitize_email($value);
    }
}
?>