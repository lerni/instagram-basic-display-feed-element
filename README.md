# Silverstripe instagram-basic-display-feed-element
WIP in the sense of "release often and early" with focus on the later

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
You'll need to setup a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) for basic display and set `appId` & `appSecret`, `redirectUri` 'll be `DYNAMICALLY-SET-HOST.TLD/_instaauth/`. You also can set it explicit with a domain per yml-config. Make sure to configure the correct values (e.g. also dev-url) in your FB App! If no token is generated yet, you'll find a link to generate one it the setting-tab of the element. The token 'll be renewed automatically (on request basis) if older than 30 days.

1. Install the module
2. Create a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) use `.../_instaauth/` as redirectUri
3. Add `appId` & `appSecret` to yml & `?flush`
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

# Todo
* handle video
* Multiple Members not just `Me`, per hash?
* how is pagination supposed to work?
* may just an extension not an element?