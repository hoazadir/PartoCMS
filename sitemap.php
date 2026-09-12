<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$seo = getSeo();
echo $seo->generateSitemap();
