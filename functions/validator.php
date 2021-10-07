<?php
class FlutterValidator {
    public static function cleanText($value) {
        return sanitize_text_field($value);
    }
}
?>