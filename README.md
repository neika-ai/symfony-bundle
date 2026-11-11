# Neika Symfony Bundle

Symfony bundle for the [Neika](https://neika.ai) chatbot: the `/neika-chatbot/v1` endpoints the chatbot calls, their auth,
one-click pairing and reporting back to Neika. PHP 8.1+, Symfony 6.4+. MIT.

Sylius users: use [`neika-ai/sylius-plugin`](https://github.com/neika-ai/sylius-plugin).
The DTOs, the HTTP contract and the clients come from [`neika-ai/sdk`](https://github.com/neika-ai/sdk).

## Features

- [Tools](#add-a-tool) and [data sources](#add-a-data-source) — one class each, no config
- [Connect from the dashboard](#connect-from-the-dashboard) — the shop admin clicks **Connect**; nobody copies a secret
- [Store credentials elsewhere](#store-credentials-elsewhere) — database, vault, per-channel keys
- [Push catalog changes](#push-catalog-changes) — the chatbot re-indexes just the entries you changed
- [Chat click tracking](#chat-click-tracking) — chat link visits reported with no code
- [Widget](#widget) — chat embed markup from your config
- [Translations](#translations) — 10 languages built in

## Install

```bash
composer require neika-ai/symfony-bundle
```

1. `config/bundles.php`. Symfony Flex adds this line for you.

```php
Neika\SymfonyBundle\NeikaSymfonyBundle::class => ['all' => true],
```

2. `config/packages/neika.yaml`

```yaml
neika:
    backend_url: '%env(NEIKA_CHATBOT_BACKEND_URL)%'
```

3. `.env.local`. Skip the three secrets if you [connect from the dashboard](#connect-from-the-dashboard)
   with your own [credentials store](#store-credentials-elsewhere).

```dotenv
NEIKA_CHATBOT_BACKEND_URL=https://neika.ai
NEIKA_CHATBOT_API_SECRET=
NEIKA_CHATBOT_INGEST_SECRET=
NEIKA_CHATBOT_SITE_KEY=
```

4. `config/routes/neika.php`. Routes aren't loaded automatically.

```php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@NeikaSymfonyBundle/config/routes.php');
};
```

5. `config/packages/security.yaml`. The chatbot sends `Authorization: Bearer <api secret>`.

```yaml
security:
    providers:
        chatbot_backend:
            memory:
                users: []
    firewalls:
        chatbot_api:
            pattern: ^/neika-chatbot/v1
            stateless: true
            provider: chatbot_backend
            entry_point: Neika\SymfonyBundle\Security\ApiAuthenticationFailureHandler
            access_token:
                token_handler: Neika\SymfonyBundle\Security\ApiSecretAuthenticator
                failure_handler: Neika\SymfonyBundle\Security\ApiAuthenticationFailureHandler
    access_control:
        - { path: ^/neika-chatbot/v1, roles: ROLE_NEIKA_CHATBOT_BACKEND }
```

**Put `chatbot_api` before any catch-all firewall.** Firewalls match in order.

> Keep `/neika-chatbot/pair` reachable without login. It's a browser redirect, and the one-time code is the proof.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/neika-chatbot/v1/tools` | List tools, labels in the `Accept-Language` locale |
| POST | `/neika-chatbot/v1/tools/{name}` | Call a tool |
| GET | `/neika-chatbot/v1/sources` | List data sources and their locales |
| GET | `/neika-chatbot/v1/sources/{name}` | Read one page of a data source |
| GET | `/neika-chatbot/pair?code=…` | The admin's browser lands here after clicking **Connect** |

Request and response bodies follow the sdk's [HTTP contract](https://github.com/neika-ai/sdk#http-contract-what-the-chatbot-backend-expects).
Errors too; `violations` is filled only on a 422.

Tools give live data in the middle of a conversation. Sources are pulled in bulk into Neika's search index,
never once per chat.

## Configuration

```yaml
neika:
    backend_url: ''
    widget:
        enabled: true
        cdn_url: ''
        defer: true
```

| Option | Default | Meaning |
|---|---|---|
| `backend_url` | `''` | Neika origin for everything the shop sends and for pairing. Empty → nothing sent. Not https outside `dev` → refused. |
| `widget.enabled` | `true` | Parameter `neika.widget.enabled`, for whoever prints the widget. |
| `widget.cdn_url` | `''` | Widget script URL. `''` = `https://neika.b-cdn.net/widget/neika-chatbot.js`. |
| `widget.defer` | `true` | `false` → plain `<script src="…">`, no `defer`. |

**Credentials aren't config.** The default provider reads them from env:

| Env var | Used for | Empty |
|---|---|---|
| `NEIKA_CHATBOT_API_SECRET` | The bearer the chatbot sends to `/neika-chatbot/v1` | Every call → 401 `unauthorized` |
| `NEIKA_CHATBOT_INGEST_SECRET` | What the shop sends, as `Bearer <site key>.<ingest secret>` | `hasIngestSecret()` → `false`, nothing sent; the widget still works |
| `NEIKA_CHATBOT_SITE_KEY` | What the shop sends, and the widget | `findCurrentSite()` → `null` |

A missing secret never breaks the boot.

## Store credentials elsewhere

**Keep the secrets in a database or vault: implement `CredentialsProviderInterface` and alias it.**

`src/Chatbot/DatabaseCredentials.php`

```php
use Neika\Sdk\DTO\SiteCredentials;
use Neika\SymfonyBundle\Contract\CredentialsProviderInterface;

class DatabaseCredentials implements CredentialsProviderInterface
{
    public function __construct(
        private readonly ChatbotSettingsRepository $settingsRepository,
    ) {
    }

    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool
    {
        $settings = $this->settingsRepository->findCurrent();
        if ($settings === null) {
            return false;
        }

        return hash_equals($settings->apiSecretHash, hash('sha256', $token));
    }

    public function findCurrentSite(): ?SiteCredentials
    {
        $settings = $this->settingsRepository->findCurrent();
        if ($settings === null) {
            return null;
        }

        return new SiteCredentials($settings->siteKey, $settings->ingestSecret);
    }
}
```

`config/services.yaml`

```diff
 services:
+    Neika\SymfonyBundle\Contract\CredentialsProviderInterface: '@App\Chatbot\DatabaseCredentials'
```

| Method | Called on | Return |
|---|---|---|
| `isApiSecretValid($token)` | Every `/neika-chatbot/v1` request | `true` lets the chatbot in. You do the compare, so you can store only a hash. |
| `findCurrentSite()` | Landings with `?neikaclid=`, and your own code | The current request's site. `null` → nothing reported. Empty ingest secret → widget only. |

- Read the store on every call instead of caching it in a property. A re-paired secret then works without a
  restart, in long-running workers too.
- Compare with `hash_equals`.

## Connect from the dashboard

**Implement `CredentialsWriterInterface` too, and the dashboard's Connect button fills your store.**

```php
use Neika\Sdk\Pairing\HostMatcher;
use Neika\SymfonyBundle\Contract\CredentialsWriterInterface;
use Neika\SymfonyBundle\Pairing\PairedCredentials;
use Symfony\Component\HttpFoundation\Request;

class DatabaseCredentials implements CredentialsProviderInterface, CredentialsWriterInterface
{
    public function __construct(
        private readonly ChatbotSettingsRepository $settingsRepository,
        private readonly HostMatcher               $hostMatcher,
    ) {
    }

    public function findTrustedHost(Request $request): ?string
    {
        foreach ($this->settingsRepository->findShopHostnames() as $shopHostname) {
            $isRequestHost = $this->hostMatcher->isSameHost($shopHostname, $request->getHost());
            if ($isRequestHost) {
                return $shopHostname;
            }
        }

        return null;
    }

    public function save(PairedCredentials $credentials, Request $request): void
    {
        $this->settingsRepository->saveCredentials(
            $credentials->siteKey,
            hash('sha256', $credentials->apiSecret),
            $credentials->ingestSecret,
        );
    }
}
```

`config/services.yaml`

```diff
 services:
     Neika\SymfonyBundle\Contract\CredentialsProviderInterface: '@App\Chatbot\DatabaseCredentials'
+    Neika\SymfonyBundle\Contract\CredentialsWriterInterface: '@App\Chatbot\DatabaseCredentials'
```

| Method | Return or effect |
|---|---|
| `findTrustedHost($request)` | The hostname this shop answers on, from your own data. `null` → `pairing_hostname_missing`. |
| `save($credentials, $request)` | Store `siteKey`, `apiSecret` and `ingestSecret`. `channelCodes` are the connection's channels, if you keep keys per channel. `verifiedDomains` are the website's verified domains, to check each channel's host against. |

- `save()` throws `PairingException` → the error page shows its code, nothing else happens. Any other exception → 500.
  Use a `PairingErrorCode` value to get a translated message:
  `throw new PairingException('Read-only store.', PairingErrorCode::Unsupported->value)`.
- No writer aliased → `/neika-chatbot/pair` answers 409 and asks the admin to paste the credentials by hand.

What happens on Connect:

1. The admin clicks **Connect** on a tool-server connection in the Neika dashboard.
2. The browser opens `{connection base URL}/neika-chatbot/pair?code=…` on your shop.
3. The bundle generates two 64-character secrets, redeems the code at `backend_url` and calls `save()`.
4. The browser goes back to the dashboard, which tests the connection with the new API secret.

> The dashboard offers Connect only for a connection whose base URL is on a verified domain of the website.

**The host of the connection's base URL must match `findTrustedHost()`.** Case, a trailing dot and IDN spelling
don't matter. `www.` does: `www.shop.example` isn't `shop.example`. Return a hostname you stored, never the
request's `Host` header, since anyone can send any `Host`. `HostMatcher` is autowired for your own compares.

When something fails, the page shows a short message in the request locale and the error code. Nothing is saved:

| Code | Status | Cause |
|---|---|---|
| — | 409 | No `CredentialsWriterInterface` service |
| `invalid_code` | 400 | `code` missing or malformed |
| `backend_url_refused` | 400 | `backend_url` empty, or not https outside `dev` |
| `pairing_hostname_missing` | 400 | `findTrustedHost()` returned `null` |
| `pairing_wrong_shop` | 400 | The base URL's host isn't the trusted host |
| `invalid_response` | 400 | The backend's answer redirected, was unreadable, or had a return URL off the `backend_url` origin |
| Your `PairingException` code | 400 | Your `save()` refused |
| Any other code | 400 | The backend refused; see the sdk's [pairing codes](https://github.com/neika-ai/sdk#pairing) |

> Once the backend has seen the code, it's used up. After `pairing_wrong_shop`, a bad return URL or a failing
> `save()`, click **Connect** again.

## Locales

**Send locales ICU knows** (`symfony/intl`). `cs-CZ`, `cs_cz` and `CS-cz` all mean `cs_CZ`. A requested locale
matches a served locale with the same code first, then one with the same language. Everything after that uses the
served locale's full code.

| Endpoint | Not an ICU locale | Valid but not served |
|---|---|---|
| `GET /sources/{name}?locale=` | 400 `invalid_locale` | 400 `invalid_locale` |
| `POST /tools/{name}` (`context.locale`) | 422 `validation_failed`, a `context.locale` violation | The channel's default locale |
| `GET /tools` (`Accept-Language`) | 400 `bad_request` | The channel's default locale |

`ChatbotLocaleContextInterface` decides which locales are served and which one is the default. The bundle's
default serves the kernel locale only. Override the service to serve more; the Sylius plugin makes it
channel-aware.

## Translations

**Strings ship in cs, en, de, sk, pl, hu, ro, sl, hr and ru**, in the `neika` domain. Override one or add a
language in your app:

`translations/neika.it.yaml`

```yaml
neika:
    tool:
        failure: 'Al momento non è possibile recuperare le informazioni. Riprova più tardi.'
```

**A string missing in the visitor's language comes from the channel's default locale**
(`ChatbotLocaleContextInterface::getDefaultLocale()`), not your app's fallback. Your tools' descriptions and
labels work the same way, in `messages` or the `translationDomain` you set on the definition.

> API error messages and the pairing page use the request locale.

## Add a tool

One class and one arguments DTO. The interface is autoconfigured.

```php
readonly class MyArguments
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $query = '',
    ) {
    }
}

readonly class MyTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('my_tool', 'app.chatbot.my_tool.description');
    }

    public function getArgumentsClass(): string
    {
        return MyArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        return new ToolResult([new ContentItem('...')]);
    }
}
```

The input schema comes from the DTO's constraints:

| Constraint | Schema |
|---|---|
| `Assert\NotBlank` | Required. Everything else is optional. |
| `Assert\Email` | `format: email` |
| `Assert\Choice` | `enum` |
| `Assert\Length(max:)` | `maxLength` |

Mark arguments that carry personal data with `#[Sensitive]`; see
[Personal data in tools](https://github.com/neika-ai/sdk#personal-data-in-tools).

**`getDefinition()` must not read constructor arguments.** It runs while the container compiles. Names must match
`^[a-z0-9_]{1,64}$` and be unique across tools; a bad or duplicate name fails the build.

## Choices loaded at runtime

`Assert\Choice` takes a fixed list or a static callback, no services. **When the allowed values live in the
database, use `#[ToolChoice]`:** `Assert\Choice` with the choices from a `ToolChoiceLoaderInterface` service
(autoconfigured). The loaded values become the property's schema `enum`, and Symfony's `ChoiceValidator` checks
every call.

```php
use Neika\Sdk\Contract\ToolChoiceLoaderInterface;
use Neika\Sdk\Validator\ToolChoice;

readonly class RegionChoiceLoader implements ToolChoiceLoaderInterface
{
    public function __construct(
        private RegionRepository $regionRepository,
    ) {
    }

    public function loadChoices(): array
    {
        return $this->regionRepository->findAllNames();
    }
}

readonly class FindByRegionArguments
{
    public function __construct(
        #[ToolChoice(loader: RegionChoiceLoader::class, message: 'The region argument must be one of the listed names.')]
        public ?string $region = null,
    ) {
    }
}
```

- Every `Assert\Choice` option works except `choices`, `callback` and `strict`: `multiple`, `min`, `max`, `match`
  and the messages. Violations carry `Choice`'s codes and parameters.
- Type the property `string`, or `array` with `multiple: true` (published as `items.enum`, plus
  `minItems`/`maxItems`). Any other type fails the tool list.
- The loader is found by its exact class among the autoconfigured loaders.
- Choices are the exact values the tool accepts, the same in every locale, not translated labels. Duplicates are
  dropped.
- An empty list leaves the argument out of the schema, and any value sent then fails validation. A required
  argument (`Assert\NotBlank`) with an empty list makes the tool uncallable.
- **Return an empty list for missing data; throw only for misconfiguration.** A loader that throws breaks both the
  tool list and the call.
- `null` passes. Add `Assert\NotBlank` to make the argument required.

## Add a data source

Implement `ChatbotDataSourceInterface`; it's autoconfigured and named like a tool. `SourceDefinition::$locales = null`
means every locale the channel serves. The query you get and the page you return follow the sdk's
[HTTP contract](https://github.com/neika-ai/sdk#http-contract-what-the-chatbot-backend-expects). `CursorCodec` and
`HtmlToText` are autowired.

## Push catalog changes

**Tell Neika which entries changed so it re-indexes them.** Inject `Neika\Sdk\Ingest\CatalogIngestClient`. It's
wired to your `backend_url` with a 2 s timeout and 5 s max duration. Pass the site from
`CredentialsProviderInterface` on each call:

```php
use Neika\Sdk\DTO\CatalogChange;
use Neika\Sdk\Enum\CatalogSourceName;

$site = $credentialsProvider->findCurrentSite();
$canSend = $site !== null && $site->hasIngestSecret();
if ($canSend) {
    $result = $catalogIngestClient->send($site, new CatalogChange(
        CatalogSourceName::Products,
        'cs_CZ',
        ['CLIPPER-01', 'CLIPPER-02'],
    ));
}
```

> **Neika accepts changes only from an active site.** A draft site gets a 401, so activate it in the dashboard
> first.

Limits, results and retries: see the sdk's [push catalog changes](https://github.com/neika-ai/sdk#outbound-push-catalog-changes).

## Widget

**The bundle doesn't print the widget; it gives you the pieces.** `WidgetSnippet` renders the markup, and the
`widget.*` options are parameters:

```php
use Neika\Sdk\Widget\WidgetSnippet;
use Neika\SymfonyBundle\Contract\CredentialsProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ChatWidget
{
    public function __construct(
        private readonly WidgetSnippet                $widgetSnippet,
        private readonly CredentialsProviderInterface $credentialsProvider,

        #[Autowire(param: 'neika.backend_url')]
        private readonly string $backendUrl,

        #[Autowire(param: 'neika.widget.cdn_url')]
        private readonly string $cdnUrl,

        #[Autowire(param: 'neika.widget.defer')]
        private readonly bool $defer,
    ) {
    }

    public function render(): string
    {
        $site = $this->credentialsProvider->findCurrentSite();
        if ($site === null) {
            return '';
        }

        return $this->widgetSnippet->render($this->backendUrl, $site->siteKey, $this->cdnUrl, '', $this->defer);
    }
}
```

Output:

```html
<script src="https://neika.b-cdn.net/widget/neika-chatbot.js" defer></script>
<neika-chatbot-widget site-key="{site key}" backend-url="{backend_url}"></neika-chatbot-widget>
```

The widget talks only to `backend_url`, never to your `/neika-chatbot/v1`.

## Chat click tracking

**Visits from chat links are reported for you.** Links the chat sends carry `?neikaclid=…`. When such a page
loads with a 2xx, the bundle tells Neika after the response is sent, even when the widget is blocked.

- Reported: the main GET request, not a prefetch, with the page address minus query and fragment.
- `findCurrentSite()` → `null`, an empty ingest secret or an empty `backend_url` → nothing sent, nothing logged.
- `findCurrentSite()` throws → a logged warning, nothing sent.
- Neika unreachable → a logged warning. Your page is never affected.
- A non-https `backend_url` outside `dev` → skipped with a logged warning.

**Report orders yourself.** Keep the `neikaclid` (in the session, say) and call
`Neika\Sdk\Telemetry\TelemetryClient` when the order is placed:

```php
use Neika\Sdk\DTO\ChatOrder;

$site = $credentialsProvider->findCurrentSite();
$canSend = $site !== null && $site->hasIngestSecret();
if ($canSend) {
    $telemetryClient->reportOrder($site, new ChatOrder(
        $clickId,
        'ORDER-1042',
        129900,
        'CZK',
    ));
}
```

Revenue units and errors: see the sdk's [chat click tracking](https://github.com/neika-ai/sdk#chat-click-tracking).

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT, see [LICENSE](LICENSE).
