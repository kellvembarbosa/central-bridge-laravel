# applu/central-bridge (Laravel)

A **Bridge** é a API de administração, assinada com HMAC, que cada produto Laravel
expõe para a Central. A Central nunca grava no banco do produto: ela pede, o
produto decide. O pacote também traz o `CentralClient`, que o produto usa para
chamar a **API de faturação** da Central (emitir, listar e cancelar NFS-e) e o
**checkout da Central** (listar planos, criar e consultar pagamentos).

O contrato completo está em `docs/central/bridge-contract.md` (repositório da
Central). Este pacote é a implementação Laravel desse contrato e passa nos
vetores compartilhados em `test-vectors/` (os mesmos do pacote AdonisJS,
[kellvembarbosa/central-bridge-adonis](https://github.com/kellvembarbosa/central-bridge-adonis)).

- PHP `^8.2`, Laravel 11, 12 ou 13.
- Compatível no fio com o `applu/central-bridge` 1.x: um produto que já usa a
  versão anterior fala com a Central sem mudar nada.

---

## 1. Instalação

O pacote é público no GitHub ([kellvembarbosa/central-bridge-laravel](https://github.com/kellvembarbosa/central-bridge-laravel))
e é instalado por repositório `vcs`. No `composer.json` do produto:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/kellvembarbosa/central-bridge-laravel"
        }
    ],
    "require": {
        "applu/central-bridge": "^2.2"
    }
}
```

> As versões são as tags do repositório (`v2.0.0`, …). Para desenvolvimento
> local dá para usar `{"type": "path", "url": "../central-bridge-laravel"}`.

```bash
composer update applu/central-bridge
php artisan vendor:publish --tag=central-bridge-config
```

O service provider é descoberto automaticamente. O comando acima cria
`config/central-bridge.php`.

### Registrar as rotas

As rotas **não** são registradas sozinhas: o produto escolhe onde. O jeito
recomendado (sem o grupo `web`, logo sem sessão nem CSRF) é no
`bootstrap/app.php`:

```php
use Applu\CentralBridge\CentralBridge;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => CentralBridge::routes(),
    )
    // ...
```

Também funciona chamar `CentralBridge::routes();` dentro de `routes/web.php`
(o pacote remove a verificação de CSRF dessas rotas). **Não** use
`routes/api.php` sem ajustar o prefixo, porque o Laravel acrescenta `/api` e o
caminho deixa de ser `/central-api/v1`.

Todas as rotas ficam sob `/central-api/v1` e têm nomes `central-bridge.*`.

## 2. Variáveis de ambiente

| Variável | Significado |
|---|---|
| `CENTRAL_BRIDGE_ENABLED` | `true` liga as rotas. Desligada, toda rota responde 404 `not_found`. |
| `CENTRAL_BRIDGE_TOKEN` | Token do produto, gerado na Central (tela do produto → "Gerar token"). |
| `CENTRAL_BRIDGE_PRODUCT` | Slug do produto na Central. O `/health` devolve este valor. |
| `CENTRAL_BRIDGE_ALLOWED_IPS` | IPs (ou faixas CIDR) da Central separados por vírgula. Vazio = qualquer IP. |
| `CENTRAL_URL` | URL base da Central. Só o `CentralClient` usa. |
| `CENTRAL_BRIDGE_VERSION` | Opcional. Versão anunciada no `/health` (padrão: versão do pacote). |

```dotenv
CENTRAL_BRIDGE_ENABLED=true
CENTRAL_BRIDGE_TOKEN=cbt_...
CENTRAL_BRIDGE_PRODUCT=meu-produto
CENTRAL_BRIDGE_ALLOWED_IPS=203.0.113.10
CENTRAL_URL=https://central.exemplo.com.br
```

Atrás de proxy ou load balancer, configure o `TrustProxies` do Laravel para que
`$request->ip()` seja o IP real da Central; senão a allowlist compara o IP do proxy.

Outras chaves de `config/central-bridge.php` (fixas pelo contrato, mude só se
souber o que está fazendo): `prefix` (`central-api/v1`), `max_skew_seconds`
(300), `idempotency_ttl_hours` (24) e `handlers`.

A idempotência usa o cache padrão da aplicação (`Cache::lock` + `Cache::put`).
Em produção use um driver compartilhado entre os servidores e com suporte a
lock: `redis`, `database` ou `memcached` (não `array` nem `file` com várias máquinas).

## 3. Implementar os handlers

O produto implementa só o que quiser oferecer. Rota cujo handler não existe
responde 501 `capability_unsupported`, e o `/health` anuncia em `capabilities`
apenas o que existe.

| Capacidade | Interface (`Applu\CentralBridge\Contracts\...`) |
|---|---|
| `payment_owner` | `ProvidesPaymentOwnership::paymentOwner($provider, $paymentId): PaymentOwner` |
| `subscriptions` | `ProvidesSubscriptions::subscriptions(?$status, $page, $perPage): Page` |
| `users_search`, `user_detail` | `ProvidesUsers::searchUsers(?$query, $page, $perPage): Page` / `findUser($ref): ?UserDetail` |
| `change_plan` | `ManagesUserPlans::changePlan($ref, PlanChange $change, Actor $actor): void` |
| `password_reset` | `SendsPasswordResets::sendPasswordReset($ref, Actor $actor): void` |
| `suspend` | `SuspendsUsers::suspend($ref, $reason, Actor $actor)` / `unsuspend(...)` |
| `metrics` | `ProvidesMetrics::metrics($from, $to): iterable<MetricsDay>` |
| `invoices` | **as duas**: `ProvidesInvoiceRequests::invoiceRequest($provider, $paymentId): InvoiceRequest` e `ReceivesInvoices::receiveInvoice(InvoiceDelivery $delivery, Actor $actor): void` |
| `payment_events` | `ReceivesPaymentEvents::paymentEvent(PaymentEvent $event, Actor $actor): void` |

Registre as classes em `config/central-bridge.php`:

```php
use Applu\CentralBridge\Contracts;

