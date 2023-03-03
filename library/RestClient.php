<?php

namespace SamanthaAdrichem\DaisyconApi;

use CurlHandle;
use Firebase\JWT\JWT;
use JetBrains\PhpStorm\NoReturn;
use RuntimeException;
use SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException;
use SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException;
use SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException;
use SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException;
use SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException;
use stdClass;
use function array_map;
use function array_shift;
use function base64_decode;
use function count;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function explode;
use function file_exists;
use function file_get_contents;
use function http_build_query;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;
use function preg_match_all;
use function serialize;
use function str_starts_with;
use function stripos;
use function strlen;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Small Daisycon rest api class
 * Simple calls, just check the url in the documentation on https://developers.daisycon.com
 *  start with your request method (get, put, post)
 *  ignore the 'publisher id or advertiser id' in the url
 *  camelcase the path, and add the 'advertiser id or publisher id' as the first parameter
 *  add filter data as an array in the second paramter
 *  for example /advertisers/{advertiser_id}/transactions
 *  becomes ->getAdvertisersTransactions( ADVERTISERID, array(FILTER_FIELD => VALUE) );
 */
class RestClient
{

	/**
	 * Safe offset for token refresh
	 *
	 * @const int
	 */
	private const TOKEN_SAFE_OFFSET = 300;

	/**
	 * The API baseUrl
	 *
	 * @const string
	 */
	private const API_BASE_URL = 'https://services.daisycon.com';

	/**
	 * The API baseUrl for sandbox mode, sandbox mode performs all API calls, but update queries are not executed
	 *
	 * @const string
	 */
	private const API_BASE_URL_SANDBOX = 'https://services.daisycon.com';

	/**
	 * Max URL length that is safe to use when performing get calls
	 *
	 * @const int
	 */
	private const SAFE_MAX_URL_LENGTH_WITHOUT_PATH = 3000;

	/**
	 * Sandbox mode
	 *
	 * @var bool
	 */
	private bool $sandbox = false;

	/**
	 * Response code of last call
	 *
	 * @var int|null
	 */
	private ?int $lastResponseCode;

	/**
	 * Constructor of the class, class uses oAuth
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $redirectUri
	 * @param string $accessTokenFile
	 */
	public function __construct(
		private readonly string $clientId,
		private readonly string $clientSecret,
		private readonly string $redirectUri = 'https://login.daisycon.com/oauth/cli',
		private readonly string $accessTokenFile = 'daisycon-api-token.json'
	)
	{
	}

	/**
	 * Returns the last API calls response code
	 *
	 * @return int|null
	 */
	public function getLastResponseCode(): ?int
	{
		return $this->lastResponseCode;
	}

	/**
	 * Enable sandbox mode
	 *
	 * @return void
	 */
	public function enableSandboxMode(): void
	{
		$this->sandbox = true;
	}

	/**
	 * Magic __call function that allows for partial pathwise api calls for example
	 * getPublishersMedia( publisher_id, filter )
	 * getPublishersMedia( publisher_id, media_id, filter )
	 * getPublishersMedia( publisher_id, media_id, 'subscriptions', filter )
	 *
	 * @param string $functionName
	 * @param array $functionArguments
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return \stdClass|array|null
	 * @see \SamanthaAdrichem\DaisyconApi\RestClient::performCall
	 */
	public function __call(string $functionName, array $functionArguments): stdClass|array|null
	{
		$requestPath = [];
		preg_match_all('/((?:^|[A-Z])[a-z]+)/', $functionName, $requestPath);
		$requestPath = array_map(callback: fn(string $chunk) => strtolower($chunk), array: $requestPath[0]);

		$requestMethod = MethodEnum::toEnum(strtoupper(array_shift($requestPath)));
		if (null === $requestMethod)
		{
			throw new RuntimeException("Unknown request method: ${$requestMethod}");
		}

		if (count($requestPath) > 1 && true === in_array($requestPath[0], ['advertisers', 'publishers', 'leadgeneration']))
		{
			if (count($functionArguments) < 1 || (int)$functionArguments[0] < 1)
			{
				throw new RuntimeException('Advertiser, LeadGeneration or Publisher service requires an ID as first param');
			}

			$temporaryPath = [
				array_shift($requestPath),
				array_shift($functionArguments),
			];

			$requestPath = [
				...$temporaryPath,
				...$requestPath
			];
		}

		while (sizeof($functionArguments) > 1)
		{
			$requestPath[] = array_shift($functionArguments);
		}

		$requestUrl = '/' . implode('/', $requestPath);
		$aData = $functionArguments[0] ?? [];

		return $this->performCall($requestUrl, $requestMethod, $aData);
	}

