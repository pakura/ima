<?php

namespace Pakura\Ima;

use Closure;
use Curl\Curl;
use InvalidArgumentException;
use Illuminate\Support\Collection;

class Ima
{
    /**
     * The curl instance.
     *
     * @var \Curl\Curl
     */
    protected $curl;

    /**
     * The merchant handler url.
     *
     * @var string
     */
    protected $merchantHandler;

    /**
     * The client handler url.
     *
     * @var string
     */
    protected $clientHandler;

    /**
     * The certificate path.
     *
     * @var string
     */
    protected $cert;

    /**
     * The key path.
     *
     * @var string
     */
    protected $key;

    /**
     * The password.
     *
     * @var string
     */
    protected $pass;

    /**
     * Transaction currency code (ISO 4217), mandatory, (3 digits).
     *
     * @var string
     */
    protected $currency;

    /**
     * The client IP address.
     *
     * @var string
     */
    private $clientIpAddr;

    /**
     * The language identifier.
     *
     * @var string
     */
    protected $language;

    /**
     * The collection instance that contains the last transaction result.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $result;

    /**
     * The last transaction raw result.
     *
     * @var string
     */
    protected $rawResult;

    /**
     * The identifiers for the transaction request.
     *
     * @var string
     */
    const COMMAND_SMS      = 'v',
        COMMAND_DMS_AUTH = 'a',
        COMMAND_DMS_EXEC = 't',
        COMMAND_RESULT   = 'c',
        COMMAND_REVERSE  = 'r',
        COMMAND_REFUND   = 'k',
        COMMAND_CREDIT   = 'g',
        COMMAND_CLOSE    = 'b';

    /**
     * Create a new integrated merchant agent instance.
     *
     * @return void
     */
    public function __construct($ima = null)
    {
        $config = ($ima == 'bot') ? config('payment.bot_ima') : config('payment.ima');

        foreach ((array) $config as $key => $value) {
            $this->{$key} = $value;
        }

        $this->curl = new Curl($this->merchantHandler);
        $this->curl->setOpt(CURLOPT_SSL_VERIFYHOST, 0);
        $this->curl->setOpt(CURLOPT_SSL_VERIFYPEER, 0);
        $this->curl->setOpt(CURLOPT_CAINFO, $this->cert);
        $this->curl->setOpt(CURLOPT_SSLCERT, $this->cert);
        $this->curl->setOpt(CURLOPT_SSLKEY, $this->key);
        $this->curl->setOpt(CURLOPT_SSLKEYPASSWD, $this->pass);

        $this->currency = $this->currency ?: '981';

        $this->clientIpAddr = request()->ip();
        $this->result = new Collection;

    }

    /**
     * Get the curl instance.
     *
     * @return \Curl\curl
     */
    public function getCurl()
    {
        return $this->curl;
    }

    /**
     * Format the transaction amount in fractional units.
     *
     * @param  string|int  $amount
     * @return string
     */
    public function formatAmount($amount)
    {
        return number_format($amount, 2, '', '');
    }

    /**
     * Set the transaction currency code.
     *
     * @param  string  $currency
     * @return $this
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    final public function getClientIpAddr()
    {
        return $this->clientIpAddr;
    }

    /**
     * Set the language identifier.
     *
     * @param  string  $language
     * @return $this
     */
    public function setLanguage($language)
    {
        $this->language = $language;

        return $this;
    }

    /**
     * Make a transaction request.
     *
     * @param  array     $data
     * @param  \Closure  $callback
     * @return $this
     */
    protected function transaction(array $data, Closure $callback = null)
    {
        $this->setResult($this->curl->post($data));

        if (! is_null($callback)) {
            $callback($this->result);
        }

        return $this;
    }

    /**
     * Register a single message system (SMS) transaction.
     *
     * @param  string    $amount
     * @param  \Closure  $callback
     * @return $this
     */
    public function startSMSTrans($amount,  Closure $callback = null)
    {
        return $this->transaction([
            'command'        => self::COMMAND_SMS,
            'amount'         => $this->formatAmount($amount),
            'currency'       => $this->currency,
            'client_ip_addr' => $this->clientIpAddr,
            'language'       => $this->language
        ], $callback);
    }

    public function startSMSTransUSD($amount,  Closure $callback = null)
    {
        //$this->currency = '840';
        $this->language = 'EN';
        return $this->transaction([
            'command'        => self::COMMAND_SMS,
            'amount'         => $this->formatAmount($amount),
            'currency'       => $this->currency,
            'client_ip_addr' => $this->clientIpAddr,
            'language'       => $this->language
        ], $callback);
    }

    /**
     * Register a dual message system (DMS) authorization.
     *
     * @param  string    $amount
     * @param  \Closure  $callback
     * @return $this
     */
    public function startDMSAuth($amount, Closure $callback = null)
    {
        return $this->transaction([
            'command'        => self::COMMAND_DMS_AUTH,
            'amount'         => $this->formatAmount($amount),
            'currency'       => $this->currency,
            'client_ip_addr' => $this->clientIpAddr,
        ], $callback);
    }

    /**
     * Execute a dual message system (DMS) transaction.
     *
     * @param  string    $transId
     * @param  string    $amount
     * @param  \Closure  $callback
     * @return $this
     */
    public function makeDMSTrans($transId, $amount, Closure $callback = null)
    {
        return $this->transaction([
            'command'        => self::COMMAND_DMS_EXEC,
            'trans_id'       => $transId,
            'amount'         => $this->formatAmount($amount),
            'currency'       => $this->currency,
            'client_ip_addr' => $this->clientIpAddr,
        ], $callback);
    }

