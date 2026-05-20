<?php
// src/Chat.php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $users; // เก็บ mapping ระหว่าง resourceId กับ user_id

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->users = [];
        echo "Chat Server Started!\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // เก็บ Connection ใหม่
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);

        // 1. กรณีเป็นการยืนยันตัวตน (Auth)
        if (isset($data['type']) && $data['type'] === 'auth') {
            $this->users[$from->resourceId] = $data['user_id'];
            echo "User {$data['user_id']} connected as {$from->resourceId}\n";
            return;
        }

        // 2. กรณีเป็นข้อความแชท (Chat Message)
        if (isset($data['type']) && $data['type'] === 'chat') {
            $receiver_id = $data['to'];
            $payload = json_encode([
                'type' => 'new_message',
                'message' => $data['message_data'] // ข้อมูลข้อความที่จัดรูปแบบแล้ว
            ]);

            // ส่งหาทุกคน (Broadcast) หรือส่งเฉพาะคน (Private)
            foreach ($this->clients as $client) {
                // ถ้าเป็น Group Chat หรือส่งหาตัวเอง หรือส่งหาผู้รับ
                // (ในตัวอย่างนี้ส่งหาทุกคนที่เกี่ยวข้องแบบง่ายๆ ก่อน)
                if ($from !== $client) {
                    // Logic การกรองผู้รับควรทำตรงนี้ (เช็ค $this->users[$client->resourceId])
                    $client->send($payload);
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->users[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}
