<?php

/**
 * Nextcloud API for the Calendar plugin.
 *
 * @author Aleksander Machniak <machniak@apheleia-it.ch>
 *
 * Copyright (C) Apheleia IT AG <contact@apheleia-it.ch>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
class calendar_nextcloud_api
{
    public const PARTICIPANT_OWNER             = 1;
    public const PARTICIPANT_MODERATOR         = 2;
    public const PARTICIPANT_USER              = 3;
    public const PARTICIPANT_GUEST             = 4;
    public const PARTICIPANT_PUBLIC            = 5;
    public const PARTICIPANT_GUEST_MODERATOR   = 6;


    /**
     * Make a request to the Nextcloud API
     *
     * @return false|array Response data or False on failure
     */
    protected function request($path, $method = 'GET', $params = [])
    {
        $rcmail = rcube::get_instance();

        $url = unslashify($rcmail->config->get('calendar_nextcloud_url'));
        $url .= "/ocs/v2.php/$path";

        try {
            $request_config = [
                'store_body'       => true,
                'follow_redirects' => true,
            ];

            $request = libkolab::http_request($url, $method, $request_config);

            // Authentication
            $request->setAuth(
                $rcmail->user->get_username(),
                $rcmail->decrypt($_SESSION['password'])
            );

            // Disable CSRF prevention, and enable JSON responses
            $request->setHeader([
                    'OCS-APIRequest' => 'true',
                    'Accept' => 'application/json',
            ]);

            if (!empty($params)) {
                if ($method == 'POST') {
                    $request->addPostParameter($params);
                } else {
                    $request->setUrl($url . '?' . http_build_query($params));
                }
            }

            // rcube::console($method . ": " . (string) $request->getUrl());

            // Send the request
            $response = $request->send();

            $body = $response->getBody();
            $code = $response->getStatus();

            // rcube::console($code, $body);

            if ($code < 400) {
                return json_decode($body, true);
            }

            if (strpos($body, '<?xml') === 0) {
                $doc = new DOMDocument();
                $doc->loadXML($body);
                $code = $doc->getElementsByTagName('statuscode')->item(0)->textContent;
                $msg = $doc->getElementsByTagName('message')->item(0)->textContent;
            } else {
                $msg = 'Unknown error';
            }

            throw new Exception("Nextcloud API Error: [$code] $msg");
        } catch (Exception $e) {
            rcube::raise_error($e, true, false);
        }

        return false;
    }

    /**
     * Find user by email address
     */
    protected function findUserByEmail($email)
    {
        $email = strtolower($email);
        $params = [
            'search' => $email,
            'itemType' => 'call',
            'itemId' => ' ',
            'shareTypes' => [0, 1, 7, 4],
        ];

        // FIXME: Is this the only way to find a user by his email address?
        $response = $this->request("core/autocomplete/get", 'GET', $params);

        if (!empty($response['ocs']['data'])) {
            foreach ($response['ocs']['data'] as $user) {
                // FIXME: This is the only field that contains email address?
                // Note: A Nextcloud contact (the "emails" source) will have an email address in
                // the 'id' attribute instead in 'shareWithDisplayNameUnique'.
                // Another option might be to parse 'label' attribute
                if (strtolower($user['shareWithDisplayNameUnique']) == $email) {
                    return $user;
                }
            }
        }
    }

    /**
     * Create a Talk room
     *
     * @param string $name Room name
     *
     * @return string|false Room URL
     */
    public function talk_room_create($name = '')
    {
        $rcmail = rcube::get_instance();

        $params = [
            'roomType' => 3,
            'roomName' => $name ?: $rcmail->gettext('calendar.talkroomname'),
        ];

        $response = $this->request('apps/spreed/api/v4/room', 'POST', $params);

        if (is_array($response) && !empty($response['ocs']['data']['token'])) {
            $token = $response['ocs']['data']['token'];
            $url = unslashify($rcmail->config->get('calendar_nextcloud_url'));

            return $url . '/call/' . $token;
        }

        return false;
    }

    /**
     * Update a Talk room
     *
     * @param string $room         Room ID (or url)
     * @param array  $participants Room participants' email addresses (extept the owner)
     *
     * @return bool
     */
    public function talk_room_update($room = '', $participants = [])
    {
        if (preg_match('|https?://|', $room)) {
            $arr = explode('/', $room);
            $room = $arr[count($arr) - 1];
        }

        // Get existing room participants
        $response = $this->request("apps/spreed/api/v4/room/{$room}/participants", 'GET');

        if ($response === false) {
            return false;
        }

        $attendees = [];
        foreach ($response['ocs']['data'] as $attendee) {
            if ($attendee['participantType'] != self::PARTICIPANT_OWNER) {
                $attendees[$attendee['actorId']] = $attendee['attendeeId'];
            }
        }

        foreach ($participants as $email) {
            if ($user = $this->findUserByEmail($email)) {
                // Participant already exists, skip
                // Note: We're dealing with 'users' source here for now, 'emails' source
                // will have an email address in 'actorId'
                if (isset($attendees[$user['id']])) {
                    unset($attendees[$user['id']]);
                    continue;
                }

                // Register the participant
                $params = ['newParticipant' => $user['id'], 'source' => $user['source']];
                $response = $this->request("apps/spreed/api/v4/room/{$room}/participants", 'POST', $params);
            }
        }

        // Remove participants not in the event anymore
        foreach ($attendees as $attendeeId) {
            $params = ['attendeeId' => $attendeeId];
            $response = $this->request("apps/spreed/api/v4/room/{$room}/attendees", 'DELETE', $params);
        }

        return true;
    }
}
