[1.0.0]
* Initial version
* Log in with the Cloudron OIDC provider, using the `preferred_username` claim as the YOURLS username
* Users are stored in the `oidc_users` option so auth cookies and API signature tokens work without a `config.php` entry

[1.1.0]
* Show the OIDC login button above the local login form
* Hide the local login form behind a "Log in with local account" link. It stays open after a failed local login and without JavaScript

[1.2.0]
* Restyle the login page with the Cloudron colors, including dark mode and mobile layout
* Hide the "Please log in" notice on a normal visit
* Show an error when the OIDC login fails