	/**
	 * Retrieve all your connected publishers
	 *
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return array
	 */
	public function getPublishers(): array
	{
		$publishers = $this->performCall('/publishers');
		return false === empty($publishers)
			? $publishers
			: [];
	}

	/**
	 * Retrieve all your connected publisher ids
	 *
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return array
	 */
	public function getPublisherIds(): array
	{
		$publishers = $this->getPublishers();
		return array_map(fn(stdClass $publisher) => $publisher->id, $publishers);
	}

	/**
	 * Retrieve all your connected advertisers
	 *
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return array
	 */
	public function getAdvertisers(): array
	{
		$advertisers = $this->performCall('/advertisers');
		return false === empty($advertisers)
			? $advertisers
			: [];
	}

	/**
	 * Retrieve all your connected advertiser ids
	 *
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return array
	 */
	public function getAdvertiserIds(): array
	{
		$advertisers = $this->getAdvertisers();
		return array_map(fn(stdClass $advertiser) => $advertiser->id, $advertisers);
	}

	/**
	 * Retrieve all your connected lead generation accounts
	 *
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return array
	 */
	public function getLeadGeneration(): array
	{
		$accounts = $this->performCall('/leadgeneration');
		return false === empty($accounts)
			? $accounts
			: [];
	}

	/**
	 * Retrieve all your connected lead generation account ids
	 *
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return array
	 */
	public function getLeadGenerationIds(): array
	{
		$accounts = $this->getLeadGeneration();
		return array_map(fn(stdClass $account) => $account->id, $accounts);
	}

	/**
	 * Starts oAuth authentication if needed, returns valid token
	 *
	 * @return string
	 */
	private function getAuthenticationToken(): string
	{
		$accessTokenFile = $this->getRootPath() . $this->accessTokenFile;

		$tokens = true === file_exists($accessTokenFile)
			? json_decode(json_decode(file_get_contents($accessTokenFile), true), true)
			: null;

		[,$payload,] = [
			...explode('.', $tokens['access_token'] ?? ''),
			'',
			''
		];
		$decodedToken = json_decode(base64_decode($payload), true);

		if (true === empty($decodedToken))
		{
			$this->startAuthenticationHandshake();
		}

		if (($decodedToken['exp'] ?? 0) - self::TOKEN_SAFE_OFFSET < time())
		{
			try
			{
				$tokens = $this->refreshTokens($tokens['refresh_token'] ?? '');
			}
			catch (\Exception $exception)
			{
				echo 'Could not refresh tokens, deleting tokens: ' . $exception->getMessage(), PHP_EOL;
				unlink($accessTokenFile);
			}
		}
		return $tokens['access_token'];
	}

	private function refreshTokens(string $refreshToken): array
	{
		$responseHeaders = [];
		$tokens = $this->performCall(
			'https://login.daisycon.com/oauth/access-token',
			MethodEnum::Post,
			[
				'grant_type'    => 'refresh_token',
				'redirect_uri'  => $this->redirectUri,
				'client_id'     => $this->clientId,
				'client_secret' => $this->clientSecret,
				'refresh_token' => $refreshToken,
			],
			$responseHeaders,
			true
		);
		file_put_contents($this->getRootPath() . $this->accessTokenFile, json_encode($tokens));
		return $tokens;
	}

	private function getRootPath(): string
	{
		$path = __DIR__ . '/../';
		if (false !== str_contains($path, 'vendor/SamanthaAdrichem'))
		{
			$path .= '../../../';
		}
		return $path;
	}

