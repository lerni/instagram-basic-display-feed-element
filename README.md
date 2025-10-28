# Silverstripe instagram-basic-display-feed-element
Instagram feed in a dnadesign/silverstripe-elemental-element. It utilizes [espresso-dev/instagram-php](https://github.com/espresso-dev/instagram-php) and caches the API-response for performance reasons. Since different scrapers lead to all sorts of problems - mostly cookie/session related, this module came to existence. `appId` & `appSecret` are stored in `yml`-config or `.env`, the rotating token in DB.

**As of December 2024 https://github.com/espresso-dev/instagram-php is used and not https://github.com/espresso-dev/instagram-basic-display-php anymore. `instagram_business_basic` scope is in use. A business- or creator-account is needed for API-access. ATM this only works with dev-master or the 6.x brach which is for SS 6.x.**

[![License](https://img.shields.io/badge/License-BSD%203--Clause-blue.svg)](LICENSE.md)

![Instagram feed module screenshot](docs/images/lippundleuthold.webp)
Example in action from <a href="https://lippundleuthold.ch/info/#instagram" target="_blank">Lipp&Leuthold</a>


# Installation
Composer is the recommended way installing Silverstripe modules.
```
composer require lerni/instagram-basic-display-feed-element
```
* Run a `dev/build`

## Requirements
* Silverstripe 5.x
* dnadesign/silverstripe-elemental
* espresso-dev/instagram-php 1.x

## Configuration
You'll need to setup a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) for basic display and set `appId` & `appSecret` ([Instagram not FB](https://stackoverflow.com/questions/60258144/invalid-platform-app-error-using-instagram-basic-display-api)). `redirectUri` will be `DYNAMICALLY-SET-HOST.TLD/_instaauth` but you can also set it explicit with a domain per yml-config. Make sure to configure the correct values (e.g. dev-url) in your FB App! If no token is generated yet, you'll find a link to generate one in the setting-tab of the element. The token 'll be renewed automatically (on request basis) if older than 30 days.

1. Install the module
2. Create a [FB App](https://developers.facebook.com/docs/instagram-basic-display-api/getting-started/) use `https://DOMAIN.TLD/_instaauth` as redirectUri
    - create an Instagram app per other services (Other -> Business) on https://developers.facebook.com
        - add Instagram to the App

        Sometimes you just can't - try reloading, different browser or do some other black magic :shrug:
    - add Instagram-Tester (Roles add -> Instagram-Tester)
    - Login on Instagram & accept/confirm
        - Settings
            - Website Permissions
                - Apps and Websites
                    - Accept Tester Invitations
3. Add `appId` & `appSecret` in yml-config or `.env` like below & `?flush`
    - make sure to use those from the "Instagram", not "App-Settings"!
4. Create an Instagram Feed Element & click on the link in the setting-tab to authenticate
    - <em>**dev-master** create a token in developers.facebook.com and add it manually to the element under settings, since currently creating one via link throws: "Invalid request: Request parameters are invalid: Invalid redirect_uri". May it just works in live mode?</em>
5. Reload CMS to see the generated token
6. That's it. Token 'll be updated if older than 30 days on request basis. This means, if a token is older than 30 days and from there on no request is made (element never shown to any visitor), the token invalidates and a warning is thrown. To "fix" this, you'll need to delete all tokens and regenerate one with the link provided in CMS.

```yaml
Kraftausdruck\InstagramFeed\Control\InstaAuthController:
  credentials:
    appId: '2598599940246020'
    appSecret: '7e29795bva6d352e3286769ff3a3a836'
    verificationToken: 'SetThisToSomethingRandom'
    # redirectUri: 'https://example.tld/_instaauth'
```
```.env
KRAFT_INSTAFEED_APP_ID='2598599940246020'
KRAFT_INSTAFEED_APP_SECRET='7e29795bva6d352e3286769ff3a3a836'
KRAFT_INSTAFEED_VERIFICATION_TOKEN='SetThisToSomethingRandom'
```

# Styling
Example styling with text as hover overlay.
<details>
<summary>CSS with a bit PostCSS magic</summary>


```css
.instafeed {
	display: flex;
	flex-wrap: wrap;
	margin-left: -1px;
	margin-right: -1px;
	width: calc(100% + 2px);
	a {
		outline: none;
		overflow: hidden;
		position: relative;
		display: block;
		width: auto;
		height: 500px;
		@media (max-width: 980px) {
			height: 400px;
		}
		@media (max-width: 480px) {
			height: 300px;
		}
		figure {
			height: 100%;
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
				inset: 0;
				opacity: 0;
				transition: opacity 120ms linear;
				z-index: 1;
				color: var(--white);
				font-size: var(--font-size--small);
				padding: calc(var(--lh) * 1em);
				display: flex;
				flex-direction: column;
				background-color: rgba(0,0,0,.8);
				span[data-icon="instagram"] {
					transition: transform 120ms linear;
					transform: scale(.4);
					width: calc(var(--lh) * 1em);
					height: calc(var(--lh) * 1em);
					background-image: svg-load("instagram.svg", stroke=#fff);
					margin: auto auto 0 auto;
				}
			}
            @media (hover: hover) {
                &:hover {
                    figcaption {
                        opacity: 1;
                        span[data-icon="instagram"] {
                            transform: scale(1);
                        }
                    }
                }
            }
		}
		video {
			height: 100%;
			width: 100%;
		}
        @media (hover: none) {
            &:focus,
            &:focus-within,
            &:active {
                figure figcaption {
                    pointer-events: none;
                    opacity: 1;
                    span[data-icon="instagram"] {
                        transform: scale(1);
                    }
                }
            }
        }
	}
}
```
</details>

# Troubleshooting
If things go wrong, you may want to check [Facebook Platform Status](https://metastatus.com/).

