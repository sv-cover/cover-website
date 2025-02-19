<?php

namespace App\Markup;

/**
 * Interface for a token parser capable of parsing tags (or rather, supporting
 * our tag parser).
 */
interface TagParserInterface
{
    /**
     * Returns an iterable with metadata for the tags supported by this parser.
     *
     * The metadata for an individual tag looks like this:
     *
     * [
     *     'name' => 'tag',
     *     'is_void' => false, (default if not provided is false)
     *     ... (any other keys you might need for internal use.)
     * ]
     *
     * If is_void is false, this will parse all tags that look like
     * [tag<optional classes or attributes>]<content>[/tag]
     *
     * If is_void is true, this will parse all tags that look like
     * [tag<optional classes or attributes>]
     *
     * See also: https://developer.mozilla.org/en-US/docs/Glossary/Void_element
     */
    public function getTags(): iterable;
}
