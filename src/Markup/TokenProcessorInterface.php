<?php

namespace App\Markup;

/**
 * Interface for a token parser capable of processing tokens.
 *
 * This interface can be used for two purposes:
 * - Parsing tokens that can't be represented by a tag (e.g. macros or table content).
 * - Cleaning up the token list to prevent converstion into nodes (e.g. removing
 *   hidden tags or preventing nested URL tags).
 *
 * If it matters when a token processor is executed with respect to other token
 * processors, implement the static getDefaultPriority function. See also
 * https://symfony.com/doc/current/service_container/tags.html#tagged-services-with-priority
 */
interface TokenProcessorInterface
{
    /**
     * Process iterable of tokens and turn it into a modified iterable of
     * tokens, which may contain more or fewer tokens than te original. It may
     * also be the exact same as the original. That's okay, too.
     */
    public function processTokens(iterable $tokens): iterable;
}
