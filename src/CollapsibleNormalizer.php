<?php

namespace Streekomroep;

use WP_HTML_Processor;

/**
 * Normalizes collapsible sections and serializes surrounding HTML canonically.
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

    /** Text blocks accepted as section titles and item headings. */
    private const TEXT_BLOCK_TAGS = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P'];

    /** Matches empty paragraphs produced by the editor. */
    private const EMPTY_PARAGRAPH = '(?:<p>\s*)?(?:&nbsp;|\x{00A0})+(?:\s*</p>)?(?=\s|$)';

    public static function normalize(string $content): string
    {
        if (!str_contains($content, 'collapsible')) {
            return $content;
        }

        $processor = WP_HTML_Processor::create_fragment($content);
        if ($processor === null) {
            return $content;
        }

        $output = '';
        $changed = false;
        while ($processor->next_token()) {
            if (self::isOpening($processor, 'DIV') && $processor->has_class('collapsible')) {
                $output .= self::section($processor);
                $changed = true;
                continue;
            }

            $output .= $processor->serialize_token();
        }

        if (!$changed || $processor->get_last_error() !== null) {
            return $content;
        }

        // Remove editor-generated empty paragraphs next to sections.
        $output = preg_replace('#(</details>\n</div>)(?:\s*' . self::EMPTY_PARAGRAPH . ')++#u', '$1', $output) ?? $output;

        return preg_replace('#(?:' . self::EMPTY_PARAGRAPH . '\s*)++(?:(?=<div class="collapsible">\n<h3)|(*SKIP)(*FAIL))#u', '', $output) ?? $output;
    }

    /** Replaces disclosure items with headings for feed readers. */
    public static function flatten(string $content): string
    {
        return preg_replace(
            '#<details\b[^>]*\bcollapsible-item\b[^>]*>\s*<summary>(.*?)</summary>(.*?)</details>#is',
            '<h4>$1</h4>$2',
            $content
        ) ?? $content;
    }

    /** Advances to the next token, unless that token closes the element that was current at the given depth. */
    private static function nextInside(WP_HTML_Processor $processor, int $depth): bool
    {
        return $processor->next_token() && $processor->get_current_depth() >= $depth;
    }

    /** Consumes the current section and returns its canonical HTML. */
    private static function section(WP_HTML_Processor $processor): string
    {
        $title = '';
        $items = [];
        $stray = '';
        $depth = $processor->get_current_depth();

        while (self::nextInside($processor, $depth)) {
            if ($title === '' && self::isTextBlock($processor) && $processor->has_class('collapsible-title')) {
                $title = self::textUntil($processor);
                continue;
            }
            if (self::isOpening($processor, 'DETAILS')) {
                $item = self::item($processor, $processor->get_attribute('open') !== null);
                if ($item['heading'] === '') {
                    // Headingless content does not belong in a disclosure item.
                    $stray .= "\n\n" . $item['body'];
                } else {
                    $items[] = $item;
                }
                continue;
            }

            $stray .= self::token($processor);
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

        return $html;
    }

    /** @return array{heading: string, body: string, open: bool} */
    private static function item(WP_HTML_Processor $processor, bool $open): array
    {
        $heading = '';
        $body = '';
        $depth = $processor->get_current_depth();

        while (self::nextInside($processor, $depth)) {
            // A top-level text block may replace a missing summary.
            if (
                $heading === '' && trim($body) === ''
                && (self::isOpening($processor, 'SUMMARY') || self::isTextBlock($processor))
                && !in_array('DETAILS', array_slice($processor->get_breadcrumbs(), $depth), true)
            ) {
                $heading = self::textUntil($processor);
                $body = '';
                continue;
            }

            $body .= self::token($processor);
        }

        return ['heading' => $heading, 'body' => self::tidy($body), 'open' => $open];
    }

    /** Collects plain text up to the end of the current element. */
    private static function textUntil(WP_HTML_Processor $processor): string
    {
        $depth = $processor->get_current_depth();
        $text = '';

        while (self::nextInside($processor, $depth)) {
            if (self::isText($processor)) {
                $text .= $processor->serialize_token();
            }
        }

        return trim(preg_replace('#\s+#', ' ', $text) ?? $text);
    }

    /** Serializes supported section content from the current token. */
    private static function token(WP_HTML_Processor $processor): string
    {
        if (self::isText($processor)) {
            return $processor->serialize_token();
        }
        if (!self::isTag($processor)) {
            return '';
        }

        $tag = $processor->get_tag();
        if (in_array($tag, self::BODY_TAGS, true)) {
            return $processor->serialize_token();
        }

        return in_array($tag, self::BLOCK_TAGS, true) ? "\n\n" : '';
    }

    private static function tidy(string $html): string
    {
        $html = preg_replace('#^[ \t]*' . self::EMPTY_PARAGRAPH . '[ \t]*$#mu', '', $html) ?? $html;

        return trim(preg_replace("#[ \t]*\n(?:[ \t]*\n)+#", "\n\n", $html) ?? $html);
    }

    private static function isTag(WP_HTML_Processor $processor): bool
    {
        return $processor->get_token_type() === '#tag';
    }

    private static function isText(WP_HTML_Processor $processor): bool
    {
        return $processor->get_token_type() === '#text';
    }

    private static function isTextBlock(WP_HTML_Processor $processor): bool
    {
        return self::isTag($processor) && !$processor->is_tag_closer()
        && in_array($processor->get_tag(), self::TEXT_BLOCK_TAGS, true);
    }

    private static function isOpening(WP_HTML_Processor $processor, string $tag): bool
    {
        return self::isTag($processor) && !$processor->is_tag_closer() && $processor->get_tag() === $tag;
    }
}
