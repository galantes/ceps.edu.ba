<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
require_once dirname(__DIR__) . '/src/news-image.php';
require_once dirname(__DIR__) . '/src/icons.php';

legacyEnglishRedirect();
$path = languagePath(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'));
if (str_starts_with($path,'/media/')) {
    if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);exit;}
    require dirname(__DIR__).'/src/admin-media.php';serveAdminMedia($path);
}

if (preg_match('~^/admin(?:/|$)~i', $path)) {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST','HEAD'],true)) { http_response_code(405);header('Allow: GET, POST, HEAD');exit; }
    if ($path !== '/admin') { header('Location: /admin',true,$_SERVER['REQUEST_METHOD']==='POST'?307:301);exit; }
    require dirname(__DIR__).'/src/admin.php';
    try { adminRun(); } catch(Throwable $error) { error_log((string)$error);adminPage('Privremeno nedostupno','<p>Administraciju nije moguće učitati. Pokušajte kasnije.</p>',503); }
}

try {
$requestedUrl = $_SERVER['REQUEST_URI'] ?? '/';
$seoTarget = seoUrl($requestedUrl);
if ($seoTarget !== $requestedUrl && in_array($_SERVER['REQUEST_METHOD'], ['GET','HEAD'], true)) {
    header('Location: '.$seoTarget, true, 301); exit;
}
if (preg_match('~^/(?:novosti|news|nachrichten)/([0-9]+)(?:-.*)?$~i', $path, $newsRoute)) {
    $_GET['id'] = (string)(int)$newsRoute[1];
    $path = '/Novost';
} else $path = seoInternalPath($path);
$route = strtolower($path);
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    layout('Nedopušten zahtjev', '', '', '<section class="empty"><h1>Nedopušten zahtjev</h1></section>', 405);
}
if ($route === '/sitemap.xml') { require dirname(__DIR__).'/src/sitemap.php'; serveSitemap(); }
if ($route === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo getenv('CEPS_ENV') === 'production'
        ? "User-agent: *\nDisallow: /admin\nDisallow: /pretraga\nDisallow: /en/search\nDisallow: /de/suche\nSitemap: https://ceps.edu.ba/sitemap.xml\n"
        : "User-agent: *\nDisallow: /\n";
    exit;
}
    if ($route === '/pretraga') {
        require dirname(__DIR__) . '/src/search.php';
        layout('Pretraga', 'Pretražite novosti i podstranice CEPS-a.', '', searchPage());
    }
    if (in_array($route, ['/', '/default', '/default.aspx'], true)) {
        if ($path !== '/') { header('Location: '.seoUrl('/'), true, 301); exit; }
        require dirname(__DIR__) . '/src/home.php';
        layout(SCHOOL_NAME, 'CEPS – Centar za poslovne studije. Studijski programi, upisi, novosti i informacije o studiranju u Kiseljaku.', '/', homePage());
    }
    if (in_array($route, ['/onama', '/onama.aspx'], true)) {
        if ($path !== '/Onama') { header('Location: '.seoUrl('/Onama'), true, 301); exit; }
        require dirname(__DIR__) . '/src/about.php';
        layout('O nama', 'Upoznajte Visoku školu CEPS u Kiseljaku, osnivanje i razvoj ustanove te mogućnosti studiranja.', '/Onama', aboutPage());
    }
    require_once dirname(__DIR__) . '/src/pages.php';
    $pageKey = preg_replace('/\.aspx$/', '', ltrim($route, '/'));
    $staticPages = contentPages();
    if (isset($staticPages[$pageKey])) {
        $staticPage = $staticPages[$pageKey];
        $canonical = '/' . $staticPage['slug'];
        if ($path !== $canonical) { header('Location: ' . seoUrl($canonical) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301); exit; }
        layout($staticPage['title'], excerpt(preg_replace('/\{\{[^}]+\}\}/', '', $staticPage['html'])), $canonical, contentPage($staticPage));
    }
    if (str_starts_with($route, '/files/')) {
        require dirname(__DIR__) . '/src/files.php';
        serveLegacyFile($path);
    }
    if (in_array($route, ['/novost', '/novost.aspx'], true)) {
        $params = array_change_key_case($_GET, CASE_LOWER);
        $id = $params['id'] ?? '';
        $row = false;
        if (is_string($id) && preg_match('/^[0-9]{1,18}$/D', $id)) {
            $stmt = database()->prepare('SELECT * FROM Novosti WHERE ID = ?'.(siteLanguage() !== 'bs'?' AND '.newsLanguageColumn().'=1':'').'');
            $stmt->execute([(int)$id]);
            $row = $stmt->fetch();
        }
        if (!$row) {
            layout('Objava nije pronađena', '', '', '<section class="empty"><p class="eyebrow">404</p><h1>Objava nije pronađena.</h1><p>Provjerite adresu ili pronađite objavu u arhivi.</p><a class="button" href="/Novosti">Pregledaj novosti</a></section>', 404);
        }
        $canonical = '/Novost?id=' . $row['ID'];
        if ($path !== '/Novost' || $id !== (string)$row['ID'] || !isset($_GET['id'])) {
            header('Location: ' . seoUrl($canonical), true, 301);
            exit;
        }
        require_once dirname(__DIR__) . '/src/news-footer.php';
        $title = titleText($row['Naslov']);
        $content = '<article data-news-id="' . $row['ID'] . '"><div class="article-heading"><a class="back" href="/Novosti">' . icon('arrow-left') . ' <span>Sve novosti</span></a><p class="eyebrow">CEPS / NOVOSTI</p><h1>' . esc($title) . '</h1><time datetime="' . esc(str_replace(' ', 'T', $row['Datum'] ?? '')) . '">' . esc(newsDate($row['Datum'])) . '</time></div><div class="article-body">' . articleHtml($row['Tekst'] ?? '') . '</div>' . newsFooter($row) . '</article>';
        require_once dirname(__DIR__) . '/src/news-image.php';
        $shareImage = newsImage($row['Tekst'] ?? '');
        layout($title, excerpt($row['Tekst'] ?? ''), $canonical, $content, 200, ['type'=>'article', 'image'=>$shareImage === NEWS_DEFAULT_IMAGE ? null : $shareImage, 'published'=>$row['Datum'] ?? null]);
    }
    if (in_array($route, ['/', '/default', '/default.aspx', '/novosti', '/novosti.aspx'], true)) {
        $page = $_GET['page'] ?? '1';
        if (!is_string($page) || !preg_match('/^[1-9][0-9]{0,5}$/D', $page)) {
            layout('Stranica nije pronađena', '', '', '<section class="empty"><h1>Stranica nije pronađena.</h1></section>', 404);
        }
        $page = (int)$page;
        $pdo = database();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM Novosti WHERE '.newsLanguageColumn().'=1')->fetchColumn();
        $pages = max(1, (int)ceil($count / 12));
        if ($page > $pages) layout('Stranica nije pronađena', '', '', '<section class="empty"><h1>Stranica nije pronađena.</h1></section>', 404);
        $canonical = '/Novosti' . ($page > 1 ? '?page=' . $page : '');
        if ($route !== '/novosti' || $path !== '/Novosti') {
            // Homepage is not implemented yet. Do not permanently consolidate
            // the real site's homepage into the news archive during development.
            header('Location: ' . seoUrl($canonical), true, in_array($route, ['/', '/default', '/default.aspx'], true) ? 302 : 301);
            exit;
        }
        $stmt = $pdo->prepare('SELECT ID, Naslov, Tekst, Datum FROM Novosti WHERE '.newsLanguageColumn().'=1 ORDER BY Datum DESC, ID DESC LIMIT 12 OFFSET ?');
        $stmt->bindValue(1, ($page - 1) * 12, PDO::PARAM_INT);
        $stmt->execute();
        $content = '<section class="intro news-intro"><h1>Novosti i objave</h1><span class="heading-rule" aria-hidden="true"></span><p>Novosti, događaji i obavijesti CEPS-a.</p></section><section class="archive" aria-label="Arhiva novosti"><div class="archive-label"><h2>Najnovije s CEPS-a</h2><span>' . $count . ' objava u arhivi</span></div><div class="cards">';
        foreach ($stmt as $row) {
            $content .= '<article class="card"><a class="archive-picture" href="/Novost?id=' . $row['ID'] . '" tabindex="-1" aria-hidden="true"><img class="news-cover" src="' . esc(newsImage($row['Tekst'] ?? '')) . '" data-fallback="' . NEWS_DEFAULT_IMAGE . '" alt="" loading="lazy" width="640" height="400"></a><div class="archive-card-copy"><time>' . esc(newsDate($row['Datum'])) . '</time><h3><a href="/Novost?id=' . $row['ID'] . '">' . esc(titleText($row['Naslov'])) . '</a></h3><p>' . esc(excerpt($row['Tekst'] ?? '')) . '</p><a class="read" href="/Novost?id=' . $row['ID'] . '" aria-label="' . esc('Pročitaj: ' . titleText($row['Naslov'])) . '">Pročitaj objavu ' . icon('arrow-up-right') . '</a></div></article>';
        }
        $content .= '</div><nav class="pagination" aria-label="Stranice arhive">';
        if ($page > 1) $content .= '<a href="/Novosti' . ($page > 2 ? '?page=' . ($page - 1) : '') . '">← Prethodna</a>';
        $content .= '<span>Stranica ' . $page . ' od ' . $pages . '</span>';
        if ($page < $pages) $content .= '<a href="/Novosti?page=' . ($page + 1) . '">Sljedeća →</a>';
        $content .= '</nav></section>';
        layout('Novosti i objave' . ($page > 1 ? ' — stranica ' . $page : ''), 'Novosti, projekti i prilike akademske zajednice CEPS-a u Kiseljaku.', $canonical, $content);
    }
    layout('Stranica nije pronađena', '', '', '<section class="empty"><p class="eyebrow">404</p><h1>Stranica nije pronađena.</h1><a href="/Novosti">Pregledaj novosti</a></section>', 404);
} catch (Throwable $error) {
    error_log((string)$error);
    layout('Privremeno nedostupno', '', '', '<section class="empty"><h1>Sadržaj je privremeno nedostupan.</h1><p>Pokušajte ponovno kasnije.</p></section>', 503);
}
