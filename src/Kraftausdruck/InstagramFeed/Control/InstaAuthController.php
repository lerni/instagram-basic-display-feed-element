<?php

namespace Kraftausdruck\InstagramFeed\Control;

use SilverStripe\Control\Controller;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;
use SilverStripe\Control\HTTPRequest;
use EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;

class InstaAuthController extends Controller
{
    public function index(HTTPRequest $request)
    {
        if ($ShortAuthCode = $request->getVar('code')) {
            $AuthObj = InstaAuthObj::create();
            $redirectUri = $this->getAuthControllerRoute();

            $instacredentials = $this->config()->get('credentials');
            $instagram = new InstagramBasicDisplay([
                'appId' => $instacredentials['appId'],
                'appSecret' => $instacredentials['appSecret'],
                'redirectUri' => $redirectUri
            ]);

            $token = $instagram->getOAuthToken($ShortAuthCode, true);

            if ($LongLivedToken = $instagram->getLongLivedToken($token, true)) {
                $AuthObj->LongLivedToken = $LongLivedToken;
                $AuthObj->write();
                return [
                    'Content' => _t(self::class . '.CREATEDTOKEN', 'received token!<br/><a href="/home">/home</a>')
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