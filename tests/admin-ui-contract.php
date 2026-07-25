<?php

declare(strict_types=1);

$root     = dirname(__DIR__);
$css      = file_get_contents($root . '/assets/css/ys-raq-addons-admin.css');
$loader   = file_get_contents($root . '/vendor/yangsheep/ys-plugin-hub-client/ys-plugin-hub-client.php');
$failures = 0;

function ys_raq_ui_assert(bool $condition, string $message, int &$failures): void
{
	if (!$condition) {
		++$failures;
		fwrite(STDERR, "FAIL: {$message}\n");
		return;
	}

	echo "PASS: {$message}\n";
}

ys_raq_ui_assert(
	str_contains($css, 'max-width: 1200px;')
		&& str_contains($css, 'padding: 30px;')
		&& str_contains($css, 'font-size: 28px;')
		&& str_contains($css, 'padding: 25px;'),
	'RAQ matches the shared settings dimensions',
	$failures
);

ys_raq_ui_assert(
	str_contains($loader, 'Version:     2.0.4')
		&& str_contains($loader, 'YSToolboxMenuNormalizer'),
	'RAQ vendors the menu-normalizing Hub Client 2.0.4',
	$failures
);

exit($failures > 0 ? 1 : 0);
