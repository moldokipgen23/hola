<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS Provider
    |--------------------------------------------------------------------------
    |
    | Supported: "msg91", "log"
    |
    | - msg91: Uses MSG91 API (popular in India, free tier available)
    |   Get auth key: https://msg91.com
    |   Get template ID: Create OTP template in MSG91 dashboard
    |
    | - log: Logs OTP to Laravel log (development/testing only)
    |
    */

    'provider' => env('SMS_PROVIDER', 'log'),

];
