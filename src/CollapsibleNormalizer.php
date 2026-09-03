<?php

namespace Streekomroep;

/**
 * Normalizes collapsible sections without changing unrelated post content.
 *
 * Canonical sections contain an h3 title and details items with plain-text
 * summaries. Unsupported block markup becomes paragraphs or moves after the
 * section. Sections are located with the WordPress HTML API and replaced in
 * place, so bytes outside a section are copied untouched.
 */
final class CollapsibleNormalizer
{
    /** Tags allowed in item content. */
    private const BODY_TAGS = ['P', 'BR', 'UL', 'OL', 'LI', 'A', 'STRONG', 'B', 'EM', 'I', 'IMG', 'IFRAME'];

    /** Block-level tags converted to paragraph breaks. */
    private const BLOCK_TAGS = [
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TD', 'TH',
        'DIV', 'DETAILS', 'SUMMARY', 'SECTION', 'ARTICLE', 'ASIDE', 'FIGURE', 'FIGCAPTION', 'HR', 'DL', 'DT', 'DD',
    ];

    /** Tags without content or closing tag. */
    private const VOID_TAGS = ['BR', 'IMG'];

    /** Text blocks accepted as section titles and item headings. */
    private const TEXT_BLOCK_TAGS = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P'];

    /** Matches empty paragraphs produced by the editor. */
    private const EMPTY_PARAGRAPH = '(?:<p>\s*)?(?:&nbsp;|\x{00A0})+(?:\s*</p>)?(?=\s|$)';

    public static function normalize(string $content): string
    {
        $processor = HtmlProcessor::create_fragment($content);
        if ($processor === null) {
            return $content;
        }

        $output = '';
        $cursor = 0;

        while ($processor->next_token()) {
            if (!self::isOpening($processor, 'DIV') || !$processor->has_class('collapsible')) {
                continue;
            }
            $span = $processor->sourceSpan();
            if ($span === null) {
                continue;
            }

            [$html, $end] = self::section($processor);
            $output .= substr($content, $cursor, $span[0] - $cursor) . $html;
            $cursor = $end;
        }

        // The parser stops at markup it does not support; leave the content alone rather than truncating it.
        if ($processor->get_last_error() !== null) {
            return $content;
        }

        $output .= substr($content, $cursor);

        // Remove editor-generated empty paragraphs next to sections in linear time.
        $output = preg_replace('#(</details>\n</div>)(?:\s*' . self::EMPTY_PARAGRAPH . ')++#u', '$1', $output) ?? $output;

        return preg_replace('#(?:' . self::EMPTY_PARAGRAPH . '\s*)++(?:(?=<div class="collapsible">\n<h3)|(*SKIP)(*FAIL))#u', '', $output) ?? $output;
    }

    /** Replaces disclosure items with headings for feed readers. */
    public static function flatten(string $content): string
    {
        $processor = HtmlProcessor::create_fragment($content);
        if ($processor === null) {
            return $content;
        }

        $output = '';
        $cursor = 0;

        while ($processor->next_token()) {
            if (!self::isOpening($processor, 'DETAILS') || !$processor->has_class('collapsible-item')) {
                continue;
            }
            $start = $processor->sourceSpan();
            $item = self::flattenItem($processor);
            if ($start === null || $item === null) {
                continue;
            }

            $output .= substr($content, $cursor, $start[0] - $cursor)
            . '<h4>' . substr($content, $item['summary'][0], $item['summary'][1] - $item['summary'][0]) . '</h4>'
            . substr($content, $item['body'][0], $item['body'][1] - $item['body'][0]);
            $cursor = $item['end'];
        }

        if ($processor->get_last_error() !== null) {
            return $content;
        }

        return $output . substr($content, $cursor);
    }

    /**
     * Locates the summary and body of a canonical item in the source text.
     *
     * @return array{summary: array{0: int, 1: int}, body: array{0: int, 1: int}, end: int}|null
     */
    private static function flattenItem(HtmlProcessor $processor): ?array
    {
        $summary = null;
        $body = null;
        $depth = 0;

        while ($processor->next_token()) {
            if (self::isOpening($processor, 'DETAILS')) {
                $depth++;
            } elseif (self::isClosing($processor, 'DETAILS')) {
                if ($depth > 0) {
                    $depth--;
                    continue;
                }
                $span = $processor->sourceSpan();
                if ($span === null || $body === null) {
                    return null;
                }

                return ['summary' => $summary, 'body' => [$body, $span[0]], 'end' => $span[1]];
            } elseif ($depth === 0 && $summary === null) {
                // The summary must be the first child; whitespace before it is dropped.
                if (self::isText($processor) && trim((string) $processor->sourceText()) === '') {
                    continue;
                }
                $span = self::isOpening($processor, 'SUMMARY') ? $processor->sourceSpan() : null;
                if ($span === null) {
                    return null;
                }
                $summary = [$span[1], $span[1]];
            } elseif ($depth === 0 && $body === null && self::isClosing($processor, 'SUMMARY')) {
                $span = $processor->sourceSpan();
                if ($span === null) {
                    return null;
                }
                $summary[1] = $span[0];
                $body = $span[1];
            }
        }

        return null;
    }

