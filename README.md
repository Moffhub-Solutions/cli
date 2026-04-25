# Moffhub CLI

Command-line companion for the **Moffhub Payment Standard**. Scaffold a new connector, validate its manifest, and run the certification suite — without bringing any of it into your application code.

The CLI is connector-agnostic: it works with any class that implements `Moffhub\MpsSpec\Contracts\ConnectorInterface`, regardless of who wrote it.

## Installation

### Global (recommended for connector authors)

```bash
composer global require moffhub/cli
```

Make sure Composer's global bin directory is on your `PATH`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"      # bash/zsh
# or, on newer setups:
export PATH="$HOME/.config/composer/vendor/bin:$PATH"
```

Verify:

```bash
moffhub --version
```

### Project-local (when you want it pinned per-repo)

```bash
composer require --dev moffhub/cli
./vendor/bin/moffhub --version
```

## Commands

### `moffhub init-connector <name> [namespace]`

Scaffolds a new connector package in the current directory.

```bash
moffhub init-connector acme-payments "Acme\\Connector\\Acme"
cd acme-payments
composer install
```

You get a `composer.json`, a starter connector class extending `BaseConnector`, and a `tests/` directory.

### `moffhub validate <connector-class>`

Loads the class, calls `manifest()`, and checks that all required fields are populated and well-typed.

```bash
moffhub validate "Acme\\Connector\\Acme\\AcmeConnector"
```

Prints a table of manifest fields and exits non-zero if anything is missing.

### `moffhub certify <connector-class> [--config=path] [--sandbox]`

Runs the full certification suite: spec compliance, lifecycle, capability conformance (charge/refund/webhook/settlement), error handling, and idempotency.

```bash
# Sandbox mode — skips real API calls, validates contract only
moffhub certify "Acme\\Connector\\Acme\\AcmeConnector" --sandbox

# Live mode — exercises the real provider with credentials from a JSON file
moffhub certify "Acme\\Connector\\Acme\\AcmeConnector" --config=./credentials.json
```

`credentials.json` is whatever your connector expects in `initialize()`:

```json
{
    "api_key": "sk_test_...",
    "environment": "sandbox"
}
```

Exits `0` on PASS, `1` if any test fails. Wire this into CI on your connector's repo.

## Resolving connector classes

The CLI needs to be able to autoload your connector. There are three ways it finds your code:

1. **Run from your project directory.** If `./vendor/autoload.php` exists in the current working directory, the CLI loads it automatically. This is the common case.

2. **Pass an explicit autoloader with `--bootstrap`.**
   ```bash
   moffhub certify "Acme\\AcmeConnector" --bootstrap=/path/to/project/vendor/autoload.php
   ```

3. **Install your connector globally** alongside the CLI:
   ```bash
   composer global require vendor/connector-acme
   moffhub certify "Acme\\AcmeConnector"
   ```

If the CLI prints `Class ... not found`, one of the above is missing.

## Typical workflow for connector authors

```bash
# 1. Scaffold
moffhub init-connector my-gateway "MyVendor\\Connector\\MyGateway"
cd my-gateway && composer install

# 2. Implement createCharge(), queryCharge(), etc.

# 3. Validate the manifest as you go
moffhub validate "MyVendor\\Connector\\MyGateway\\MyGatewayConnector"

# 4. Run the certification suite in sandbox mode (CI-friendly)
moffhub certify "MyVendor\\Connector\\MyGateway\\MyGatewayConnector" --sandbox

# 5. Run against real credentials before tagging a release
moffhub certify "MyVendor\\Connector\\MyGateway\\MyGatewayConnector" --config=secrets.json
```

## Related packages

- [`moffhub/mps-spec`](https://packagist.org/packages/moffhub/mps-spec) — interfaces, DTOs, and enums the CLI checks against.
- [`moffhub/connector-sdk`](https://packagist.org/packages/moffhub/connector-sdk) — base classes that make implementing a connector straightforward.

## License

MIT. See `LICENSE`.
