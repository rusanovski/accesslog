<?php

declare(ticks = 1);

require_once 'vendor/autoload.php';

class AccessLog {

    private $config = [
        'redis' => [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => 'password',
            'database' => 10
        ],
        'access_log_regex' => "/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})[\s\S]+([0-9]{3})[\s\S]*\s([0-9]+|-)\s\"([\S]+)\"\s\"([\S\s]+)\"/",
        'access_log_entries' => [null, 'ip', 'status', 'size', 'url', 'useragent'],
    ];

    private $redis = null;
    private $tick = 10000;
    private $i = 1;
    private $j = 0;
    private $c = '.';
    public $ob = false;

    public function __construct() {
        $this->redis = new Predis\Client($this->config['redis']);
    }

    public $data = [
        'views' => 0,
        'traffic' => 0,
        'urls' => 0,
        'crawlers' => [
            'google' => 0,
            'yandex' => 0,
            'bing' => 0,
        ],
        'codes' => [],
        'lines_count' => 0,
    ];

    private function processEntries($matches) {
        foreach ($this->config['access_log_entries'] as $pos => $entry_name) {

            // Status code match.
            if ($entry_name === 'status') {
                if ($this->data['codes'] && array_key_exists($matches[$pos], $this->data['codes']))
                    $this->data['codes'][$matches[$pos]]++;
                else $this->data['codes'][$matches[$pos]] = 1;

            // Response length match.
            } elseif ($entry_name === 'size' && $matches[$pos] !== '-') {
                $this->data['traffic'] += $matches[$pos];
                if ($matches[$pos] > 0) $this->data['views']++;

            // URL.
            } elseif ($entry_name === 'url') {
                if (!$this->redis->get("unique_url|$matches[$pos]")) {
                    $this->data['urls']++;
                    $this->redis->set("unique_url|$matches[$pos]", 1);
                }

            // User-Agent.
            } elseif ($entry_name === 'useragent') {
                if (preg_match("/Googlebot/", $matches[$pos]))
                    $this->data['crawlers']['google']++;
                elseif (preg_match("/YandexBot/", $matches[$pos]))
                    $this->data['crawlers']['yandex']++;
                elseif (preg_match("/bingbot/", $matches[$pos]))
                    $this->data['crawlers']['bing']++;

            }
        }

    }

    private function displayProgress() {
        if ($this->i > $this->tick) $this->i = 0;
        if ($this->i === 0) {
            ob_implicit_flush(); echo $this->c;
            $this->j++; $this->ob = true;
        }
        if ($this->j >= 3) {
            $this->c = ['.', '#', '@'][mt_rand(0, 2)];
            echo " Please wait...\r"; $this->j = 0;
        }
    }

    public function readLine($line) {
        $this->displayProgress();

        if (preg_match($this->config['access_log_regex'], $line, $matches))
            $this->processEntries($matches);

        if ($this->i === 0) usleep(100);
        $this->data['lines_count']++; $this->i++;
    }

    public function clear() {
        $this->redis->flushdb();
    }

}

$handle = null;
$access_log = new AccessLog;

// Clear redis data and close file on CTRL+C.
function sigint_handler() {
    global $access_log, $handle;

    $access_log->clear();
    if ($handle) fclose($handle);

    sleep(1);
    echo "\r                  \rBye!". PHP_EOL;
    exit;

}
pcntl_signal(SIGINT, "sigint_handler");


if ($argv[1] && file_exists($argv[1])) {

    $handle = fopen($argv[1], "r");
    if ($handle) {

        // Read line.
        while (($line = fgets($handle)) !== false) {
            $regex = "";
            $access_log->readLine($line);
        }

        // Display result.
        if ($access_log->ob) echo "\r                  \r";
        else echo PHP_EOL;
        echo json_encode($access_log->data). PHP_EOL;

        $access_log->clear();
        fclose($handle);
    } else {
        echo "Error: can't open the file.". PHP_EOL;
    }

} else echo "Error: file not found or not specified.". PHP_EOL;