	/**
	 * Start authentication challenge
	 *
	 * @return void
	 */
	#[NoReturn] public function startAuthenticationHandshake(): void
	{
		$path = $this->getRootPath();

		if ($this->isCli())
		{
			$accessTokenFile = $path . $this->accessTokenFile;

			$cliScript = $path . 'vendor/daisyconbv/oauth-examples/PHP/cli-client.php';
			echo 'Please run:', PHP_EOL, PHP_EOL,
				"php {$cliScript} --clientId=\"{$this->clientId}\" --clientSecret=\"{$this->clientSecret}\" --redirectUri=\"{$this->redirectUri}\" --outputFile=\"{$accessTokenFile}\"",
				PHP_EOL, PHP_EOL;
			exit;
		}

		// Start: PKCE challenge request
		session_start();

		require_once $path . 'vendor/daisyconbv/oauth-examples/PHP/functions.php';
		require_once $path . 'vendor/daisyconbv/oauth-examples/PHP/pkce.php';

		$pkce = new \Pkce();
		$_SESSION['code_verifier'] = $pkce->getCodeVerifier();

		$params = [
			'client_id'      => $this->clientId,
			'response_type'  => 'code',
			'redirect_uri'   => $this->redirectUri,
			'code_challenge' => $pkce->getCodeChallenge()
		];

		$authorizeUri = 'https://login.daisycon.com/oauth/authorize?' . http_build_query($params);
		header('Location: ' . $authorizeUri);
		exit;
	}

	/**
	 * Handle response from web request and write tokens to file
	 *
	 * @param string|null $code
	 * @return void
	 */
	private function handleAuthenticationHandshakeResponse(?string $code = null): void
	{
		$accessTokenFile = $this->getRootPath() . $this->accessTokenFile;

		session_start();
		$response = httpPost(
			'https://login.daisycon.com/oauth/access-token',
			[
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $this->redirectUri,
				'client_id'     => $this->clientId,
				'client_secret' => $this->clientSecret,
				'code'          => $code ?? $_GET['code'],
				'code_verifier' => $_SESSION['code_verifier'],
			]
		);
		file_put_contents($accessTokenFile, json_encode($response));
	}

	/**
	 * Returns whether this is a CLI client
	 *
	 * @return bool
	 */
	private function isCli(): bool
	{
		return php_sapi_name() === 'cli';
	}

