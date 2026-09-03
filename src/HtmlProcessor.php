<?php

namespace Streekomroep;

use WP_HTML_Processor;
use WP_HTML_Tag_Processor;

/**
 * HTML processor that reports where each token sits in the source text.
 *
 * WP_HTML_Processor also visits virtual tokens that only exist in the parsed
 * tree, such as the implied closing tag of an unclosed element. Those own no
 * bytes, so callers replacing part of a document need to know which tokens do.
 */
final class HtmlProcessor extends WP_HTML_Processor
{
    private const BOOKMARK = 'zw-source-span';

    /** Byte offset just past the last source token visited so far. */
    private int $sourceEnd = 0;

    public function next_token(): bool
    {
        if (!parent::next_token()) {
            return false;
        }

        $span = $this->sourceSpan();
        if ($span !== null) {
            $this->sourceEnd = max($this->sourceEnd, $span[1]);
        }

        return true;
    }

    /**
     * Returns the byte range of the current token in the original HTML.
     *
     * @return array{0: int, 1: int}|null Start and end offset, or null for a virtual token.
     */
    public function sourceSpan(): ?array
    {
        if (!$this->isSourceToken() || !WP_HTML_Tag_Processor::set_bookmark(self::BOOKMARK)) {
            return null;
        }

        $span = $this->bookmarks[self::BOOKMARK];
        WP_HTML_Tag_Processor::release_bookmark(self::BOOKMARK);

        return [$span->start, $span->start + $span->length];
    }

    /** Returns the original bytes of the current token, or null for a virtual token. */
    public function sourceText(): ?string
    {
        $span = $this->sourceSpan();

        return $span === null ? null : substr($this->html, $span[0], $span[1] - $span[0]);
    }

    public function sourceEnd(): int
    {
        return $this->sourceEnd;
    }

    /**
     * Checks whether the visited token is the one the tag processor last read.
     *
     * Virtual tokens are visited while the underlying tag processor still rests on
     * the source token that implied them, which is a different type or tag.
     */
    private function isSourceToken(): bool
    {
        $type = $this->get_token_type();
        if ($type === null || $type !== WP_HTML_Tag_Processor::get_token_type()) {
            return false;
        }

        return $type !== '#tag' || (
            $this->get_tag() === WP_HTML_Tag_Processor::get_tag()
            && $this->is_tag_closer() === WP_HTML_Tag_Processor::is_tag_closer()
        );
    }
}
