	require_once __DIR__ . "/daisycon_rest_api.php";

	/**
	 * Basic get example
	 */
	$oApiCall = new DaisyconRestApi( "USERNAME", "PASSWORD" );
	foreach ($oApiCall->getAdvertiserIds() as $iAdvertiserId)
	{
		$aFilterData = array(
			'start' => "2014-10-01", 
			'end' => "2014-10-01" 
		);
		$sUrl = '/advertisers/' . $iAdvertiserId . '/transactions';
		foreach ($oApiCall->performCall($sUrl, DaisyconRestApi::REQUEST_GET, $aFilterData) as $oTransaction)
		{
			var_dump( $oTransaction );break; // stop @ 1
		}
	}

	/**
	 * Basic put example
	 */
	$oApiCall = new DaisyconRestApi( "USERNAME", "PASSWORD" );
	foreach ($oApiCall->getAdvertiserIds() as $iAdvertiserId)
	{
		$aPutData = array(
			'commission' => 12.00,
			'status' => 'disapproved',
			'disapproved_reason' => 'duplicate',
		);
		$sUrl = '/advertisers/' . $iAdvertiserId . '/transactions/95AFBVUK48AFBLU6V3/7K12';
		$oResult = $oApiCall->performCall($sUrl, DaisyconRestApi::REQUEST_PUT, $aPutData);
		var_dump($oResult);
	}

	/**
	 * Magic Call not all features supported
	 */
	$oApiCall = new DaisyconRestApi( "USERNAME", "PASSWORD" );
	foreach ($oApiCall->getPublisherIds() as $iPublisherId)
	{
		$aFilterData = array(
			'start' => "2014-10-01",
			'end' => "2014-10-01"
		);
		foreach( $oApiCall->getPublishersTransactions( $iPublisherId, $aFilterData ) as $oTransaction )
		{
			var_dump( $oTransaction );break; // stop @ 1
		}
	}


		

