// c:\xampp\htdocs\PowertechTest\websocket-server.js

const { WebSocketServer } = require('ws');

// สร้าง Server บน Port 8080 (ต้องไม่ชนกับ Apache/XAMPP ที่ใช้ Port 80)
const wss = new WebSocketServer({ port: 8081, host: '0.0.0.0' });

// Map สำหรับเก็บข้อมูลว่า user_id ไหนมี connection อะไรบ้าง (รองรับหลาย Tab/Device)
// Key: user_id, Value: Set of WebSocket connection objects
const clients = new Map();

// Map สำหรับเก็บชื่อผู้ใช้ (user_id -> name) เพื่อใช้แสดงตอนกำลังพิมพ์
const userNames = new Map();

console.log('✅ WebSocket server started on ws://localhost:8081');

// ฟังก์ชันสำหรับส่งข้อมูลหาทุกคนที่เชื่อมต่ออยู่ (Broadcast)
function broadcastToAll(data) {
    const payload = JSON.stringify(data);
    clients.forEach(sockets => {
        sockets.forEach(client => {
            if (client.readyState === client.OPEN) {
                client.send(payload);
            }
        });
    });
}

wss.on('connection', (ws) => {
    let currentUserId = null; // เก็บ ID ของ user ที่เชื่อมต่อเข้ามาใน connection นี้

    // Event: เมื่อได้รับข้อความจาก Client (หน้าเว็บ)
    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);

            // 1. จัดการการยืนยันตัวตน (Authentication)
            // หน้าเว็บจะส่ง { type: 'auth', user_id: '...' } มาทันทีที่เชื่อมต่อ
            if (data.type === 'auth' && data.user_id) {
                currentUserId = data.user_id;
                
                // ถ้ายังไม่มี Set สำหรับ user นี้ ให้สร้างใหม่
                if (!clients.has(currentUserId)) {
                    clients.set(currentUserId, new Set());
                    
                    // เก็บชื่อผู้ใช้ถ้าส่งมา
                    if (data.name) {
                        userNames.set(currentUserId, data.name);
                    }

                    // ** แจ้งทุกคนว่า User นี้ Online แล้ว (เพราะเพิ่งสร้าง Set ใหม่) **
                    broadcastToAll({
                        type: 'user_status',
                        status: 'online',
                        user_id: currentUserId
                    });
                }
                
                // เพิ่ม connection นี้เข้าไปใน Set
                clients.get(currentUserId).add(ws);
                
                console.log(`User [${currentUserId}] authenticated. Total connections: ${clients.get(currentUserId).size}`);

                // ** ส่งรายชื่อคน Online ทั้งหมดกลับไปให้ User ที่เพิ่งเข้ามา **
                const onlineUsers = Array.from(clients.keys());
                ws.send(JSON.stringify({
                    type: 'online_users',
                    users: onlineUsers
                }));

                return;
            }

            // 2. จัดการข้อความแชท
            // หน้าเว็บส่ง { type: 'chat', to: '...', message_data: {...} }
            if (data.type === 'chat' && data.to && data.message_data) {
                const receiverId = data.to;
                const senderId = currentUserId;
                
                // เตรียมข้อมูลที่จะส่งต่อไปยังผู้รับ
                const payload = JSON.stringify({
                    type: 'new_message',
                    message: data.message_data
                });

                // กรณี: ส่งเข้ากลุ่ม (Group Chat)
                if (receiverId.startsWith('GROUP_')) {
                    // Broadcast หาทุกคนที่ออนไลน์อยู่
                    clients.forEach((sockets, userId) => {
                        // ส่งหาทุกคน (รวมถึง connection อื่นๆ ของเราเองด้วย เพื่อให้ sync)
                        sockets.forEach(client => {
                             // ไม่ส่งกลับหา connection ปัจจุบัน (เพราะหน้าเว็บแสดงผลไปแล้ว)
                             if (client !== ws && client.readyState === client.OPEN) {
                                 client.send(payload);
                             }
                        });
                    });
                    console.log(`Broadcasted group message from [${senderId}] to [${receiverId}]`);
                } 
                // กรณี: ส่งส่วนตัว (Private Chat)
                else {
                    // 2.1 ส่งหาผู้รับ (Receiver) - ทุก Device/Tab ของเขา
                    if (clients.has(receiverId)) {
                        const receiverSockets = clients.get(receiverId);
                        receiverSockets.forEach(client => {
                            if (client.readyState === client.OPEN) {
                                client.send(payload);
                            }
                        });
                    }

                    // 2.2 ส่งกลับหาผู้ส่ง (Sender) - ทุก Device/Tab ของเรา (ยกเว้น Tab ปัจจุบันที่ส่ง)
                    // เพื่อให้ถ้าเราเปิดแชทไว้หลายจอ ทุกจอจะเห็นข้อความที่เราส่งไป
                    if (clients.has(senderId)) {
                        const senderSockets = clients.get(senderId);
                        senderSockets.forEach(client => {
                            if (client !== ws && client.readyState === client.OPEN) {
                                client.send(payload);
                            }
                        });
                    }

                    console.log(`Sent private message from [${senderId}] to [${receiverId}]`);
                }
            }

            // 3. จัดการสถานะกำลังพิมพ์ (Typing Indicator)
            if (data.type === 'typing' && data.to) {
                const receiverId = data.to;
                const senderId = currentUserId;
                const senderName = userNames.get(senderId) || 'Someone';
                
                const payload = JSON.stringify({
                    type: 'typing',
                    sender_id: senderId,
                    sender_name: senderName,
                    receiver_id: receiverId
                });

                if (receiverId.startsWith('GROUP_')) {
                    // Broadcast หาคนอื่นในกลุ่ม (ทุกคนที่ออนไลน์)
                    clients.forEach((sockets, userId) => {
                        sockets.forEach(client => {
                             if (client !== ws && client.readyState === client.OPEN) {
                                 client.send(payload);
                             }
                        });
                    });
                } else {
                    // ส่งหาคู่สนทนา
                    if (clients.has(receiverId)) {
                        clients.get(receiverId).forEach(client => {
                            if (client.readyState === client.OPEN) client.send(payload);
                        });
                    }
                }
            }

        } catch (error) {
            console.error('Failed to process message:', error);
        }
    });

    // Event: เมื่อการเชื่อมต่อถูกปิด (เช่น ปิดหน้าเว็บ)
    ws.on('close', () => {
        if (currentUserId && clients.has(currentUserId)) {
            const userSockets = clients.get(currentUserId);
            userSockets.delete(ws); // ลบเฉพาะ connection ที่ปิด
            
            if (userSockets.size === 0) {
                clients.delete(currentUserId); // ถ้าไม่มี connection เหลือแล้ว ให้ลบ user ออก
                userNames.delete(currentUserId); // ลบชื่อออกด้วย
                console.log(`User [${currentUserId}] disconnected (all sessions).`);
                
                // ** แจ้งทุกคนว่า User นี้ Offline แล้ว **
                broadcastToAll({
                    type: 'user_status',
                    status: 'offline',
                    user_id: currentUserId
                });
            } else {
                console.log(`User [${currentUserId}] disconnected a session. Remaining: ${userSockets.size}`);
            }
        }
    });

    // Event: เมื่อเกิด Error
    ws.on('error', (error) => {
        console.error('WebSocket error:', error);
    });
});
