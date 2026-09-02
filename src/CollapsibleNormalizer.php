<?php

namespace Streekomroep;

/**
 * Normalizes collapsible sections without changing unrelated post content.
 *
 * Canonical sections contain an h3 title and details items with plain-text
 * summaries. Unsupported block markup becomes paragraphs or moves after the
 * section.
 */
final class CollapsibleNormalizer
{
    /** Tags allowed in item content. */
    private const BODY_TAGS = ['p', 'br', 'ul', 'ol', 'li', 'a', 'strong', 'b', 'em', 'i', 'img', 'iframe'];

    /** Block-level tags converted to paragraph breaks. */
    private const BLOCK_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'div', 'details', 'summary', 'section', 'article', 'aside', 'figure', 'figcaption', 'hr', 'dl', 'dt', 'dd',
    ];

    /** Matches empty paragraphs produced by the editor. */
    private const EMPTY_PARAGRAPH = '(?:<p>\s*)?(?:&nbsp;|\x{00A0})+(?:\s*</p>)?(?=\s|$)';

    public static function normalize(string $content): string
    {
        $tokens = preg_split('#(<[^>]+>)#', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return $content;
        }

        $output = '';
        $count = count($tokens);
        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];
            if (self::isOpening($token, 'div') && self::hasClass($token, 'collapsible')) {
                [$html, $i] = self::section($tokens, $i + 1);
                $output .= $html;
                continue;
            }
            $output .= $token;
            $i++;
        }

        // Remove editor-generated empty paragraphs next to sections in linear time.
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

    /** @return array{0: string, 1: int} Section HTML and the next token index. */
    private static function section(array $tokens, int $i): array
    {
        $title = '';
        $items = [];
        $stray = '';
        $divDepth = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if (self::isOpening($token, 'div')) {
                // Flatten nested block content.
                $divDepth++;
                $i++;
                continue;
            }
            if (self::isClosing($token, 'div')) {
                $i++;
                if ($divDepth === 0) {
                    break;
                }
                $divDepth--;
                continue;
            }
            if ($title === '' && self::hasClass($token, 'collapsible-title') && self::isTextBlock($token)) {
                [$title, $i] = self::textUntil($tokens, $i + 1, $token);
                continue;
            }
            if (self::isOpening($token, 'details')) {
                [$item, $i] = self::item($tokens, $i + 1, (bool) preg_match('#\sopen\b#i', $token));
                if ($item['heading'] === '') {
                    // Move content without a heading outside the section.
                    $stray .= "\n\n" . $item['body'];
                } else {
                    $items[] = $item;
                }
                continue;
            }

            $stray .= self::isTag($token) ? self::bodyTag($token) : self::text($token);
            $i++;
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

        return [$html, $i];
    }

    /** @return array{0: array{heading: string, body: string, open: bool}, 1: int} */
    private static function item(array $tokens, int $i, bool $open): array
    {
        $heading = '';
        $body = '';
        // Track each tag type independently to tolerate malformed nesting.
        $detailsDepth = 0;
        $divDepth = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if (!self::isTag($token)) {
                $body .= self::text($token);
                $i++;
                continue;
            }
            if (self::isClosing($token, 'details')) {
                if ($detailsDepth === 0) {
                    $i++;
                    break;
                }
                $detailsDepth--;
                $body .= "\n\n";
                $i++;
                continue;
            }
            if (self::isClosing($token, 'div')) {
                if ($divDepth === 0) {
                    // The section closes the unclosed item.
                    break;
                }
                $divDepth--;
                $body .= "\n\n";
                $i++;
                continue;
            }
            if (self::isOpening($token, 'details')) {
                $detailsDepth++;
            } elseif (self::isOpening($token, 'div')) {
                $divDepth++;
            }
            if ($heading === '' && trim($body) === '' && $detailsDepth === 0) {
                // Accept a text block when the summary tag is missing.
                if (self::isOpening($token, 'summary') || self::isTextBlock($token)) {
                    [$heading, $i] = self::textUntil($tokens, $i + 1, $token);
                    $body = '';
                    continue;
                }
            }

            $body .= self::bodyTag($token);
            $i++;
        }

        return [['heading' => $heading, 'body' => self::tidy($body), 'open' => $open], $i];
    }

    /** Collects plain text up to the matching closing tag. */
    private static function textUntil(array $tokens, int $i, string $opening): array
    {
        $tag = self::tagName($opening);
        $text = '';
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];
            $i++;
            if (self::isClosing($token, $tag)) {
                break;
            }
            if (!self::isTag($token)) {
                $text .= $token;
            }
        }

        return [trim(preg_replace('#\s+#', ' ', $text) ?? $text), $i];
    }

    private static function bodyTag(string $token): string
    {
        $name = self::tagName($token);
        if (in_array($name, self::BODY_TAGS, true)) {
            return $token;
        }

        return in_array($name, self::BLOCK_TAGS, true) ? "\n\n" : '';
    }

    /** Normalizes line endings in section text. */
    private static function text(string $token): string
    {
        return str_replace("\r", '', $token);
    }

    private static function tidy(string $html): string
    {
        $html = preg_replace('#^[ \t]*' . self::EMPTY_PARAGRAPH . '[ \t]*$#mu', '', $html) ?? $html;

        return trim(preg_replace("#[ \t]*\n(?:[ \t]*\n)+#", "\n\n", $html) ?? $html);
    }

    private static function isTag(string $token): bool
    {
        return $token[0] === '<';
    }

    private static function tagName(string $token): string
    {
        return preg_match('#^</?([a-zA-Z][a-zA-Z0-9]*)#', $token, $match) ? strtolower($match[1]) : '';
    }

    /** Checks for text blocks accepted as section headings. */
    private static function isTextBlock(string $token): bool
    {
        return (bool) preg_match('#^<(h[1-6]|p)[\s/>]#i', $token);
    }

    private static function isOpening(string $token, string $tag): bool
    {
        return (bool) preg_match('#^<' . $tag . '[\s/>]#i', $token);
    }

    private static function isClosing(string $token, string $tag): bool
    {
        return (bool) preg_match('#^</' . $tag . '\s*>#i', $token);
    }

    private static function hasClass(string $token, string $class): bool
    {
        return (bool) preg_match('#^<[^>]*\sclass=(["\'])[^"\']*(?<![\w-])' . preg_quote($class, '#') . '(?![\w-])[^"\']*\1#i', $token);
    }
}
