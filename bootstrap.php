<?php

/**
 * Project bootstrap: defines BASE_PATH then loads DB settings and helpers.
 * Load this instead of referencing theme/config.php directly.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}

require_once BASE_PATH . '/includes/env.php';
lgueLoadEnvFile();

require_once BASE_PATH . '/includes/session.php';
lgueStartSession();

require_once BASE_PATH . '/theme/config.php';
require_once BASE_PATH . '/includes/settings_helpers.php';
require_once BASE_PATH . '/includes/input_validate.php';
