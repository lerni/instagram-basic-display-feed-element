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
        $ref = $request->getHeaders();
        $ref = parse_url($ref['referer']);
        $ref = $ref['host'];
        $hostName = explode(".", $ref);
        $mainDomainName = $hostName[count($hostName) - 2] . "." . $hostName[count($hostName) - 1];

        if ($request->getVar('code') && $mainDomainName == 'instagram.com') {
            $AuthObj = InstaAuthObj::create();
            $redirectUri = $this->getAuthControllerRoute();

            $instacredentials = $this->config()->get('credentials');
            $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: $instacredentials['appId'];
            $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET') ?: $instacredentials['appSecret'];

            $instagram = new Instagram([
                'appId' => $appId,
                'appSecret' => $appSecret,
                'redirectUri' => $redirectUri
            ]);

            $token = $instagram->getOAuthToken($request->getVar('code'), false);
            $LongLivedToken = $instagram->getLongLivedToken($token->access_token, true);

            if ($LongLivedToken) {
                $AuthObj->LongLivedToken = $LongLivedToken->access_token;
                $AuthObj->user_id = $token->user_id;
                $AuthObj->write();
                $obj = DBHTMLText::create();
                $obj->setValue(_t(self::class . '.CREATEDTOKEN', 'received token!<br/><a href="/home">/home</a>'));
                return [
                    'Content' => $obj
                ];
            }
        } else {
            return $this->httpError(404);
        }
    }

    public static function getAuthControllerRoute()
    {
        // get redirectUri from config or generate dynamically with absoluteURL
        $instacredentials = Config::inst()->get(InstaAuthController::class, 'credentials');

        if ($instacredentials && array_key_exists('redirectUri', $instacredentials)) {
            $url = $instacredentials['redirectUri'];
        } else {
            $url = Controller::join_links(Director::absoluteBaseURL(), '_instaauth/');
        }
        return $url;
    }
}
