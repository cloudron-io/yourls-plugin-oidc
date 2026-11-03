[1.0.0]
* Initial version
* Log in with the Cloudron OIDC provider, using the `preferred_username` claim as the YOURLS username
* Users are stored in the `oidc_users` option so auth cookies and API signature tokens work without a `config.php` entry
