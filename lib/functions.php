<?php

define('WP_ADMIN_PATH', '/wp-login.php');
define('DP', 'http://');
define('AUTHOR_DORK', '/?author=1');
define('LOGIN_MACROS', '{login}');
define('LOCATION_URL_RULES', ['', 'http:', '?author=1', 'author']);

function trimRow($item) {
    return trim($item);
}
