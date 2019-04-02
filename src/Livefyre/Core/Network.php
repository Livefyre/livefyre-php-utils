<?php

namespace Livefyre\Core;


use JWT;

use Livefyre\Exceptions\LivefyreException;
use Livefyre\Model\NetworkData;
use Livefyre\Routing\Client;
use Livefyre\Api\Domain;
use Livefyre\Validator\NetworkValidator;

class Network extends Core {
    const ALPHA_DASH_UNDER_DOT_REGEX = "/^[a-zA-Z0-9_\\.-]+$/";
    const DEFAULT_EXPIRES = 86400;
    const DEFAULT_USER = "system";

    private $_data;
    private $_ssl;

    public function __construct(NetworkData $data) {
		$this->_data = $data;
		$this->_ssl = true;
	}

    public static function init($name, $key) {
        $data = new NetworkData($name, $key);
        return new Network(NetworkValidator::validate($data));
    }

	public function setUserSyncUrl($urlTemplate) {
		if (strpos($urlTemplate, "{id}") === false) {
			throw new \InvalidArgumentException("urlTemplate should contain {id}");
		}

		$url = sprintf("%s", Domain::quill($this));
		$data = array("actor_token" => $this->buildLivefyreToken(), "pull_profile_url" => $urlTemplate);
		Client::POST($url, array(), $data);
	}

	public function syncUser($userId) {
		$data = array("lftoken" => $this->buildLivefyreToken());
		$url = sprintf("%s/api/v3_0/user/%s/refresh", Domain::quill($this), $userId);

		Client::POST($url, array(), $data);
		return $this;
	}

	public function buildLivefyreToken() {
		return $this->buildUserAuthToken(self::DEFAULT_USER, self::DEFAULT_USER, self::DEFAULT_EXPIRES);
	}

	public function buildUserAuthToken($userId, $displayName, $expires) {
		if (!preg_match(self::ALPHA_DASH_UNDER_DOT_REGEX, $userId)) {
			throw new \InvalidArgumentException(sprintf("userId is not alphanumeric. Ensure the following regex pattern is respected %s", self::ALPHA_DASH_UNDER_DOT_REGEX));
		}

		$token = array(
		    "domain" => $this->getData()->getName(),
		    "user_id" => $userId,
		    "display_name" => $displayName,
		    "expires" => time() + (int)$expires
		);

		return JWT::encode($token, $this->getData()->getKey());
	}

	public function validateLivefyreToken($lfToken) {
		try {
			$tokenAttributes = JWT::decode($lfToken, $this->getData()->getKey(), array(Core::ENCRYPTION));
		} catch (\Exception $e) {
			if ($e instanceof \DomainException OR $e instanceof \UnexpectedValueException) {
				throw new \InvalidArgumentException("problem with your livefyre jwt", 0, $e);
			} else {
				throw $e;
			}
		}

		return $tokenAttributes->domain == $this->getData()->getName()
			&& $tokenAttributes->user_id == self::DEFAULT_USER
			&& $tokenAttributes->expires >= time();
	}

	public function getSite($siteId, $siteKey) {
		return Site::init($this, $siteId, $siteKey);
	}

	public function getUrn() {
		return "urn:livefyre:" . $this->getData()->getName();
	}

	public function getUrnForUser($user) {
        return $this->getUrn() . ":user=" . $user;
    }

	public function getNetworkName() {
		$nameArray = explode(".", $this->getData()->getName());
		if (count($nameArray) > 1) {
			return $nameArray[0];
		}
		throw new LivefyreException("The network name is not in the correct format: " . $this->getData()->getName());
	}

    public function getData() {
    	return $this->_data;
    }

    public function setData($data) {
    	return $this->_data = $data;
    }

    public function isSsl() {
    	return $this->_ssl;
    }

    public function setSsl($ssl) {
    	$this->_ssl = $ssl;
    }
}
