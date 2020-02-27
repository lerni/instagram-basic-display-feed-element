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
use SilverStripe\View\ArrayData;

class ElementInstagramFeed extends BaseElement {
	private static $db = [
		'HTML' => 'HTMLText',
		'Limit' => 'Int'
	];
	private static $has_one = [];
	private static $has_many = [];
	private static $many_many = [];

	private static $field_labels = [
		'HTML' => 'Text',
		'Limit' => 'Limit (default = 4)'
	];

	private static $owns = [];

	private static $table_name = 'ElementInstagramFeed';

	private static $title = 'Instagram Feed Element';

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
			$TextEditor->setRows(16);
			$TextEditor->setAttribute('data-mce-body-class', $this->getSimpleClassName());
		}

		if ($LimitField = $fields->dataFieldByName('Limit')) {
			$LimitField->setDescription('0 = alle resp. default 4');
		}

		$fields->addFieldToTab('Root.Settings', 
			LiteralField::create('redirectUri', 'redirectUri: ' . InstaAuthController::getAuthControllerRoute() . '<br/>')
		);

		if (!$this->getLatestToken()) {
			$fields->addFieldToTab('Root.Settings', 
				LiteralField::create('getLoginURL', 'LoginURL: <a href="'. $this->getLoginURL() .'" target="_blank" rel="noopener">'. $this->getLoginURL() .'</a><br/>')
			);
		} else {
			$InstaAuthObjGridFieldConfig = GridFieldConfig_Base::create(20);
			$InstaAuthObjGridFieldConfig->addComponents(
				new GridFieldDeleteAction()
			); 
			$gridField = new GridField('InstaAuthObj', 'current Auth Object - latest one \'ll be used', InstaAuthObj::get()->sort('Created DESC'), $InstaAuthObjGridFieldConfig);

			$InstaAuthObjGridFieldConfig->getComponentByType(GridFieldDataColumns::class)->setDisplayFields([
				'Created' => 'Crated',
				'ShortLivedToken.LimitCharacters' => 'Short living token',
				'LongLivedToken.LimitCharacters' => '60days token'
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
		return _t('ElementNameInstagramFeed', 'Instagram Feed');
	}
}