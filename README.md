# Silverstripe instagram-basic-display-feed-element
WIP in the sense of "release often and early" with focus on the later

Retrieves a Instagram feed and shows it as an dnadesign/silverstripe-elemental-element. It utilizes [espresso-dev/instagram-basic-display-php](https://github.com/espresso-dev/instagram-basic-display-php), for performance reasons it caches the feed.

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
You'll need to setup a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) for basic display an set `appId` & `appSecret`, `redirectUri` 'll be DYNAMICALLY-SET-HOST.TLD/_instaauth/ but you can also set it explicit with a domain per yml-config. Make sure to configure the correct values (e.g. also dev-url) in your FB App! If no token is present you'll see a link to generate on under settings in the element. The token 'll be renewed automatically (on request basis) if older than 30 days.
```yaml
Kraftausdruck\InstagramFeed\Control\InstaAuthController:
 credentials:
  appId: '2598599940246020'
  appSecret: '7e29795bva6d352e3286769ff3a3a836'
# redirectUri: 'https://example.tld/_instaauth/'
```

# Todo
* Multiple Members not just `Me`, per hash?
* how is pagination supposed to work?
* may just an extension not an element?