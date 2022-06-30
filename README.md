# Silverstripe instagram-basic-display-feed-element
Retrieves a Instagram feed and shows it as an dnadesign/silverstripe-elemental-element. It utilizes [espresso-dev/instagram-basic-display-php](https://github.com/espresso-dev/instagram-basic-display-php) and caches the api-response for performance reasons. Since different scrappers led to all sorts of problems - mostly cookie/session relaed, this module came to existence. `appId` & `appSecret` are stored in `yml` and the rotating token is stored in DB. The API is read-only for "public" data anyway.

[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](LICENSE.md)

## Installation
Composer is the recommended way installing Silverstripe modules.
```
composer require lerni/instagram-basic-display-feed-element
```
* Run a `dev/build?flush`

# Requirements
* Silverstripe 4.x
* dnadesign/silverstripe-elemental
* espresso-dev/instagram-basic-display-php 1.x

# Configuration
You'll need to setup a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) for basic display and set `appId` & `appSecret`. `redirectUri` 'll be `DYNAMICALLY-SET-HOST.TLD/_instaauth/` but you can also set it explicit with a domain per yml-config. Make sure to configure the correct values (e.g. also dev-url) in your FB App! If no token is generated yet, you'll find a link to generate one in the setting-tab of the element. The token 'll be renewed automatically (on request basis) if older than 30 days.

1. Install the module
2. Create a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) use `.../_instaauth/` as redirectUri
3. Add `appId` & `appSecret` in yml-config like bellow & `?flush`
4. Create an Instagram Feed Element & click on the Link in the setting-tab to authenticate
5. reload CMS to see the generated token
6. Use it. Token 'll be updated if older than 30 days on request basis. This means if a token is older than 30 day and from there on no html-request is made (element is never shown to any visitor), the token invalidates and a waring is thrown. To fix this you'll need to delete all tokens and regenerate one with the link provided in the CMS.

```yaml
Kraftausdruck\InstagramFeed\Control\InstaAuthController:
  credentials:
    appId: '2598599940246020'
    appSecret: '7e29795bva6d352e3286769ff3a3a836'
    # redirectUri: 'https://example.tld/_instaauth/'
```
# Styling
Example SCSS customisation
```scss
$lh: 1.41;
$white: #fff;

.instafeed {
    margin-left: -1px;
    margin-right: -1px;
    width: calc(100% + 2px);
    a {
        outline: none;
        float: left;
        overflow: hidden;
        position: relative;
        margin: 0 2px 2px 0;
        display: block;
        width: calc(#{math.div(100,4)}% - 2px);
        padding: 0 0 calc(#{math.div(100,4)}% - 2px) 0;
        @include breakpoint($Lneg) {
            width: calc(#{math.div(100,2)}% - 2px);
            padding: 0 0 calc(#{math.div(100,2)}% - 2px) 0;
        }
        @include breakpoint($Sneg) {
            width: calc(#{math.div(100,1)}% - 2px);
            padding: 0 0 calc(#{math.div(100,1)}% - 2px) 0;

        }
        video,
        figure {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            margin: 0;
            img {
                object-fit: cover;
                margin-bottom: 0;
                max-width: none;
                width: 100%;
                height: 100%;
            }
            figcaption {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                opacity: 0;
                transition: opacity 120ms linear;
                z-index: 1;
                color: $white;
                font-size: .8em;
                padding: #{math.div($lh,2)}em;
                display: flex;
                flex-direction: column;
                background-color: rgba(0,0,0,.8);
                .feather-instagram {
                    margin: auto auto 0;
                    color: $white;
                    transition: transform 120ms linear;
                    transition-delay: 80ms;
                    align-self: center;
                    transform: scale(.4);
                    flex-shrink: 0;
                }
            }
            &:hover {
                figcaption {
                    opacity: 1;
                    .feather-instagram {
                        transform: scale(1);
                    }
                }
            }
        }
        video {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            object-fit: cover;
            height: 100%;
            width: 100%;
        }
    }
}
```
# Troubleshooting
If things go wrong you may wanna check [Facebooks Plattformstatus](https://developers.facebook.com/status/dashboard/).

# Todo
* Multiple Members not just `Me`, per hash?
* how is pagination supposed to work?
* may just an extension not an element?
