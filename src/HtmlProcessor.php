<?php

namespace Streekomroep;

use WP_HTML_Processor;
use WP_HTML_Tag_Processor;

/**
 * Exposes source byte offsets for WordPress HTML processor tokens.
 */
final class HtmlProcessor extends WP_HTML_Processor
{
    private const BOOKMARK = 'zw-source-span';

    /** @var array{0: int, 1: int}|null Byte range of the current token, or null for a virtual token. */
    private ?array $span = null;

    /** Byte offset just past the last source token visited so far. */
    private int $sourceEnd = 0;

    public function next_token(): bool
    {
        if (!parent::next_token()) {
            return false;
        }

        $this->span = $this->readSpan();
        if ($this->span !== null) {
            $this->sourceEnd = $this->span[1];
        }

        return true;
    }

    /**
     * Returns the current token's source span.
     *
     * @return array{0: int, 1: int}|null Source offsets, or null for a virtual token.
     */
    public function sourceSpan(): ?array
    {
        return $this->span;
    }

    /** Returns the current token's source text, or null for a virtual token. */
    public function sourceText(): ?string
    {
        return $this->span === null ? null : substr($this->html, $this->span[0], $this->span[1] - $this->span[0]);
    }

    /** Returns the offset after the last visited source token. */
    public function sourceEnd(): int
    {
        return $this->sourceEnd;
    }

    /**
     * Reads the current token's source span through the bookmark API.
     *
     * @return array{0: int, 1: int}|null
     */
    private function readSpan(): ?array
    {
        if (!$this->isSourceToken() || !WP_HTML_Tag_Processor::set_bookmark(self::BOOKMARK)) {
            return null;
        }

        $span = $this->bookmarks[self::BOOKMARK];

        return [$span->start, $span->start + $span->length];
    }

    /**
     * Checks whether the current token maps to source bytes.
     *
     * Virtual elements differ from the tag processor token that implied them.
     */
    private function isSourceToken(): bool
    {
        return $this->get_token_type() !== '#tag' || (
            $this->get_tag() === WP_HTML_Tag_Processor::get_tag()
            && $this->is_tag_closer() === WP_HTML_Tag_Processor::is_tag_closer()
        );
    }
}
