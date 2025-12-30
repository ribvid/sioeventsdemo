<?php
/*
Plugin Name: AI Engine TinyMCE Translate
Description: Adds AI Engine translate post functionality to TinyMCE editor
Version: 1.0.3
Author: Custom
*/

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Load the actual plugin from subdirectory
require_once __DIR__ . '/ai-engine-tinymce-translate/ai-engine-tinymce-translate.php';
