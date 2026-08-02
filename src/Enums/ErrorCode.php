<?php

namespace Ghanem\Basata\Enums;

use Ghanem\Basata\Exceptions\BasataAuthenticationException;
use Ghanem\Basata\Exceptions\BasataInsufficientBalanceException;
use Ghanem\Basata\Exceptions\BasataNotFoundException;
use Ghanem\Basata\Exceptions\BasataRateLimitException;
use Ghanem\Basata\Exceptions\BasataServerException;
use Ghanem\Basata\Exceptions\BasataTransactionInProgressException;
use Ghanem\Basata\Exceptions\BasataValidationException;

/**
 * Every documented API error code, transcribed verbatim from the Channel API
 * V3.0.8 spec, section 6 "Error Codes" (page 18).
 *
 * Codes 1027-1029 refer to "Beecard" — the API's own product name for a
 * physical card. That wording is kept verbatim, not renamed.
 */
enum ErrorCode: int
{
    case LoginRequired = 1001;
    case PasswordRequired = 1002;
    case IncorrectCredentials = 1003;
    case ActionRequired = 1004;
    case IncorrectActionName = 1005;
    case VersionRequired = 1006;
    case IncorrectVersion = 1007;
    case DataRequired = 1008;
    case DataInvalid = 1009;
    case InvalidUser = 1010;
    case LanguageRequired = 1011;
    case ChangePasswordRequired = 1012;
    case PermissionDenied = 1013;
    case AccountNumberNotFound = 1014;
    case ReceiverAccountNotFound = 1015;
    case InsufficientBalance = 1016;
    case WrongAmount = 1017;
    case UnknownService = 1018;
    case ServiceHasNotInquiryFeature = 1019;
    case InquiryTransactionIdRequired = 1020;
    case InquiryTransactionNotFound = 1021;
    case WrongServiceCharge = 1022;
    case DuplicateTransactionId = 1023;
    case TerminalIdRequired = 1024;
    case IncorrectServiceVersion = 1025;
    case TransactionNotFound = 1026;
    case BeecardNotFound = 1027;
    case BeecardIsUsed = 1028;
    case BeecardIsExpired = 1029;
    case RateLimitExceeded = 1033;
    case TransactionInProgress = 1034;
    case InternalServerError = 2000;
    case AmbiguousServerError = 20000;
    case InvalidHttpContentType = 2001;
    case InvalidHttpCharset = 2002;
    case InvalidHttpContent = 2003;
    case UnsupportedHttpMethod = 2004;
    case InvalidUrlPath = 2005;

    public static function tryFromCode(int $code): ?self
    {
        return self::tryFrom($code);
    }

    public function message(): string
    {
        return match ($this) {
            self::LoginRequired => '«login» is required',
            self::PasswordRequired => '«password» is required',
            self::IncorrectCredentials => 'Incorrect login or password',
            self::ActionRequired => '«action» is required',
            self::IncorrectActionName => 'Incorrect action name',
            self::VersionRequired => '«version» is required',
            self::IncorrectVersion => 'Incorrect version',
            self::DataRequired => '«data» is required',
            self::DataInvalid => '«data» is invalid',
            self::InvalidUser => 'Invalid user',
            self::LanguageRequired => '«language» is required',
            self::ChangePasswordRequired => 'Change password is required',
            self::PermissionDenied => 'Permission denied',
            self::AccountNumberNotFound => 'Account number not found',
            // PDF (page 18): "Receiver account not found" — the brief's
            // transcription reads "Receiver account number not found";
            // the PDF is the authority and wins.
            self::ReceiverAccountNotFound => 'Receiver account not found',
            self::InsufficientBalance => 'Insufficient balance',
            self::WrongAmount => 'Wrong amount',
            self::UnknownService => 'Unknown service',
            self::ServiceHasNotInquiryFeature => 'Service has not inquiry feature',
            self::InquiryTransactionIdRequired => 'Inquiry transaction ID is required',
            self::InquiryTransactionNotFound => 'Inquiry transaction not found',
            self::WrongServiceCharge => 'Wrong service charge',
            self::DuplicateTransactionId => 'Duplicate transaction ID',
            self::TerminalIdRequired => '«Terminal_id» is required',
            self::IncorrectServiceVersion => 'Incorrect service version, service list update is required',
            self::TransactionNotFound => 'Transaction not found',
            self::BeecardNotFound => 'Beecard not found',
            self::BeecardIsUsed => 'Beecard is used',
            self::BeecardIsExpired => 'Beecard is expired',
            self::RateLimitExceeded => 'Rate limit exceeded',
            self::TransactionInProgress => 'Transaction is in progress, please try again later',
            self::InternalServerError => 'Internal server error',
            self::AmbiguousServerError => 'Ambiguous Server Error',
            self::InvalidHttpContentType => 'Invalid HTTP content type',
            self::InvalidHttpCharset => 'Invalid HTTP charset',
            self::InvalidHttpContent => 'Invalid HTTP content',
            self::UnsupportedHttpMethod => 'Unsupported HTTP method',
            self::InvalidUrlPath => 'Invalid URL path',
        };
    }

    /**
     * Groups follow the design spec (docs/superpowers/specs/2026-08-02-
     * basata-rename-and-audit-design.md, "Error handling"). That spec does
     * not explicitly place 1019, 1023, 1025, 1028 and 1029 into a group;
     * they are client-side/business-rule validation failures (unsupported
     * feature, duplicate submission, stale version, card already used or
     * expired), so they are grouped under BasataValidationException here.
     */
    public function exceptionClass(): string
    {
        return match ($this) {
            self::LoginRequired,
            self::PasswordRequired,
            self::IncorrectCredentials,
            self::InvalidUser,
            self::ChangePasswordRequired,
            self::PermissionDenied => BasataAuthenticationException::class,

            self::ActionRequired,
            self::IncorrectActionName,
            self::VersionRequired,
            self::IncorrectVersion,
            self::DataRequired,
            self::DataInvalid,
            self::LanguageRequired,
            self::WrongAmount,
            self::InquiryTransactionIdRequired,
            self::WrongServiceCharge,
            self::TerminalIdRequired,
            self::ServiceHasNotInquiryFeature,
            self::DuplicateTransactionId,
            self::IncorrectServiceVersion,
            self::BeecardIsUsed,
            self::BeecardIsExpired,
            self::InvalidHttpContentType,
            self::InvalidHttpCharset,
            self::InvalidHttpContent,
            self::UnsupportedHttpMethod,
            self::InvalidUrlPath => BasataValidationException::class,

            self::InsufficientBalance => BasataInsufficientBalanceException::class,

            self::RateLimitExceeded => BasataRateLimitException::class,

            self::TransactionInProgress => BasataTransactionInProgressException::class,

            self::AccountNumberNotFound,
            self::ReceiverAccountNotFound,
            self::UnknownService,
            self::InquiryTransactionNotFound,
            self::TransactionNotFound,
            self::BeecardNotFound => BasataNotFoundException::class,

            self::InternalServerError,
            self::AmbiguousServerError => BasataServerException::class,
        };
    }
}
