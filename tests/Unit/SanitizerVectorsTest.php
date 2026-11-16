<?php

namespace Applu\CentralBridge\Tests\Unit;

use Applu\CentralBridge\Support\ResponseSanitizer;
use Applu\CentralBridge\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SanitizerVectorsTest extends TestCase
{
    public static function cases(): iterable
    {
        foreach (self::vectors('sanitizer.json')['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    #[DataProvider('cases')]
    public function test_vector(array $case): void
    {
        $this->assertSame($case['output'], ResponseSanitizer::sanitize($case['input'], $case['allow']));
    }

    public function test_forbidden_segments_match_shared_vectors(): void
    {
        $this->assertSame(
            self::vectors('sanitizer.json')['forbidden_segments'],
            ResponseSanitizer::FORBIDDEN_SEGMENTS,
        );
    }

    public function test_list_indices_are_ignored_in_allow_paths(): void
    {
        $input = ['items' => [['document' => '1', 'name' => 'a'], ['document' => '2', 'name' => 'b']]];

        $this->assertSame($input, ResponseSanitizer::sanitize($input, ['items.document']));
        $this->assertSame(
            ['items' => [['name' => 'a'], ['name' => 'b']]],
            ResponseSanitizer::sanitize($input),
        );
    }

    public function test_segment_matching(): void
    {
        foreach (['ref', 'email', 'tokenizer', 'access_key', 'plan', 'has_more', 'hashtag'] as $key) {
            $this->assertFalse(ResponseSanitizer::isForbidden($key), $key);
        }

        foreach (['Senha Atual', 'two_factor_secret', 'APIToken', 'cpf', 'documento_fiscal'] as $key) {
            $this->assertTrue(ResponseSanitizer::isForbidden($key), $key);
        }
    }
}