    /**
     * Execute a request for transaction result.
     *
     * @param  string    $transId
     * @param  \Closure  $callback
     * @return $this
     */
    public function getTransResult($transId, Closure $callback = null)
    {
        return $this->transaction([
            'command'        => self::COMMAND_RESULT,
            'trans_id'       => $transId,
            'client_ip_addr' => $this->clientIpAddr,
        ], $callback);
    }

    /**
     * Execute a request for transaction reversal.
     *
     * @param  string       $transId
     * @param  string|null  $amount
     * @param  \Closure     $callback
     * @param  bool         $fraud
     * @return $this
     */
    public function reverse($transId, $amount = null, Closure $callback = null, $fraud = false)
    {
        $data = [
            'command'  => self::COMMAND_REVERSE,
            'trans_id' => $transId
        ];

        if (! is_null($amount)) {
            $data['amount'] = $this->formatAmount($amount);
        }

        if ($fraud) {
            $data['fraud'] = 'yes';
        }

        return $this->transaction($data, $callback);
    }

    /**
     * Execute a request for transaction refund.
     *
     * @param  string    $transId
     * @param  \Closure  $callback
     * @return $this
     */
    public function refund($transId, Closure $callback = null)
    {
        return $this->transaction([
            'command'  => self::COMMAND_REFUND,
            'trans_id' => $transId
        ], $callback);
    }

    /**
     * Execute a request for credit transaction.
     *
     * @param  string       $transId
     * @param  string|null  $amount
     * @param  \Closure     $callback
     * @return $this
     */
    public function credit($transId, $amount = null, Closure $callback = null)
    {
        $data = [
            'command'  => self::COMMAND_CREDIT,
            'trans_id' => $transId
        ];

        if (! is_null($amount)) {
            $data['amount'] = $this->formatAmount($amount);
        }

        return $this->transaction($data, $callback);
    }

    /**
     * Close the last opened batch for a particular merchant.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function closeDay(Closure $callback = null)
    {
        return $this->transaction([
            'command' => self::COMMAND_CLOSE
        ], $callback);
    }

    /**
     * Determine if the transaction completed successfuly.
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->get('result') == 'OK';
    }

    /**
     * Determine if the transaction completed successfuly.
     *
     * @return bool
     */
    public function isWaiting()
    {
        return ($this->get('result') == 'Waiting' || $this->get('result') == 'waiting' || $this->get('result') == 'WAITING');
    }

    /**
     * Determine if the transaction result has an error.
     *
     * @return bool
     */
    public function hasError()
    {
        return ! is_null($this->get('error'));
    }

    /**
     * Get the value of the result collection.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string
     */
    public function get($key, $default = null)
    {
        return $this->getResult()->get($key, $default);
    }

    /**
     * Set the transaction result that will be transformed to collection.
     *
     * @param  string  $result
     * @return void
     */
    protected function setResult($result)
    {
        $this->rawResult = $result;

        $items = [];

        $fakeKey = '';

        foreach (explode(PHP_EOL, (string) $result) as $item) {
            if (! $item) break;

            foreach (explode(': ', $item) as $key => $value) {
                if ($key == 0) {
                    $items[$fakeKey = strtolower($value)] = '';
                } else {
                    $items[$fakeKey] = $value;

                    break;
                }
            }
        }

        $this->result = new Collection($items);
    }

    /**
     * Get the transaction result collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Get the transaction raw result.
     *
     * @return string
     */
    public function getRawResult()
    {
        return $this->rawResult;
    }

    /**
     * Readdress the client.
     *
     * @param  string|null  $transId
     * @param  bool  $decode
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function readdressClient($transId = null, $decode = true)
    {
        if (is_null($transId) && is_null($transId = $this->get('transaction_id'))) {
            throw new InvalidArgumentException("Transaction id is not provided.");
        }

        $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
    <title>Merchant post to ECOMM</title>
    <style>body {text-align:center;}</style>
    </head>
    <body>
    <form action="' . $this->clientHandler . '" id="form" method="POST">
    <input type="hidden" name="trans_id" value="' . $transId . '" >
    <noscript>
    <p>Please click the submit button below.</p>
    <input type="submit" value="Submit" />
    </noscript>
    </form>
    <script type="text/javascript">
    document.getElementById("form").submit();
    </script>
    </body>
    </html>';

        return $decode ? html_entity_decode($html, ENT_QUOTES, 'UTF-8') : $html;

    }
    public function readdressClientJson($transId = null, $decode = true)
    {
        if (is_null($transId) && is_null($transId = $this->get('transaction_id'))) {
            throw new InvalidArgumentException("Transaction id is not provided.");
        }

        $html = '<!DOCTYPE html>
    <html lang="en">
    <head>
    <title>Merchant post to ECOMM</title>
    <style>body {text-align:center;}</style>
    </head>
    <body>
    <form action="' . $this->clientHandler . '" id="form" method="POST">
    <input type="hidden" name="trans_id" value="' . $transId . '" >
    <noscript>
    <p>Please click the submit button below.</p>
    <input type="submit" value="Submit" />
    </noscript>
    </form>
    <script type="text/javascript">
    document.getElementById("form").submit();
    </script>
    </body>
    </html>';

//      return $decode ? html_entity_decode($html, ENT_QUOTES, 'UTF-8') : $html;
//
        return response()->json([
            'html' => $decode ? html_entity_decode($html, ENT_QUOTES, 'UTF-8') : $html
        ]);
    }

    /**
     * Dynamically get the value of the transaction result collection.
     *
     * @param  string  $key
     * @return string
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Make dynamic calls into the transaction result collection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->result, $method], $parameters);
    }

    /**
     * Convert the transaction result collection to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->result->toJson();
    }
}
