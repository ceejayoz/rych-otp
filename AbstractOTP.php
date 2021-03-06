<?php
/**
 * Ryan's OATH-OTP Library
 *
 * @package Rych\OTP
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2013, Ryan Chouinard
 * @license MIT License - http://www.opensource.org/licenses/mit-license.php
 */

namespace Rych\OTP;

use Rych\OTP\Seed;

/**
 * One-Time Password Base Class
 *
 * @package Rych\OTP
 * @author Ryan Chouinard <rchouinard@gmail.com>
 * @copyright Copyright (c) 2013, Ryan Chouinard
 * @license MIT License - http://www.opensource.org/licenses/mit-license.php
 */
abstract class AbstractOTP
{

    /**
     * @var integer
     */
    protected $digits;

    /**
     * @var string
     */
    protected $hashFunction;

    /**
     * @var \Rych\OTP\Seed
     */
    protected $secret;

    /**
     * @var integer
     */
    protected $window;

    /**
     * Class constructor
     *
     * @param string|\Rych\OTP\Seed $secret  The shared secret key.
     * @param array                 $options An array of options to be used
     *     when generating one-time passwords.
     * @return void
     */
    public function __construct($secret, array $options = array ())
    {
        // Option names taken from Google Authenticator docs for consistency
        $options = array_merge(
            array (
                'algorithm' => 'sha1',
                'digits' => 6,
                'window' => 4,
            ),
            array_change_key_case($options, CASE_LOWER)
        );

        $this->setDigits($options['digits']);
        $this->setHashFunction($options['algorithm']);
        $this->setSecret($secret);
        $this->setWindow($options['window']);
    }

    /**
     * Generate a one-time password from a given counter value
     *
     * @param  integer $counter The counter value. Defaults to 0.
     * @return string  Returns the generated one-time password.
     */
    public function calculate($counter = 0)
    {
        $digits = $this->getDigits();
        $hashFunction = $this->getHashFunction();
        $secret = $this->getSecret()->getValue(Seed::FORMAT_RAW);

        $counter = $this->counterToString($counter);
        $hash = hash_hmac($hashFunction, $counter, $secret, true);

        $otp = $this->truncate($hash);
        if ($digits < 10) {
            $otp %= pow(10, $digits);
        }

        return str_pad($otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Get the number of digits in the one-time password
     *
     * @return integer Returns the number of digits in a one-time password.
     */
    public function getDigits()
    {
        return $this->digits;
    }

    /**
     * Set the number of digits in the one-time password
     *
     * @param  integer                   $digits The number of digits in a
     *     one-time password.
     * @return \Rych\OTP\OTP             Returns an instance of self for method
     *     chaining.
     * @throws \InvalidArgumentException Thrown if the requested number of
     *     digits is outside of the inclusive range 1-10.
     */
    public function setDigits($digits)
    {
        $digits = abs(intval($digits));
        if ($digits < 1 || $digits > 10) {
            throw new \InvalidArgumentException('Digits must be a number between 1 and 10 inclusive');
        }
        $this->digits = $digits;

        return $this;
    }

    /**
     * Get the hash function
     *
     * @return string Returns the hash function.
     */
    public function getHashFunction()
    {
        return $this->hashFunction;
    }

    /**
     * Set the hash function
     *
     * @param  string                    $hashFunction The hash function.
     * @return \Rych\OTP\OTP             Returns an instance of self for method
     *     chaining.
     * @throws \InvalidArgumentException Thrown if the supplied hash function is
     *     not supported.
     */
    public function setHashFunction($hashFunction)
    {
        $hashFunction = strtolower($hashFunction);
        if (!in_array($hashFunction, hash_algos())) {
            throw new \InvalidArgumentException("$hashFunction is not a supported hash function");
        }
        $this->hashFunction = $hashFunction;

        return $this;
    }

    /**
     * Get the shared secret key
     *
     * @return \Rych\OTP\Seed Returns a Seed object instance which represents
     *     the shared secret key.
     */
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Set the shared secret key
     *
     * @param  string|\Rych\OTP\Seed $secret The shared secret key.
     * @return \Rych\OTP\OTP         Returns an instance of self for method
     *     chaining.
     */
    public function setSecret($secret)
    {
        if (!$secret instanceof Seed) {
            $secret = new Seed($secret);
        }
        $this->secret = $secret;

        return $this;
    }

    /**
     * Get the window value
     *
     * @return integer The window value.
     */
    public function getWindow()
    {
        return $this->window;
    }

    /**
     * Set the window value
     *
     * @param  integer        $window The window value
     * @return \Rych\OTP\HOTP Returns an instance of self for method chaining.
     */
    public function setWindow($window)
    {
        $window = abs(intval($window));
        $this->window = $window;

        return $this;
    }

    /**
     * Extract 4 bytes from a hash value
     *
     * Uses the method defined in RFC 4226, § 5.4.
     *
     * @param  string  $hash Hash value.
     * @return integer Truncated hash value.
     */
    private function truncate($hash)
    {
        $offset = ord($hash[19]) & 0xf;
        $value  = (ord($hash[$offset + 0]) & 0x7f) << 24;
        $value |= (ord($hash[$offset + 1]) & 0xff) << 16;
        $value |= (ord($hash[$offset + 2]) & 0xff) << 8;
        $value |= (ord($hash[$offset + 3]) & 0xff);

        return $value;
    }

    /**
     * Convert an integer counter into a string of 8 bytes
     *
     * @param  integer $counter The counter value.
     * @return string  Returns an 8-byte binary string.
     */
    private function counterToString($counter)
    {
        $temp = array ();
        while ($counter != 0) {
            $temp[] = chr($counter & 0xff);
            $counter >>= 8;
        }

        return str_pad(join(array_reverse($temp)), 8, "\0", STR_PAD_LEFT);
    }

}

