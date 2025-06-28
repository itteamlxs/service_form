<?php
require_once __DIR__ . '/../core/env.php';
loadEnv();

define('APP_NAME', getenv('APP_NAME'));
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL'));
