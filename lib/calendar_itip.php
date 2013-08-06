<?php

/**
 * iTIP functions for the Calendar plugin
 *
 * Class providing functionality to manage iTIP invitations
 *
 * @version @package_version@
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @package @package_name@
 *
 * Copyright (C) 2011, Kolab Systems AG <contact@kolabsys.com>
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
class calendar_itip
{
  private $rc;
  private $cal;
  private $sender;
  private $itip_send = false;

  function __construct($cal, $identity = null)
  {
    $this->cal = $cal;
    $this->rc = $cal->rc;
    $this->sender = $identity ? $identity : $this->rc->user->get_identity();

    $this->cal->add_hook('smtp_connect', array($this, 'smtp_connect_hook'));
  }

  function set_sender_email($email)
  {
    if (!empty($email))
      $this->sender['email'] = $email;
  }

  /**
   * Send an iTip mail message
   *
   * @param array   Event object to send
   * @param string  iTip method (REQUEST|REPLY|CANCEL)
   * @param array   Hash array with recipient data (name, email)
   * @param string  Mail subject
   * @param string  Mail body text label
   * @param object  Mail_mime object with message data
   * @return boolean True on success, false on failure
   */
  public function send_itip_message($event, $method, $recipient, $subject, $bodytext, $message = null)
  {
    if (!$this->sender['name'])
      $this->sender['name'] = $this->sender['email'];
    
    if (!$message)
      $message = $this->compose_itip_message($event, $method);
    
    $mailto = rcube_idn_to_ascii($recipient['email']);
    
    $headers = $message->headers();
    $headers['To'] = format_email_recipient($mailto, $recipient['name']);
    $headers['Subject'] = $this->cal->gettext(array(
      'name' => $subject,
      'vars' => array('title' => $event['title'], 'name' => $this->sender['name'])
    ));
    
    // compose a list of all event attendees
    $attendees_list = array();
    foreach ((array)$event['attendees'] as $attendee) {
      $attendees_list[] = ($attendee['name'] && $attendee['email']) ?
        $attendee['name'] . ' <' . $attendee['email'] . '>' :
        ($attendee['name'] ? $attendee['name'] : $attendee['email']);
    }
    
    $mailbody = $this->cal->gettext(array(
      'name' => $bodytext,
      'vars' => array(
        'title' => $event['title'],
        'date' => $this->cal->lib->event_date_text($event, true),
        'attendees' => join(', ', $attendees_list),
        'sender' => $this->sender['name'],
        'organizer' => $this->sender['name'],
      )
    ));
    
    // append links for direct invitation replies
    if ($method == 'REQUEST' && ($token = $this->store_invitation($event, $recipient['email']))) {
      $mailbody .= "\n\n" . $this->cal->gettext(array(
        'name' => 'invitationattendlinks',
        'vars' => array('url' => $this->cal->get_url(array('action' => 'attend', 't' => $token))),
      ));
    }
    else if ($method == 'CANCEL') {
      $this->cancel_itip_invitation($event);
    }
    
    $message->headers($headers, true);
    $message->setTXTBody(rcube_mime::format_flowed($mailbody, 79));

    // finally send the message
    $this->itip_send = true;
    $sent = $this->rc->deliver_message($message, $headers['X-Sender'], $mailto, $smtp_error);
    $this->itip_send = false;

    return $sent;
  }

  /**
   * Plugin hook to alter SMTP authentication.
   * This is used if iTip messages are to be sent from an unauthenticated session
   */
  public function smtp_connect_hook($p)
  {
    // replace smtp auth settings if we're not in an authenticated session
    if ($this->itip_send && !$this->rc->user->ID) {
      foreach (array('smtp_server', 'smtp_user', 'smtp_pass') as $prop) {
        $p[$prop] = $this->rc->config->get("calendar_itip_$prop", $p[$prop]);
      }
    }

    return $p;
  }

  /**
   * Helper function to build a Mail_mime object to send an iTip message
   *
   * @param array   Event object to send
   * @param string  iTip method (REQUEST|REPLY|CANCEL)
   * @return object Mail_mime object with message data
   */
  public function compose_itip_message($event, $method)
  {
    $from = rcube_idn_to_ascii($this->sender['email']);
    $from_utf = rcube_idn_to_utf8($from);
    $sender = format_email_recipient($from, $this->sender['name']);
    
    // truncate list attendees down to the recipient of the iTip Reply.
    // constraints for a METHOD:REPLY according to RFC 5546
    if ($method == 'REPLY') {
      $replying_attendee = null; $reply_attendees = array();
      foreach ($event['attendees'] as $attendee) {
        if ($attendee['role'] == 'ORGANIZER') {
          $reply_attendees[] = $attendee;
        }
        else if (strcasecmp($attedee['email'], $from) == 0 || strcasecmp($attendee['email'], $from_utf) == 0) {
          $replying_attendee = $attendee;
        }
      }
      if ($replying_attendee) {
        $reply_attendees[] = $replying_attendee;
        $event['attendees'] = $reply_attendees;
      }
    }
    
    // compose multipart message using PEAR:Mail_Mime
    $message = new Mail_mime("\r\n");
    $message->setParam('text_encoding', 'quoted-printable');
    $message->setParam('head_encoding', 'quoted-printable');
    $message->setParam('head_charset', RCMAIL_CHARSET);
    $message->setParam('text_charset', RCMAIL_CHARSET . ";\r\n format=flowed");
    $message->setContentType('multipart/alternative');
    
    // compose common headers array
    $headers = array(
      'From' => $sender,
      'Date' => $this->rc->user_date(),
      'Message-ID' => $this->rc->gen_message_id(),
      'X-Sender' => $from,
    );
    if ($agent = $this->rc->config->get('useragent'))
      $headers['User-Agent'] = $agent;
    
    $message->headers($headers);
    
    // attach ics file for this event
    $ical = $this->cal->get_ical();
    $ics = $ical->export(array($event), $method, false, $method == 'REQUEST' ? array($this->cal->driver, 'get_attachment_body') : false);
    $message->addAttachment($ics, 'text/calendar', 'event.ics', false, '8bit', '', RCMAIL_CHARSET . "; method=" . $method);
    
    return $message;
  }


  /**
   * Find invitation record by token
   *
   * @param string Invitation token
   * @return mixed Invitation record as hash array or False if not found
   */
  public function get_invitation($token)
  {
    if ($parts = $this->decode_token($token)) {
      $result = $this->rc->db->query("SELECT * FROM itipinvitations WHERE token=?", $parts['base']);
      if ($result && ($rec = $this->rc->db->fetch_assoc($result))) {
        $rec['event'] = unserialize($rec['event']);
        $rec['attendee'] = $parts['attendee'];
        return $rec;
      }
    }
    
    return false;
  }

  /**
   * Update the attendee status of the given invitation record
   *
   * @param array Invitation record as fetched with calendar_itip::get_invitation()
   * @param string Attendee email address
   * @param string New attendee status
   */
  public function update_invitation($invitation, $email, $newstatus)
  {
    if (is_string($invitation))
      $invitation = $this->get_invitation($invitation);
    
    if ($invitation['token'] && $invitation['event']) {
      // update attendee record in event data
      foreach ($invitation['event']['attendees'] as $i => $attendee) {
        if ($attendee['role'] == 'ORGANIZER') {
          $organizer = $attendee;
        }
        else if ($attendee['email'] == $email) {
          // nothing to be done here
          if ($attendee['status'] == $newstatus)
            return true;
          
          $invitation['event']['attendees'][$i]['status'] = $newstatus;
          $this->sender = $attendee;
        }
      }
      $invitation['event']['changed'] = new DateTime();
      
      // send iTIP REPLY message to organizer
      if ($organizer) {
        $status = strtolower($newstatus);
        if ($this->send_itip_message($invitation['event'], 'REPLY', $organizer, 'itipsubject' . $status, 'itipmailbody' . $status))
          $this->rc->output->command('display_message', $this->cal->gettext(array('name' => 'sentresponseto', 'vars' => array('mailto' => $organizer['name'] ? $organizer['name'] : $organizer['email']))), 'confirmation');
        else
          $this->rc->output->command('display_message', $this->cal->gettext('itipresponseerror'), 'error');
      }
      
      // update record in DB
      $query = $this->rc->db->query(
        "UPDATE itipinvitations
         SET event=?
         WHERE token=?",
        self::serialize_event($invitation['event']),
        $invitation['token']
      );

      if ($this->rc->db->affected_rows($query))
        return true;
    }
    
    return false;
  }


  /**
   * Create iTIP invitation token for later replies via URL
   *
   * @param array Hash array with event properties
   * @param string Attendee email address
   * @return string Invitation token
   */
  public function store_invitation($event, $attendee)
  {
    static $stored = array();
    
    if (!$event['uid'] || !$attendee)
      return false;
      
    // generate token for this invitation
    $token = $this->generate_token($event, $attendee);
    $base = substr($token, 0, 40);
    
    // already stored this
    if ($stored[$base])
      return $token;

    // delete old entry
    $this->rc->db->query("DELETE FROM itipinvitations WHERE token=?", $base);

    $query = $this->rc->db->query(
      "INSERT INTO itipinvitations
       (token, event_uid, user_id, event, expires)
       VALUES(?, ?, ?, ?, ?)",
      $base,
      $event['uid'],
      $this->rc->user->ID,
      self::serialize_event($event),
      date('Y-m-d H:i:s', $event['end'] + 86400 * 2)
    );
    
    if ($this->rc->db->affected_rows($query)) {
      $stored[$base] = 1;
      return $token;
    }
    
    return false;
  }

  /**
   * Mark invitations for the given event as cancelled
   *
   * @param array Hash array with event properties
   */
  public function cancel_itip_invitation($event)
  {
    // flag invitation record as cancelled
    $this->rc->db->query(
      "UPDATE itipinvitations
       SET cancelled=1
       WHERE event_uid=? AND user_id=?",
       $event['uid'],
       $this->rc->user->ID
    );
  }

  /**
   * Generate an invitation request token for the given event and attendee
   *
   * @param array Event hash array
   * @param string Attendee email address
   */
  public function generate_token($event, $attendee)
  {
    $base = sha1($event['uid'] . ';' . $this->rc->user->ID);
    $mail = base64_encode($attendee);
    $hash = substr(md5($base . $mail . $this->rc->config->get('des_key')), 0, 6);
    
    return "$base.$mail.$hash";
  }

  /**
   * Decode the given iTIP request token and return its parts
   *
   * @param string Request token to decode
   * @return mixed Hash array with parts or False if invalid
   */
  public function decode_token($token)
  {
    list($base, $mail, $hash) = explode('.', $token);
    
    // validate and return parts
    if ($mail && $hash && $hash == substr(md5($base . $mail . $this->rc->config->get('des_key')), 0, 6)) {
      return array('base' => $base, 'attendee' => base64_decode($mail));
    }
    
    return false;
  }

  /**
   * Helper method to serialize the given event for storing in invitations table
   */
  private static function serialize_event($event)
  {
    $ev = $event;
    $ev['description'] = abbreviate_string($ev['description'], 100);
    unset($ev['attachments']);
    return serialize($ev);
  }

}
