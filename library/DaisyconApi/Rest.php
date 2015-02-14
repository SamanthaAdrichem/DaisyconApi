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

		const REQUEST_POSTGET = 'POSTGET';
		const REQUEST_GET = 'GET';
		const REQUEST_POST = 'POST';
		const REQUEST_PUT = 'PUT';

		protected $sApiBaseUrl = "https://services.daisycon.com";
		protected $sUsername;
		protected $sPassword;

		public function __construct( $sUsername, $sPassword )
		{
			$this->sUsername = $sUsername;
			$this->sPassword = $sPassword;
		}

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

			$sResponse = curl_exec( $rCurlHandler );
			$iResponseCode = curl_getinfo( $rCurlHandler, CURLINFO_HTTP_CODE);
			$oResponse = @json_decode($sResponse);

			$sErrorMessage = isset($oResponse->error) ? $oResponse->error : '';

			curl_close( $rCurlHandler );

			switch ( $iResponseCode )
			{
				case 200:
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
					throw new Exception("Http resonse error " . $iResponseCode . ": " . $sErrorMessage, $iResponseCode);
				}
				break;
			}
		}

	}
