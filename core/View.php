<?php
namespace App\Core;

$sections = [];
$currentSection = null;

function startSection($name) {
    global $sections, $currentSection;
    $currentSection = $name;
    ob_start();
}

function endSection() {
    global $sections, $currentSection;
    $sections[$currentSection] = ob_get_clean();
    $currentSection = null;
}
