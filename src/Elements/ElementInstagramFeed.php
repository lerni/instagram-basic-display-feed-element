<?php

namespace Kraftausdruck\InstagramFeed\Elements;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Forms\TextField;
use SilverStripe\Model\ArrayData;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Forms\HeaderField;
use EspressoDev\Instagram\Instagram;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\GridField\GridField;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use Kraftausdruck\InstagramFeed\Control\InstaAuthController;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

class ElementInstagramFeed extends BaseElement implements Flushable
{
    private static $db = [
        'HTML' => 'HTMLText',
        'Limit' => 'Int',
    ];
    private static $has_one = [];
    private static $has_many = [];
    private static $many_many = [];

    private static $owns = [];

    private static $table_name = 'ElementInstagramFeed';

    private static $title = 'Instagram Feed Element';

    private static $icon = 'font-icon-block-instagram';

    private static $defaults = [
        'Limit' => 4,
    ];

    private static $inline_editable = false;

    private static $refresh_token_just_in_live_env = true;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $instaCredentials = [];

        if (Config::inst()->exists(InstaAuthController::class, 'credentials')) {
            $instaCredentials = Config::inst()->get(InstaAuthController::class, 'credentials');
        }
        $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: ($instaCredentials['appId'] ?? null);
        $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET') ?: ($instaCredentials['appSecret'] ?? null);

        $missing = [];
        if (!$appId) {
            $missing[] = 'appId | KRAFT_INSTAFEED_APP_ID';
        }
        if (!$appSecret) {
            $missing[] = 'appSecret | KRAFT_INSTAFEED_APP_SECRET';
        }
        if (count($missing)) {
            $missing = implode(' & ', $missing);
            $message = _t(self::class . '.APIValuesMissing', 'API: {missing} are missing.', ['missing' => $missing]);
            $fields->unshift(
                LiteralField::create(
                    'APIValuesMissing',
                    sprintf(
                        '<p class="alert alert-warning">%s</p>',
                        $message,
                    ),
                ),
            );
        }

        if ($TextEditor = $fields->dataFieldByName('HTML')) {
            $TextEditor->setTitle(_t(self::class . '.HTMLFIELDTITLE', 'Text'));
            $TextEditor->setRows(16);
        }

        if ($LimitField = $fields->dataFieldByName('Limit')) {
            $LimitField->setTitle(_t(self::class . '.LIMITFIELDTITLE', 'Limit'));
            $LimitField->setDescription(_t(self::class . '.LIMITFIELDDESCRIPTION', '0 = all | default 4'));
        }

        $verificationToken = Environment::getEnv('KRAFT_INSTAFEED_VERIFICATION_TOKEN') ?: ($instaCredentials['verificationToken'] ?? null);
        $fields->addFieldsToTab('Root.Settings', [
            HeaderField::create('InstagramAPI', 'Instagram API'),
            $redirectUriField = TextField::create('redirectUriTEXT', 'redirectUri', InstaAuthController::getAuthControllerRoute())->setReadonly(true),
            $verificationTokenField = TextField::create('verificationTokenTEXT', 'verificationToken', $verificationToken)->setReadonly(true),
        ]);

        $redirectUriField->setDescription(_t(self::class . '.REDIRECTURIFIELDDESCRIPTION', 'This URL must be deposited in the FB application!'));
        $verificationTokenField->setDescription(_t(self::class . '.VERIFICATIONTOKENFIELDDESCRIPTION', 'This token must be deposited in the FB application as the webhook verification token.'));

        if (!$this->getLatestToken()) {
            $instagram = $this->InstagramInstance();

            $fields->addFieldToTab(
                'Root.Settings',
                LiteralField::create('getLoginURL', _t(self::class . '.LOGINURLDESCRIPTION', 'Generate a API token with the link below') . '<br/> <a href="' . $instagram->getLoginUrl() . '" target="_blank" rel="noopener">' . $this->getLoginURL() . '</a><br/>'),
            );
        }

        $InstaAuthObjGridFieldConfig = GridFieldConfig_Base::create(20);
        // $InstaAuthObjGridFieldConfig = GridFieldConfig_RecordEditor::create();
        $InstaAuthObjGridFieldConfig->addComponents(
            new GridFieldDeleteAction(),
        );
        $InstaAuthObjGridFieldConfig->removeComponentsByType(GridFieldFilterHeader::class);
        $gridField = GridField::create('InstaAuthObj', _t(self::class . '.INSTAGRAMAUTHTOKENTITLE', 'Instagram Auth Token - latest one \'ll be used'), InstaAuthObj::get()->sort('LastEdited DESC'), $InstaAuthObjGridFieldConfig);
        $gridField->setDescription(_t(self::class . '.INSTAGRAMAUTHTOKENDESCRIPTION', 'You\'ll retrieve a link to generate a new Token if no one is present.'));

        $InstaAuthObjGridFieldConfig->getComponentByType(GridFieldDataColumns::class)->setDisplayFields([
            'user_id' => 'User ID',
            'Created' => 'Created',
            'LastEdited' => 'Updated',
            'LongLivedToken.LimitCharacters' => '60 days token',
        ]);

        $fields->addFieldToTab('Root.Settings', $gridField);