    /** @return array{0: string, 1: int} Section HTML and the offset just past the section in the source. */
    private static function section(HtmlProcessor $processor): array
    {
        $title = '';
        $items = [];
        $stray = '';
        $divDepth = 0;

        while ($processor->next_token()) {
            if (self::isOpening($processor, 'DIV')) {
                // Flatten nested block content.
                $divDepth++;
                continue;
            }
            if (self::isClosing($processor, 'DIV')) {
                if ($divDepth === 0) {
                    break;
                }
                $divDepth--;
                continue;
            }
            if ($title === '' && self::isTextBlock($processor) && $processor->has_class('collapsible-title')) {
                $title = self::textUntil($processor);
                continue;
            }
            if (self::isOpening($processor, 'DETAILS')) {
                $item = self::item($processor, $processor->get_attribute('open') !== null);
                if ($item['heading'] === '') {
                    // Move content without a heading outside the section.
                    $stray .= "\n\n" . $item['body'];
                } else {
                    $items[] = $item;
                }
                continue;
            }

            $stray .= self::isTag($processor) ? self::bodyTag($processor) : self::text($processor);
        }

        $stray = self::tidy($stray);

        if ($items === []) {
            $html = $title === '' ? '' : '<strong>' . $title . '</strong>';
        } else {
            $html = '<div class="collapsible">' . "\n" . '<h3 class="collapsible-title">' . $title . '</h3>' . "\n";
            foreach ($items as $item) {
                $html .= '<details class="collapsible-item"' . ($item['open'] ? ' open' : '') . '>' . "\n"
                . '<summary>' . $item['heading'] . '</summary>' . "\n"
                . $item['body'] . ($item['body'] === '' ? '' : "\n")
                . '</details>' . "\n";
            }
            $html .= '</div>';
        }

        if ($stray !== '') {
            $html .= ($html === '' ? '' : "\n\n") . $stray;
        }

        // A section closed by its own tag ends there; one closed implicitly ends at its last token.
        return [$html, $processor->sourceEnd()];
    }

    /** @return array{heading: string, body: string, open: bool} */
    private static function item(HtmlProcessor $processor, bool $open): array
    {
        $heading = '';
        $body = '';
        $depth = 0;

        while ($processor->next_token()) {
            if (!self::isTag($processor)) {
                $body .= self::text($processor);
                continue;
            }
            if (self::isClosing($processor, 'DETAILS')) {
                // The parser closes an unclosed item when its section ends.
                if ($depth === 0) {
                    break;
                }
                $depth--;
                $body .= "\n\n";
                continue;
            }
            if (self::isOpening($processor, 'DETAILS')) {
                $depth++;
            }
            if ($heading === '' && trim($body) === '' && $depth === 0) {
                // Accept a text block when the summary tag is missing.
                if (self::isOpening($processor, 'SUMMARY') || self::isTextBlock($processor)) {
                    $heading = self::textUntil($processor);
                    $body = '';
                    continue;
                }
            }

            $body .= self::bodyTag($processor);
        }

        return ['heading' => $heading, 'body' => self::tidy($body), 'open' => $open];
    }

    /** Collects plain text up to the closing tag of the current element. */
    private static function textUntil(HtmlProcessor $processor): string
    {
        $tag = $processor->get_tag();
        $text = '';

        while ($processor->next_token()) {
            if (self::isClosing($processor, $tag)) {
                break;
            }
            if (self::isText($processor)) {
                $text .= $processor->sourceText();
            }
        }

        return trim(preg_replace('#\s+#', ' ', $text) ?? $text);
    }

    /** Serializes allowed tags from their parsed attributes so odd source quoting cannot leak through. */
    private static function bodyTag(HtmlProcessor $processor): string
    {
        $tag = $processor->get_tag();
        if (!in_array($tag, self::BODY_TAGS, true)) {
            return in_array($tag, self::BLOCK_TAGS, true) ? "\n\n" : '';
        }

        $name = strtolower($tag);
        if ($processor->is_tag_closer()) {
            return '</' . $name . '>';
        }

        $html = '<' . $name;
        foreach ($processor->get_attribute_names_with_prefix('') ?? [] as $attribute) {
            $value = $processor->get_attribute($attribute);
            $html .= ' ' . $attribute;
            if ($value !== true) {
                $html .= '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
            }
        }

        $html .= '>';
        // Raw text elements such as iframe swallow their closing tag; their content is not article text.
        if (!in_array($tag, self::VOID_TAGS, true) && !$processor->expects_closer()) {
            $html .= '</' . $name . '>';
        }

        return $html;
    }

    /** Returns section text with normalized line endings; comments and other non-text carry nothing. */
    private static function text(HtmlProcessor $processor): string
    {
        return self::isText($processor) ? str_replace("\r", '', (string) $processor->sourceText()) : '';
    }

    private static function tidy(string $html): string
    {
        $html = preg_replace('#^[ \t]*' . self::EMPTY_PARAGRAPH . '[ \t]*$#mu', '', $html) ?? $html;

        return trim(preg_replace("#[ \t]*\n(?:[ \t]*\n)+#", "\n\n", $html) ?? $html);
    }

    private static function isTag(HtmlProcessor $processor): bool
    {
        return $processor->get_token_type() === '#tag';
    }

    private static function isText(HtmlProcessor $processor): bool
    {
        return $processor->get_token_type() === '#text';
    }

    private static function isTextBlock(HtmlProcessor $processor): bool
    {
        return self::isTag($processor) && !$processor->is_tag_closer()
        && in_array($processor->get_tag(), self::TEXT_BLOCK_TAGS, true);
    }

    private static function isOpening(HtmlProcessor $processor, string $tag): bool
    {
        return self::isTag($processor) && !$processor->is_tag_closer() && $processor->get_tag() === $tag;
    }

    private static function isClosing(HtmlProcessor $processor, string $tag): bool
    {
        return self::isTag($processor) && $processor->is_tag_closer() && $processor->get_tag() === $tag;
    }
}
