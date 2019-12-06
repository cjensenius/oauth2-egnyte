# Egnyte Provider for OAuth 2.0 Client

This package provides Egnyte OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

To install, use composer:

```
composer require cjensenius/oauth2-egnyte
```

## Usage

Usage is the same as The League's OAuth client, using `\cjensenius\OAuth2\Client\Provider\Egnyte` as the provider.

### Authorization Code Flow

```php
    $provider = new \cjensenius\OAuth2\Client\Provider\Egnyte([    
        'domain' => 'mydomain',
        'clientId' => 'myClientId',
        'clientSecret' => 'myClientSecret',
        'redirectUri' => 'https://redirect.url/oauth'
    ]);
    
    // If we don't have an authorization code then get one
    if (!isset($_GET['code'])) {
    
        // Fetch the authorization URL from the provider; this returns the
        // urlAuthorize option and generates and applies any necessary parameters
        // (e.g. state).
        $authorizationUrl = $provider->getAuthorizationUrl();
    
        // Get the state generated for you and store it to the session.
        $_SESSION['oauth2state'] = $provider->getState();
    
        // Redirect the user to the authorization URL.
        header('Location: ' . $authorizationUrl);
        exit;
    
    // Check given state against previously stored one to mitigate CSRF attack
    } elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
    
        if (isset($_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
        }
        
        exit('Invalid state');
    
    } else {
    
        try {
    
            // Try to get an access token using the authorization code grant.
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);
    
            // We have an access token, which we may use in authenticated
            // requests against the service provider's API.
            echo 'Access Token: ' . $accessToken->getToken() . "<br>";
            echo 'Refresh Token: ' . $accessToken->getRefreshToken() . "<br>";
            echo 'Expired in: ' . $accessToken->getExpires() . "<br>";
            echo 'Already expired? ' . ($accessToken->hasExpired() ? 'expired' : 'not expired') . "<br>";
    
            // Using the access token, we may look up details about the
            // resource owner.
            $resourceOwner = $provider->getResourceOwner($accessToken);
    
            var_export($resourceOwner->toArray());
    
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
    
            // Failed to get the access token or user details.
            exit($e->getMessage());
    
        }
    
    }
```

## Testing

``` bash
$ ./vendor/bin/phpunit
```

## Credits

- [Chris Jensen](https://github.com/cjensenius)


## License

The MIT License (MIT). Please see [License File](https://github.com/cjensenius/oauth2-egnyte/blob/master/LICENSE) for more information.
