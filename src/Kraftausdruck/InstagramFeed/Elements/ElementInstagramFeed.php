<?php

namespace Kraftausdruck\InstagramFeed\Elements;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Flushable;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Environment;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Forms\HeaderField;
use EspressoDev\Instagram\Instagram;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Core\Injector\Injector;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\GridField\GridField;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use Kraftausdruck\InstagramFeed\Control\InstaAuthController;

class ElementInstagramFeed extends BaseElement implements Flushable
{
    private static $db = [
        'HTML' => 'HTMLText',
        'Limit' => 'Int'
    ];
    private static $has_one = [];
    private static $has_many = [];
    private static $many_many = [];

    private static $owns = [];

    private static $table_name = 'ElementInstagramFeed';

    private static $title = 'Instagram Feed Element';

    private static $icon = 'font-icon-block-instagram';

    private static $defaults = [
        'Limit' => 4
    ];

    private static $inline_editable = false;

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $instacredentials = [];

        if (Config::inst()->exists(InstaAuthController::class, 'credentials')) {
            $instaCredentials = Config::inst()->get(InstaAuthController::class, 'credentials');
        }
        $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: ($instaCredentials['appId'] ?? null);
        $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET')?: ($instaCredentials['appSecret'] ?? null);

        $missing = [];
        if (!$appId) {
            $missing[] = 'appId | KRAFT_INSTAFEED_APP_ID';
        }
        if (!$appSecret) {
            $missing[] = 'appSecret | KRAFT_INSTAFEED_APP_SECRET';
        }
        if (count($missing) ) {
            $missing = implode(' & ', $missing);
            $message = _t(__CLASS__ . '.APIValuesMissing', 'API: {missing} are missing.', ['missing' => $missing]);
            $fields->unshift(
                LiteralField::create(
                    'HeroNeeded',
                    sprintf(
                        '<p class="alert alert-warning">%s</p>',
                        $message
                    )
                )
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

        $fields->addFieldsToTab('Root.Settings', [
            HeaderField::create('InstagramAPI', 'Instagram API'),
            $redirectUriField = TextField::create('redirectUriTEXT', 'redirectUri', InstaAuthController::getAuthControllerRoute())
        ]);
        $redirectUriField->setReadonly(true);
        $redirectUriField->setDescription(_t(self::class . '.REDIRECTURIFIELDDESCRIPTION', 'This URL must be deposited in the FB application!'));

        if (!$this->getLatestToken()) {
            $fields->addFieldToTab(
                'Root.Settings',
                LiteralField::create('getLoginURL', _t(self::class . '.LOGINURLDESCRIPTION', 'Generate a API token with the link below') . '<br/> <a href="' . $this->getLoginURL() . '" target="_blank" rel="noopener">' . $this->getLoginURL() . '</a><br/>')
            );
        } else {
            $InstaAuthObjGridFieldConfig = GridFieldConfig_Base::create(20);
            $InstaAuthObjGridFieldConfig->addComponents(
                new GridFieldDeleteAction()
            );
            $gridField = new GridField('InstaAuthObj', _t(self::class . '.INSTAGRAMAUTHTOKENTITLE', 'Instagram Auth Token - latest one \'ll be used'), InstaAuthObj::get()->sort('LastEdited DESC'), $InstaAuthObjGridFieldConfig);
            $gridField->setDescription(_t(self::class . '.INSTAGRAMAUTHTOKENDESCRIPTION', 'You\'ll retrieve a link to generate a new Token if no one is present.'));

            $InstaAuthObjGridFieldConfig->getComponentByType(GridFieldDataColumns::class)->setDisplayFields([
                'user_id' => 'User ID',
                'Created' => 'Crated',
                'LastEdited' => 'Updated',
                'LongLivedToken.LimitCharacters' => '60 days token'
            ]);

            $fields->addFieldToTab('Root.Settings', $gridField);
        }

        return $fields;
    }

    private function InstagramInstance()
    {
        $instacredentials = [];
        if (Config::inst()->exists(InstaAuthController::class, 'credentials')) {
            $instacredentials = Config::inst()->get(InstaAuthController::class, 'credentials');
        }

        $appId = Environment::getEnv('KRAFT_INSTAFEED_APP_ID') ?: $instacredentials['appId'];
        $appSecret = Environment::getEnv('KRAFT_INSTAFEED_APP_SECRET') ?: $instacredentials['appSecret'];
        $redirectUri = InstaAuthController::getAuthControllerRoute();
        $instagram = new Instagram([
            'appId' => $appId,
            'appSecret' => $appSecret,
            'redirectUri' => $redirectUri
        ]);
        return $instagram;
    }

    public function getLoginURL()
    {
        $instagram = $this->InstagramInstance();
        return $instagram->getLoginUrl();
    }

    private function getLatestToken()
    {
        $latestAuthObj = InstaAuthObj::get()->first();

        if ($latestAuthObj) {
            $agoSoft = date('Y-m-d H:i:s', strtotime('-30 days'));
            $agoHard = date('Y-m-d H:i:s', strtotime('-60 days'));
            if ($latestAuthObj->LastEdited < $agoSoft) {
                $instagram = $this->InstagramInstance();
                if ($latestAuthObj->LastEdited < $agoHard) {
                    Injector::inst()->get(LoggerInterface::class)->info('Instagram token expired!');
                } elseif ($LongLivedToken = $instagram->refreshLongLivedToken($latestAuthObj->LongLivedToken, true)) {
                    $latestAuthObj->LongLivedToken = $LongLivedToken;
                    $latestAuthObj->write();
                }
            } else {
                $LongLivedToken = $latestAuthObj->LongLivedToken;
            }
            return $LongLivedToken;
        }
    }

    public function getInstagramFeed()
    {
        $cacheKey = crc32(implode([$this->ID, $this->LastEdited, InstaAuthObj::get()->max('LastEdited')]));
        $this->cache = Injector::inst()->get(CacheInterface::class . '.InstagramCache');

        $r = ArrayData::create();

        if (!$this->cache->has($cacheKey)) {

            $instagram = $this->InstagramInstance();

            if ($LatestToken = $this->getLatestToken()) {
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
                $this->cache->set($cacheKey, $r);
            }
        } else {
            $r = $this->cache->get($cacheKey);
        }

        return $r;
    }

    public static function flush()
    {
        Injector::inst()->get(CacheInterface::class . '.InstagramCache')->clear();
    }

    public function getType()
    {
        return _t(self::class . '.NAME', 'Instagram Feed');
    }

    // short cache lifetime on unexpected response,
    // prevents API hammering on every request
    public function errorCacheKey()
    {
        // Returns a new number every x minutes
        return (int)(time() / 60 / 3);
    }
}