'handlers' => [
    Contracts\ProvidesUsers::class => App\Central\CentralUsers::class,
    Contracts\SuspendsUsers::class => App\Central\CentralUsers::class,
    Contracts\ProvidesPaymentOwnership::class => App\Central\CentralPayments::class,
],
```

(Também vale fazer `$this->app->bind(Contracts\ProvidesUsers::class, ...)` num
service provider: o pacote usa o binding do container quando o contrato não está
no mapa.) As classes são resolvidas pelo container, então podem receber
dependências no construtor.

### Exemplo

```php
<?php

namespace App\Central;

use App\Models\User;
use Applu\CentralBridge\Contracts\ProvidesUsers;
use Applu\CentralBridge\Contracts\SuspendsUsers;
use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\Page;
use Applu\CentralBridge\Data\UserDetail;
use Applu\CentralBridge\Data\UserSummary;
use Applu\CentralBridge\Exceptions\UserNotFound;

class CentralUsers implements ProvidesUsers, SuspendsUsers
{
    public function searchUsers(?string $query, int $page, int $perPage): Page
    {
        $users = User::query()
            ->when($query, fn ($q) => $q->where('email', 'like', "%{$query}%"))
            ->orderByDesc('id')
            ->forPage($page, $perPage + 1)
            ->get();

        return new Page(
            $users->take($perPage)->map(fn (User $u) => $this->summary($u))->all(),
            $page,
            $perPage,
            hasMore: $users->count() > $perPage,
        );
    }

    public function findUser(string $ref): ?UserDetail
    {
        $user = User::find($ref);

        return $user ? new UserDetail(
            $this->summary($user),
            limits: ['projects' => $user->plan_limit],
            usage: ['projects' => $user->projects()->count()],
        ) : null;
    }

    public function suspend(string $ref, string $reason, Actor $actor): void
    {
        $user = User::find($ref) ?? throw new UserNotFound($ref);
        $user->update(['suspended_at' => now()]);

        activity()->log("Suspenso pela Central por {$actor->email}: {$reason}");
    }

    public function unsuspend(string $ref, string $reason, Actor $actor): void
    {
        $user = User::find($ref) ?? throw new UserNotFound($ref);
        $user->update(['suspended_at' => null]);
    }

