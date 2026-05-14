<?php

$log_file = 'bot_counter.txt';
$block_log_file = 'bot_blocked.txt';
$timespan = 60;
$bot_limits = [
    'facebookexternalhit' => 10,
    'openai' => 10,
];

$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Find first matching bot identifier from the configured list
$matched_bot = null;
$matched_limit = null;
foreach ($bot_limits as $bot_string => $bot_limit) {
    if (stripos($user_agent, $bot_string) !== false) {
        $matched_bot = $bot_string;
        $matched_limit = (int)$bot_limit;
        break;
    }
}

// Run this logic only for configured bots
if ($matched_bot !== null) {
    
    // Open file for reading and writing (creates it if it does not exist)
    $fp = fopen($log_file, 'c+');
    
    if ($fp) {
        // Exclusive lock to avoid collisions on parallel requests
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            exit;
        }
        
        // Read content
        $content = stream_get_contents($fp);
        $all_bot_timestamps = [];

        if ($content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $all_bot_timestamps = $decoded;
            }
        }

        $current_bot_timestamps = $all_bot_timestamps[$matched_bot] ?? [];
        if (!is_array($current_bot_timestamps)) {
            $current_bot_timestamps = [];
        }

        $timestamps = array_values(array_filter($current_bot_timestamps, static function ($timestamp) {
            return is_int($timestamp)
                || (is_string($timestamp) && $timestamp !== '' && ctype_digit($timestamp));
        }));
        $now = time();
        
        // Keep only entries from the last 1 minute
        $valid_timestamps = [];
        foreach ($timestamps as $timestamp) {
            $timestamp = (int)$timestamp;
            if ($now - $timestamp < $timespan) {
                $valid_timestamps[] = $timestamp;
            }
        }
        
        if (count($valid_timestamps) >= $matched_limit) {
            // Log the block with timestamp and IP address
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log_entry = date('Y-m-d H:i:s') . " - BLOCKED - BOT: $matched_bot - IP: $ip - UA: $user_agent\n";
            file_put_contents($block_log_file, $log_entry, FILE_APPEND);

            // Limit exceeded - send 429 Too Many Requests response
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: 60');
            
            // Release and close the file before exiting
            flock($fp, LOCK_UN);
            fclose($fp);
            
            die("Rate limit exceeded. Please try again later.");
        }
        
        // Add current timestamp
        $valid_timestamps[] = $now;
        $all_bot_timestamps[$matched_bot] = $valid_timestamps;
        
        // Clear the file and save new data
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($all_bot_timestamps, JSON_UNESCAPED_SLASHES));
        
        // Release lock
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

?>