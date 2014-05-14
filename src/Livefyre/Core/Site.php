<?php
namespace Livefyre\Core;

use JWT;
use Requests;

class Site {
	private $_networkName;
	private $_siteId;
	private $_siteKey;

	private static $TYPE = array(
		"reviews", "sidenotes", "ratings", "counting", "liveblog", "livechat", "livecomments");

	public function __construct($networkName, $siteId, $siteKey) {
		$this->_networkName = $networkName;
		$this->_siteId = $siteId;
		$this->_siteKey = $siteKey;
	}

	public function buildCollectionMetaToken($title, $articleId, $url, $tags = "", $type = null) {
		if (filter_var($url, FILTER_VALIDATE_URL) === false || 
			preg_match('/[^\x20-\x7f]/', $url)) {
			throw new \InvalidArgumentException("provided url is not a valid url");
		}
		if (strlen($title) > 255) {
			throw new \InvalidArgumentException("title length should be under 255 char");
		}

		$collectionMeta = array(
		    "url" => $url,
		    "tags" => $tags,
		    "title" => $title,
		    "articleId" => $articleId
		);

		if (!empty($type)) {
			if (in_array($type, self::$TYPE)) {
				$collectionMeta["type"] = $type;
			} else {
				throw new \InvalidArgumentException("type is not a recognized type. must be liveblog, livechat, livecomments, reviews, sidenotes, or an empty string");
			}
		}

		return JWT::encode($collectionMeta, $this->_siteKey);
	}

	public function buildChecksum($title, $url, $tags = "") {
		if (filter_var($url, FILTER_VALIDATE_URL) === false || 
			preg_match('/[^\x20-\x7f]/', $url)) {
			throw new \InvalidArgumentException("provided url is not a valid url");
		}
		if (strlen($title) > 255) {
			throw new \InvalidArgumentException("title length should be under 255 char");
		}

		$metaString = sprintf('{"url":"%s","tags":"%s","title":"%s"}', $url, $tags, $title);
		return md5($metaString);
	}

	public function getCollectionContent($articleId) {
		$url = sprintf("http://bootstrap.%s/bs3/%s/%s/%s/init", $this->_networkName, $this->_networkName, $this->_siteId, base64_encode($articleId));
		$response = Requests::get($url);

		return json_decode($response->body);
	}

	public function getCollectionId($articleId) {
		$content = $this->getCollectionContent($articleId);
		return $content->{"collectionSettings"}->{"collectionId"};
	}
}
