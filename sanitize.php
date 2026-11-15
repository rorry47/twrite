<?php
// HTML allowlist for user-generated posts. Requires PHP DOM extension.
function twrite_safe_url(string $url): string {
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match('/[\\x00-\\x20\\x7f]/', $url)) return '';
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host'])) return '';
    return in_array(strtolower($parts['scheme']), ['http', 'https'], true) ? $url : '';
}
function twrite_sanitize_html(string $html): string {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="twrite-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $dom->getElementById('twrite-root');
    if (!$root) return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $allowed = ['b', 'strong', 'i', 'em', 'u', 's', 'strike', 'a', 'br', 'p', 'div', 'pre', 'code', 'img', 'font', 'span', 'ul', 'ol', 'li', 'blockquote'];
    // Attribute filtering is done in a separate pass to avoid retaining event handlers.
    $filter = function (DOMNode $node) use (&$filter, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!($child instanceof DOMElement)) continue;
            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'template', 'video', 'audio', 'base', 'meta', 'link'], true)) {
                $node->removeChild($child); continue;
            }
            $filter($child);
            if (!in_array($tag, $allowed, true)) {
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child); continue;
            }
            $href = $tag === 'a' ? twrite_safe_url($child->getAttribute('href')) : '';
            $src = $tag === 'img' ? twrite_safe_url($child->getAttribute('src')) : '';
            $style = $child->getAttribute('style');
            $fontSize = '';
            if (preg_match('/(?:^|;)\s*font-size\s*:\s*(1[0-9]|2[0-9]|30)px\s*(?:;|$)/i', $style, $m)) $fontSize = $m[1] . 'px';
            $attrs = [];
            foreach ($child->attributes as $attr) $attrs[] = $attr->name;
            foreach ($attrs as $attr) $child->removeAttribute($attr);
            if ($tag === 'a' && $href !== '') {
                $child->setAttribute('href', $href);
                $child->setAttribute('rel', 'nofollow noopener noreferrer');
            }
            if ($tag === 'img') {
                if ($src === '') { $node->removeChild($child); continue; }
                $child->setAttribute('src', $src);
                $child->setAttribute('alt', 'Image');
                $child->setAttribute('loading', 'lazy');
            }
            if ($fontSize !== '' && in_array($tag, ['font', 'span'], true)) $child->setAttribute('style', 'font-size:' . $fontSize);
        }
    };
    $filter($root);
    $out = '';
    foreach ($root->childNodes as $child) $out .= $dom->saveHTML($child);
    return $out;
}
