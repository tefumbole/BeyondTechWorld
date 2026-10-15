<?php

namespace App\Hotspot;

/**
 * Minimal RouterOS API client. It talks only to the host it is given.
 * Callers pass the tunnel address. This client does not change firewall or NAT.
 */
class RouterOsClient
{
    /** @var resource|null */
    protected $socket;

    public function command($host, $port, $user, $password, array $words, $timeout = 8)
    {
        $this->open($host, $port, $timeout);
        try {
            $this->login($user, $password);
            $this->writeSentence($words);

            return $this->readUntilDone();
        } finally {
            $this->close();
        }
    }

    public function open($host, $port, $timeout)
    {
        $remote = 'tcp://'.$host.':'.(int) $port;
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout);
        if (! is_resource($socket)) {
            throw new HotspotLinkException($errstr !== '' ? $errstr : 'The tunnel did not answer.');
        }
        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;
    }

    public function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
    }

    protected function login($user, $password)
    {
        $this->writeSentence(['/login', '=name='.$user, '=password='.$password]);
        $rows = $this->readUntilDone();
        $challenge = null;
        foreach ($rows as $row) {
            if (isset($row['trap'])) {
                throw new HotspotLinkException('The router rejected the API login.');
            }
            if (! empty($row['ret'])) {
                $challenge = $row['ret'];
            }
        }
        if ($challenge) {
            $hash = md5(chr(0).$password.hex2bin($challenge));
            $this->writeSentence(['/login', '=name='.$user, '=response=00'.$hash]);
            foreach ($this->readUntilDone() as $row) {
                if (isset($row['trap'])) {
                    throw new HotspotLinkException('The router rejected the API login.');
                }
            }
        }
    }

    public function writeSentence(array $words)
    {
        foreach ($words as $word) {
            $this->writeWord($word);
        }
        $this->writeWord('');
    }

    protected function writeWord($word)
    {
        $payload = self::encodeLength(strlen($word)).$word;
        $written = fwrite($this->socket, $payload);
        if ($written === false) {
            throw new HotspotLinkException('The tunnel closed while sending.');
        }
    }

    public function readUntilDone()
    {
        $rows = [];
        while (true) {
            $sentence = $this->readSentence();
            if ($sentence === []) {
                break;
            }
            $kind = isset($sentence[0]) ? $sentence[0] : '';
            $row = ['type' => $kind];
            foreach ($sentence as $word) {
                if (strpos($word, '=') === 0) {
                    $pair = explode('=', substr($word, 1), 2);
                    $row[$pair[0]] = isset($pair[1]) ? $pair[1] : '';
                }
            }
            if ($kind === '!trap') {
                $row['trap'] = isset($row['message']) ? $row['message'] : 'RouterOS error';
            }
            if ($kind === '!done') {
                if (! empty($row['ret'])) {
                    $rows[] = $row;
                }
                break;
            }
            if ($kind === '!fatal') {
                $row['trap'] = 'The router closed the API.';
                $rows[] = $row;
                break;
            }
            if ($kind !== '!empty') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function readSentence()
    {
        $words = [];
        while (true) {
            $word = $this->readWord();
            if ($word === '') {
                break;
            }
            $words[] = $word;
        }

        return $words;
    }

    protected function readWord()
    {
        $length = $this->readLength();
        if ($length === 0) {
            return '';
        }
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($this->socket);
                if (! empty($meta['timed_out'])) {
                    throw new HotspotLinkException('The router did not answer in time.');
                }
                throw new HotspotLinkException('The tunnel closed.');
            }
            $data .= $chunk;
        }

        return $data;
    }

    protected function readLength()
    {
        $first = $this->readByte();
        if ($first === null) {
            return 0;
        }
        if (($first & 0x80) === 0) {
            return $first;
        }
        if (($first & 0xC0) === 0x80) {
            return (($first & 0x3F) << 8) + $this->readByte();
        }
        if (($first & 0xE0) === 0xC0) {
            return (($first & 0x1F) << 16) + ($this->readByte() << 8) + $this->readByte();
        }
        if (($first & 0xF0) === 0xE0) {
            return (($first & 0x0F) << 24) + ($this->readByte() << 16) + ($this->readByte() << 8) + $this->readByte();
        }
        if ($first === 0xF0) {
            return ($this->readByte() << 24) + ($this->readByte() << 16) + ($this->readByte() << 8) + $this->readByte();
        }

        return 0;
    }

    protected function readByte()
    {
        $byte = fread($this->socket, 1);
        if ($byte === false || $byte === '') {
            return null;
        }

        return ord($byte);
    }

    public static function encodeLength($length)
    {
        $length = (int) $length;
        if ($length < 0x80) {
            return chr($length);
        }
        if ($length < 0x4000) {
            $length |= 0x8000;

            return chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }
        if ($length < 0x200000) {
            $length |= 0xC00000;

            return chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }
        if ($length < 0x10000000) {
            $length |= 0xE0000000;

            return chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        return chr(0xF0).chr(($length >> 24) & 0xFF).chr(($length >> 16) & 0xFF).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
    }
}
