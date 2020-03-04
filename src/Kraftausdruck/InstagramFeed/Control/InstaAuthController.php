<?php

namespace Kraftausdruck\InstagramFeed\Control;

use SilverStripe\Control\Controller;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;
use SilverStripe\Control\HTTPRequest;
use EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\FieldType\DBHTMLText;

class InstaAuthController extends Controller
{
    public function index(HTTPRequest $request)
    {
        // parse referer to check if XY.instagram.com is calling
        $ref = $request->getHeaders();
        $ref = parse_url($ref['referer']);
        $ref = $ref['host'];
        $hostName = explode(".", $ref);
        $mainDomainName = $hostName[count($hostName)-2] . "." . $hostName[count($hostName)-1];

        if ($request->getVar('code') && $mainDomainName == 'instagram.com') {
            $AuthObj = InstaAuthObj::create();
            $redirectUri = $this->getAuthControllerRoute();

            $instacredentials = $this->config()->get('credentials');
            $instagram = new InstagramBasicDisplay([
                'appId' => $instacredentials['appId'],
                'appSecret' => $instacredentials['appSecret'],
                'redirectUri' => $redirectUri
            ]);

            $token = $instagram->getOAuthToken($request->getVar('code'), true);

            if ($LongLivedToken = $instagram->getLongLivedToken($token, true)) {
                $AuthObj->LongLivedToken = $LongLivedToken;
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

		if (array_key_exists('redirectUri', $instacredentials)) {
			$url = $instacredentials['redirectUri'];
		} else {
            $url = Controller::join_links(Director::absoluteBaseURL() , '_instaauth/');
		}
		return $url;
	}
}