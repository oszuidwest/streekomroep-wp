<?php

namespace Streekomroep;

/**
 * Rewrites collapsible sections in post content into their canonical form.
 *
 * A section is a div.collapsible holding one h3.collapsible-title followed by
 * details.collapsible-item elements that each start with a summary. Everything
 * else is repaired or moved out: the title and item headings become plain text,
 * item bodies keep only paragraph-level formatting (lists, links, emphasis,
 * images and embeds), nested sections and other block structure dissolve into
 * paragraphs, and stray content inside a section is placed after it.
 *
 * Works on the tag level so it copes with the paragraph-less HTML the Classic
 * Editor stores as well as with full HTML from other sources.
 */
final class CollapsibleNormalizer
{
    /** Tags an item body may keep; attributes are left to the kses allowlist. */
    private const BODY_TAGS = ['p', 'br', 'ul', 'ol', 'li', 'a', 'strong', 'b', 'em', 'i', 'img', 'iframe'];

    /** Disallowed tags that mark a block boundary; their text continues as a paragraph. */
    private const BLOCK_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
        'div', 'details', 'summary', 'section', 'article', 'aside', 'figure', 'figcaption', 'hr', 'dl', 'dt', 'dd',
    ];

    public static function normalize(string $content): string
    {
        if (!str_contains($content, 'collapsible')) {
            return $content;
        }

        $tokens = preg_split('#(<[^>]+>)#', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
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

        return $output;
    }

    /** @return array{0: string, 1: int} The rebuilt section and the index after its closing tag. */
    private static function section(array $tokens, int $i): array
    {
        $title = null;
        $items = [];
        $stray = '';
        $divDepth = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if (self::isOpening($token, 'div')) {
                // A nested section or other div dissolves; its closing tag is skipped below.
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
            if ($title === null && self::hasClass($token, 'collapsible-title') && preg_match('#^<(h[1-6]|p)\b#i', $token, $match)) {
                [$title, $i] = self::textUntil($tokens, $i + 1, strtolower($match[1]));
                continue;
            }
            if (self::isOpening($token, 'details')) {
                [$item, $i] = self::item($tokens, $i + 1, (bool) preg_match('#\sopen\b#i', $token));
                if ($item['heading'] === '' && $item['body'] === '') {
                    continue;
                }
                if ($item['heading'] === '') {
                    // Nothing to promote to a heading: the text cannot collapse, so it leaves the section.
                    $stray .= "\n\n" . $item['body'];
                    continue;
                }
                $items[] = $item;
                continue;
            }

            $stray .= self::isTag($token) ? self::bodyTag($token) : $token;
            $i++;
        }

        $title = $title ?? '';
        $stray = self::tidy($stray);
        $html = '';

        if ($items === []) {
            // A section without items is just its title, if any.
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
        $detailsDepth = 0;
        $divDepth = 0;
        $count = count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if (!self::isTag($token)) {
                $body .= $token;
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
                    // The enclosing section ends before this item was closed.
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
                // The first block is the heading: the summary itself, or a block that replaced it.
                if (self::isOpening($token, 'summary')) {
                    [$heading, $i] = self::textUntil($tokens, $i + 1, 'summary');
                    continue;
                }
                if (preg_match('#^<(h[1-6]|p)\b#i', $token, $match)) {
                    [$heading, $i] = self::textUntil($tokens, $i + 1, strtolower($match[1]));
                    $body = '';
                    continue;
                }
            }

            $body .= self::bodyTag($token);
            $i++;
        }

        return [['heading' => $heading, 'body' => self::tidy($body), 'open' => $open], $i];
    }

    /** Collects the plain text up to the closing tag, dropping any markup inside. */
    private static function textUntil(array $tokens, int $i, string $tag): array
    {
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

        return [trim(preg_replace('#\s+#', ' ', $text)), $i];
    }

    private static function bodyTag(string $token): string
    {
        $name = self::tagName($token);
        if (in_array($name, self::BODY_TAGS, true)) {
            return $token;
        }

        return in_array($name, self::BLOCK_TAGS, true) ? "\n\n" : '';
    }

    private static function tidy(string $html): string
    {
        return trim(preg_replace("#[ \t]*\n(?:[ \t]*\n)+#", "\n\n", $html));
    }

    private static function isTag(string $token): bool
    {
        return $token !== '' && $token[0] === '<';
    }

    private static function tagName(string $token): string
    {
        return preg_match('#^</?([a-zA-Z][a-zA-Z0-9]*)#', $token, $match) ? strtolower($match[1]) : '';
    }

    private static function isOpening(string $token, string $tag): bool
    {
        return self::isTag($token) && $token[1] !== '/' && self::tagName($token) === $tag;
    }

    private static function isClosing(string $token, string $tag): bool
    {
        return self::isTag($token) && str_starts_with($token, '</') && self::tagName($token) === $tag;
    }

    private static function hasClass(string $token, string $class): bool
    {
        return self::isTag($token) && (bool) preg_match('#\sclass=(["\'])[^"\']*(?<![\w-])' . preg_quote($class, '#') . '(?![\w-])[^"\']*\1#i', $token);
    }
}
