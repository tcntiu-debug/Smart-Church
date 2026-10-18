<?php
// ---------------------------------------------------------------------------
// Legacy TIU host (tiu.tcnikorodu.org) -> Smart Church, without .htaccess.
//
// Only use this file when you cannot get scripts/legacy-redirect/.htaccess into
// the legacy document root (cPanel hides dotfiles until File Manager ->
// Settings -> "Show Hidden Files" is ticked). The .htaccess is the better fix:
// it 301s deep links too and stops the old endpoints from answering at all.
//
// Why this file exists: renaming index.php away is not a redirect. The old
// pages redirect to it themselves - dashboard.php, member-view.php and friends
// answer "302 -> /index.php" - so with no index.php on disk every one of those
// links ends on the host's "Page not found" page. This shim answers /index.php
// with a 301 to the new site, which repairs that chain without mod_rewrite.
//
// Install: upload as `index.php` in the legacy document root, after keeping the
// old login page as `index.php.bak`. `/` itself is served by index.html, so
// upload that as well (DirectoryIndex lists index.php first, so without the
// .htaccess the old app would win `/` again).
// ---------------------------------------------------------------------------

$target = 'https://smartchurch.tcnikorodu.org/';

header('Location: ' . $target, true, 301);
header('Cache-Control: no-store');

// Only reached if the browser ignores the header - keep it tiny and useful.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>TIU has moved to Smart Church</title>
    <meta http-equiv="refresh" content="0; url=<?= $target ?>">
    <meta name="robots" content="noindex, follow">
    <link rel="canonical" href="<?= $target ?>">
</head>
<body style="font:16px/1.5 -apple-system,'Segoe UI',Roboto,Arial,sans-serif;text-align:center;padding:48px">
    <p>TIU has moved to <a href="<?= $target ?>">smartchurch.tcnikorodu.org</a>.</p>
</body>
</html>
