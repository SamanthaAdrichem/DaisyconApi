<?php

namespace SamanthaAdrichem\DaisyconApi;


abstract class HttpResponseCodes {
	/**
	 * Http response code: OK
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_OK = 200;

	/**
	 * Http response code: Created
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_CREATED = 201;

	/**
	 * Http response code: Accepted
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_ACCEPTED = 202;

	/**
	 * Http response code: No content
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_NO_CONTENT = 204;

	/**
	 * Http response code: Bad request
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_BAD_REQUEST = 400;

	/**
	 * Http response code: Forbidden
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_FORBIDDEN = 403;

	/**
	 * Http response code: Not found
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_NOT_FOUND = 404;

	/**
	 * Http response code: Internal server error
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_INTERNAL_SERVER_ERROR = 500;

	/**
	 * Http response code: Service unavailable
	 *
	 * @const int
	 */
	public const HTTP_RESPONSE_CODE_SERVICE_UNAVAILABLE = 503;
}
