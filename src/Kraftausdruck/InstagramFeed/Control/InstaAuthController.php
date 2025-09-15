<?php

namespace Kraftausdruck\InstagramFeed\Control;

use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use EspressoDev\Instagram\Instagram;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;

class InstaAuthController extends Controller
{
    public function index(HTTPRequest $request)
    {
        // parse referer to check if XY.instagram.com is calling
        // $ref = $request->getHeaders();
        // $ref = parse_url($ref['referer']);
        // $ref = $ref['host'];
        // $hostName = explode(".", $ref);
        // $mainDomainName = $hostName[count($hostName) - 2] . "." . $hostName[count($hostName) - 1];

        // if ($request->getVar('code') && $mainDomainName == 'instagram.com') {

            $instaCredentials = $this->config()->get('credentials');

            // Handle Facebook webhook verification
            if ($request->getVar('hub_mode') === 'subscribe') {
                $verificationToken = Environment::getEnv('KRAFT_INSTAFEED_VERIFICATION_TOKEN') ?: $instaCredentials['verificationToken'];
                if ($request->getVar('hub_verify_token') === $verificationToken) {
                    return $request->getVar('hub_challenge');
                } else {
                    return $this->httpError(403);
                }
            }

            $AuthObj = InstaAuthObj::create();
            $redirectUri = $this->getAuthControllerRoute();

            $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: $instaCredentials['appId'];
            $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET') ?: $instaCredentials['appSecret'];

            $instagram = new Instagram([
                'appId' => $appId,
                'appSecret' => $appSecret,
                'redirectUri' => $redirectUri
            ]);

            $token = $instagram->getOAuthToken($request->getVar('code'), true);
            $LongLivedToken = $instagram->getLongLivedToken($token, true);

            if ($LongLivedToken) {
                $AuthObj->LongLivedToken = $LongLivedToken->access_token;
                $AuthObj->user_id = $token->user_id;
                $AuthObj->write();
                $obj = DBHTMLText::create();
                $obj->setValue(_t(__CLASS__ . '.CREATEDTOKEN', 'received token!<br/><a href="/home">/home</a>'));
                return [
                    'Content' => $obj
                ];
            }
        // } else {
        //     return $this->httpError(404);
        // }
    }

    public static function getAuthControllerRoute(): string
    {
        // get redirectUri from config or generate dynamically with absoluteURL
        $instaCredentials = Config::inst()->get(InstaAuthController::class, 'credentials');

        if ($instaCredentials && array_key_exists('redirectUri', $instaCredentials)) {
            $url = $instaCredentials['redirectUri'];
        } else {
            $url = Controller::join_links(Director::absoluteBaseURL(), '_instaauth');
        }
        return $url;
    }
}
