<?php

namespace shop\system\wcf\authentication;

use wcf\data\user\User;
use wcf\system\user\authentication\UserAuthenticationFactory;
use wcf\util\StringUtil;

class UserHTTPBasicWCFAuthentication extends HTTPBasicWCFAuthentication {
	/**
	 * authenticates user account and returns user object if authentication was sucessfull, otherwise null
	 *
	 * @return	User|null
	 */
	public function authenticate() {
		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			if (isset($_SERVER['HTTP_AUTHORIZATION']) && (strlen($_SERVER['HTTP_AUTHORIZATION']) > 0)) {
				[$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']] = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
				if (strlen($_SERVER['PHP_AUTH_USER']) == 0 || strlen($_SERVER['PHP_AUTH_PW']) == 0) {
					unset($_SERVER['PHP_AUTH_USER']);
					unset($_SERVER['PHP_AUTH_PW']);
				}
			}
		}
		
		if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
			$user = UserAuthenticationFactory::getInstance()->getUserAuthentication()->loginManually(StringUtil::trim($_SERVER['PHP_AUTH_USER']), StringUtil::trim($_SERVER['PHP_AUTH_PW']));
			if ($user !== null && $user->userID) {
				return $user;
			}
		}
		
		return null;
	}
}
