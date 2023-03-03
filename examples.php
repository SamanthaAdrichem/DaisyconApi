<?php

use SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException;
use SamanthaAdrichem\DaisyconApi\Exception\Http\HttpException;
use SamanthaAdrichem\DaisyconApi\MethodEnum;
use SamanthaAdrichem\DaisyconApi\RestClient;

// Setup client

$restClient = new RestClient(
	'myClientId',
	'myClientSecret'
);

// Fetch my publisher account
var_dump($restClient->getPublishers());

// Fetch my publisher Ids
var_dump($restClient->getPublisherIds());

// Fetch my advertiser account
var_dump($restClient->getAdvertisers());

// Fetch my advertiser Ids
var_dump($restClient->getAdvertiserIds());

// Fetch my lead generation account
var_dump($restClient->getLeadGeneration());

// Fetch my lead generation Ids
var_dump($restClient->getLeadGenerationIds());

// Catch errors
try
{
	$restClient->performCall('/some/non/existing/url');
}
// There are specific types, you can catch per type
catch (NotFoundException $exception)
{
	var_dump($exception->getMessage());
}
// Or just catch all HttpExceptions
catch (HttpException $exception)
{
	var_dump($exception->getMessage());
}
// Or just all exceptions
catch (Exception $exception)
{
	var_dump($exception->getMessage());
}

// You can also check the last response code
var_dump($restClient->getLastResponseCode());

// Get the response headers
$responseHeaders = [];
$restClient->performCall(
	'/some/existing/url',
	MethodEnum::Get,
	[],
	$responseHeaders
);
var_dump($responseHeaders);

// Enable sandbox mode
$restClient->enableSandboxMode();
