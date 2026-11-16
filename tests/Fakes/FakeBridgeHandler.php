<?php

namespace Applu\CentralBridge\Tests\Fakes;

use Applu\CentralBridge\Contracts\ManagesUserPlans;
use Applu\CentralBridge\Contracts\ProvidesInvoiceRequests;
use Applu\CentralBridge\Contracts\ProvidesMetrics;
use Applu\CentralBridge\Contracts\ProvidesPaymentOwnership;
use Applu\CentralBridge\Contracts\ProvidesSubscriptions;
use Applu\CentralBridge\Contracts\ProvidesUsers;
use Applu\CentralBridge\Contracts\ReceivesInvoices;
use Applu\CentralBridge\Contracts\SendsPasswordResets;
use Applu\CentralBridge\Contracts\SuspendsUsers;
use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\InvoiceDelivery;
use Applu\CentralBridge\Data\InvoiceRequest;
use Applu\CentralBridge\Data\InvoiceTaker;
use Applu\CentralBridge\Data\MetricsDay;
use Applu\CentralBridge\Data\Page;
use Applu\CentralBridge\Data\PaymentOwner;
use Applu\CentralBridge\Data\PlanChange;
use Applu\CentralBridge\Data\SubscriptionRecord;
use Applu\CentralBridge\Data\UserDetail;
use Applu\CentralBridge\Data\UserEvent;
use Applu\CentralBridge\Data\UserSummary;
use Applu\CentralBridge\Exceptions\UserNotFound;
use RuntimeException;

/**
 * Implements every contract and records what it received.
 */
class FakeBridgeHandler implements ManagesUserPlans, ProvidesInvoiceRequests, ProvidesMetrics, ProvidesPaymentOwnership, ProvidesSubscriptions, ProvidesUsers, ReceivesInvoices, SendsPasswordResets, SuspendsUsers
{
    /** @var list<array{0: string, 1: array<mixed>}> */
    public array $calls = [];

    public bool $explode = false;

    public function paymentOwner(string $provider, string $paymentId): PaymentOwner
    {
        $this->record('paymentOwner', [$provider, $paymentId]);

        if ($paymentId === 'unknown') {
            return PaymentOwner::notOwned();
        }

        return new PaymentOwner(true, 'u_42', PaymentOwner::KIND_SUBSCRIPTION, 'active');
    }

    public function subscriptions(?string $status, int $page, int $perPage): Page
    {
        $this->record('subscriptions', [$status, $page, $perPage]);

        return new Page([$this->subscription()], $page, $perPage, false);
    }

    public function searchUsers(?string $query, int $page, int $perPage): Page
    {
        $this->record('searchUsers', [$query, $page, $perPage]);

        return new Page([$this->summary()], $page, $perPage, true);
    }

    public function findUser(string $ref): ?UserDetail
    {
        $this->record('findUser', [$ref]);

        if ($this->explode) {
            throw new RuntimeException('database exploded with secret details');
        }

        if ($ref !== 'u_42') {
            return null;
        }

        return new UserDetail(
            $this->summary(),
            ['projects' => 10, 'api_token' => 3, 'storage' => null],
            ['projects' => 4],
            [$this->subscription()],
            [new UserEvent('login', '2026-11-14T10:00:00+00:00', 'Entrou pelo app')],
        );
    }

    public function changePlan(string $ref, PlanChange $change, Actor $actor): void
    {
        if ($this->explode) {
            throw new RuntimeException('plan service down');
        }

        $this->guard($ref);
        $this->record('changePlan', [$ref, $change, $actor]);
    }

    public function sendPasswordReset(string $ref, Actor $actor): void
    {
        $this->guard($ref);
        $this->record('sendPasswordReset', [$ref, $actor]);
    }

    public function suspend(string $ref, string $reason, Actor $actor): void
    {
        $this->guard($ref);
        $this->record('suspend', [$ref, $reason, $actor]);
    }

    public function unsuspend(string $ref, string $reason, Actor $actor): void
    {
        $this->guard($ref);
        $this->record('unsuspend', [$ref, $reason, $actor]);
    }

    public function metrics(string $from, string $to): iterable
    {
        $this->record('metrics', [$from, $to]);

        yield new MetricsDay($from, ['signups' => 3, 'revenue_cents' => 1990]);
        yield ['date' => $to, 'metrics' => ['signups' => '5']];
    }

    public function invoiceRequest(string $provider, string $paymentId): InvoiceRequest
    {
        $this->record('invoiceRequest', [$provider, $paymentId]);

        return new InvoiceRequest(
            true,
            'req_1',
            'cliente@acme.test',
            'Assinatura mensal',
            new InvoiceTaker('12345678000195', 'ACME Ltda'),
        );
    }

    public function receiveInvoice(InvoiceDelivery $delivery, Actor $actor): void
    {
        $this->record('receiveInvoice', [$delivery, $actor]);
    }

    /**
     * @return list<array<mixed>>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(
            fn ($call) => $call[1],
            array_filter($this->calls, fn ($call) => $call[0] === $method),
        ));
    }

    private function record(string $method, array $args): void
    {
        $this->calls[] = [$method, $args];
    }

    private function guard(string $ref): void
    {
        if ($ref !== 'u_42') {
            throw new UserNotFound($ref);
        }
    }

    private function summary(): UserSummary
    {
        return UserSummary::fromArray([
            'ref' => 'u_42',
            'email' => 'ana@x.com',
            'name' => 'Ana',
            'plan' => 'pro',
            'suspended' => false,
            'created_at' => new \DateTimeImmutable('2026-01-02T03:04:05+00:00'),
            'last_seen_at' => null,
            'password' => 'should never leak',
        ]);
    }

    private function subscription(): SubscriptionRecord
    {
        return new SubscriptionRecord(
            'sub_1', 'u_42', 'stripe', 'sub_abc', 'active', 'pro', 1990, 'BRL', 'month', '2026-12-01T00:00:00+00:00',
        );
    }
}
