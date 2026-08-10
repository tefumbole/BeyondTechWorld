<?php

namespace App\Support;

/**
 * Rich-text notes for quotations / bookings: store safe HTML, display as HTML.
 * Pasted Word / Docs content often uses headings, divs, and inline styles — those
 * must survive sanitize or formatting "disappears" after submit.
 */
class BookingNoteFormatter
{
    /** Tags kept after paste / editor save. */
    private static $allowedTags = [
        'p', 'br', 'div', 'span',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'hr', 'a',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
    ];

    /** Attributes allowed on any tag (plus tag-specific below). */
    private static $globalAttrs = ['style', 'class', 'align', 'dir'];

    private static $tagAttrs = [
        'a' => ['href', 'title', 'target', 'rel'],
        'td' => ['colspan', 'rowspan', 'width', 'height'],
        'th' => ['colspan', 'rowspan', 'width', 'height'],
        'table' => ['width', 'border', 'cellpadding', 'cellspacing'],
        'img' => [], // not allowed — listed empty so we never keep images
    ];

    public static function forDisplay($note)
    {
        if ($note === null || trim((string) $note) === '') {
            return '';
        }

        $note = trim((string) $note);

        if (preg_match('/<[a-z][\s\S]*>/i', $note)) {
            return self::sanitizeHtml($note);
        }

        return nl2br(e($note), false);
    }

