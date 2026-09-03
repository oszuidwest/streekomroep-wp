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

    /** @return array{0: int, 1: int}|null Start and end offset of the current token in the original HTML, or null for a virtual token. */
    public function sourceSpan(): ?array
    {
        return $this->span;
    }

    /** Returns the original bytes of the current token, or null for a virtual token. */
    public function sourceText(): ?string
    {
        return $this->span === null ? null : substr($this->html, $this->span[0], $this->span[1] - $this->span[0]);
    }

    public function sourceEnd(): int
    {
        return $this->sourceEnd;
    }

    /**
     * Reads the current token's byte range through a bookmark.
     *
     * The tag processor's offsets are private and WP_HTML_Processor::set_bookmark()
     * refuses virtual tokens with a notice, so the check happens here first. Setting
     * the same name again just overwrites it, so the bookmark is never released.
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
     * Checks whether the visited token is the one the tag processor last read.
     *
     * Mirrors the provenance rule in the push and pop handlers of
     * WP_HTML_Processor::__construct(), since is_virtual() is private: only
     * elements are ever implied, and a virtual one is visited while the tag
     * processor still rests on the source token that implied it, which differs
     * in tag name or closer flag.
     */
    private function isSourceToken(): bool
    {
        return $this->get_token_type() !== '#tag' || (
            $this->get_tag() === WP_HTML_Tag_Processor::get_tag()
            && $this->is_tag_closer() === WP_HTML_Tag_Processor::is_tag_closer()
        );
    }
}
