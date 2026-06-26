<?php
/**
 * mikrotik/RouterosApi.php
 *
 * Minimal, dependency-free MikroTik RouterOS API client for PHP.
 * Talks to the router on the API port (default 8728, or 8729 for SSL).
 *
 * This is a trimmed, well-commented version of the classic
 * "PEAR2/Net_RouterOS"-style API client pattern used widely for
 * hotspot billing systems. It implements just what we need:
 * connect, login, write a command, read the response.
 *
 * Usage:
 *   $api = new RouterosApi();
 *   if ($api->connect('192.168.88.1', 'admin', 'password')) {
 *       $api->write('/ip/hotspot/user/add', [
 *           'name'     => 'voucher123',
 *           'password' => 'voucher123',
 *           'limit-uptime' => '01:00:00',
 *       ]);
 *       $response = $api->read();
 *       $api->disconnect();
 *   }
 */

class RouterosApi
{
    private $socket;
    private $connected = false;
    public $debug = false;

    public function connect(string $ip, string $user, string $pass, int $port = 8728, int $timeout = 5): bool
    {
        $this->socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            error_log("MikroTik connect failed: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($this->socket, $timeout);

        // Login sequence (RouterOS API v6.43+ uses plain login)
        $this->write('/login', [], false);
        $this->writeWord('=name=' . $user);
        $this->writeWord('=password=' . $pass);
        $this->writeWord('');
        $response = $this->read(false);

        if (isset($response[0]) && strpos($response[0], '!trap') !== false) {
            error_log('MikroTik login failed: ' . json_encode($response));
            fclose($this->socket);
            return false;
        }

        $this->connected = true;
        return true;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Send a command with attribute key=>value pairs.
     * Example: write('/ip/hotspot/user/add', ['name' => 'john', 'password' => 'pass123'])
     */
    public function write(string $command, array $params = [], bool $autoRead = true)
    {
        $this->writeWord($command);
        foreach ($params as $key => $value) {
            $this->writeWord('=' . $key . '=' . $value);
        }
        $this->writeWord('');
        if ($autoRead) {
            return $this->read();
        }
        return true;
    }

    private function writeWord(string $word): void
    {
        $len = strlen($word);
        $this->writeLength($len);
        if ($len > 0) {
            fwrite($this->socket, $word);
        }
    }

    private function writeLength(int $len): void
    {
        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            fwrite($this->socket, chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0));
            fwrite($this->socket, chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        }
    }

    private function readLength(): int
    {
        $byte = ord(fread($this->socket, 1));
        if (($byte & 0x80) === 0x00) {
            return $byte;
        } elseif (($byte & 0xC0) === 0x80) {
            $byte &= ~0xC0;
            $next = fread($this->socket, 1);
            return ($byte << 8) + ord($next);
        } elseif (($byte & 0xE0) === 0xC0) {
            $byte &= ~0xE0;
            $next = fread($this->socket, 2);
            return ($byte << 16) + (ord($next[0]) << 8) + ord($next[1]);
        } elseif (($byte & 0xF0) === 0xE0) {
            $byte &= ~0xF0;
            $next = fread($this->socket, 3);
            return ($byte << 24) + (ord($next[0]) << 16) + (ord($next[1]) << 8) + ord($next[2]);
        } else {
            $next = fread($this->socket, 4);
            return (ord($next[0]) << 24) + (ord($next[1]) << 16) + (ord($next[2]) << 8) + ord($next[3]);
        }
    }

    private function readWord(): string
    {
        $len = $this->readLength();
        if ($len === 0) {
            return '';
        }
        $data = '';
        while (strlen($data) < $len) {
            $chunk = fread($this->socket, $len - strlen($data));
            if ($chunk === false || $chunk === '') break;
            $data .= $chunk;
        }
        return $data;
    }

    /**
     * Read the full response (until a !done sentence).
     * Returns an array of raw sentence lines (e.g. "!re", "=name=foo", ...).
     */
    public function read(bool $parse = true)
    {
        $allLines = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                // empty word = end of sentence
                if (!empty($allLines) && end($allLines) === '!done') {
                    break;
                }
                if (empty($allLines)) {
                    continue;
                }
                // peek: if last full sentence was !done, stop; otherwise keep reading more sentences
                if (str_contains(end($allLines), '!done')) {
                    break;
                }
                continue;
            }
            $allLines[] = $word;
            if ($word === '!done') {
                // consume trailing empty word terminator
                $this->readWord();
                break;
            }
        }
        return $parse ? $this->parseResponse($allLines) : $allLines;
    }

    /**
     * Turn raw sentence lines into an array of associative records.
     * Each "!re" starts a new record; "=key=value" lines populate it.
     */
    private function parseResponse(array $lines): array
    {
        $records = [];
        $current = null;
        foreach ($lines as $line) {
            if ($line === '!re') {
                if ($current !== null) $records[] = $current;
                $current = [];
            } elseif ($line === '!done' || $line === '!trap') {
                if ($current !== null) {
                    $records[] = $current;
                    $current = null;
                }
            } elseif (str_starts_with($line, '=')) {
                $parts = explode('=', $line, 3);
                if ($current === null) $current = [];
                $current[$parts[1]] = $parts[2] ?? '';
            }
        }
        if ($current !== null) $records[] = $current;
        return $records;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
        }
        $this->connected = false;
    }
}
