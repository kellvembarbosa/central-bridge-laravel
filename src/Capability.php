<?php

namespace Applu\CentralBridge;

use Applu\CentralBridge\Contracts\ManagesUserPlans;
use Applu\CentralBridge\Contracts\ProvidesInvoiceRequests;
use Applu\CentralBridge\Contracts\ProvidesMetrics;
use Applu\CentralBridge\Contracts\ProvidesPaymentOwnership;
use Applu\CentralBridge\Contracts\ProvidesSubscriptions;
use Applu\CentralBridge\Contracts\ProvidesUsers;
use Applu\CentralBridge\Contracts\ReceivesInvoices;
use Applu\CentralBridge\Contracts\SendsPasswordResets;
use Applu\CentralBridge\Contracts\SuspendsUsers;
use Applu\CentralBridge\Support\HandlerRegistry;

enum Capability: string
{
    case PaymentOwner = 'payment_owner';
    case Subscriptions = 'subscriptions';
    case UsersSearch = 'users_search';
    case UserDetail = 'user_detail';
    case ChangePlan = 'change_plan';
    case PasswordReset = 'password_reset';
    case Suspend = 'suspend';
    case Metrics = 'metrics';
    case Invoices = 'invoices';

    /**
     * Contracts that must ALL have a handler for the capability to exist.
     *
     * @return list<class-string>
     */
    public function contracts(): array
    {
        return match ($this) {
            self::PaymentOwner => [ProvidesPaymentOwnership::class],
            self::Subscriptions => [ProvidesSubscriptions::class],
            self::UsersSearch, self::UserDetail => [ProvidesUsers::class],
            self::ChangePlan => [ManagesUserPlans::class],
            self::PasswordReset => [SendsPasswordResets::class],
            self::Suspend => [SuspendsUsers::class],
            self::Metrics => [ProvidesMetrics::class],
            self::Invoices => [ProvidesInvoiceRequests::class, ReceivesInvoices::class],
        };
    }

    public function isSupportedBy(HandlerRegistry $handlers): bool
    {
        foreach ($this->contracts() as $contract) {
            if (! $handlers->has($contract)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function supported(HandlerRegistry $handlers): array
    {
        $supported = [];

        foreach (self::cases() as $capability) {
            if ($capability->isSupportedBy($handlers)) {
                $supported[] = $capability->value;
            }
        }

        return $supported;
    }
}