    private function summary(User $user): UserSummary
    {
        return UserSummary::fromArray([
            'ref' => (string) $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'plan' => $user->plan,
            'suspended' => $user->suspended_at !== null,
            'created_at' => $user->created_at,      // DateTimeInterface vira ISO-8601
            'last_seen_at' => $user->last_seen_at,
        ]);
    }
}
```

### Regras que o pacote já cuida

- **Assinatura**: `X-Central-Timestamp` + `X-Central-Signature` (HMAC-SHA256 de
  `MÉTODO\ncaminho?query\ntimestamp\nsha256(corpo)`), tolerância de 300 s.
- **Escritas** (`POST`): exigem `X-Central-Actor` (e-mail; senão 422
  `missing_actor`) e `X-Idempotency-Key` (1–128 caracteres; senão 422
  `validation_failed`). A mesma chave com o mesmo conteúdo devolve a resposta
  guardada com `X-Idempotency-Replayed: true`; com outro conteúdo, 409
  `idempotency_conflict`. Só respostas com status < 500 são guardadas (24 h).
- **Validação** de query e corpo de cada rota: erro vira 422 `validation_failed`.
- **Sanitizador**: nenhuma resposta 200 leva chave com `password`, `senha`,
  `hash`, `token`, `secret`, `segredo`, `document`, `documento`, `cpf`, `cnpj`,
  `remember` ou `recovery` no nome (snake_case, camelCase e kebab-case). A única
  exceção é `taker.document` em `invoice-request`.
- **Erros**: sempre `{"error": {"code": "...", "message": "..."}}` em pt-BR.

### Erros dentro do handler

| O handler... | A Central recebe |
|---|---|
| devolve `null` em `findUser` ou lança `UserNotFound` | 404 `not_found` |
| lança `ModelNotFoundException` (`findOrFail`) | 404 `not_found` |
| lança `BridgeException::make(ErrorCode::ValidationFailed, 'Plano inexistente.')` | 422 com a sua mensagem |
| lança qualquer outra exceção | 500 `internal_error`, sem detalhe (a exceção vai para o `report()` do produto) |

`receiveInvoice` precisa ser idempotente por `invoice_id`: a Central pode
reenviar a mesma nota.

### Avisos de pagamento (`payment_events`)

Quando a Central vende os planos do produto (contrato §10), ela confirma o
pagamento no gateway e chama `POST payment-events` com um `PaymentEvent`
(`X-Idempotency-Key` = `event->id`). Reenvia até receber 2xx, então o handler
precisa ser **idempotente por `$event->id`** e, para liberar, por
`$event->payment->id`.

| `$event->type` | O que fazer |
|---|---|
| `payment.paid` | dar ao usuário `$event->payment->productUserRef` acesso a `$event->payment->plan->slug` até `$event->payment->accessUntil` (`null` = para sempre), uma vez por `payment->id` |
| `payment.refunded` | estorno total ou chargeback: revogar |
| `payment.expired` | a cobrança pendente venceu sem pagamento (nada a liberar) |
| `subscription.renewed` | igual a `payment.paid`: o acesso passa a valer até o novo `$event->payment->accessUntil` |
| `subscription.ended` | o gateway não vai cobrar de novo; o acesso segue até `$event->payment->accessUntil` |

`$event->payment->accessUntil` é até quando o pagamento (e suas renovações) dá
acesso; `null` = vitalício (ou ainda não pago). Quem calcula é a Central: o
produto nunca faz conta de data. `$event->subscription` (`status`,
`currentPeriodEnd`) vem em todo plano pago que tem período (recorrente ou
pré-pago avulso) e é `null` só em plano vitalício e em cobrança nunca paga;
`subscription->currentPeriodEnd` é igual a `payment->accessUntil`.

```php
use Applu\CentralBridge\Contracts\ReceivesPaymentEvents;
use Applu\CentralBridge\Data\Actor;
use Applu\CentralBridge\Data\PaymentEvent;

class CentralCheckout implements ReceivesPaymentEvents
{
    public function paymentEvent(PaymentEvent $event, Actor $actor): void
    {
        $payment = $event->payment;
        $user = User::findOrFail($payment->productUserRef);

        match ($event->type) {
            // acesso ao plano até accessUntil (null = para sempre); sem conta de data
            PaymentEvent::PAYMENT_PAID,
            PaymentEvent::SUBSCRIPTION_RENEWED => Access::set($user, $payment->plan->slug, until: $payment->accessUntil),
            PaymentEvent::PAYMENT_REFUNDED => Access::revoke($user, $payment->id),
            default => null, // subscription.ended (acesso segue até accessUntil), payment.expired
        };
    }
}
```

O corpo é validado antes de chegar ao handler: tipo desconhecido ou campo
faltando/de tipo errado responde 422 `validation_failed` e o handler não roda.

## 4. Rotas

| Método | Rota (`/central-api/v1`) | Resposta 200 |
|---|---|---|
| GET | `health` | `{version, product, capabilities}` |
| GET | `payments/{provider}/{id}/owner` | `{owned, user_ref, kind, local_status}` |
| GET | `payments/{provider}/{id}/invoice-request` | `{requested, request_ref, email, service_description, taker}` |
| GET | `subscriptions?status=&page=` | `{data, page, per_page, has_more}` |
| GET | `users?q=&page=` | `{data, page, per_page, has_more}` |
| GET | `users/{ref}` | `UserDetail` |
| GET | `metrics?from=YYYY-MM-DD&to=YYYY-MM-DD` | `{data: [{date, metrics}]}` |
| POST | `users/{ref}/plan` | `{ok: true}` |
| POST | `users/{ref}/password-reset` | `{ok: true, message}` |
| POST | `users/{ref}/suspend` | `{ok: true}` |
| POST | `users/{ref}/unsuspend` | `{ok: true}` |
| POST | `invoices` | `{ok: true}` |
| POST | `payment-events` | `{ok: true}` |

## 5. Cliente da API da Central (`CentralClient`)

O produto chama a Central com o **mesmo token** e a mesma assinatura, mais o
cabeçalho `X-Central-Product`. Precisa de `CENTRAL_URL`, `CENTRAL_BRIDGE_TOKEN`
e `CENTRAL_BRIDGE_PRODUCT`.

```php
use Applu\CentralBridge\Client\CentralClient;
use Applu\CentralBridge\Exceptions\CentralApiException;

