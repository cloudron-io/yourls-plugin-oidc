# YOURLS plugin: Cloudron OIDC

A [YOURLS](https://yourls.org) plugin that adds login with the [Cloudron](https://cloudron.io) OpenID Connect provider. It is used by the [Cloudron YOURLS package](https://git.cloudron.io/packages/yourls-app).

## Features

- Adds a `Log in with <provider>` button below the YOURLS login form. Users defined in `config.php` can still log in with their password.
- The YOURLS username is taken from the `preferred_username` claim.
- Users that logged in once are stored in the `oidc_users` option. Their auth cookies and API signature tokens keep working without a password entry in `config.php`.

## Requirements

- YOURLS 1.10 or later
- PHP 8.1 or later with the `curl` and `json` extensions
- Composer

## Installation

Download `yourls-oidc-plugin-<version>.zip` from the [releases](https://github.com/cloudron-io/yourls-plugin-oidc/releases) and extract it into `user/plugins/`. The zip includes the Composer dependencies.

Or from source:

```bash
cd user/plugins
git clone https://github.com/cloudron-io/yourls-plugin-oidc.git yourls-oidc-plugin
cd yourls-oidc-plugin
composer install --no-dev
```

Then activate the plugin in `Manage Plugins`.

## Configuration

The plugin reads the environment variables that the Cloudron [oidc addon](https://docs.cloudron.io/packaging/addons/#oidc) provides:

| Variable | Description |
|---|---|
| `CLOUDRON_OIDC_ISSUER` | Issuer URL. The plugin is inactive when this is not set |
| `CLOUDRON_OIDC_CLIENT_ID` | Client ID |
| `CLOUDRON_OIDC_CLIENT_SECRET` | Client secret |
| `CLOUDRON_OIDC_PROVIDER_NAME` | Button label. Defaults to `Cloudron` |

The redirect URI to register with the provider is `<YOURLS_SITE>/admin/?oidc=callback`.

## License

MIT
