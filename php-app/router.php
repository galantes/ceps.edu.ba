<?php
// PHP built-in server router. The document root must be php-app/public.
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (in_array($path, ['/assets/vendor/jodit/jodit.min.js', '/assets/vendor/jodit/jodit.min.css', '/assets/admin-editor.js', '/assets/admin.css', '/assets/news-share.js', '/assets/virtual-tour.js', '/assets/announcement.js', '/assets/flag-ba.svg', '/assets/flag-gb.svg', '/assets/flag-de.svg', '/assets/site.css', '/assets/home.css', '/assets/news-images.js', '/assets/contact.js', '/assets/slider.js', '/assets/navigation.js', '/assets/mobile-home.js', '/assets/cooperation.js', '/assets/study-tabs.js', '/assets/gallery.js', '/assets/project-news.js', '/assets/design-system.css'], true)) return false;
require __DIR__ . '/public/index.php';
