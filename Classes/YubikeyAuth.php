<?php
namespace DERHANSEN\SfYubikey;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Provides YubiKey authentication without dependencies to PEAR packages
 */
class YubikeyAuth
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * Constructor for this class
     *
     * @param array $extensionConfiguration
     */
    public function __construct($extensionConfiguration)
    {
        // Set configuration
        $this->setConfig(trim($extensionConfiguration['yubikeyApiUrl']), 'yubikeyApiUrl');
        $this->setConfig(trim($extensionConfiguration['yubikeyClientId']), 'yubikeyClientId');
        $this->setConfig(trim($extensionConfiguration['yubikeyClientKey']), 'yubikeyClientKey');
    }

    /**
     * Do OTP check if user has been setup to do so.
     *
     * @param String $yubikeyOtp
     * @return Boolean
     */
    public function checkOtp($yubikeyOtp)
    {
        $ret = false;
        $otp = trim($yubikeyOtp);

        // Verify if the OTP is valid ?
        if ($this->verifyOtp($otp)) {
            $ret = true;
        }

        return $ret;
    }

    /**
     * Verify HMAC-SHA1 signatur on result received from Yubico server
     *
     * @param String $response Data from Yubico
     * @param String $yubicoApiKey Shared API key
     * @return Boolean Does the signature match ?
     */
    public function verifyHmac($response, $yubicoApiKey)
    {
        $lines = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(chr(10), $response);
        // Create array from data
        foreach ($lines as $line) {
            $lineparts = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('=', $line, false, 2);
            $result[$lineparts[0]] = trim($lineparts[1]);
        }
        // Sort array Alphabetically based on keys
        ksort($result);
        // Grab the signature sent by server, and delete
        $signatur = $result['h'];
        unset($result['h']);
        // Build new string to calculate hmac signature on
        $datastring = '';
        foreach ($result as $key => $value) {
            $datastring != '' ? $datastring .= '&' : $datastring .= '';
            $datastring .= $key . '=' . $value;
        }
        $hmac = base64_encode(hash_hmac('sha1', $datastring, base64_decode($yubicoApiKey), true));
        return $hmac == $signatur;
    }

    /**
     * Call the Auth API at Yubico server
     *
     * @param String $otp One-time Password entered by user
     * @return Boolean Is the password OK ?
     */
    public function verifyOtp($otp)
    {

        // Get the global API ID/KEY
        $yubicoApiId = trim($this->getConfig('yubikeyClientId'));
        $yubicoApiKey = trim($this->getConfig('yubikeyClientKey'));

        $url = $this->getConfig('yubikeyApiUrl') . '?id=' . $yubicoApiId . '&otp=' . $otp .
            '&nonce=' . md5(uniqid(rand(), false));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Enhanced TYPO3 Yubikey OTP Login Service');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer']) {
            curl_setopt($ch, CURLOPT_PROXY, $GLOBALS['TYPO3_CONF_VARS']['SYS']['curlProxyServer']);
        }
        $response = trim(curl_exec($ch));
        curl_close($ch);

        if ($this->verifyHmac($response, $yubicoApiKey)) {
            if (!preg_match('/status=([a-zA-Z0-9_]+)/', $response, $result)) {
                return false;
            }
            if ($result[1] === 'OK') {
                return true;
            }
        }
        return false;
    }

    /**
     * Set configuration
     *
     * @param Mixed $config
     * @param String $key Optional array key for config attribute
     * @return void
     */
    public function setConfig($config, $key = '')
    {
        if ($key !== '') {
            $this->config[$key] = $config;
        } else {
            $this->config = $config;
        }
    }

    /**
     * Get configuration
     *
     * @param String $key Optional array key for config attribute
     * @return array|string
     */
    public function getConfig($key = '')
    {
        if ($key !== '') {
            $ret = $this->config[$key];
        } else {
            $ret = $this->config;
        }
        return $ret;
    }
}
