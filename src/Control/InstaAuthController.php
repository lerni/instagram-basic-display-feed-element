<?php

namespace Kraftausdruck\InstagramFeed\Control;

use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use EspressoDev\Instagram\Instagram;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;

class InstaAuthController extends Controller
{
    public function index(HTTPRequest $request)
    {
        $instaCredentials = $this->config()->get('credentials') ?: [];

        // Handle Facebook webhook verification
        if ($request->getVar('hub_mode') === 'subscribe') {
            $verificationToken = Environment::getEnv('KRAFT_INSTAFEED_VERIFICATION_TOKEN')
                ?: ($instaCredentials['verificationToken'] ?? null);
            if ($verificationToken && $request->getVar('hub_verify_token') === $verificationToken) {
                return $request->getVar('hub_challenge');
            }

            return $this->httpError(403);
        }

        if (!$request->getVar('code')) {
            return $this->httpError(400, 'Missing OAuth code');
        }

        $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: ($instaCredentials['appId'] ?? null);
        $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET') ?: ($instaCredentials['appSecret'] ?? null);
        if (!$appId || !$appSecret) {
            Injector::inst()->get(LoggerInterface::class)->error('Instagram OAuth: appId/appSecret not configured');

            return $this->httpError(500, 'Instagram credentials not configured');
        }

        $redirectUri = self::getAuthControllerRoute();

        try {
            $instagram = new Instagram([
                'appId' => $appId,
                'appSecret' => $appSecret,
                'redirectUri' => $redirectUri,
            ]);

            $token = $instagram->getOAuthToken($request->getVar('code'), true);
            $longLivedToken = $instagram->getLongLivedToken($token, true);
        } catch (\Exception $e) {
            Injector::inst()->get(LoggerInterface::class)->error('Instagram OAuth failed: ' . $e->getMessage());

            return $this->httpError(500, 'Instagram OAuth failed');
        }

        if (!$longLivedToken) {
            return $this->httpError(500, 'Instagram OAuth: no long-lived token returned');
        }

        $authObj = InstaAuthObj::create();
        $authObj->LongLivedToken = (string) $longLivedToken->access_token;
        $authObj->user_id = (string) $token->user_id;
        $authObj->write();

        $obj = DBHTMLText::create();
        $obj->setValue(_t(self::class . '.CREATEDTOKEN', 'received token!<br/><a href="/home">/home</a>'));

        return [
            'Content' => $obj,
        ];
    }

    public static function getAuthControllerRoute(): string
    {
        // get redirectUri from config or generate dynamically with absoluteURL
        $instaCredentials = Config::inst()->get(self::class, 'credentials') ?: [];

        if (!empty($instaCredentials['redirectUri'])) {
            return $instaCredentials['redirectUri'];
        }

        return Controller::join_links(Director::absoluteBaseURL(), '_instaauth');
    }
}
