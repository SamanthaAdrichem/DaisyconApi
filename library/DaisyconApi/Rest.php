<?php

	namespace DaisyconApi;

	use Exception;

	/**
	 * Small Daisycon rest api class
	 * Simple calls, just check the url in the documentation on https://developers.daisycon.com
	 *  start with your request method (get, put, post)
	 *  ignore the "publisher id or advertiser id" in the url
	 *  camelcase the path, and add the "advertiser id or publisher id" as the first parameter
	 *  add filter data as an array in the second paramter
	 *  for example /advertisers/{advertiser_id}/transactions
	 *  becomes ->getAdvertisersTransactions( ADVERTISERID, array(FILTER_FIELD => VALUE) );
	 */
	class Rest
	{

		/**
		 * @var string perform get call using post
		 */
		const REQUEST_POSTGET = 'POSTGET';

		/**
		 * @var string perform get call
		 */
		const REQUEST_GET = 'GET';

		/**
		 * @var string perform post call
		 */
		const REQUEST_POST = 'POST';

		/**
		 * @var string perform put call
		 */
		const REQUEST_PUT = 'PUT';

		/**
		 * @var string api base url
		 */
		protected $sApiBaseUrl = "https://services.daisycon.com";

		/**
		 * @var string api username
		 */
		protected $sUsername;

		/**
		 * @var string api password
		 */
		protected $sPassword;

		/**
		 * @var array last response headers
		 */
		protected $aResponseHeaders = array();

		/**
		 * @var integer last response code
		 */
		protected $iLastResponseCode = 0;

		/**
		 * Constructor function
		 * @param string $sUsername
		 * @param string $sPassword
		 */
		public function __construct( $sUsername, $sPassword )
		{
			$this->sUsername = $sUsername;
			$this->sPassword = $sPassword;
		}

		/**
		 * Magic __call function that allows for partial pathwise api calls for example
		 * getPublishersMedia( publisher_id, filter )
		 * getPublishersMedia( publisher_id, media_id, filter )
		 * getPublishersMedia( publisher_id, media_id, 'subscriptions', filter )
		 * @param string $sFunctionName
		 * @param array $aArguments
		 * @return \DaisyconApi\Rest::performCall
		 */
		public function __call( $sFunctionName, $aArguments )
		{
			$aPath = array();
			preg_match_all('/((?:^|[A-Z])[a-z]+)/', $sFunctionName, $aPath);
			$aPath = array_map("strtolower", $aPath[0]);
			$eRequestType = strtoupper( array_shift($aPath) );
			if (false === in_array( $eRequestType, array( self::REQUEST_GET, self::REQUEST_POST, self::REQUEST_PUT ) ) )
			{
				throw new Exception("Unknown request type: " . $eRequestType );
			}
			if (count( $aPath ) > 1 && true === in_array( $aPath[0], array("advertisers", "publishers") ) )
			{
				if (count($aArguments) < 1 || (int) $aArguments[0] < 1)
				{
					throw new Exception("Advertiser or Publisher service requires an ID as first param");
				}
				$aPathTmp = array(
					array_shift( $aPath ),
					array_shift( $aArguments )
				);
				$aPath = array_merge( $aPathTmp , $aPath );
			}
			while (sizeof($aArguments) > 1)
			{
				$aPath[] = array_shift($aArguments);
			}
			$sRequestUrl = "/" . implode("/", $aPath );
			$aData = isset($aArguments[0]) ? $aArguments[0] : array();
			return $this->performCall( $sRequestUrl, $eRequestType, $aData );
		}

		/**
		 * Retrieve all your connected publishers
		 * @return array
		 */
		public function getPublishers()
		{
			try
			{
				return $this->performCall( "/publishers" );
			}
			catch (Exception $oException)
			{
				if ($oException->getCode() != 204)
				{
					throw $oException;
				}
			}
			return array();
		}

		/**
		 * Retrieve all your connected publisher ids
		 * @return array
		 */
		public function getPublisherIds()
		{
			$oPublishers = $this->getPublishers();
			$aPublisherIds = array();
			foreach ($oPublishers as $oPublisher)
			{
				$aPublisherIds[] = $oPublisher->id;
			}
			return $aPublisherIds;
		}

		/**
		 * Retrieve all your connected advertisers
		 * @return array
		 */
		public function getAdvertisers()
		{
			try
			{
				return $this->performCall( "/advertisers" );
			}
			catch (Exception $oException)
			{
				if ($oException->getCode() != 204)
				{
					throw $oException;
				}
			}
			return array();
		}

		/**
		 * Retrieve all your connected advertiser ids
		 * @return array
		 */
		public function getAdvertiserIds()
		{
			$oAdvertisers = $this->getAdvertisers();
			$aAdvertiserIds = array();
			foreach ($oAdvertisers as $oAdvertiser)
			{
				$aAdvertiserIds[] = $oAdvertiser->id;
			}
			return $aAdvertiserIds;
		}

		/**
		 * Handles response headers, only returns custom response headers starting with X-, can be overloaded
		 * @param resource $rCurlHandler cURL handle
		 * @param string $sHeaderLine
		 * @return integer
		 */
		protected function handleResponseHeaders( $rCurlHandler, $sHeaderLine )
		{
			@list($sHeader, $sHeaderContent) = explode(": ", $sHeaderLine);
			if (false !== strpos($sHeader, 'X-'))
			{
				$this->aResponseHeaders[ $sHeader ] = trim($sHeaderContent);
			}
			return strlen($sHeaderLine);
		}

		/**
		 * Returns the response headers of the last call
		 * @return array
		 */
		public function getResponseHeaders()
		{
			return $this->aResponseHeaders;
		}

		/**
		 * Returns the response code of the last call 0 if none
		 * @return integer
		 */
		public function getLastResponseCode()
		{
			return (int) $this->iLastResponseCode;
		}

		/**
		 * Performs a cURL call to the Daisycon Api
		 * @param string $sRequestUrl
		 * @param constant $eRequestType
		 * @param array $aData
		 * @throws \Exception if response header isn't valid
		 * @return null|\stdClass
		 */
		public function performCall( $sRequestUrl, $eRequestType = self::REQUEST_GET, $aData = array() )
		{
			if (false === stripos( $sRequestUrl, $this->sApiBaseUrl ))
			{
				$sRequestUrl =  $this->sApiBaseUrl . $sRequestUrl;
			}
			$rCurlHandler = curl_init();
			$aHeaders = array( 
				'Authorization: Basic ' . base64_encode( $this->sUsername . ':' . $this->sPassword ) ,
			);
			if ( self::REQUEST_GET === $eRequestType && strlen(serialize($aData)) <= 3000)
			{
				$sChar = false === stripos($sRequestUrl, "?") ? "?" : "&";
				foreach ($aData as $sKey => $mValue)
				{
					$sRequestUrl .= $sChar . $sKey . "=" . urlencode( $mValue );
					$sChar = "&";
				}
			}
			else
			{
				curl_setopt( $rCurlHandler, CURLOPT_POST, true);
				if ( self::REQUEST_PUT == $eRequestType )
				{
					curl_setopt( $rCurlHandler, CURLOPT_CUSTOMREQUEST, "PUT");
				}
				if ( true === in_array($eRequestType, array(self::REQUEST_POSTGET, self::REQUEST_GET)) )
				{
					$aHeaders[] = 'X-HTTP-Method-Override: GET';
					curl_setopt( $rCurlHandler, CURLOPT_POSTFIELDS, $aData );
				}
				else
				{
					curl_setopt( $rCurlHandler, CURLOPT_POSTFIELDS, json_encode($aData) );
				}
			}

			curl_setopt( $rCurlHandler, CURLOPT_URL, $sRequestUrl);
			curl_setopt( $rCurlHandler, CURLOPT_HTTPHEADER, $aHeaders);
			curl_setopt( $rCurlHandler, CURLOPT_RETURNTRANSFER, true);
			curl_setopt( $rCurlHandler, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt( $rCurlHandler, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt( $rCurlHandler, CURLOPT_HEADERFUNCTION, array(&$this, 'handleResponseHeaders'));

			$sResponse = curl_exec( $rCurlHandler );
			$this->iLastResponseCode = curl_getinfo( $rCurlHandler, CURLINFO_HTTP_CODE);
			$oResponse = @json_decode($sResponse);

			$sErrorMessage = isset($oResponse->error) ? $oResponse->error : '';

			curl_close( $rCurlHandler );

			switch ( $this->iLastResponseCode )
			{
				case 200: // OK
				case 201: // Created
				{
					return $oResponse;
				}
				break;

				case 204:
				{
					throw new Exception("204 No content: " . $sErrorMessage, 204);
				}
				break;

				case 404:
				{
					throw new Exception("404 Service not found: " . $sErrorMessage, 404);
				}
				break;

				case 403:
				{
					throw new Exception("403 Forbidden: " . $sErrorMessage, 403);
				}
				break;

				default:
				{
					throw new Exception("Http resonse error " . $this->iLastResponseCode . ": " . $sErrorMessage, $this->iLastResponseCode);
				}
				break;
			}
		}

	}
