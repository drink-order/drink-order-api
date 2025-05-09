<?php

namespace App\Services;

use Twilio\Rest\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected $client;
    protected $twilioNumber;
    protected $isEnabled;

    public function __construct()
    {
        // Check if Twilio credentials are configured
        $this->isEnabled = !empty(env('TWILIO_SID')) && 
                          !empty(env('TWILIO_AUTH_TOKEN')) && 
                          !empty(env('TWILIO_PHONE_NUMBER'));
                          
        if ($this->isEnabled) {
            try {
                // Set up curl options to ignore SSL verification (DEVELOPMENT ONLY)
                $httpClient = new \Twilio\Http\CurlClient([
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0
                ]);
                
                // Initialize Twilio client with credentials and custom HTTP client
                $this->client = new Client(
                    env('TWILIO_SID'),
                    env('TWILIO_AUTH_TOKEN'),
                    null,
                    null,
                    $httpClient
                );
                $this->twilioNumber = env('TWILIO_PHONE_NUMBER');
            } catch (Exception $e) {
                Log::error('Twilio initialization error: ' . $e->getMessage());
                $this->isEnabled = false;
            }
        } else {
            Log::warning('Twilio is not configured properly. SMS functionality is disabled.');
        }
    }

    /**
     * Send SMS message
     *
     * @param string $to Phone number to send to
     * @param string $message Message content
     * @return bool Whether the message was sent successfully
     */
    public function sendSMS(string $to, string $message): bool
    {
        if (!$this->isEnabled) {
            Log::warning('Attempted to send SMS but Twilio is not properly configured.');
            return false;
        }

        try {
            // Format the phone number if needed
            $to = $this->formatPhoneNumber($to);
            
            // Send the SMS
            $this->client->messages->create(
                $to,
                [
                    'from' => $this->twilioNumber,
                    'body' => $message
                ]
            );
            
            return true;
        } catch (Exception $e) {
            // Log detailed errors
            Log::error('Twilio SMS error: ' . $e->getMessage());
            Log::error('Twilio error details: ' . json_encode([
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]));
            return false;
        }
    }

    /**
     * Format phone number to E.164 format for Twilio
     * 
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-numeric characters except the + sign
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // If the number doesn't start with +, add it
        if (substr($phoneNumber, 0, 1) !== '+') {
            $phoneNumber = '+' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Send OTP verification code
     *
     * @param string $to Phone number to send to
     * @param string $otp The OTP code
     * @return bool Whether the message was sent successfully
     */
    public function sendOTP(string $to, string $otp): bool
    {
        $message = "Your verification code is: $otp. This code will expire in 10 minutes.";
        return $this->sendSMS($to, $message);
    }

    /**
     * Test Twilio connection
     *
     * @return array Connection status
     */
    public function testConnection(): array
    {
        $status = [
            'curl_installed' => extension_loaded('curl'),
            'twilio_configured' => !empty(env('TWILIO_SID')) && 
                                !empty(env('TWILIO_AUTH_TOKEN')) && 
                                !empty(env('TWILIO_PHONE_NUMBER')),
            'credentials' => [
                'sid' => substr(env('TWILIO_SID') ?? '', 0, 5) . '...',
                'phone' => env('TWILIO_PHONE_NUMBER') ?? 'not configured'
            ]
        ];
        
        try {
            if ($status['curl_installed'] && $status['twilio_configured']) {
                // Just test a basic API call
                $this->client->api->v2010->accounts(env('TWILIO_SID'))->fetch();
                $status['connection_test'] = 'success';
            }
        } catch (\Exception $e) {
            $status['connection_test'] = 'failed';
            $status['error'] = $e->getMessage();
        }
        
        return $status;
    }
}