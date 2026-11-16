<?php

namespace Applu\CentralBridge\Tests\Unit;

use Applu\CentralBridge\Signature;
use Applu\CentralBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SignatureVectorsTest extends TestCase
{
    public static function cases(): iterable
    {
        $vectors = self::vectors('signature.json');

        foreach ($vectors['cases'] as $case) {
            yield $case['name'] => [$case, $vectors['token'], $vectors['max_skew_seconds']];
        }
    }

    #[DataProvider('cases')]
    public function test_vector(array $case, string $token, int $maxSkew): void
    {
        $outcome = match (true) {
            ! Signature::isFresh($case['timestamp'], $case['now'], $maxSkew) => 'timestamp_skew',
            ! Signature::verify($token, $case['method'], $case['path'], $case['timestamp'], $case['body'], $case['signature']) => 'signature_invalid',
            default => 'valid',
        };

        $this->assertSame($case['expect'], $outcome);

        if ($case['expect'] !== 'signature_invalid') {
            // Every non-tampered case must be reproducible byte for byte.
            $this->assertSame(
                strtolower($case['signature']),
                Signature::make($token, $case['method'], $case['path'], $case['timestamp'], $case['body']),
            );
        }
    }

    public function test_timestamp_header_format(): void
    {
        $this->assertTrue(Signature::isValidTimestamp('1763200000'));
        $this->assertTrue(Signature::isValidTimestamp('123456789012'));
        $this->assertFalse(Signature::isValidTimestamp('1234567890123'));
        $this->assertFalse(Signature::isValidTimestamp('-1'));
        $this->assertFalse(Signature::isValidTimestamp('17632e5'));
        $this->assertFalse(Signature::isValidTimestamp(''));
        $this->assertFalse(Signature::isValidTimestamp(null));
    }

    public function test_empty_token_never_verifies(): void
    {
        $this->assertFalse(Signature::verify('', 'GET', '/x', 1, '', Signature::make('', 'GET', '/x', 1, '')));
    }
}
