<?php

namespace Kraftausdruck\InstagramFeed\Elements;

use Kraftausdruck\InstagramFeed\Control\InstaAuthController;
use Kraftausdruck\InstagramFeed\Models\InstaAuthObj;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Injector\Injector;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\ORM\ArrayList;
use EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_Base;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\View\ArrayData;

class ElementInstagramFeed extends BaseElement {
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

	private static $defaults = [
		'Limit' => 4
	];

	private static $inline_editable = false;

	public function getCMSFields()
	{
		$fields = parent::getCMSFields();

		$instacredentials = Config::inst()->get(InstaAuthController::class, 'credentials');
		if (!array_key_exists('appId', $instacredentials)) {
			$fields->push(LiteralField::create('no-appId', '<p style="color: red"><strong>appId isn\'t configured!</strong></p>'));
		}
		if (!array_key_exists('appSecret', $instacredentials)) {
			$fields->push(LiteralField::create('no-appSecret', '<p style="color: red"><strong>appSecret isn\'t configured!</strong></p>'));
		}

		if ($TextEditor = $fields->dataFieldByName('HTML')) {
			$TextEditor->setTitle(_t(self::class . 'HTMLFIELDTITLE', 'Text'));
			$TextEditor->setRows(16);
			$TextEditor->setAttribute('data-mce-body-class', $this->getSimpleClassName());
		}

		if ($LimitField = $fields->dataFieldByName('Limit')) {
			$LimitField->setTitle(_t(self::class . 'LIMITFIELDTITLE', 'Limit'));
			$LimitField->setDescription(_t(self::class . 'LIMITFIELDDESCRIPTION','0 = all | default 4'));
		}

		$fields->addFieldToTab('Root.Settings',
			$txtF = TextField::create('redirectUriTEXT', 'redirectUri', InstaAuthController::getAuthControllerRoute())
		);
		$txtF->setReadonly(true);
		$txtF->setDescription(_t(self::class . 'REDIRECTURIFIELDDESCRIPTION','This value needs to be set in your FB-Application!'));

		if (!$this->getLatestToken()) {
			$fields->addFieldToTab('Root.Settings', 
				LiteralField::create('getLoginURL', _t(self::class . 'LOGINURLDESCRIPTION', 'Generate a API token with the link below') . '<br/> <a href="'. $this->getLoginURL() .'" target="_blank" rel="noopener">'. $this->getLoginURL() .'</a><br/>')
			);
		} else {
			$InstaAuthObjGridFieldConfig = GridFieldConfig_Base::create(20);
			$InstaAuthObjGridFieldConfig->addComponents(
				new GridFieldDeleteAction()
			); 
			$gridField = new GridField('InstaAuthObj', _t(self::class . 'INSTAGRAMAUTHTOKENTITLE', 'Instagram Auth Token - latest one \'ll be used'), InstaAuthObj::get()->sort('Created DESC'), $InstaAuthObjGridFieldConfig);
			$gridField->setDescription(_t(self::class . 'INSTAGRAMAUTHTOKENDESCRIPTION', 'You\'ll retrieve a link to generate a new Token if no one is present.'));

			$InstaAuthObjGridFieldConfig->getComponentByType(GridFieldDataColumns::class)->setDisplayFields([
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
		$instacredentials = Config::inst()->get(InstaAuthController::class, 'credentials');

		$redirectUri = InstaAuthController::getAuthControllerRoute();
		$instagram = new InstagramBasicDisplay([
			'appId' => $instacredentials['appId'],
			'appSecret' => $instacredentials['appSecret'],
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
		$latestAuthObj = InstaAuthObj::get()->first();;

		if ($latestAuthObj) {
			$ago = date('Y-m-d H:i:s', strtotime('-30 days'));
			if ($latestAuthObj->Created < $ago) {
				$instagram = $this->InstagramInstance();
				if ($LongLivedToken = $instagram->getLongLivedToken($latestAuthObj->LongLivedToken, true)) {
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
		$this->cache = Injector::inst()->get(CacheInterface::class . '.InstagramCache');
		if (!$this->cache->has('InstagramCache')) {

			$instagram = $this->InstagramInstance();
				
			$this->cache = Injector::inst()->get(CacheInterface::class . '.InstagramCache');
			
			$instagram->setAccessToken($this->getLatestToken());
	
			$media = $instagram->getUserMedia($id = 'me', $this->Limit);
			$mediaArray = json_decode(json_encode($media->data), true); // object2array through json
			$mediaArrayList = ArrayList::create($mediaArray);

			$profile = $instagram->getUserProfile();
			$profileArray = json_decode(json_encode($profile), true); // object2array through json
			$profileArrayData = ArrayData::create($profileArray);


			$r = ArrayData::create([
				'Media' => $mediaArrayList,
				'Profile' => $profileArrayData
			]);

			$this->cache->set('InstagramCache', $r);
		} else {
			$r = $this->cache->get('InstagramCache');
		}
		return $r;
	}

	public function getType()
	{
		return _t(self::class . 'NAME', 'Instagram Feed');
	}
}