        return $fields;
    }

    private function InstagramInstance(): Instagram
    {
        $instaCredentials = [];
        if (Config::inst()->exists(InstaAuthController::class, 'credentials')) {
            $instaCredentials = Config::inst()->get(InstaAuthController::class, 'credentials');
        }

        $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: ($instaCredentials['appId'] ?? '');
        $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET') ?: ($instaCredentials['appSecret'] ?? '');
        $redirectUri = InstaAuthController::getAuthControllerRoute();

        return new Instagram([
            'appId' => $appId,
            'appSecret' => $appSecret,
            'redirectUri' => $redirectUri,
        ]);
    }

    public function getLoginURL(): string
    {
        $instagram = $this->InstagramInstance();
        $scopes = (array) Config::inst()->get(InstaAuthController::class, 'scopes');

        return $instagram->getLoginUrl($scopes);
    }

    private function getLatestToken(): ?string
    {
        $latestAuthObj = InstaAuthObj::get()->first();
        if (!$latestAuthObj) {
            return null;
        }

        $agoSoft = date('Y-m-d H:i:s', strtotime('-30 days'));
        $agoHard = date('Y-m-d H:i:s', strtotime('-60 days'));
        $longLivedToken = $latestAuthObj->LongLivedToken;

        if ($latestAuthObj->LastEdited < $agoSoft) {
            if ($latestAuthObj->LastEdited < $agoHard) {
                Injector::inst()->get(LoggerInterface::class)->info('Instagram token expired!');
            } else {
                // Check if token refresh should only happen in live environment
                $refreshTokenJustInLive = $this->config()->get('refresh_token_just_in_live_env');
                $shouldRefresh = !$refreshTokenJustInLive || Director::isLive();
                if ($shouldRefresh) {
                    try {
                        $instagram = $this->InstagramInstance();
                        $instagram->setAccessToken($longLivedToken);
                        $refreshedToken = $instagram->refreshLongLivedToken($longLivedToken, true);
                        $latestAuthObj->LongLivedToken = $longLivedToken = $refreshedToken->access_token;
                        $latestAuthObj->write();
                        Injector::inst()->get(LoggerInterface::class)->info('Instagram token refreshed successfully');
                    } catch (Exception $e) {
                        Injector::inst()->get(LoggerInterface::class)->error('Failed to refresh Instagram token: ' . $e->getMessage());

                        return null;
                    }
                } else {
                    Injector::inst()->get(LoggerInterface::class)->info('Instagram token refresh skipped (not in live environment)');
                }
            }
        }

        return $longLivedToken;
    }

    public function getInstagramFeed(): ArrayData
    {
        $cacheKey = crc32(implode([$this->ID, $this->LastEdited, InstaAuthObj::get()->max('LastEdited')]));
        $cache = Injector::inst()->get(CacheInterface::class . '.InstagramCache');

        $r = ArrayData::create();

        if (!$cache->has($cacheKey)) {

            $instagram = $this->InstagramInstance();

            if ($LatestToken = $this->getLatestToken()) {
                try {
                    $instagram->setAccessToken($LatestToken);
                    $media = $instagram->getUserMedia($id = 'me', $this->Limit);

                    $mediaArrayList = ArrayList::create();
                    if (property_exists($media, 'data')) {
                        foreach ($media->data as $mediaItem) {

                            $mediaObjt = ArrayData::create();

                            foreach ($mediaItem as $key => $value) {
                                if (is_string($key) && is_string($value)) {
                                    $mediaObjt->{$key} = $value;
                                }
                            }

                            if (property_exists($mediaItem, 'children') && count($mediaItem->children->data)) {
                                $mediaChildrenArray = json_decode(json_encode($mediaItem->children->data), true); // object2array through json
                                $mediaChildrenArrayList = ArrayList::create($mediaChildrenArray);
                                $mediaObjt->Children = $mediaChildrenArrayList;
                            }
                            $mediaArrayList->push($mediaObjt);
                        }

                        $profile = $instagram->getUserProfile();
                        $profileArray = json_decode(json_encode($profile), true); // object2array through json
                        $profileArrayData = ArrayData::create($profileArray);

                        $r->Media = $mediaArrayList;
                        $r->Profile = $profileArrayData;
                    } else {
                        Injector::inst()->get(LoggerInterface::class)->info('unexpected Instagram-API response!' . json_encode($media));
                        $cacheKey = $this->errorCacheKey();
                    }
                } catch (Exception $e) {
                    Injector::inst()->get(LoggerInterface::class)->error('Instagram API call failed: ' . $e->getMessage());
                    $cacheKey = $this->errorCacheKey();
                }
                $cache->set($cacheKey, $r);
            } else {
                Injector::inst()->get(LoggerInterface::class)->info('No valid Instagram token available');
                $cacheKey = $this->errorCacheKey();
                $cache->set($cacheKey, $r);
            }
        } else {
            $r = $cache->get($cacheKey);
        }

        return $r;
    }

    public static function flush(): void
    {
        Injector::inst()->get(CacheInterface::class . '.InstagramCache')->clear();
    }

    public function getType(): string
    {
        return _t(self::class . '.NAME', 'Instagram Feed');
    }

    // short cache lifetime on unexpected response,
    // prevents API hammering on every request
    public function errorCacheKey(): int
    {
        // Returns a new number every x minutes
        return (int)(time() / 60 / 3);
    }
}
