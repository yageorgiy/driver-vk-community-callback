<?php
namespace BotMan\Drivers\VK;

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use BotMan\Drivers\VK\Events\Confirmation;
use BotMan\Drivers\VK\Events\MessageEdit;
use BotMan\Drivers\VK\Events\MessageNew;
use BotMan\Drivers\VK\Events\MessageReply;
use CURLFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class VkCommunityCallbackDriver extends HttpDriver
{
    const DRIVER_NAME = "VK Community Callback Driver";

    /**
     * Array of messages
     *
     * @var array
     */
    private $messages;

    /**
     * IP-address of client
     *
     * @var string
     */
    private $ip;

    /**
     * Peer ID (user or conversation ID)
     * TODO: changing to int?
     *
     * @var string
     */
    private $peer_id;

    /**
     * Incoming message from user/conversation
     *
     * @param Request $request
     */

    /**
     * @var bool
     */
    private $reply = false;

    /**
     * @var
     */
    private $driverEvent;



    public function buildPayload(Request $request)
    {
        // Setting IP-address
        $this->ip = $request->getClientIp();
        // Setting the payload, which contains all JSON data sent by VK
        $this->payload = new ParameterBag((array) json_decode($request->getContent(), true));
        // Setting the event, which contains only JSON 'object' field
        $this->event = Collection::make((array) $this->payload->get("object"));
        // Setting the content, contains raw data sent by VK
        $this->content = $request->getContent();
        // Setting the config values from 'config/vk.php' file
        $this->config = Collection::make($this->config->get('vk', []));
    }



    /**
     * Manages the text to be echoed for VK API
     */
    protected function reply(){
        if(!$this->reply){
            switch($this->payload->get("type")){
                // Echo OK for incoming messages
                case "message_new":
                case "message_reply":
                case "message_edit":

                    $this->ok();
                    break;

                // Echo the confirmation token
                case "confirmation":
                    $this->echoConfirmationToken();
                    break;
            }
            $this->reply = true;
        }
    }

    /**
     * @var bool
     */
    private $ok = false;

    /**
     * Echos 'ok'
     */
    public function ok(){
        if(!$this->ok){
            echo("ok");
            $this->ok = true;
        }
    }

    /**
     * Echoes confirmation pass-phrase
     */
    public function echoConfirmationToken(){
        //TODO: save output?
        echo($this->config->get("confirm"));
    }


    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        //TODO: anything else?

        return
            !is_null($this->payload->get("secret")) &&
            $this->payload->get("secret") == $this->config->get("secret") &&
            !is_null($this->payload->get("group_id")) &&
            $this->payload->get("group_id") == $this->config->get("group_id"); //&&
//            preg_match('/95\.142\.([0-9]+)\.([0-9]+)/', $this->ip) === true; //TODO: ip checkups for production server
    }

    /**
     * Retrieve the chat message(s).
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {


//            $message = $this->event->get('message');
//            $userId = $this->event->get('userId');
            $message = "generic";
            $peer_id = 0;
            $message_object = [];

            // message_new and message_reply / message_edit has different JSON schemas!
            switch($this->payload->get("type")){
                case "message_new":
                    $message_object = $this->payload->get("object")["message"];
                    $message = $this->payload->get("object")["message"]["text"];
                    $peer_id = $this->payload->get("object")["message"]["peer_id"];
                    break;

                case "message_reply":
                case "message_edit":
                    $message_object = $this->payload->get("object");
                    $message = $this->payload->get("object")["text"];
                    $peer_id = $this->payload->get("object")["peer_id"];
                    break;
            }
            $this->peer_id = $peer_id;

            if(isset($message_object["payload"])){
                $payload_text = json_decode($message_object["payload"], true)["__message"];
                if(isset($payload_text) && $payload_text != null) $message = $payload_text;
            }


            $incomingMessage = new IncomingMessage($message, $peer_id, $peer_id, $this->payload);
            $incomingMessage->addExtras("message_object", $message_object);

            $this->messages = [$incomingMessage];
        }

        $this->reply();

        return $this->messages;
    }

    /**
     * Checking if bot is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return
            !empty($this->config->get('secret')) &&
            !empty($this->config->get('token')) &&
            !empty($this->config->get('version')) &&
            version_compare($this->config->get('version'), "5.103", ">=");
    }

    /**
     * Setting the driver event via payload
     * as we need to get type of the event.
     * Example data:
     * {"type": "group_join", "object": {"user_id": 1, "join_type" : "approved"}, "group_id": 1}
     * {"type": "confirmation", "group_id": 1}
     *
     * @return DriverEventInterface|bool
     */
