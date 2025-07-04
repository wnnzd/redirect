<?php
/**
 * BotMother Configuration File
 * Edit this file to customize your bot protection settings
 */
return [
    // General Settings
    'test_mode'         => false,       // Set to true for testing (disables protections)
    'license_key'       => "",          // Your license key (if applicable)
   
    // Bot Handling
    'exit_behavior'     => "404",       // Options: "redirect", "404", "403", "captcha"
    'exit_link'         => "https://www.google.com/", // Used only with "redirect" behavior
   
    // Legitimate User Handling
    'user_behavior'     => "same_page", // Options: "same_page", "redirect"
    'user_redirect'     => "https://midas.307shredding.com", // Used only with "redirect" behavior
   
    // Required URL Parameter
    'require_parameter' => [
        'enabled'       => true,        // Set to false to disable parameter checking
        'parameter_name'=> "easycodex",     // The required URL parameter
    ],
   
    // Geographic Filtering
    'geo_filter' => [
        'enabled'       => true,        // Set to false to disable geo filtering
        'allowed_countries' => "IQ,LY,YE,LB,PS,UZ,JO,EG,SA,OM,AE,BH,QA,KW,ID,IL"
    ],
   
    // Rate Limiting
    'rate_limit' => [
        'enabled'       => true,        // Set to false to disable rate limiting
        'max_requests'  => 60,          // Maximum requests allowed in time frame
        'time_frame'    => 60           // Time frame in seconds (60 = per minute)
    ],
   
    // File Paths (relative to botMother directory)
    'paths' => [
        'agents_blacklist'  => "data/AGENTS.jhn",
        'ips_blacklist'     => "data/IPS.jhn",
        'ips_range_blacklist' => "data/IPS_RANGE.jhn",
        'logs'              => "../bots_log.txt",
        'visits'            => "../visits_log.txt",
        'request_log'       => "data/request_log.txt"
    ],
   
    // Custom 404 Page Settings
    '404_page' => [
        'title'         => "Page Not Found",
        'heading'       => "404 - Page Not Found",
        'message'       => "The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.",
        'button_text'   => "Go Home",
        'button_url'    => "https://www.google.com",
        'background_color' => "#f8f9fa",
        'text_color'    => "#343a40",
        'accent_color'  => "#007bff"
    ]
];
?>