<?php
// backend/lib/cms_helper.php

/**
 * Get CMS JSON for a page.
 * - published by default
 * - when $preferDraft=true, tries draft then falls back to published
 */
function getCMSContent(string $pageKey, bool $preferDraft = false): array {
    global $pdo; // from backend/config/app.php

    $sql = "
      SELECT content_data
      FROM rt_cms_pages
      WHERE page_key = :k AND status = :s
      ORDER BY updated_at DESC
      LIMIT 1
    ";

    $try = function(string $status) use ($pdo, $sql, $pageKey) {
        $st = $pdo->prepare($sql);
        $st->execute([':k' => $pageKey, ':s' => $status]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $data = json_decode($row['content_data'] ?? '{}', true);
        return is_array($data) ? $data : [];
    };

    if ($preferDraft) {
        return $try('draft') ?? $try('published') ?? [];
    }
    return $try('published') ?? $try('draft') ?? [];
}

/** Replace template tokens like {{customer_name}} */
function cmsTokens(string $html, array $vars = []): string {
    $map = [
        '{{customer_name}}' => $vars['customer_name'] ?? '{Customer Name}',
    ];
    return strtr($html, $map);
}
