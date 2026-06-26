<?php
/**
 * mikrotik/HotspotManager.php
 *
 * Wraps RouterosApi with the specific actions our billing system needs:
 *  - create a hotspot user with a time/data limit
 *  - remove/expire a hotspot user
 *  - check active sessions
 *
 * This assumes you have already configured MikroTik's Hotspot service
 * (IP > Hotspot > Hotspot Setup) on the router itself. This class only
 * manages the USER ACCOUNTS, not the hotspot network setup.
 */

require_once __DIR__ . '/RouterosApi.php';

class HotspotManager
{
    private RouterosApi $api;
    private array $router; // row from `routers` table

    public function __construct(array $routerConfig)
    {
        $this->router = $routerConfig;
        $this->api = new RouterosApi();
    }

    private function connect(): bool
    {
        return $this->api->connect(
            $this->router['ip_address'],
            $this->router['api_username'],
            $this->router['api_password'],
            (int)($this->router['api_port'] ?? 8728)
        );
    }

    /**
     * Create a hotspot user account on the router.
     *
     * @param string $username      e.g. voucher code or phone number
     * @param string $password      can be same as username for simplicity
     * @param int    $durationMins  validity in minutes (used to set limit-uptime)
     * @param string|null $rateLimit  e.g. "2M/2M" (download/upload)
     * @param int|null $dataLimitMb  optional data cap in MB
     * @return bool success
     */
    public function createUser(
        string $username,
        string $password,
        int $durationMins,
        ?string $rateLimit = null,
        ?int $dataLimitMb = null
    ): bool {
        if (!$this->connect()) {
            return false;
        }

        $hours   = floor($durationMins / 60);
        $minutes = $durationMins % 60;
        $uptimeLimit = sprintf('%02d:%02d:00', $hours, $minutes);

        $params = [
            'name'           => $username,
            'password'       => $password,
            'limit-uptime'   => $uptimeLimit,
            'server'         => $this->router['hotspot_server'] ?? 'all',
            'comment'        => 'Created by billing system on ' . date('Y-m-d H:i:s'),
        ];

        if ($rateLimit) {
            $params['limit-bytes-total'] = ''; // placeholder, see data limit below
            $params['rate-limit'] = $rateLimit;
        }

        if ($dataLimitMb) {
            // MikroTik expects bytes
            $params['limit-bytes-total'] = (string)($dataLimitMb * 1024 * 1024);
        } else {
            unset($params['limit-bytes-total']);
        }

        $result = $this->api->write('/ip/hotspot/user/add', $params);
        $this->api->disconnect();

        // If !trap appears in result, something went wrong (e.g. duplicate username)
        foreach ($result as $row) {
            if (isset($row['message'])) {
                error_log('MikroTik createUser error: ' . $row['message']);
                return false;
            }
        }
        return true;
    }

    /**
     * Remove a hotspot user (e.g. when a voucher expires or is revoked).
     */
    public function removeUser(string $username): bool
    {
        if (!$this->connect()) {
            return false;
        }

        // Need the internal .id first
        $found = $this->api->write('/ip/hotspot/user/print', [
            '?name' => $username,
        ]);

        if (empty($found)) {
            $this->api->disconnect();
            return false;
        }

        $id = $found[0]['.id'] ?? null;
        if (!$id) {
            $this->api->disconnect();
            return false;
        }

        $this->api->write('/ip/hotspot/user/remove', ['.id' => $id]);
        $this->api->disconnect();
        return true;
    }

    /**
     * Force-disconnect an active session for a user (kick them off immediately).
     */
    public function disconnectActiveSession(string $username): bool
    {
        if (!$this->connect()) {
            return false;
        }

        $active = $this->api->write('/ip/hotspot/active/print', [
            '?user' => $username,
        ]);

        foreach ($active as $session) {
            if (isset($session['.id'])) {
                $this->api->write('/ip/hotspot/active/remove', ['.id' => $session['.id']]);
            }
        }
        $this->api->disconnect();
        return true;
    }

    /**
     * Get all currently active hotspot sessions (for the admin dashboard).
     */
    public function getActiveSessions(): array
    {
        if (!$this->connect()) {
            return [];
        }
        $active = $this->api->write('/ip/hotspot/active/print');
        $this->api->disconnect();
        return $active;
    }

    /**
     * Test connectivity to the router (used in admin settings page).
     */
    public function testConnection(): bool
    {
        $ok = $this->connect();
        if ($ok) {
            $this->api->disconnect();
        }
        return $ok;
    }
}
