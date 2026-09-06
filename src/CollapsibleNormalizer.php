<?php

namespace Streekomroep;

use WP_HTML_Processor;

/**
 * Normalizes collapsible sections and serializes surrounding HTML canonically.
 */
final class CollapsibleNormalizer
{
    private const BODY_TAGS = ['P', 'BR', 'UL', 'OL', 'LI', 'A', 'STRONG', 'B', 'EM', 'I', 'IMG', 'IFRAME'];

    private const BLOCK_TAGS = [
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'PRE', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TD', 'TH',
        'DIV', 'DETAILS', 'SUMMARY', 'SECTION', 'ARTICLE', 'ASIDE', 'FIGURE', 'FIGCAPTION', 'HR', 'DL', 'DT', 'DD',
    ];

    private const TEXT_BLOCK_TAGS = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P'];

    /** Matches editor-empty paragraphs and standalone non-breaking spaces. */
    private const EMPTY_PARAGRAPH = '(?:<p>\s*)?\x{00A0}+(?:\s*</p>)?(?=\s|$)';

    public static function normalize(string $content): string
    {
        if (!str_contains($content, 'collapsible')) {
            return $content;
        }

        $processor = WP_HTML_Processor::create_fragment($content);
        $output = '';
        $changed = false;
        while ($processor->next_token()) {
            if (self::opens($processor, 'DIV') && $processor->has_class('collapsible')) {
                $output .= self::section($processor);
                $changed = true;
                continue;
            }

            $output .= $processor->serialize_token();
        }

        // Preserve content byte-for-byte unless a section was parsed completely.
        if (!$changed || $processor->get_last_error() !== null) {
            return $content;
        }

        // Remove empty paragraphs beside sections; (*SKIP)(*FAIL) keeps this linear.
        return preg_replace(
            '#(</details>\n</div>)(?:\s*' . self::EMPTY_PARAGRAPH . ')++'
            . '|(?:' . self::EMPTY_PARAGRAPH . '\s*)++(?:(?=<div class="collapsible">\n<h3)|(*SKIP)(*FAIL))#u',
            '$1',
            $output
        ) ?? $output;
    }

    /** Replaces disclosure items with headings for feed readers. */
    public static function flatten(string $content): string
    {
        if (!str_contains($content, 'collapsible-item')) {
            return $content;
        }

        $processor = WP_HTML_Processor::create_fragment($content);
        $output = '';
        $changed = false;

        while ($processor->next_token()) {
            if (self::opens($processor, 'DETAILS') && $processor->has_class('collapsible-item')) {
                [$html, $flattened] = self::flattenItem($processor);
                $output .= $html;
                $changed = $changed || $flattened;
                continue;
            }

            $output .= $processor->serialize_token();
        }

        return $changed && $processor->get_last_error() === null ? $output : $content;
    }

    /**
     * Replaces a disclosure item's first-child summary with a heading.
     *
     * @return array{0: string, 1: bool} HTML and whether it was flattened.
     */
    private static function flattenItem(WP_HTML_Processor $processor): array
    {
        $depth = $processor->get_current_depth();
        $opening = $processor->serialize_token();

        while (self::nextInside($processor, $depth)) {
            $token = $processor->serialize_token();
            if ($processor->get_token_name() === '#text' && trim($token) === '') {
                $opening .= $token;
                continue;
            }
            if ($processor->get_current_depth() !== $depth + 1 || !self::opens($processor, 'SUMMARY')) {
                return [$opening . $token, false];
            }

            $heading = '';
            $summaryDepth = $processor->get_current_depth();
            while (self::nextInside($processor, $summaryDepth)) {
                $heading .= $processor->serialize_token();
            }

            $body = '';
            while (self::nextInside($processor, $depth)) {
                $body .= $processor->serialize_token();
            }

            return ['<h4>' . $heading . '</h4>' . $body, true];
        }

        if ($processor->is_tag_closer() && $processor->get_token_name() === 'DETAILS') {
            $opening .= $processor->serialize_token();
        }

        return [$opening, false];
    }

    /** Advances unless the next token closes the element at the given depth. */
    private static function nextInside(WP_HTML_Processor $processor, int $depth): bool
    {
        return $processor->next_token() && $processor->get_current_depth() >= $depth;
    }

    private static function section(WP_HTML_Processor $processor): string
    {
        $title = '';
        $items = [];
        $stray = '';
        $depth = $processor->get_current_depth();

        while (self::nextInside($processor, $depth)) {
            if ($title === '' && self::opens($processor, ...self::TEXT_BLOCK_TAGS) && $processor->has_class('collapsible-title')) {
                $title = self::textUntil($processor);
                continue;
            }
            if (self::opens($processor, 'DETAILS')) {
                $item = self::item($processor);
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
    private static function item(WP_HTML_Processor $processor): array
    {
        $open = $processor->get_attribute('open') !== null;
        $heading = '';
        $body = '';
        $depth = $processor->get_current_depth();

        while (self::nextInside($processor, $depth)) {
            // A direct child text block may replace a missing summary.
            if (
                $heading === '' && trim($body) === ''
                && $processor->get_current_depth() === $depth + 1
                && self::opens($processor, 'SUMMARY', ...self::TEXT_BLOCK_TAGS)
            ) {
                $heading = self::textUntil($processor);
                continue;
            }

            $body .= self::token($processor);
        }

        return ['heading' => $heading, 'body' => self::tidy($body), 'open' => $open];
    }

    private static function textUntil(WP_HTML_Processor $processor): string
    {
        $depth = $processor->get_current_depth();
        $text = '';

        while (self::nextInside($processor, $depth)) {
            if ($processor->get_token_name() === '#text') {
                $text .= $processor->serialize_token();
            }
        }

        return trim(preg_replace('#\s+#', ' ', $text) ?? $text);
    }

    private static function token(WP_HTML_Processor $processor): string
    {
        $name = $processor->get_token_name();
        if ($name === '#text' || in_array($name, self::BODY_TAGS, true)) {
            return $processor->serialize_token();
        }

        return in_array($name, self::BLOCK_TAGS, true) ? "\n\n" : '';
    }

    private static function tidy(string $html): string
    {
        $html = preg_replace('#^[ \t]*' . self::EMPTY_PARAGRAPH . '[ \t]*$#mu', '', $html) ?? $html;

        return trim(preg_replace("#[ \t]*\n(?:[ \t]*\n)+#", "\n\n", $html) ?? $html);
    }

    private static function opens(WP_HTML_Processor $processor, string ...$tags): bool
    {
        return !$processor->is_tag_closer() && in_array($processor->get_token_name(), $tags, true);
    }
}
