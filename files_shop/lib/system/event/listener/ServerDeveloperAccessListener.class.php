<?php

namespace shop\system\event\listener;

use shop\data\customer\Customer;
use shop\data\storage\Storage;
use shop\data\storage\StorageAction;
use shop\data\wcf\package\version\WCFPackageVersion;
use shop\data\wcf\package\WCFPackage;
use shop\data\wcf\package\WCFPackageList;
use shop\page\UpdatePage;
use shop\system\customer\CustomerAccountManager;
use shop\system\wcf\authentication\UserHTTPBasicWCFAuthentication;
use wcf\data\user\User;
use wcf\system\event\listener\IParameterizedEventListener;
use wcf\system\exception\IllegalLinkException;
use wcf\system\WCF;
use wcf\util\FileReader;
use wcf\util\StringUtil;

class ServerDeveloperAccessListener implements IParameterizedEventListener {
	/**
	 * @var User|null
	 */
	protected $user;
	
	/**
	 * @var WCFPackageList
	 */
	protected $packages;
	
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters) {
		/** @var UpdatePage $eventObj */
		
		if ($eventName == 'readData' && $eventObj->customer === null) {
			$auth = new UserHTTPBasicWCFAuthentication();
			$this->user = $auth->authenticate();
			
			if ($this->user !== null) {
				WCF::getSession()->changeUser($this->user);
				CustomerAccountManager::getInstance()->setActiveCustomer(new Customer(null, [
					'customerID' => -1,
					'identifier' => 'DEVELOPER',
					'environment' => SHOP_ENVIRONMENT,
					'userID' => $this->user->userID,
					'nickname' => $this->user->username,
					'company' => 'DEVELOPER @ ' . WCF::getLanguage()->get(PAGE_TITLE),
					'firstname' => '',
					'name' => $this->user->username,
					'email' => $this->user->email,
					'street' => '',
					'zipcode' => '',
					'city' => '',
					'countryCode' => WCF::getLanguage()->countryCode,
					'country' => '',
					'notes' => 'developer access granted by package de.mysterycode.shop.server.accessall',
					'active' => 1,
					'isDeleted' => 0,
					'lastActivity' => TIME_NOW
				]));
				
				if (WCF::getSession()->getPermission('admin.shop.products.grantFullDownloadAccess')) {
					$this->packages = new WCFPackageList();
					$this->packages->setVersionLoading(true);
					$this->packages->setPackageServerID($eventObj->server->serverID);
					$this->packages->readObjects();
					
					$statement = WCF::getDB()->prepareStatement("
						SELECT	v.*, p.*
						FROM	shop1_wcf_package_version v, shop1_wcf_package p
						WHERE	v.packageID = p.packageID
							AND p.packageName = ?
							AND v.name = ?
					");
					$statement->execute([StringUtil::trim($_POST['packageName']), StringUtil::trim($_POST['packageVersion'])]);
					$row = $statement->fetchArray();
					if ($row === false) {
						throw new IllegalLinkException();
					}
					else {
						$version = new WCFPackageVersion(null, $row);
						$package = new WCFPackage(null, $row);
						
						// download original package
						if ($version->path === null) {
							$action = new StorageAction([new Storage($version->storageID)], 'download', ['userID' => WCF::getUser()->userID]);
							$action->executeAction();
							exit;
						// library
						} else {
							$fileReader = new FileReader(SHOP_DIR."storage/server/packages/".$version->path, [
								'filename' => $package->package.".tar",
								'mimeType' => 'application/octet-stream',
								'showInline' => false,
								'enableRangeSupport' => true,
								'expirationDate' => TIME_NOW + 31536000,
								'maxAge' => 31536000
							]);
							
							// send file to client
							$fileReader->send();
							exit;
						}
					}
				}
			}
		}
		else if ($eventName == 'show') {
			if ($this->user !== null && WCF::getSession()->getPermission('admin.shop.products.grantFullDownloadAccess')) {
				$eventObj->packages = $this->packages;
			}
		}
	}
}
