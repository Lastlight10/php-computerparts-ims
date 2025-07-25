<?php ob_start(); ?>

<?php // content here ?>

<?php
$content = ob_get_clean();
require_once 'staff_layout.php';

use App\Core\Logger;
$memory = memory_get_usage();
Logger::log("Used: $memory on products_list.php");
?>