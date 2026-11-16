<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/native-oauth.php';
native_endpoint(static fn() => native_token_grant(app_db(), native_form_input()));
