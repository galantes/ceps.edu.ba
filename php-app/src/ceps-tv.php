<?php
declare(strict_types=1);

function cepsTv(): string {
    $channel = 'UCgdU4Eeb9HcwpRioPzOjAgw';
    $cache = sys_get_temp_dir().'/ceps-tv-feed-'. $channel .'.xml';
    $xml = is_file($cache) ? file_get_contents($cache) : '';
    if (!$xml || filemtime($cache) < time() - 3600) {
        $context = stream_context_create(['http'=>['timeout'=>3, 'header'=>"User-Agent: CEPS-Website/1.0\r\n"]]);
        $fresh = @file_get_contents('https://www.youtube.com/feeds/videos.xml?channel_id='.$channel, false, $context);
        if ($fresh && str_contains($fresh, '<feed') && str_contains($fresh, '<entry>')) {
            $xml = $fresh;
            @file_put_contents($cache, $xml, LOCK_EX);
        }
    }
    if (!$xml) $xml = file_get_contents(dirname(__DIR__).'/content/ceps-tv-feed.xml');
    $previous = libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $html = '<div class="ceps-tv-grid">';
    if ($feed) {
        $count = 0;
        foreach ($feed->entry as $entry) {
            $id = (string)$entry->children('http://www.youtube.com/xml/schemas/2015')->videoId;
            if (!preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) continue;
            $title = (string)$entry->title;
            $html .= '<article class="ceps-tv-video"><button type="button" class="ceps-tv-play" data-video="'.$id.'" aria-label="Pokreni video: '.esc($title).'"><img src="https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg" alt="" loading="lazy"><span aria-hidden="true">'.icon('player-play').'</span></button><p class="ceps-tv-title"><a href="https://www.youtube.com/watch?v='.$id.'" target="_blank" rel="noopener noreferrer">'.esc($title).'</a></p></article>';
            if (++$count === 6) break;
        }
    }
    return $html.'</div>';
}

