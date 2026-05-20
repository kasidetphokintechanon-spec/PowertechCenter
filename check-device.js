const ping = require('ping');

// ตั้งค่า IP ที่ต้องการเทส
const targetIP = '192.168.0.9';

async function testPing() {
    console.clear();
    console.log(`==========================================`);
    console.log(`   กำลังทดสอบการเชื่อมต่อ (Ping Test)`);
    console.log(`   Target: ${targetIP}`);
    console.log(`   Time: ${new Date().toLocaleString()}`);
    console.log(`==========================================`);

    try {
        // ส่งคำสั่ง Ping
        let res = await ping.promise.probe(targetIP, {
            timeout: 3, // รอ 3 วินาที
        });

        if (res.alive) {
            console.log(` ✅ สถานะ: ONLINE`);
            console.log(` 🚀 ความเร็ว (Latency): ${res.time} ms`);
        } else {
            console.log(` ❌ สถานะ: OFFLINE (ติดต่อไม่ได้)`);
            console.log(` 📢 คำแนะนำ: เช็คสายแลน หรือการตั้งค่า Firewall ของเครื่องปลายทาง`);
        }

    } catch (err) {
        console.log(` ⚠️ เกิดข้อผิดพลาด: ${err.message}`);
    }

    console.log(`==========================================`);
    console.log(`ระบบจะเช็คใหม่ทุกๆ 5 วินาที... (กด Ctrl+C เพื่อเลิก)`);
}

// ตั้งเวลาให้ทำงานทุก 5 วินาทีเพื่อให้เห็นความเปลี่ยนแปลงต่อเนื่อง
setInterval(testPing, 5000);

// สั่งรันครั้งแรกทันที
testPing();