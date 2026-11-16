<?php

namespace Applu\CentralBridge;

enum ErrorCode: string
{
    case Unauthorized = 'unauthorized';
    case SignatureInvalid = 'signature_invalid';
    case TimestampSkew = 'timestamp_skew';
    case IpNotAllowed = 'ip_not_allowed';
    case NotFound = 'not_found';
    case IdempotencyConflict = 'idempotency_conflict';
    case ValidationFailed = 'validation_failed';
    case MissingActor = 'missing_actor';
    case InternalError = 'internal_error';
    case CapabilityUnsupported = 'capability_unsupported';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Unauthorized, self::SignatureInvalid, self::TimestampSkew => 401,
            self::IpNotAllowed => 403,
            self::NotFound => 404,
            self::IdempotencyConflict => 409,
            self::ValidationFailed, self::MissingActor => 422,
            self::InternalError => 500,
            self::CapabilityUnsupported => 501,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::Unauthorized => 'Requisição sem autenticação válida da Central.',
            self::SignatureInvalid => 'Assinatura da Central inválida.',
            self::TimestampSkew => 'Requisição expirada: o relógio difere em mais de 5 minutos.',
            self::IpNotAllowed => 'Endereço IP não autorizado.',
            self::NotFound => 'Recurso não encontrado.',
            self::IdempotencyConflict => 'Esta chave de idempotência já foi usada com outro conteúdo.',
            self::ValidationFailed => 'Dados inválidos.',
            self::MissingActor => 'Informe quem pediu a ação (X-Central-Actor).',
            self::InternalError => 'Erro interno no produto.',
            self::CapabilityUnsupported => 'Este produto não oferece esta operação.',
        };
    }
}
