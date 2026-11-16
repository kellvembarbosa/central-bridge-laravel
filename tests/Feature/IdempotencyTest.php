<?php

namespace Applu\CentralBridge\Tests\Feature;

use Applu\CentralBridge\Headers;
use Applu\CentralBridge\Http\Middleware\EnforceIdempotency;
use Applu\CentralBridge\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class IdempotencyTest extends TestCase
{
    private const SUSPEND = '/central-api/v1/users/u_42/suspend';

    public function test_missing_actor_is_422(): void
    {
        $response = $this->signed('POST', self::SUSPEND, '{"reason":"x"}', [Headers::IDEMPOTENCY_KEY => 'k']);

        $this->assertError($response, 422, 'missing_actor', 'Informe quem pediu a ação (X-Central-Actor).');
    }

    public function test_invalid_actor_is_422(): void
    {
        $response = $this->signed('POST', self::SUSPEND, '{"reason":"x"}', [
            Headers::IDEMPOTENCY_KEY => 'k',
            Headers::ACTOR => 'not-an-email',
        ]);

        $this->assertError($response, 422, 'missing_actor');
    }

    public function test_missing_key_is_422(): void
    {
        $this->assertError(
            $this->signedWrite(self::SUSPEND, ['reason' => 'x'], key: null),
            422,
            'validation_failed',
            'Dados inválidos.',
        );
        $this->assertSame([], $this->fake()->callsTo('suspend'));
    }

    public function test_key_longer_than_128_is_422(): void
    {
        $this->assertError(
            $this->signedWrite(self::SUSPEND, ['reason' => 'x'], key: str_repeat('k', 129)),
            422,
            'validation_failed',
        );

        $this->signedWrite(self::SUSPEND, ['reason' => 'x'], key: str_repeat('k', 128))->assertOk();
    }

    public function test_same_key_and_body_replays_stored_response(): void
    {
        $first = $this->signedWrite(self::SUSPEND, ['reason' => 'Fraude'], 'key-replay');
        $first->assertOk()->assertExactJson(['ok' => true]);
        $first->assertHeaderMissing(Headers::IDEMPOTENCY_REPLAYED);

        $second = $this->signedWrite(self::SUSPEND, ['reason' => 'Fraude'], 'key-replay');
        $second->assertOk()->assertExactJson(['ok' => true]);
        $second->assertHeader(Headers::IDEMPOTENCY_REPLAYED, 'true');

        $this->assertCount(1, $this->fake()->callsTo('suspend'));
    }

    public function test_client_errors_are_stored_and_replayed(): void
    {
        $this->signedWrite('/central-api/v1/users/nobody/suspend', ['reason' => 'x'], 'key-404')->assertNotFound();

        $this->signedWrite('/central-api/v1/users/nobody/suspend', ['reason' => 'x'], 'key-404')
            ->assertNotFound()
            ->assertHeader(Headers::IDEMPOTENCY_REPLAYED, 'true')
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_same_key_with_different_body_is_409(): void
    {
        $this->signedWrite(self::SUSPEND, ['reason' => 'Fraude'], 'key-conflict')->assertOk();

        $this->assertError(
            $this->signedWrite(self::SUSPEND, ['reason' => 'Outro motivo'], 'key-conflict'),
            409,
            'idempotency_conflict',
            'Esta chave de idempotência já foi usada com outro conteúdo.',
        );
    }

    public function test_same_key_on_another_route_is_409(): void
    {
        $this->signedWrite(self::SUSPEND, ['reason' => 'x'], 'key-route')->assertOk();

        $this->assertError(
            $this->signedWrite('/central-api/v1/users/u_42/unsuspend', ['reason' => 'x'], 'key-route'),
            409,
            'idempotency_conflict',
        );
    }

    public function test_key_still_running_is_409_in_progress(): void
    {
        $lock = Cache::lock(EnforceIdempotency::lockKey('key-busy'), 10);
        $this->assertTrue($lock->get());

        $this->assertError(
            $this->signedWrite(self::SUSPEND, ['reason' => 'x'], 'key-busy'),
            409,
            'idempotency_conflict',
            'Outra requisição com esta chave de idempotência ainda está em andamento.',
        );

        $lock->release();
        $this->signedWrite(self::SUSPEND, ['reason' => 'x'], 'key-busy')->assertOk();
    }

    public function test_server_errors_are_not_stored(): void
    {
        $this->fake()->explode = true;
        $body = ['plan' => 'pro', 'until' => null, 'reason' => 'x'];

        $this->assertError(
            $this->signedWrite('/central-api/v1/users/u_42/plan', $body, 'key-500'),
            500,
            'internal_error',
            'Erro interno no produto.',
        );

        $this->fake()->explode = false;

        $this->signedWrite('/central-api/v1/users/u_42/plan', $body, 'key-500')
            ->assertOk()
            ->assertHeaderMissing(Headers::IDEMPOTENCY_REPLAYED);
    }

    public function test_actor_reaches_the_handler(): void
    {
        $this->signedWrite(self::SUSPEND, ['reason' => 'x'], 'key-actor', [Headers::REQUEST_ID => 'req-9'])->assertOk();

        [, , $actor] = $this->fake()->callsTo('suspend')[0];

        $this->assertSame(self::ACTOR, $actor->email);
        $this->assertSame('req-9', $actor->requestId);
    }
}