$central = app(CentralClient::class);

try {
    $invoice = $central->issueInvoice([
        'taker' => ['document' => '12345678000195', 'name' => 'ACME Ltda', 'email' => 'nf@acme.com'],
        'amount_cents' => 4990,
        'description' => 'Assinatura mensal - plano Pro',
        'reference' => $payment->id,           // opcional
        // 'service_code' => '01.07.01',       // opcional
    ], idempotencyKey: "invoice-{$payment->id}");

    $page = $central->listInvoices(page: 2);
    $one = $central->getInvoice($id);
    $pdf = $central->invoicePdf($id);        // bytes do DANFSe
    $xml = $central->invoiceXml($id);        // XML autorizado
    $central->cancelInvoice($id, 'Cobrança emitida em duplicidade', idempotencyKey: "cancel-{$id}");
} catch (CentralApiException $e) {
    $e->errorCode;   // ex.: "validation_failed", "connection_failed", "unexpected_response"
    $e->getMessage(); // mensagem da Central, em pt-BR
    $e->status;      // status HTTP (0 quando não conectou)
    $e->body;        // corpo JSON decodificado, se houver
}
```

- Escritas (`issueInvoice`, `cancelInvoice`) exigem chave de idempotência:
  repita a **mesma** chave ao tentar de novo a mesma operação.
- O motivo do cancelamento deve ter de 15 a 255 caracteres.
- Os métodos JSON devolvem o corpo decodificado como veio da Central.
- O produto só enxerga as próprias notas.

### Checkout da Central (planos e pagamentos)

A Central cria a cobrança com as chaves de gateway **dela**; o produto só
escolhe o plano pelo `slug` (o preço vem da Central).

```php
use Applu\CentralBridge\Data\NewPayment;

$plans = $central->listPlans();              // list<Plan>, à venda e na ordem

$payment = $central->createPayment(new NewPayment(
    plan: 'mensal',
    customerName: $user->name,
    customerEmail: $user->email,
    productUserRef: (string) $user->id,
    method: 'pix',                           // ou 'card', 'boleto', null = padrão do gateway
    tracking: ['utm_source' => 'google'],    // opcional
), idempotencyKey: "checkout-{$user->id}-{$attempt}");

$payment->pix?->copyPaste;                   // Pix copia e cola (só pendente)
$payment->checkoutUrl;                       // página hospedada (só pendente)

$payment = $central->getPayment($payment->id); // tela de retorno: consulta o status
if ($payment->isPaid()) { /* liberar, idempotente por $payment->id */ }
```

- `createPayment` também aceita o corpo cru (`array`) do contrato.
- Mesma chave + mesmo corpo devolve o mesmo pagamento; depois de
  `gateway_error` use **outra** chave.
- Resposta da Central fora do formato esperado vira `CentralApiException` com
  `unexpected_response`.
- Liberar pelo aviso (`payment_events`) ou pelo `getPayment` que devolver
  `paid` é seguro desde que os dois sejam idempotentes por `payment->id`.

## 6. Testes do pacote

```bash
composer install
composer test        # ou vendor/bin/phpunit
```

Os testes leem os vetores compartilhados em `test-vectors/`
(`signature.json` e `sanitizer.json`).

### Testar o seu handler no produto

```php
use Applu\CentralBridge\Signature;

$ts = time();
$body = json_encode(['reason' => 'Fraude confirmada']);
$uri = '/central-api/v1/users/42/suspend';

$this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars([
    'X-Central-Timestamp' => (string) $ts,
    'X-Central-Signature' => Signature::make(config('central-bridge.token'), 'POST', $uri, $ts, $body),
    'X-Central-Actor' => 'ops@central.com',
    'X-Idempotency-Key' => 'teste-1',
]), $body)->assertOk();
```

## Licença

MIT. Veja `LICENSE`.
