<?php

namespace Ghanem\Basata\Exceptions;

/**
 * API error 1023 "Duplicate transaction ID".
 *
 * Separately catchable because it is the one validation error that can mean
 * the opposite of what it says: per FAQ A10 (p.21), a connection drop after
 * the server processed a payment makes the retry re-post the same external_id,
 * so a SUCCESSFUL payment can surface here. Catch it and resolve the outcome
 * with getTransaction($externalId, 'external_id') before treating it as a
 * caller bug. Extends BasataValidationException so existing catches still see
 * it as one.
 */
class BasataDuplicateTransactionIdException extends BasataValidationException
{
}