    public static function forPlainText($note)
    {
        if ($note === null || trim((string) $note) === '') {
            return '';
        }

        $note = trim((string) $note);
        $note = preg_replace('/<br\s*\/?>/i', "\n", $note);
        $note = preg_replace('/<\/p>/i', "\n", $note);
        $note = preg_replace('/<\/(li|h[1-6]|div|tr)>/i', "\n", $note);
        $note = strip_tags($note);
        $note = html_entity_decode($note, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $note));
    }

    /** Sanitize rich-text note HTML before persisting (quotations, bookings, etc.). */
    public static function forStorage($note)
    {
        if ($note === null || trim((string) $note) === '') {
            return null;
        }

        $clean = self::sanitizeHtml(trim((string) $note));
        if ($clean === '' || trim(html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
            return null;
        }

        return $clean;
    }

    /**
     * Default editable note for new quotations (formerly the fixed "Quotation agreement" block).
     * Staff can edit or clear this in the create/edit editor before sending.
     */
    public static function defaultQuotationNote($companyName = null)
    {
        $company = trim((string) ($companyName ?: 'Beyond Tech World'));
        if ($company === '') {
            $company = 'Beyond Tech World';
        }
        $company = e($company);

        return self::sanitizeHtml(
            '<p><strong>Please read carefully before approving or rejecting:</strong></p>'
            .'<ol>'
            .'<li><strong>This document is a quotation, not a receipt or invoice.</strong> '
            .'It is an offer of goods/services and pricing for your consideration only. '
            .'No payment obligation arises until a sale or booking is confirmed after your approval.</li>'
            .'<li><strong>Suppliers / fulfilment will be arranged upon cleared payments.</strong> '
            .'Procurement, reservation, or delivery of items proceeds only after payment has been received '
            .'and cleared as agreed with '.$company.'.</li>'
            .'<li><strong>You reserve the right to request modifications.</strong> '
            .'You may request changes to quantities, items, or terms. '
            .'Revised quotations may be issued for your review before final acceptance.</li>'
            .'<li>By signing and approving, you confirm that you have reviewed the quoted items and totals, '
            .'and you authorise '.$company.' to proceed toward order processing subject to payment and availability.</li>'
            .'</ol>'
        );
    }

    private static function sanitizeHtml($html)
    {
        $html = self::normalizePasteHtml($html);

        // Prefer DOM so we keep safe attributes (style for bold/size from Word paste).
        if (class_exists(\DOMDocument::class)) {
            $cleaned = self::sanitizeWithDom($html);
            if ($cleaned !== null) {
                return $cleaned;
            }
        }

        $allow = '<'.implode('><', self::$allowedTags).'>';

        return strip_tags($html, $allow);
    }

    /**
     * Soften Word / Google Docs paste quirks before allow-listing tags.
     */
    private static function normalizePasteHtml($html)
    {
        // Drop Word conditional comments and XML leftovers
        $html = preg_replace('/<!--\[if[\s\S]*?<!\[endif\]-->/i', '', $html);
        $html = preg_replace('/<\/?o:[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?w:[^>]*>/i', '', $html);
        $html = preg_replace('/<\/?m:[^>]*>/i', '', $html);

        // Convert font tags to span
        $html = preg_replace('/<font[^>]*>/i', '<span>', $html);
        $html = preg_replace('/<\/font>/i', '</span>', $html);

        // Promote style-only bold/italic on p/div/span into semantic tags when easy
        $html = preg_replace_callback(
            '/<(p|div|span|li|td|th)([^>]*)style=("|\')([^"\']*)\3([^>]*)>([\s\S]*?)<\/\1>/i',
            function ($m) {
                $tag = strtolower($m[1]);
                $before = $m[2];
                $style = $m[4];
                $after = $m[5];
                $inner = $m[6];
                $openExtra = $before.' style="'.$style.'"'.$after;
                if (preg_match('/font-weight\s*:\s*(bold|[6-9]00)/i', $style)
                    && ! preg_match('/<(strong|b)\b/i', $inner)) {
                    $inner = '<strong>'.$inner.'</strong>';
                }
                if (preg_match('/font-style\s*:\s*italic/i', $style)
                    && ! preg_match('/<(em|i)\b/i', $inner)) {
                    $inner = '<em>'.$inner.'</em>';
                }
                if (preg_match('/text-decoration\s*:[^;]*underline/i', $style)
                    && ! preg_match('/<u\b/i', $inner)) {
                    $inner = '<u>'.$inner.'</u>';
                }

                return '<'.$tag.$openExtra.'>'.$inner.'</'.$tag.'>';
            },
            $html
        );

        return $html;
    }

    private static function sanitizeWithDom($html)
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="bnf-root">'.$html.'</div>';
        $ok = @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $ok) {
            return null;
        }

        $root = $dom->getElementById('bnf-root');
        if (! $root) {
            return null;
        }

        self::sanitizeNode($root, $dom);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function sanitizeNode(\DOMNode $node, \DOMDocument $dom)
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        /** @var \DOMNode[] $children */
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                continue;
            }
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);
            // Never keep contents of these (XSS / noise)
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta', 'form', 'input', 'button', 'textarea', 'select'], true)) {
                $node->removeChild($child);
                continue;
            }
            if (! in_array($tag, self::$allowedTags, true)) {
                // Unwrap: keep children, drop the disallowed wrapper (e.g. Word <font>)
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                self::sanitizeNode($node, $dom);
                continue;
            }

            self::sanitizeAttributes($child, $tag);
            self::sanitizeNode($child, $dom);
        }
    }

    private static function sanitizeAttributes(\DOMElement $el, $tag)
    {
        $allowed = self::$globalAttrs;
        if (isset(self::$tagAttrs[$tag])) {
            $allowed = array_merge($allowed, self::$tagAttrs[$tag]);
        }
        $allowed = array_fill_keys($allowed, true);

        $remove = [];
        if ($el->hasAttributes()) {
            foreach (iterator_to_array($el->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = trim((string) $attr->value);
                if (! isset($allowed[$name])) {
                    $remove[] = $attr->name;
                    continue;
                }
                if ($name === 'href') {
                    if ($value === '' || preg_match('/^\s*javascript:/i', $value) || preg_match('/^\s*data:/i', $value)) {
                        $remove[] = $attr->name;
                        continue;
                    }
                    // Force safe link behaviour
                    $el->setAttribute('rel', 'noopener noreferrer');
                }
                if ($name === 'style') {
                    $safe = self::sanitizeStyle($value);
                    if ($safe === '') {
                        $remove[] = $attr->name;
                    } else {
                        $el->setAttribute('style', $safe);
                    }
                }
                if ($name === 'class') {
                    // Drop Word/mso classes; keep nothing by default to avoid layout leakage
                    if (preg_match('/mso|Mso|xl|Word/i', $value)) {
                        $remove[] = $attr->name;
                    }
                }
            }
        }
        foreach ($remove as $name) {
            $el->removeAttribute($name);
        }
    }

    private static function sanitizeStyle($style)
    {
        $keep = [];
        foreach (explode(';', $style) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, ':') === false) {
                continue;
            }
            list($prop, $val) = array_map('trim', explode(':', $part, 2));
            $prop = strtolower($prop);
            $val = preg_replace('/\s*!important/i', '', $val);
            if (preg_match('/expression|javascript|url\s*\(/i', $val)) {
                continue;
            }
            if (in_array($prop, [
                'font-weight', 'font-style', 'font-size', 'text-decoration',
                'text-align', 'color', 'background-color',
                'margin', 'margin-left', 'margin-right', 'margin-top', 'margin-bottom',
                'padding-left', 'list-style-type', 'white-space',
            ], true)) {
                $keep[] = $prop.': '.$val;
            }
        }

        return implode('; ', $keep);
    }
}