//    public function hasMatchingEvent() {
//        if (!is_null($this->payload)) {
//            $this->driverEvent = $this->getEventFromEventData($this->payload);
//            return $this->driverEvent;
//        }
//
//        return false;
//    }

    /**
     * Generating event from payload
     *
     * @param $eventData
     * @return GenericEvent|Confirmation|MessageEdit|MessageNew|MessageReply
     */
//    protected function getEventFromEventData($eventData)
//    {
//        $name = (string) $eventData->get("type");
//        $event = (array) $eventData->get("object") ?? [];
//        switch ($name) {
//            case 'message_new':
//                return new MessageNew($event);
//                break;
//
//            case 'message_edit':
//                return new MessageEdit($event);
//                break;
//
//            case 'message_reply':
//                return new MessageReply($event);
//                break;
//
//            case 'confirmation':
//                return new Confirmation($event);
//                break;
//
//            default:
//                $event = new GenericEvent($event);
//                $event->setName($name);
//
//                return $event;
//                break;
//        }
//    }


    /**
     * Retrieve User information.
     *
     * @param IncomingMessage $matchingMessage
     * @return User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        // Retrieving all relevant information about user
        // TODO: custom fields
        $response = $this->api("users.get", [
            "user_ids" => $matchingMessage->getExtras("message_object")["from_id"],
            "fields" => "photo_id, verified, sex, bdate, city, country, photo_50, photo_100, photo_200, photo_max, online, status, nickname, can_post, can_see_all_posts, can_see_audio, can_write_private_message, can_send_friend_request, is_favorite, is_hidden_from_feed, timezone, blacklisted, blacklisted_by_me, can_be_invited_group"
        ], true);

        $first_name = $response["response"][0]["first_name"];
        $last_name = $response["response"][0]["first_name"];
        $username = "id".$response["response"][0]["id"];

        // TODO: remade with proper user class suitable for VK user
        return new User($matchingMessage->getSender(), $first_name, $last_name, $username, $response["response"][0]);
    }

    /**
     * Building conversation message created by bot
     *
     * @param IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Building payload for VK to send
     *
     * @param string|Question $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $text = $message->getText();
        $peer_id = $matchingMessage->getRecipient();

        $data = [
            "peer_id" => $peer_id,
            "message" => $text,
            "random_id" => 0
        ];

        /*
        Not supported by VK API yet =(

        if($this->config->get("forward_messages") && $this->isConversation())
            $data["forward_messages"] = $this->event->get("message")["conversation_message_id"];
        */

        /* Building attachments */
        if(!is_string($message)){

            // Building buttons
            // TODO: make a dedicated method
            // TODO: make a suitable VK buttons class
            if(method_exists($message,'getActions') && $message->getActions() != null){
                $actions = $message->getActions();

                $inline = false;
                $max_fields = $inline ? 10 : 50;
                $max_x = $inline ? 5 : 5;
                $max_y = $inline ? 6 : 10;

                $x = 0;
                $y = 0;
                $fields = 0;

                $buttons = [];
                foreach($actions as $action){
                    if($fields >= $max_fields) break;

                    $break_me = false;

                    $current_x = (isset($action["additional"]["__x"])) ? $action["additional"]["__x"] : $x;
                    $current_y = (isset($action["additional"]["__y"])) ? $action["additional"]["__y"] : $y;

                    if(!isset($action["additional"]["__x"])){
                        if($x + 1 > $max_x - 1) $x = 0; else $x++;
                    } else {
                        unset($action["additional"]["__x"]);
                    }
                    if(!isset($action["additional"]["__y"])){
                        if($x + 1 > $max_x - 1) $y++;
                        if($y > $max_y - 1) $break_me = true;
                    } else {
                        unset($action["additional"]["__y"]);
                    }


                    $cur_btn = &$buttons[$current_y][$current_x];

                    $cur_btn = $action["additional"];

                    $cur_btn["color"] = $cur_btn["color"] ?? "primary";
                    $cur_btn["action"] = $cur_btn["action"] ?? [];
                    $cur_btn["action"]["label"] = $cur_btn["action"]["label"] ?? $action["text"];
                    $cur_btn["action"]["type"] = $cur_btn["action"]["type"] ?? "text";
                    $cur_btn["action"]["payload"] = $cur_btn["action"]["payload"] ??
                        (isset($action["value"])) ? json_encode(["__message" => $action["value"]]) : json_encode([]);

                    $fields++;

                    if($break_me) break;
                }

                $keyboard = [
                    "buttons" => $buttons,
                    "inline" => $inline
                ];

                if(!$inline) $keyboard["one_time"] = true;



                $data["keyboard"] = json_encode($keyboard);
            }

            if(method_exists($message,'getAttachment') && $message->getAttachment() != null){
                $attachment = $message->getAttachment();

                switch(get_class($attachment)){
                    case "BotMan\BotMan\Messages\Attachments\Image":
                        $getUploadUrl = $this->api("photos.getMessagesUploadServer", [
                            'peer_id' => $peer_id
                        ], true);

                        $uploadImg = $this->upload($getUploadUrl["response"]['upload_url'], $attachment->getUrl());

                        $saveImg = $this->api('photos.saveMessagesPhoto', [
                            'photo' => $uploadImg['photo'],
                            'server' => $uploadImg['server'],
                            'hash' => $uploadImg['hash']
                        ], true);

                        $data["attachment"][] = "photo".$saveImg["response"][0]['owner_id']."_".$saveImg["response"][0]['id'];
                        break;
                }


            }
        }

        if(isset($data["attachment"]) && is_array($data["attachment"]) && count($data["attachment"]) <= 0) unset($data["attachment"]);
        if(isset($data["attachment"]) && is_array($data["attachment"])) $data["attachment"] = implode(",", $data["attachment"]);

        $ret = [
            'data' => $data
        ];

        return $ret;
    }

    /**
     * Sending payload to VK
     *
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        return $this->api("messages.send", $payload["data"]);
    }

    /**
     * Low-level method to perform driver specific API requests (unused)
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return void
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {

    }

    /**
     * Sending typing action
     *
     * @param IncomingMessage $matchingMessage
     * @return bool|void
     */
    public function types(IncomingMessage $matchingMessage) {
        $this->api("messages.setActivity", [
            "peer_id" => $matchingMessage->getRecipient(),
            "type" => "typing"
        ], true);

        return true;
    }

    /**
     * Executing all api requests via this method
     *
     * @param string $method
     * @param array $post_data
     * @param bool $asArray
     * @return Response
     */
    public function api(string $method, array $post_data, bool $asArray = false){

        $post_data += [
            "v" => $this->config->get("version"),
            "access_token" => $this->config->get("token")
        ];
        $response = $this->http->post($this->config->get("endpoint").$method, [], $post_data, [], false);

        if($asArray)
            return json_decode($response->getContent(),true);

        return $response;
    }

    /**
     * Uploading files for attachments
     *
     * @param string $url
     * @param string $filename
     * @return array
     */
    public function upload(string $url, string $filename/*, bool $asArray = false*/){

        //TODO: upload with native tools
        if(preg_match("/^http/i", $filename)){
            $temp_dir = sys_get_temp_dir();

            $basename =
                "botman_vk_driver_api_" .
                pathinfo($filename, PATHINFO_FILENAME) . "." . pathinfo($filename, PATHINFO_EXTENSION);
            $contents = fopen($filename, 'r');
            $filename = $temp_dir . '/' . $basename;

            file_put_contents($filename, $contents);
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, array('file' => new CURLfile($filename)));
        $json = curl_exec($curl);
        curl_close($curl);

        unlink($filename);

        return json_decode($json, true);
    }

    /**
     * Is conversation?
     *
     * @return bool
     */
    public function isConversation(){
        return $this->peer_id >= 2000000000;
    }

}