	/**
	 * Performs a cURL call to the Daisycon Api
	 *
	 * @param string $requestUrl
	 * @param \SamanthaAdrichem\DaisyconApi\MethodEnum $requestMethod
	 * @param array $data
	 * @param array $responseHeaders
	 * @param bool $skipAuthHeader
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return \stdClass|array|null
	 *
	 * @see \SamanthaAdrichem\DaisyconApi\RestClient::handleResponse
	 */
	public function performCall(
		string $requestUrl,
		MethodEnum $requestMethod = MethodEnum::Get,
		array $data = [],
		array &$responseHeaders = [],
		bool $skipAuthHeader = false
	): stdClass|array|null
	{
		$requestUrl = $this->appendBaseUrl($requestUrl);
		$curlHandler = curl_init();

		$requestHeaders = [
			'Content-Type: application/json',
		];

		if (false === $skipAuthHeader)
		{
			$token = $this->getAuthenticationToken();
			$requestHeaders[] = "Authorization: Bearer {$token}";
		}

		if ($requestMethod === MethodEnum::Get && strlen(serialize($data)) <= self::SAFE_MAX_URL_LENGTH_WITHOUT_PATH)
		{
			$requestMethod = MethodEnum::GetAsPost;
		}

		switch ($requestMethod)
		{
			case MethodEnum::Get:
				$paramSeparator = false === stripos($requestUrl, '?') ? '?' : '&';
				$requestUrl .= $paramSeparator . http_build_query($data);
				break;

			case MethodEnum::Delete:
				curl_setopt($curlHandler, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;

			case MethodEnum::Put:
				curl_setopt($curlHandler, CURLOPT_CUSTOMREQUEST, 'PUT');
				// fallthrough

			default:
				curl_setopt($curlHandler, CURLOPT_POST, true);

				if ($requestMethod ===MethodEnum::GetAsPost)
				{
					$requestHeaders[] = 'X-HTTP-Method-Override: GET';
				}

				if (true === isset($data['body']))
				{
					$data['body'] = json_encode($data['body']);
				}

				curl_setopt($curlHandler, CURLOPT_POSTFIELDS, $data);
				break;
		}

		curl_setopt($curlHandler, CURLOPT_URL, $requestUrl);
		curl_setopt($curlHandler, CURLOPT_HTTPHEADER, $requestHeaders);
		curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
		if (true === isset($_SERVER['AT_DEV']))
		{
			curl_setopt($curlHandler, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, 0);
		}

		curl_setopt(
			$curlHandler,
			CURLOPT_HEADERFUNCTION,
			function ($curlHandler, $responseHeader) use (&$responseHeaders) {
				@list($headerName, $headerValue) = explode(': ', $responseHeader);
				if (true === str_starts_with($headerName, 'X-'))
				{
					$responseHeaders[$headerName] = trim($headerValue);
				}
				return strlen($responseHeader);
			}
		);

		$response = curl_exec($curlHandler);
		$returnResponse = $this->handleResponse($curlHandler, $response);

		curl_close($curlHandler);

		return $returnResponse;
	}

	/**
	 * Appends the base URL to the URI, because we do not know wheter or not you are using sandbox correctly we strip it first
	 *
	 * @param string $requestUrl
	 * @return string
	 */
	private function appendBaseUrl(string $requestUrl): string
	{
		if (true === str_starts_with('https://', $requestUrl))
		{
			return $requestUrl;
		}
		return ($this->sandbox ? self::API_BASE_URL_SANDBOX : self::API_BASE_URL)
			. $requestUrl;
	}

	/**
	 * Handles response so that it can be extended
	 *
	 * @param \CurlHandle $curlHandler cURL handle
	 * @param string $response
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\BadRequestException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\ForbiddenException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\InternalServerErrorException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\NotFoundException
	 * @throws \SamanthaAdrichem\DaisyconApi\Exception\Http\UnsupportedHttpException
	 * @return \stdClass|array|null
	 */
	protected function handleResponse(CurlHandle $curlHandler, string $response): stdClass|array|null
	{
		$this->lastResponseCode = curl_getinfo($curlHandler, CURLINFO_HTTP_CODE);
		$decodedResponse = @json_decode($response);

		$errorMessage = $decodedResponse->error ?? '';

		switch ($this->lastResponseCode)
		{
			case HttpResponseCodes::HTTP_RESPONSE_CODE_OK:
			case HttpResponseCodes::HTTP_RESPONSE_CODE_CREATED:
			case HttpResponseCodes::HTTP_RESPONSE_CODE_ACCEPTED:
			case HttpResponseCodes::HTTP_RESPONSE_CODE_NO_CONTENT:
				return $decodedResponse;

			case HttpResponseCodes::HTTP_RESPONSE_CODE_BAD_REQUEST:
				throw new BadRequestException("Bad request:  {$errorMessage}", HttpResponseCodes::HTTP_RESPONSE_CODE_BAD_REQUEST);

			case HttpResponseCodes::HTTP_RESPONSE_CODE_FORBIDDEN:
				throw new ForbiddenException("Forbidden:  {$errorMessage}", HttpResponseCodes::HTTP_RESPONSE_CODE_FORBIDDEN);

			case HttpResponseCodes::HTTP_RESPONSE_CODE_NOT_FOUND:
				throw new NotFoundException("Not found:  {$errorMessage}", HttpResponseCodes::HTTP_RESPONSE_CODE_NOT_FOUND);

			case HttpResponseCodes::HTTP_RESPONSE_CODE_INTERNAL_SERVER_ERROR:
				throw new InternalServerErrorException("Internal server error:  {$errorMessage}", HttpResponseCodes::HTTP_RESPONSE_CODE_INTERNAL_SERVER_ERROR);

			case HttpResponseCodes::HTTP_RESPONSE_CODE_SERVICE_UNAVAILABLE:
				throw new InternalServerErrorException("Service unavailable:  {$errorMessage}", HttpResponseCodes::HTTP_RESPONSE_CODE_SERVICE_UNAVAILABLE);

			default:
				throw new UnsupportedHttpException("Unsupported http error {$this->lastResponseCode}: {$errorMessage}", $this->lastResponseCode);
		}
	}

}
