<?php

namespace App\Services\Funnel;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for the rich text block.
 *
 * The editor produces a small set of formatting tags. Anything else is either
 * unwrapped (its text kept) or, for elements that carry code or embedded
 * documents, removed together with its content. Every attribute is dropped
 * except a validated href and target on links.
 */
class RichTextSanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'blockquote' => [],
        'span' => [], 'a' => ['href', 'target'],
    ];

    private const REMOVE_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'noscript',
        'template', 'svg', 'math', 'head', 'title', 'meta', 'link', 'base', 'textarea', 'select',
        'option', 'form', 'input', 'button', 'audio', 'video', 'canvas', 'img', 'picture', 'source',
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the input as UTF-8, which keeps
        // Bengali text intact without the deprecated HTML-ENTITIES conversion.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="__funnel_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;
        foreach ($doc->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('id') === '__funnel_root') {
                $root = $div;
                break;
            }
        }
        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Copy first: the loop below mutates the live child list.
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                // Comments, processing instructions, CDATA.
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            $this->cleanChildren($child);

            if (!array_key_exists($tag, self::ALLOWED)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $this->cleanAttributes($child, self::ALLOWED[$tag]);
        }
    }

    private function cleanAttributes(DOMElement $element, array $allowed): void
    {
        $names = [];
        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->nodeName;
        }
        foreach ($names as $name) {
            if (!in_array(strtolower($name), $allowed, true)) {
                $element->removeAttribute($name);
            }
        }

        if ($element->nodeName !== 'a') {
            return;
        }

        $href = trim($element->getAttribute('href'));
        if ($href === '' || !UrlRule::isSafe($href)) {
            $element->removeAttribute('href');
        }

        if ($element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        } else {
            $element->removeAttribute('target');
        }
    }
}
