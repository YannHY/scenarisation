<?php
declare(strict_types=1);

function public_search_normalize(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    return strtr($value, ['à'=>'a', 'â'=>'a', 'ä'=>'a', 'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e', 'î'=>'i', 'ï'=>'i', 'ô'=>'o', 'ö'=>'o', 'ù'=>'u', 'û'=>'u', 'ü'=>'u', 'ç'=>'c', 'œ'=>'oe']);
}

function public_search_text(array $document): string
{
    $parts = [];
    $append = static function (array $item, array $keys) use (&$parts): void {
        foreach ($keys as $key) {
            if (is_string($item[$key] ?? null)) $parts[] = $item[$key];
        }
    };
    $append(is_array($document['meta'] ?? null) ? $document['meta'] : [], ['name', 'description', 'command', 'personas', 'outcomes']);
    foreach ((is_array($document['sessions'] ?? null) ? $document['sessions'] : []) as $session) {
        if (!is_array($session)) continue;
        $append($session, ['title', 'objectives', 'intentions']);
        foreach ((is_array($session['activities'] ?? null) ? $session['activities'] : []) as $activity) {
            if (is_array($activity)) $append($activity, ['title', 'name', 'description', 'instructions']);
        }
    }
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(implode(' ', $parts)), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function search_public_designs(PDO $db, string $query, int $offset = 0, int $limit = 12): array
{
    $query = mb_substr(trim($query), 0, 200, 'UTF-8');
    $words = array_values(array_unique(preg_split('/[^\p{L}\p{N}]+/u', public_search_normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: []));
    if (mb_strlen($query, 'UTF-8') < 2 || !$words) return ['results' => [], 'count' => 0];
    // Same visibility rules as share.php: a link-only publication is not discoverable.
    $rows = $db->query("SELECT title, document_json, share_token FROM learning_designs
        WHERE is_published = 1 AND is_listed = 1 AND share_token IS NOT NULL AND share_token <> ''
        ORDER BY listed_at DESC, id DESC");
    $matches = [];
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $document = json_decode((string)$row['document_json'], true);
        if (!is_array($document)) continue;
        $title = is_string($document['meta']['name'] ?? null) ? trim($document['meta']['name']) : '';
        if ($title === '') $title = trim((string)$row['title']) ?: 'Scénario sans titre';
        $text = $title . ' ' . public_search_text($document);
        $normalized = public_search_normalize($text);
        foreach ($words as $word) {
            if (!str_contains($normalized, $word)) continue 2;
        }
        $score = 0;
        foreach ($words as $word) {
            if (str_contains(public_search_normalize($title), $word)) $score++;
        }
        $start = max(0, (int)mb_strpos($normalized, $words[0], 0, 'UTF-8') - 70);
        $excerpt = ($start ? '…' : '') . mb_substr($text, $start, 220, 'UTF-8');
        if (mb_strlen($text, 'UTF-8') > $start + 220) $excerpt .= '…';
        $matches[] = [
            'url' => 'view.php?token=' . rawurlencode((string)$row['share_token']),
            'meta' => ['title' => $title],
            'excerpt' => htmlspecialchars($excerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'score' => $score,
        ];
    }
    usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    return ['results' => array_slice($matches, max(0, $offset), max(1, min(100, $limit))), 'count' => count($matches)];
}
